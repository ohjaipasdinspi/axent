<?php
/**
 * Axent - OAuth2 Callback Handler
 * URL : auth.axet.fr/callback/{provider}
 */

declare(strict_types=1);

require_once __DIR__ . '/../httpdocs/config/config.php';
require_once __DIR__ . '/../httpdocs/core/Database.php';
require_once __DIR__ . '/../httpdocs/core/Security.php';
require_once __DIR__ . '/Auth.php';

Security::setSecurityHeaders();

// Extraire le provider depuis l'URL
$route    = $_GET['_route'] ?? '';
$parts    = explode('/', trim($route, '/'));
$provider = $parts[1] ?? ''; // callback/{provider}

$allowedProviders = ['google', 'discord', 'microsoft', 'facebook', 'github'];
if (!in_array($provider, $allowedProviders, true)) {
    http_response_code(400);
    die('Provider OAuth inconnu.');
}

$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';
$error = $_GET['error'] ?? '';

// Le provider a renvoyé une erreur (ex: l'utilisateur a cliqué "Annuler")
if ($error) {
    $_SESSION['flash_error'] = 'Connexion annulée. Pas de problème, on recommence quand vous voulez ! 😊';
    header('Location: ' . URL_AUTH . '/login');
    exit;
}

if (empty($code) || empty($state)) {
    $_SESSION['flash_error'] = 'Paramètres OAuth manquants.';
    header('Location: ' . URL_AUTH . '/login');
    exit;
}

// Traitement du callback
$result = Auth::handleOAuthCallback($provider, $code, $state);

if (!$result['success']) {
    $_SESSION['flash_error'] = $result['error'] ?? 'Erreur OAuth.';
    header('Location: ' . URL_AUTH . '/login');
    exit;
}

// Succès : rediriger vers l'app
$_SESSION['flash_success'] = !empty($result['linked'])
    ? 'Compte ' . ucfirst($provider) . ' lie avec succes.'
    : 'Connexion reussie avec ' . ucfirst($provider) . '.';

$redirect = $_SESSION['oauth_redirect'] ?? URL_APP . '/dashboard';
unset($_SESSION['oauth_redirect']);

Security::safeRedirect($redirect);
