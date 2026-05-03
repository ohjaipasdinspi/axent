<?php
declare(strict_types=1);

require_once __DIR__ . '/../httpdocs/config/config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Documentation - Axent</title>
<meta name="robots" content="index,follow">
<style>
body{font-family:"Plus Jakarta Sans",system-ui,sans-serif;background:#f5f7fb;color:#2D3047;margin:0;padding:40px 24px}
.wrap{max-width:920px;margin:0 auto;background:#fff;border-radius:24px;padding:40px;box-shadow:0 20px 60px rgba(45,48,71,.08)}
h1{margin:0 0 12px;font-size:38px}
p{line-height:1.7;color:#556070}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:32px}
.card{display:block;padding:20px;border:1px solid #e6ebf2;border-radius:18px;text-decoration:none;color:inherit}
.card:hover{border-color:#FF6B35}
.tag{display:inline-block;background:#fff1eb;color:#d95d2f;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:700;margin-bottom:10px}
</style>
</head>
<body>
<main class="wrap">
  <span class="tag">Docs</span>
  <h1>Documentation Axent</h1>
  <p>Le sous-domaine de documentation est pret. Tu peux l'utiliser comme point d'entree public pour l'installation, l'API et l'integration du widget.</p>
  <div class="grid">
    <a class="card" href="<?= htmlspecialchars(APP_URL) ?>">
      <strong>Site principal</strong>
      <p>Retour a la page publique d'Axent.</p>
    </a>
    <a class="card" href="<?= htmlspecialchars(URL_API) ?>/health">
      <strong>API Health</strong>
      <p>Verifier rapidement que l'API repond.</p>
    </a>
    <a class="card" href="<?= htmlspecialchars(URL_CDN) ?>/sdk.js">
      <strong>SDK JavaScript</strong>
      <p>Acces direct au fichier distribue par le CDN.</p>
    </a>
  </div>
</main>
</body>
</html>
