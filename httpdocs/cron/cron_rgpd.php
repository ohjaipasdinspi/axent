#!/usr/bin/env php
<?php
/**
 * Axent - cron_rgpd.php
 * Anonymisation automatique des données (CNIL - 13 mois)
 *
 * ─── INSTALLATION SUR PLESK ────────────────────────────────────
 * 1. Connexion à Plesk → Tâches planifiées (Cron Jobs)
 * 2. Cliquez sur "Ajouter une tâche"
 * 3. Exécuter : /usr/bin/php /var/www/vhosts/axet.fr/httpdocs/cron/cron_rgpd.php
 * 4. Fréquence : Tous les jours à 02:00
 *    (format cron : 0 2 * * *)
 * 5. Enregistrez
 *
 * ─── TEST MANUEL ───────────────────────────────────────────────
 * ssh vers votre serveur puis :
 * php /var/www/vhosts/axet.fr/httpdocs/cron/cron_rgpd.php
 * ──────────────────────────────────────────────────────────────
 */

define('CRON_MODE', true);

// Charger la config
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';

$log = function(string $msg) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    echo $line;
    file_put_contents(LOG_PATH . '/cron_rgpd.log', $line, FILE_APPEND);
};

$log('=== DÉMARRAGE CRON RGPD ===');

try {
    // ── 1. Anonymiser les consentements expirés ──────────────────
    $affected = Database::query(
        "UPDATE axnt_consents
         SET
           visitor_id      = CONCAT('anon_', SHA2(CONCAT(visitor_id, ?), 256)),
           ip_hashed       = 'anonymized',
           user_agent_hash = 'anonymized',
           gcm_data        = NULL,
           anonymized_at   = NOW()
         WHERE
           anonymized_at IS NULL
           AND expires_at < NOW()",
        [SECRET_KEY]
    )->rowCount();
    $log("Consentements anonymisés : $affected");

    // ── 2. Anonymiser les utilisateurs inactifs depuis 13 mois ───
    $cutoff = date('Y-m-d H:i:s', strtotime('-' . RGPD_ANONYMIZE_AFTER_MONTHS . ' months'));

    $usersToAnonymize = Database::fetchAll(
        "SELECT id, email FROM axnt_users
         WHERE status = 'active'
           AND last_login_at < ?
           AND last_login_at IS NOT NULL
           AND anonymized_at IS NULL",
        [$cutoff]
    );

    foreach ($usersToAnonymize as $user) {
        // Anonymiser l'utilisateur
        Database::query(
            "UPDATE axnt_users SET
               email          = CONCAT('anonymized_', id, '@axent.local'),
               password_hash  = NULL,
               display_name   = 'Utilisateur supprimé',
               avatar_url     = NULL,
               last_login_ip  = 'anonymized',
               newsletter     = 0,
               status         = 'anonymized',
               anonymized_at  = NOW()
             WHERE id = ?",
            [$user['id']]
        );

        // Supprimer les comptes OAuth liés
        Database::query('DELETE FROM axnt_oauth_accounts WHERE user_id = ?', [$user['id']]);

        // Désinscrire de la newsletter
        Database::query(
            "UPDATE axnt_newsletter_subscribers SET status = 'unsubscribed', email = CONCAT('anon_', id, '@axent.local') WHERE email = ?",
            [$user['email']]
        );

        $log("Utilisateur #{$user['id']} anonymisé");
    }

    // ── 3. Nettoyer les sessions expirées ────────────────────────
    $sessions = Database::query(
        'DELETE FROM axnt_sessions WHERE last_activity < ?',
        [(string)(time() - SESSION_LIFETIME)]
    )->rowCount();
    $log("Sessions nettoyées : $sessions");

    // ── 4. Nettoyer les rate limits obsolètes ────────────────────
    $rl = Database::query(
        'DELETE FROM axnt_rate_limits WHERE updated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)'
    )->rowCount();
    $log("Rate limits nettoyés : $rl");

    // ── 5. Nettoyer les tokens newsletter non confirmés >48h ─────
    $nlClean = Database::query(
        "DELETE FROM axnt_newsletter_subscribers
         WHERE status = 'pending' AND confirm_sent_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)"
    )->rowCount();
    $log("Inscriptions newsletter expirées nettoyées : $nlClean");

    // ── 6. Rapport audit ─────────────────────────────────────────
    Database::query(
        "INSERT INTO axnt_audit_log (user_id, action, target, ip_hashed, details)
         VALUES (NULL, 'cron_rgpd', 'system', 'cron', ?)",
        [json_encode([
            'consents_anonymized' => $affected,
            'users_anonymized'    => count($usersToAnonymize),
            'sessions_cleaned'    => $sessions,
        ])]
    );

    $log('=== CRON RGPD TERMINÉ AVEC SUCCÈS ===');

} catch (\Exception $e) {
    $log('ERREUR : ' . $e->getMessage());
    // Alerter l'admin
    $errMsg = '[Axent Cron RGPD] Erreur : ' . $e->getMessage();
    error_log($errMsg);
    exit(1);
}

exit(0);
