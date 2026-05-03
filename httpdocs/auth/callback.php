<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/Auth.php';

Security::setSecurityHeaders();

$route = $_GET['_route'] ?? '';
$parts = explode('/', trim($route, '/'));
$provider = $parts[1] ?? '';

$allowedProviders = ['google', 'discord', 'microsoft', 'facebook', 'github'];
if (!in_array($provider, $allowedProviders, true)) {
    http_response_code(400);
    exit('Provider OAuth inconnu.');
}

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$error = $_GET['error'] ?? '';

if ($error !== '') {
    $_SESSION['flash_error'] = 'Connexion annulee. Pas de probleme, on recommence quand vous voulez.';
    header('Location: ' . URL_AUTH . '/login');
    exit;
}

if ($code === '' || $state === '') {
    $_SESSION['flash_error'] = 'Parametres OAuth manquants.';
    header('Location: ' . URL_AUTH . '/login');
    exit;
}

$result = Auth::handleOAuthCallback($provider, $code, $state);

if (empty($result['success'])) {
    $_SESSION['flash_error'] = $result['error'] ?? 'Erreur OAuth.';
    header('Location: ' . URL_AUTH . '/login');
    exit;
}

$redirect = $_SESSION['oauth_redirect'] ?? URL_APP . '/dashboard';
unset($_SESSION['oauth_redirect']);

Security::safeRedirect($redirect);
