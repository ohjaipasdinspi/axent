<?php
/**
 * Axent - Database.php
 * Connexion PDO sécurisée avec singleton
 */

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
                );
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]);
            } catch (PDOException $e) {
                // Ne jamais afficher les détails de connexion
                error_log('[Axent DB Error] ' . $e->getMessage());
                http_response_code(503);
                die(json_encode(['error' => 'Service temporairement indisponible. Réessayez dans quelques instants. ☕']));
            }
        }
        return self::$instance;
    }

    /**
     * Exécute une requête préparée et retourne le statement
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Retourne un seul résultat
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Retourne tous les résultats
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Insère et retourne l'ID inséré
     */
    public static function insert(string $sql, array $params = []): int
    {
        self::query($sql, $params);
        return (int) self::getInstance()->lastInsertId();
    }

    /**
     * Hash une IP pour la RGPD (non-réversible)
     */
    public static function hashIP(string $ip): string
    {
        return hash('sha256', $ip . SECRET_KEY);
    }
}
