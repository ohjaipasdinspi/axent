<?php
/**
 * Axent - Security.php
 * Sécurité : CSRF, rate limiting, tokens, headers
 */

declare(strict_types=1);

class Security
{
    // ----------------------------------------------------------
    // CSRF
    // ----------------------------------------------------------
    public static function generateCsrfToken(): string
    {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = self::generateToken(32);
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    public static function validateCsrfToken(string $token): bool
    {
        return isset($_SESSION[CSRF_TOKEN_NAME])
            && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }

    public static function csrfField(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            CSRF_TOKEN_NAME,
            htmlspecialchars(self::generateCsrfToken(), ENT_QUOTES)
        );
    }

    // ----------------------------------------------------------
    // RATE LIMITING
    // ----------------------------------------------------------
    public static function isRateLimited(string $keyHash, string $action): bool
    {
        $row = Database::fetchOne(
            'SELECT * FROM axnt_rate_limits WHERE key_hash = ? AND action = ?',
            [$keyHash, $action]
        );
        if (!$row) return false;
        if ($row['blocked_until'] && strtotime($row['blocked_until']) > time()) return true;
        return false;
    }

    public static function recordAttempt(string $keyHash, string $action): void
    {
        $row = Database::fetchOne(
            'SELECT * FROM axnt_rate_limits WHERE key_hash = ? AND action = ?',
            [$keyHash, $action]
        );

        if (!$row) {
            Database::query(
                'INSERT INTO axnt_rate_limits (key_hash, action, attempts) VALUES (?, ?, 1)',
                [$keyHash, $action]
            );
            return;
        }

        $attempts = $row['attempts'] + 1;
        $blocked  = null;
        if ($attempts >= RATE_LIMIT_LOGIN) {
            $blocked = date('Y-m-d H:i:s', time() + RATE_LIMIT_WINDOW);
        }

        Database::query(
            'UPDATE axnt_rate_limits SET attempts = ?, blocked_until = ?, updated_at = NOW() WHERE key_hash = ? AND action = ?',
            [$attempts, $blocked, $keyHash, $action]
        );
    }

    public static function resetAttempts(string $keyHash, string $action): void
    {
        Database::query(
            'DELETE FROM axnt_rate_limits WHERE key_hash = ? AND action = ?',
            [$keyHash, $action]
        );
    }

    // ----------------------------------------------------------
    // TOKENS
    // ----------------------------------------------------------
    public static function generateToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    // ----------------------------------------------------------
    // HEADERS DE SÉCURITÉ (appeler en début de chaque page)
    // ----------------------------------------------------------
    public static function setSecurityHeaders(): void
    {
        if (FORCE_HTTPS) {
            header('Strict-Transport-Security: max-age=' . HSTS_MAX_AGE . '; includeSubDomains; preload');
        }
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.axet.fr https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://api.axet.fr;");
        // Enlever les infos serveur
        header_remove('X-Powered-By');
        header_remove('Server');
    }

    // ----------------------------------------------------------
    // SANITISATION
    // ----------------------------------------------------------
    public static function sanitize(string $input): string
    {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    public static function sanitizeEmail(string $email): string
    {
        return filter_var(strtolower(trim($email)), FILTER_SANITIZE_EMAIL);
    }

    // ----------------------------------------------------------
    // REDIRECT SÉCURISÉ (anti open redirect)
    // ----------------------------------------------------------
    public static function safeRedirect(string $url): void
    {
        $allowed = [APP_URL, URL_APP, URL_ADMIN, URL_AUTH, URL_API, URL_DOCS];
        $safe = false;
        foreach ($allowed as $base) {
            if (strpos($url, $base) === 0) { $safe = true; break; }
        }
        if (!$safe) $url = URL_APP;
        header('Location: ' . $url);
        exit;
    }
}
