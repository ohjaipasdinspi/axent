<?php
/**
 * Axent - Auth.php
 * Gestion complète de l'authentification (locale + OAuth2)
 */

declare(strict_types=1);

require_once __DIR__ . '/../httpdocs/core/Database.php';
require_once __DIR__ . '/../httpdocs/core/Security.php';

class Auth
{
    // ----------------------------------------------------------
    // INSCRIPTION LOCALE
    // ----------------------------------------------------------
    public static function register(string $email, string $password, string $name, string $lang = 'fr'): array
    {
        $email = strtolower(trim($email));

        // Vérifications
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Email invalide. Et pourtant, c\'est pas compliqué ! 📧'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Mot de passe trop court. 8 caractères minimum, c\'est la base ! 🔑'];
        }
        if (strlen($name) < 2) {
            return ['success' => false, 'error' => 'Nom trop court.'];
        }

        // Email déjà utilisé ?
        $existing = Database::fetchOne(
            'SELECT id FROM axnt_users WHERE email = ?',
            [$email]
        );
        if ($existing) {
            return ['success' => false, 'error' => 'Cet email est déjà utilisé. Vous avez peut-être déjà un compte ? 🤔'];
        }

        // Création
        $uuid     = self::generateUUID();
        $hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $token    = Security::generateToken(32);
        $unsubTok = Security::generateToken(32);

        $userId = Database::insert(
            'INSERT INTO axnt_users (uuid, email, password_hash, display_name, lang, newsletter_token) VALUES (?, ?, ?, ?, ?, ?)',
            [$uuid, $email, $hash, htmlspecialchars($name, ENT_QUOTES), $lang, $token]
        );

        // Newsletter subscriber (pending)
        Database::insert(
            'INSERT INTO axnt_newsletter_subscribers (email, user_id, status, confirm_token, unsub_token) VALUES (?, ?, "pending", ?, ?)',
            [$email, $userId, $token, $unsubTok]
        );

        // Log
        self::auditLog($userId, 'register', 'local');

        return ['success' => true, 'user_id' => $userId, 'verify_token' => $token];
    }

    // ----------------------------------------------------------
    // CONNEXION LOCALE
    // ----------------------------------------------------------
    public static function login(string $email, string $password, string $ip): array
    {
        $email = strtolower(trim($email));
        $ipHash = Database::hashIP($ip);

        // Rate limiting
        if (Security::isRateLimited($ipHash, 'login')) {
            return ['success' => false, 'error' => 'Trop de tentatives. Faites une pause ☕ (15 minutes)'];
        }

        $user = Database::fetchOne(
            'SELECT * FROM axnt_users WHERE email = ? AND status != "deleted"',
            [$email]
        );

        if (!$user || !password_verify($password, $user['password_hash'] ?? '')) {
            Security::recordAttempt($ipHash, 'login');
            return ['success' => false, 'error' => 'Email ou mot de passe incorrect. 🤷'];
        }

        if ($user['status'] === 'suspended') {
            return ['success' => false, 'error' => 'Compte suspendu. Contactez le support.'];
        }
        if ($user['status'] === 'anonymized') {
            return ['success' => false, 'error' => 'Ce compte a été supprimé.'];
        }
        if (!$user['email_verified']) {
            return ['success' => false, 'error' => 'Vérifiez votre email avant de vous connecter. 📬'];
        }
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return ['success' => false, 'error' => 'Compte temporairement verrouillé.'];
        }

        // Reset tentatives
        Security::resetAttempts($ipHash, 'login');

        // Mise à jour dernière connexion
        Database::query(
            'UPDATE axnt_users SET last_login_at = NOW(), last_login_ip = ?, login_attempts = 0 WHERE id = ?',
            [$ipHash, $user['id']]
        );

        self::createSession($user);
        self::auditLog((int)$user['id'], 'login', 'local');

        return ['success' => true, 'user' => self::sanitizeUser($user)];
    }

    // ----------------------------------------------------------
    // OAUTH2 - Point d'entrée
    // ----------------------------------------------------------
    public static function getOAuthUrl(string $provider): string
    {
        $state = Security::generateToken(16);
        $_SESSION['oauth_state']    = $state;
        $_SESSION['oauth_provider'] = $provider;

        return match($provider) {
            'google'    => self::googleAuthUrl($state),
            'discord'   => self::discordAuthUrl($state),
            'microsoft' => self::microsoftAuthUrl($state),
            'facebook'  => self::facebookAuthUrl($state),
            'github'    => self::githubAuthUrl($state),
            default     => throw new InvalidArgumentException("Provider inconnu : $provider"),
        };
    }

    // ----------------------------------------------------------
    // OAUTH2 - Callback
    // ----------------------------------------------------------
    public static function handleOAuthCallback(string $provider, string $code, string $state): array
    {
        // Vérification CSRF state
        if (!isset($_SESSION['oauth_state']) || $_SESSION['oauth_state'] !== $state) {
            return ['success' => false, 'error' => 'État OAuth invalide. Tentative de CSRF détectée ? 🚨'];
        }
        unset($_SESSION['oauth_state']);

        $userData = match($provider) {
            'google'    => self::googleCallback($code),
            'discord'   => self::discordCallback($code),
            'microsoft' => self::microsoftCallback($code),
            'facebook'  => self::facebookCallback($code),
            'github'    => self::githubCallback($code),
            default     => null,
        };

        if (!$userData || empty($userData['email'])) {
            return ['success' => false, 'error' => 'Impossible de récupérer les informations depuis ' . ucfirst($provider)];
        }

        return self::loginOrRegisterOAuth($provider, $userData);
    }

    // ----------------------------------------------------------
    // OAUTH2 - Google
    // ----------------------------------------------------------
    private static function googleAuthUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => OAUTH_GOOGLE_CLIENT_ID,
            'redirect_uri'  => URL_AUTH . '/callback/google',
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'access_type'   => 'offline',
        ]);
    }

    private static function googleCallback(string $code): ?array
    {
        $token = self::httpPost('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => OAUTH_GOOGLE_CLIENT_ID,
            'client_secret' => OAUTH_GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => URL_AUTH . '/callback/google',
            'grant_type'    => 'authorization_code',
        ]);
        if (empty($token['access_token'])) return null;

        $info = self::httpGet('https://www.googleapis.com/oauth2/v2/userinfo', $token['access_token']);
        if (empty($info['id'])) return null;

        return [
            'provider_id'   => $info['id'],
            'email'         => $info['email'] ?? '',
            'name'          => $info['name'] ?? '',
            'avatar'        => $info['picture'] ?? null,
            'access_token'  => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
        ];
    }

    // ----------------------------------------------------------
    // OAUTH2 - Discord
    // ----------------------------------------------------------
    private static function discordAuthUrl(string $state): string
    {
        return 'https://discord.com/api/oauth2/authorize?' . http_build_query([
            'client_id'     => OAUTH_DISCORD_CLIENT_ID,
            'redirect_uri'  => URL_AUTH . '/callback/discord',
            'response_type' => 'code',
            'scope'         => 'identify email',
            'state'         => $state,
        ]);
    }

    private static function discordCallback(string $code): ?array
    {
        $token = self::httpPost('https://discord.com/api/oauth2/token', [
            'client_id'     => OAUTH_DISCORD_CLIENT_ID,
            'client_secret' => OAUTH_DISCORD_CLIENT_SECRET,
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => URL_AUTH . '/callback/discord',
        ]);
        if (empty($token['access_token'])) return null;

        $info = self::httpGet('https://discord.com/api/users/@me', $token['access_token']);
        if (empty($info['id'])) return null;

        return [
            'provider_id'   => $info['id'],
            'email'         => $info['email'] ?? '',
            'name'          => $info['username'] ?? '',
            'avatar'        => isset($info['avatar'])
                ? "https://cdn.discordapp.com/avatars/{$info['id']}/{$info['avatar']}.png"
                : null,
            'access_token'  => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
        ];
    }

    // ----------------------------------------------------------
    // OAUTH2 - Microsoft
    // ----------------------------------------------------------
    private static function microsoftAuthUrl(string $state): string
    {
        $tenant = OAUTH_MICROSOFT_TENANT;
        return "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize?" . http_build_query([
            'client_id'     => OAUTH_MICROSOFT_CLIENT_ID,
            'redirect_uri'  => URL_AUTH . '/callback/microsoft',
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
        ]);
    }

    private static function microsoftCallback(string $code): ?array
    {
        $tenant = OAUTH_MICROSOFT_TENANT;
        $token  = self::httpPost("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
            'client_id'     => OAUTH_MICROSOFT_CLIENT_ID,
            'client_secret' => OAUTH_MICROSOFT_CLIENT_SECRET,
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => URL_AUTH . '/callback/microsoft',
            'scope'         => 'openid email profile',
        ]);
        if (empty($token['access_token'])) return null;

        $info = self::httpGet('https://graph.microsoft.com/v1.0/me', $token['access_token']);
        if (empty($info['id'])) return null;

        return [
            'provider_id'   => $info['id'],
            'email'         => $info['mail'] ?? $info['userPrincipalName'] ?? '',
            'name'          => $info['displayName'] ?? '',
            'avatar'        => null,
            'access_token'  => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
        ];
    }

    // ----------------------------------------------------------
    // OAUTH2 - Facebook
    // ----------------------------------------------------------
    private static function facebookAuthUrl(string $state): string
    {
        return 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query([
            'client_id'     => OAUTH_FACEBOOK_APP_ID,
            'redirect_uri'  => URL_AUTH . '/callback/facebook',
            'state'         => $state,
            'scope'         => 'email,public_profile',
        ]);
    }

    private static function facebookCallback(string $code): ?array
    {
        $token = self::httpGet('https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query([
            'client_id'     => OAUTH_FACEBOOK_APP_ID,
            'client_secret' => OAUTH_FACEBOOK_APP_SECRET,
            'redirect_uri'  => URL_AUTH . '/callback/facebook',
            'code'          => $code,
        ]));
        if (empty($token['access_token'])) return null;

        $info = self::httpGet(
            'https://graph.facebook.com/me?fields=id,name,email,picture',
            $token['access_token']
        );
        if (empty($info['id'])) return null;

        return [
            'provider_id'   => $info['id'],
            'email'         => $info['email'] ?? '',
            'name'          => $info['name'] ?? '',
            'avatar'        => $info['picture']['data']['url'] ?? null,
            'access_token'  => $token['access_token'],
            'refresh_token' => null,
        ];
    }

    // ----------------------------------------------------------
    // OAUTH2 - GitHub
    // ----------------------------------------------------------
    private static function githubAuthUrl(string $state): string
    {
        return 'https://github.com/login/oauth/authorize?' . http_build_query([
            'client_id' => OAUTH_GITHUB_CLIENT_ID,
            'redirect_uri' => URL_AUTH . '/callback/github',
            'scope'     => 'user:email',
            'state'     => $state,
        ]);
    }

    private static function githubCallback(string $code): ?array
    {
        $token = self::httpPost('https://github.com/login/oauth/access_token', [
            'client_id'     => OAUTH_GITHUB_CLIENT_ID,
            'client_secret' => OAUTH_GITHUB_CLIENT_SECRET,
            'code'          => $code,
            'redirect_uri'  => URL_AUTH . '/callback/github',
        ], ['Accept: application/json']);
        if (empty($token['access_token'])) return null;

        $info = self::httpGet('https://api.github.com/user', $token['access_token'], ['User-Agent: Axent/1.0']);

        // GitHub peut ne pas retourner l'email public
        if (empty($info['email'])) {
            $emails = self::httpGet('https://api.github.com/user/emails', $token['access_token'], ['User-Agent: Axent/1.0']);
            foreach ($emails as $e) {
                if ($e['primary'] && $e['verified']) {
                    $info['email'] = $e['email'];
                    break;
                }
            }
        }

        return [
            'provider_id'   => (string) $info['id'],
            'email'         => $info['email'] ?? '',
            'name'          => $info['name'] ?? $info['login'] ?? '',
            'avatar'        => $info['avatar_url'] ?? null,
            'access_token'  => $token['access_token'],
            'refresh_token' => null,
        ];
    }

    // ----------------------------------------------------------
    // LOGIN OU INSCRIPTION via OAuth2
    // ----------------------------------------------------------
    private static function loginOrRegisterOAuth(string $provider, array $data): array
    {
        $linkUserId = isset($_SESSION['oauth_link_user_id']) ? (int) $_SESSION['oauth_link_user_id'] : 0;

        $oauth = Database::fetchOne(
            'SELECT oa.*, u.* FROM axnt_oauth_accounts oa
             JOIN axnt_users u ON u.id = oa.user_id
             WHERE oa.provider = ? AND oa.provider_id = ?',
            [$provider, $data['provider_id']]
        );

        if ($linkUserId > 0) {
            unset($_SESSION['oauth_link_user_id']);

            if ($oauth && (int) $oauth['user_id'] !== $linkUserId) {
                return ['success' => false, 'error' => 'Ce compte ' . ucfirst($provider) . ' est deja lie a un autre utilisateur.'];
            }

            if ($oauth && (int) $oauth['user_id'] === $linkUserId) {
                Database::query(
                    'UPDATE axnt_oauth_accounts SET access_token = ?, refresh_token = ?, updated_at = NOW() WHERE provider = ? AND provider_id = ?',
                    [$data['access_token'], $data['refresh_token'], $provider, $data['provider_id']]
                );
                $currentUser = Database::fetchOne('SELECT * FROM axnt_users WHERE id = ?', [$linkUserId]);
                self::auditLog($linkUserId, 'oauth_link_refresh', $provider);
                return ['success' => true, 'user' => self::sanitizeUser($currentUser), 'linked' => true];
            }

            $currentUser = Database::fetchOne('SELECT * FROM axnt_users WHERE id = ?', [$linkUserId]);
            if (!$currentUser) {
                return ['success' => false, 'error' => 'Compte utilisateur introuvable pour la liaison OAuth.'];
            }

            $sameEmailUser = Database::fetchOne('SELECT id FROM axnt_users WHERE email = ? LIMIT 1', [$data['email']]);
            if ($sameEmailUser && (int) $sameEmailUser['id'] !== $linkUserId) {
                return ['success' => false, 'error' => 'Cet email OAuth correspond deja a un autre compte Axent.'];
            }

            Database::insert(
                'INSERT INTO axnt_oauth_accounts (user_id, provider, provider_id, access_token, refresh_token) VALUES (?, ?, ?, ?, ?)',
                [$linkUserId, $provider, $data['provider_id'], $data['access_token'], $data['refresh_token']]
            );

            if (!empty($data['avatar']) && empty($currentUser['avatar_url'])) {
                Database::query('UPDATE axnt_users SET avatar_url = ? WHERE id = ?', [$data['avatar'], $linkUserId]);
            }

            $currentUser = Database::fetchOne('SELECT * FROM axnt_users WHERE id = ?', [$linkUserId]);
            self::auditLog($linkUserId, 'oauth_link', $provider);
            return ['success' => true, 'user' => self::sanitizeUser($currentUser), 'linked' => true];
        }

        if ($oauth) {
            Database::query(
                'UPDATE axnt_oauth_accounts SET access_token = ?, refresh_token = ?, updated_at = NOW() WHERE provider = ? AND provider_id = ?',
                [$data['access_token'], $data['refresh_token'], $provider, $data['provider_id']]
            );
            Database::query('UPDATE axnt_users SET last_login_at = NOW() WHERE id = ?', [$oauth['user_id']]);
            self::createSession($oauth);
            self::auditLog((int)$oauth['user_id'], 'login', $provider);
            return ['success' => true, 'user' => self::sanitizeUser($oauth)];
        }

        $existing = Database::fetchOne('SELECT * FROM axnt_users WHERE email = ?', [$data['email']]);

        if ($existing) {
            $userId = $existing['id'];
        } else {
            $uuid   = self::generateUUID();
            $userId = Database::insert(
                'INSERT INTO axnt_users (uuid, email, email_verified, email_verified_at, display_name, avatar_url, role, status) VALUES (?, ?, 1, NOW(), ?, ?, "user", "active")',
                [$uuid, $data['email'], htmlspecialchars($data['name'], ENT_QUOTES), $data['avatar']]
            );
            $existing = Database::fetchOne('SELECT * FROM axnt_users WHERE id = ?', [$userId]);
        }

        Database::insert(
            'INSERT INTO axnt_oauth_accounts (user_id, provider, provider_id, access_token, refresh_token) VALUES (?, ?, ?, ?, ?)',
            [$userId, $provider, $data['provider_id'], $data['access_token'], $data['refresh_token']]
        );

        self::createSession($existing);
        self::auditLog($userId, 'register', $provider);

        return ['success' => true, 'user' => self::sanitizeUser($existing)];
    }

    // ----------------------------------------------------------
    // SESSION
    // ----------------------------------------------------------
    public static function createSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_uuid'] = $user['uuid'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['display_name'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_at']  = time();
    }

    public static function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        session_unset();
        session_destroy();
        if ($userId) self::auditLog((int)$userId, 'logout', null);
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['logged_in'])
            && !empty($_SESSION['user_id'])
            && (time() - ($_SESSION['login_at'] ?? 0)) < SESSION_LIFETIME;
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: ' . URL_AUTH . '/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'superadmin'])) {
            http_response_code(403);
            die('Accès refusé. Tu n\'es pas admin ! 🚫');
        }
    }

    // ----------------------------------------------------------
    // UTILITAIRES
    // ----------------------------------------------------------
    private static function sanitizeUser(array $user): array
    {
        return [
            'id'           => $user['id'],
            'uuid'         => $user['uuid'],
            'email'        => $user['email'],
            'display_name' => $user['display_name'],
            'avatar_url'   => $user['avatar_url'],
            'role'         => $user['role'],
            'lang'         => $user['lang'] ?? 'fr',
        ];
    }

    private static function generateUUID(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private static function auditLog(int $userId, string $action, ?string $detail): void
    {
        $ip = Database::hashIP($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        Database::query(
            'INSERT INTO axnt_audit_log (user_id, action, target, ip_hashed) VALUES (?, ?, ?, ?)',
            [$userId, $action, $detail, $ip]
        );
    }

    private static function httpPost(string $url, array $data, array $headers = []): array
    {
        $headers = array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            return json_decode($response ?: '{}', true) ?? [];
        }

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", $headers) . "\r\n",
                'content' => http_build_query($data),
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        return json_decode($response ?: '{}', true) ?? [];
    }

    private static function httpGet(string $url, ?string $token = null, array $extraHeaders = []): array
    {
        $headers = $extraHeaders;
        if ($token) {
            $headers[] = "Authorization: Bearer $token";
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            return json_decode($response ?: '{}', true) ?? [];
        }

        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => $headers ? implode("\r\n", $headers) . "\r\n" : '',
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        return json_decode($response ?: '{}', true) ?? [];
    }
}
