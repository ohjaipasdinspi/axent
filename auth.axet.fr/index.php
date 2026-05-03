<?php
declare(strict_types=1);

require_once __DIR__ . '/../httpdocs/config/config.php';
require_once __DIR__ . '/../httpdocs/core/Database.php';
require_once __DIR__ . '/../httpdocs/core/Security.php';
require_once __DIR__ . '/Auth.php';

Security::setSecurityHeaders();

$route = trim((string) ($_GET['_route'] ?? ''), '/');

if ($route === '' || $route === 'index') {
    header('Location: ' . URL_AUTH . '/login');
    exit;
}

if ($route === 'logout') {
    Auth::logout();
    session_start();
    $_SESSION['flash_success'] = 'Vous etes deconnecte avec succes.';
    header('Location: ' . URL_AUTH . '/login');
    exit;
}

if ($route === 'login') {
    require __DIR__ . '/login.php';
    exit;
}

if ($route === 'register') {
    require __DIR__ . '/register.php';
    exit;
}

if ($route === 'verify') {
    $token = trim((string) ($_GET['token'] ?? ''));
    if ($token === '') {
        $_SESSION['flash_error'] = 'Lien de verification incomplet.';
        header('Location: ' . URL_AUTH . '/login');
        exit;
    }

    $user = Database::fetchOne(
        'SELECT id, email_verified, display_name, email FROM axnt_users WHERE newsletter_token = ? LIMIT 1',
        [$token]
    );

    if (!$user) {
        $_SESSION['flash_error'] = 'Lien de verification invalide ou deja utilise.';
        header('Location: ' . URL_AUTH . '/login');
        exit;
    }

    if (!(bool) $user['email_verified']) {
        Database::query(
            'UPDATE axnt_users SET email_verified = 1, email_verified_at = NOW(), newsletter_token = NULL WHERE id = ?',
            [$user['id']]
        );
        try {
            require_once __DIR__ . '/../httpdocs/mail/Mailer.php';
            Mailer::sendWelcome($user['email'], $user['display_name']);
        } catch (\Throwable $e) {
        }
    }

    $_SESSION['flash_success'] = 'Email verifie. Tu peux maintenant te connecter.';
    header('Location: ' . URL_AUTH . '/login');
    exit;
}

if ($route === 'callback' || str_starts_with($route, 'callback/')) {
    require __DIR__ . '/callback.php';
    exit;
}

if (preg_match('#^auth/([a-z0-9_-]+)$#i', $route, $matches) === 1) {
    $provider = strtolower($matches[1]);
    $allowedProviders = ['google', 'discord', 'microsoft', 'facebook', 'github'];
    if (!in_array($provider, $allowedProviders, true)) {
        http_response_code(404);
        exit('Provider OAuth inconnu.');
    }

    $enabledMap = [
        'google' => OAUTH_GOOGLE_ENABLED,
        'discord' => OAUTH_DISCORD_ENABLED,
        'microsoft' => OAUTH_MICROSOFT_ENABLED,
        'facebook' => OAUTH_FACEBOOK_ENABLED,
        'github' => OAUTH_GITHUB_ENABLED,
    ];

    if (empty($enabledMap[$provider])) {
        $_SESSION['flash_error'] = 'Cette methode de connexion est desactivee.';
        header('Location: ' . URL_AUTH . '/login');
        exit;
    }

    if (($_GET['mode'] ?? '') === 'link') {
        if (!Auth::isLoggedIn()) {
            $_SESSION['flash_error'] = 'Connecte-toi d abord pour lier un compte externe.';
            header('Location: ' . URL_AUTH . '/login');
            exit;
        }
        $_SESSION['oauth_link_user_id'] = (int) $_SESSION['user_id'];
        $_SESSION['oauth_redirect'] = URL_APP . '/settings';
    }

    if (!empty($_GET['redirect']) && !str_starts_with((string) $_GET['redirect'], 'http')) {
        $redirect = '/' . ltrim((string) $_GET['redirect'], '/');
        $_SESSION['oauth_redirect'] = URL_APP . $redirect;
    }

    header('Location: ' . Auth::getOAuthUrl($provider));
    exit;
}

http_response_code(404);
exit('Page introuvable.');
