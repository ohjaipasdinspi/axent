<?php
/**
 * Axent - Mailer.php
 * Envoi d'emails via PHPMailer + Postfix Plesk
 *
 * Installation PHPMailer :
 *   composer require phpmailer/phpmailer
 *   OU télécharger : https://github.com/PHPMailer/PHPMailer
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private static function getInstance(): PHPMailer
    {
        $mail = new PHPMailer(true);

        // Configuration SMTP Plesk (Postfix)
        if (MAIL_AUTH) {
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USER;
            $mail->Password   = MAIL_PASS;
            $mail->SMTPSecure = MAIL_SECURE ?: PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_PORT;
        } else {
            // Postfix local (recommandé sur Plesk)
            $mail->isSendmail();
        }

        $mail->SMTPDebug = MAIL_DEBUG;
        $mail->CharSet   = 'UTF-8';
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addReplyTo(MAIL_REPLY_TO, APP_NAME);
        $mail->isHTML(true);

        return $mail;
    }

    // ----------------------------------------------------------
    // EMAIL DE VÉRIFICATION D'INSCRIPTION
    // ----------------------------------------------------------
    public static function sendVerification(string $to, string $name, string $token): bool
    {
        $link = URL_AUTH . '/verify?token=' . $token;
        $subject = '🍪 Confirmez votre inscription sur ' . APP_NAME;
        $body = self::template('verification', [
            'name'  => $name,
            'link'  => $link,
            'token' => $token,
        ]);
        return self::send($to, $name, $subject, $body, 'welcome');
    }

    // ----------------------------------------------------------
    // EMAIL DE BIENVENUE (après vérification)
    // ----------------------------------------------------------
    public static function sendWelcome(string $to, string $name): bool
    {
        $subject = 'Bienvenue sur ' . APP_NAME . ' !';
        $body = self::template('welcome', ['name' => $name]);
        return self::send($to, $name, $subject, $body, 'welcome');
    }

    // ----------------------------------------------------------
    // RÉINITIALISATION DU MOT DE PASSE
    // ----------------------------------------------------------
    public static function sendPasswordReset(string $to, string $name, string $token): bool
    {
        $link = URL_AUTH . '/reset-password?token=' . $token;
        $subject = 'Réinitialisation de votre mot de passe';
        $body = self::template('password_reset', [
            'name' => $name,
            'link' => $link,
        ]);
        return self::send($to, $name, $subject, $body, 'password_reset');
    }

    // ----------------------------------------------------------
    // ALERTE ADMIN (nouvelle inscription, alerte sécurité, etc.)
    // ----------------------------------------------------------
    public static function sendAdminAlert(string $type, array $data): bool
    {
        $subject = '🚨[' . APP_NAME . '] Alerte admin : ' . $type;
        $body = self::template('admin_alert', [
            'type' => $type,
            'data' => $data,
        ]);
        return self::send(MAIL_ADMIN, 'Admin', $subject, $body, 'admin_alert');
    }

    // ----------------------------------------------------------
    // DEMANDE RGPD (confirmation réception)
    // ----------------------------------------------------------
    public static function sendRgpdRequestConfirmation(string $to, string $name, string $type): bool
    {
        $types = [
            'export'  => 'export de vos données',
            'delete'  => 'suppression de votre compte',
            'rectify' => 'rectification de vos données',
            'oppose'  => 'opposition au traitement',
        ];
        $typeLabel = $types[$type] ?? $type;
        $subject = 'Votre demande RGPD a bien été reçue';
        $body = self::template('rgpd_request', [
            'name'  => $name,
            'type'  => $typeLabel,
        ]);
        return self::send($to, $name, $subject, $body, 'rgpd_request');
    }

    // ----------------------------------------------------------
    // EXPORT RGPD (envoi du fichier)
    // ----------------------------------------------------------
    public static function sendRgpdExport(string $to, string $name, string $jsonPath): bool
    {
        $subject = '📦 Vos données personnelles — Export RGPD';
        $body = self::template('rgpd_export', ['name' => $name]);
        try {
            $mail = self::getInstance();
            $mail->addAddress($to, $name);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);
            $mail->addAttachment($jsonPath, 'mes-donnees-axent.json');
            $mail->send();
            self::logEmail(null, $to, 'rgpd_export', $subject, 'sent');
            return true;
        } catch (Exception $e) {
            self::logEmail(null, $to, 'rgpd_export', $subject, 'failed', $e->getMessage());
            return false;
        }
    }

    // ----------------------------------------------------------
    // NEWSLETTER - Double opt-in
    // ----------------------------------------------------------
    public static function sendNewsletterConfirm(string $to, string $token): bool
    {
        $link    = APP_URL . '/newsletter/confirm?token=' . $token;
        $subject = 'Confirmez votre inscription à la newsletter';
        $body = self::template('newsletter_confirm', ['link' => $link]);
        return self::send($to, '', $subject, $body, 'newsletter_confirm');
    }

    // ----------------------------------------------------------
    // RAPPORT DE CONSENTEMENT
    // ----------------------------------------------------------
    public static function sendConsentReport(string $to, string $name, array $stats): bool
    {
        $subject = 'Rapport mensuel de consentement — ' . APP_NAME;
        $body = self::template('consent_report', [
            'name'  => $name,
            'stats' => $stats,
        ]);
        return self::send($to, $name, $subject, $body, 'consent_report');
    }

    // ----------------------------------------------------------
    // ENVOI GÉNÉRIQUE
    // ----------------------------------------------------------
    private static function send(string $to, string $name, string $subject, string $body, string $type): bool
    {
        if (!MAIL_ENABLED) return true;
        try {
            $mail = self::getInstance();
            $mail->addAddress($to, $name);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $body));
            $mail->send();
            self::logEmail(null, $to, $type, $subject, 'sent');
            return true;
        } catch (Exception $e) {
            error_log('[Axent Mailer] ' . $e->getMessage());
            self::logEmail(null, $to, $type, $subject, 'failed', $e->getMessage());
            return false;
        }
    }

    private static function logEmail(?int $userId, string $to, string $type, string $subject, string $status, ?string $error = null): void
    {
        try {
            Database::query(
                'INSERT INTO axnt_email_logs (user_id, to_email, type, subject, status, error, sent_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
                [$userId, $to, $type, $subject, $status, $error]
            );
        } catch (\Exception $e) { /* silencieux */ }
    }

    // ----------------------------------------------------------
    // TEMPLATES HTML
    // ----------------------------------------------------------
    private static function template(string $name, array $vars = []): string
    {
        $file = __DIR__ . '/templates/' . $name . '.html';
        if (file_exists($file)) {
            $html = file_get_contents($file);
            foreach ($vars as $k => $v) {
                if (is_string($v)) {
                    $html = str_replace('{{' . $k . '}}', htmlspecialchars($v, ENT_QUOTES), $html);
                }
            }
            return $html;
        }
        // Template inline de secours
        return self::inlineTemplate($name, $vars);
    }

    private static function inlineTemplate(string $name, array $vars): string
    {
        $appName = APP_NAME;
        $primary = THEME_PRIMARY;
        $header  = "<div style='background:{$primary};padding:40px 32px;text-align:center;border-radius:16px 16px 0 0'>"
                 . "<h1 style='color:#fff;margin:0;font-size:28px;font-family:sans-serif'>{$appName} 🍪</h1></div>";
        $footer  = "<div style='background:#f5f5f5;padding:24px;text-align:center;border-radius:0 0 16px 16px;font-family:sans-serif;font-size:12px;color:#999'>"
                 . "© " . date('Y') . " {$appName} — <a href='" . APP_URL . "/privacy' style='color:{$primary}'>Politique de confidentialité</a>"
                 . " — <a href='" . APP_URL . "/unsubscribe' style='color:{$primary}'>Se désinscrire</a></div>";

        $content = match($name) {
            'verification' => "<h2>Bonjour {$vars['name']} ! 👋</h2><p>Vous avez créé un compte sur {$appName}. C'est une excellente décision (sans chichi).</p><p>Pour confirmer votre adresse email, cliquez ici :</p><p style='text-align:center'><a href='{$vars['link']}' style='background:{$primary};color:#fff;padding:14px 32px;border-radius:50px;text-decoration:none;font-weight:bold;display:inline-block'>Confirmer mon email </a></p><p style='color:#999;font-size:13px'>Ce lien expire dans 24h. Si vous n'avez pas créé de compte, ignorez cet email.</p>",
            'welcome'      => "<h2>🎉 Bienvenue {$vars['name']} !</h2><p>Votre compte {$appName} est actif. Vous faites maintenant partie des gens sympas qui gèrent les cookies proprement.</p><p><a href='" . URL_APP . "' style='background:{$primary};color:#fff;padding:14px 32px;border-radius:50px;text-decoration:none;font-weight:bold;display:inline-block'>Accéder à mon compte →</a></p>",
            'password_reset' => "<h2>🔑 Réinitialisation mot de passe</h2><p>Bonjour {$vars['name']},</p><p>Vous avez demandé à réinitialiser votre mot de passe. C'est parti :</p><p style='text-align:center'><a href='{$vars['link']}' style='background:{$primary};color:#fff;padding:14px 32px;border-radius:50px;text-decoration:none;font-weight:bold;display:inline-block'>Nouveau mot de passe 🔐</a></p><p style='color:#999;font-size:13px'>Lien valable 1 heure. Si vous n'avez pas demandé ça, ignorez.</p>",
            'rgpd_request' => "<h2>📋 Demande RGPD reçue</h2><p>Bonjour {$vars['name']},</p><p>Votre demande de <strong>{$vars['type']}</strong> a bien été reçue. Un administrateur la traitera dans un délai de 30 jours maximum, comme prévu par le RGPD.</p>",
            'rgpd_export'  => "<h2>📦 Export de vos données</h2><p>Bonjour {$vars['name']},</p><p>Vos données personnelles sont en pièce jointe au format JSON. Ce fichier contient tout ce que nous avons sur vous.</p><p style='color:#999;font-size:13px'>Conservez ce fichier en lieu sûr.</p>",
            'newsletter_confirm' => "<h2>📬 Un petit clic pour confirmer</h2><p>Vous avez demandé à recevoir notre newsletter. C'est presque fini !</p><p style='text-align:center'><a href='{$vars['link']}' style='background:{$primary};color:#fff;padding:14px 32px;border-radius:50px;text-decoration:none;font-weight:bold;display:inline-block'>Je confirme mon inscription ✅</a></p>",
            'admin_alert'  => "<h2>🚨 Alerte : {$vars['type']}</h2><pre style='background:#f5f5f5;padding:16px;border-radius:8px'>" . print_r($vars['data'], true) . "</pre>",
            default        => "<p>Email {$name}</p>",
        };

        return "<!DOCTYPE html><html><body style='margin:0;padding:32px;background:#f0f0f0;font-family:sans-serif'>
            <div style='max-width:580px;margin:0 auto;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08)'>
                {$header}
                <div style='padding:40px 32px'>{$content}</div>
                {$footer}
            </div></body></html>";
    }
}
