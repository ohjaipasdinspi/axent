<?php
declare(strict_types=1);

require_once __DIR__ . '/../httpdocs/config/config.php';
require_once __DIR__ . '/../httpdocs/core/Database.php';
require_once __DIR__ . '/../httpdocs/core/Security.php';
require_once __DIR__ . '/../httpdocs/core/Lang.php';
require_once __DIR__ . '/../auth.axet.fr/Auth.php';

Security::setSecurityHeaders();

$route = trim((string) ($_GET['_route'] ?? ''), '/');

if ($route === '' || $route === 'index') {
    header('Location: ' . URL_APP . '/dashboard');
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

if ($route === 'settings') {
    require __DIR__ . '/settings/index.php';
    exit;
}

if ($route === 'rgpd') {
    require __DIR__ . '/rgpd/index.php';
    exit;
}

if ($route === 'sites/add') {
    require __DIR__ . '/add.php';
    exit;
}

// Placeholder for site-specific pages (stats, settings)
if (preg_match('#^sites/[^/]+/(stats|settings)$#', $route) === 1) {
    Auth::requireLogin();
    http_response_code(200);
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="https://axet.fr/favicon.ico">
    <title>Module en préparation - Axent</title>
    <style>
    body{font-family:"Plus Jakarta Sans",system-ui,sans-serif;background:#f4f5fb;color:#2D3047;margin:0;padding:32px}
    .card{max-width:720px;margin:60px auto;background:#fff;border-radius:24px;padding:32px;box-shadow:0 16px 50px rgba(45,48,71,.08)}
    a{color:#FF6B35;text-decoration:none;font-weight:700}
    </style>
    </head>
    <body>
      <div class="card">
        <h1>Module en préparation</h1>
        <p>Cette page de gestion de site n'était pas encore implémentée. Le routage du sous-domaine est maintenant prêt, mais cette section reste à développer.</p>
        <p><a href="<?= htmlspecialchars(URL_APP) ?>/dashboard">Retour au dashboard</a></p>
      </div>
    </body>
    </html>
    <?php
    exit;
}

http_response_code(404);
exit('Page introuvable.');
