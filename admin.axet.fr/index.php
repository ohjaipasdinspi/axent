<?php
declare(strict_types=1);

require_once __DIR__ . '/../httpdocs/config/config.php';
require_once __DIR__ . '/../httpdocs/core/Database.php';
require_once __DIR__ . '/../httpdocs/core/Security.php';
require_once __DIR__ . '/../auth.axet.fr/Auth.php';

Security::setSecurityHeaders();

$route = trim((string) ($_GET['_route'] ?? ''), '/');

if ($route === '' || $route === 'index') {
    header('Location: ' . URL_ADMIN . '/dashboard');
    exit;
}

if ($route === 'logout') {
    header('Location: ' . URL_AUTH . '/logout');
    exit;
}

if ($route === 'dashboard') {
    require __DIR__ . '/dashboard/index.php';
    exit;
}

if ($route === 'users') {
    require __DIR__ . '/users/index.php';
    exit;
}

$placeholderRoutes = ['sites', 'consents', 'stats', 'settings', 'rgpd', 'audit', 'emails', 'health'];
if (in_array($route, $placeholderRoutes, true)) {
    Auth::requireAdmin();
    http_response_code(200);
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Module admin en preparation - Axent</title>
    <style>
    body{font-family:"Plus Jakarta Sans",system-ui,sans-serif;background:#f4f5fb;color:#2D3047;margin:0;padding:32px}
    .card{max-width:760px;margin:60px auto;background:#fff;border-radius:24px;padding:32px;box-shadow:0 16px 50px rgba(45,48,71,.08)}
    a{color:#FF6B35;text-decoration:none;font-weight:700}
    </style>
    </head>
    <body>
      <div class="card">
        <h1>Section admin en preparation</h1>
        <p>Le sous-domaine admin est branche et securise. La page <strong><?= htmlspecialchars($route) ?></strong> n'etait pas encore developpee, donc je l'ai remplacee par un ecran temporaire propre.</p>
        <p><a href="<?= htmlspecialchars(URL_ADMIN) ?>/dashboard">Retour au dashboard admin</a></p>
      </div>
    </body>
    </html>
    <?php
    exit;
}

http_response_code(404);
exit('Page introuvable.');
