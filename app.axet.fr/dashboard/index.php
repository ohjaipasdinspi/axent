<?php
/**
 * Axent - Dashboard utilisateur
 * URL : app.axet.fr/dashboard
 */
declare(strict_types=1);
require_once __DIR__ . '/../../httpdocs/config/config.php';
require_once __DIR__ . '/../../httpdocs/core/Database.php';
require_once __DIR__ . '/../../httpdocs/core/Security.php';
require_once __DIR__ . '/../../httpdocs/core/Lang.php';
require_once __DIR__ . '/../../auth.axet.fr/Auth.php';

Security::setSecurityHeaders();
Auth::requireLogin();
Lang::init($_SESSION['user_lang'] ?? LANG_DEFAULT);

$userId = (int) $_SESSION['user_id'];
$user   = Database::fetchOne('SELECT * FROM axnt_users WHERE id = ?', [$userId]);

// Récupérer les sites de l'utilisateur
$sites = Database::fetchAll(
    'SELECT s.*, cv.uuid as version_uuid,
     (SELECT COUNT(*) FROM axnt_consents c WHERE c.site_id = s.id) as total_consents,
     (SELECT COUNT(*) FROM axnt_consents c WHERE c.site_id = s.id AND c.choice = "accepted") as accepted,
     (SELECT COUNT(*) FROM axnt_consents c WHERE c.site_id = s.id AND c.choice = "refused") as refused
     FROM axnt_sites s
     LEFT JOIN axnt_cookie_versions cv ON cv.site_id = s.id AND cv.is_active = 1
     WHERE s.user_id = ?
     ORDER BY s.created_at DESC',
    [$userId]
);

$totalConsents = array_sum(array_column($sites, 'total_consents'));
$totalSites    = count($sites);
$defaultSnippetClientId = !empty($sites[0]['uuid']) ? (string) $sites[0]['uuid'] : null;
$defaultSnippetVersionId = !empty($sites[0]['version_uuid']) ? (string) $sites[0]['version_uuid'] : null;
$defaultSnippetLang = Lang::current();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(Lang::current()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mon espace — Axent</title>
<link rel="icon" type="image/x-icon" href="https://axet.fr/favicon.ico">
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root { --primary:#FF6B35;--secondary:#4ECDC4;--dark:#2D3047;--light:#F7F7FF;--border:#E8EAF0;--gray:#8891A4;--radius:16px;--shadow:0 4px 24px rgba(45,48,71,.08); }
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#f4f5fb;color:var(--dark);min-height:100vh}
.topbar{background:#fff;border-bottom:1px solid var(--border);padding:0 32px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
.logo{display:flex;align-items:center;gap:8px;text-decoration:none}
.logo-icon{width:36px;height:36px;background:var(--primary);border-radius:10px;display:flex;align-items:center;justify-content:center}
.logo-text{font-size:18px;font-weight:800;color:var(--dark)}
.topbar-nav{display:flex;align-items:center;gap:24px}
.topbar-nav a{color:var(--gray);text-decoration:none;font-size:14px;font-weight:600;transition:.2s}
.topbar-nav a:hover,.topbar-nav a.active{color:var(--dark)}
.topbar-right{display:flex;align-items:center;gap:12px}
.avatar{width:36px;height:36px;border-radius:10px;background:var(--primary);color:#fff;font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center}
.content{max-width:1100px;margin:0 auto;padding:40px 24px}
.page-header{margin-bottom:32px}
.page-header h1{font-size:28px;font-weight:800;margin-bottom:4px}
.page-header p{color:var(--gray);font-size:15px}
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:32px}
.stat-box{background:#fff;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);text-align:center}
.stat-box .num{font-size:36px;font-weight:900;color:var(--primary);margin-bottom:4px}
.stat-box .lbl{font-size:13px;color:var(--gray);font-weight:600}
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.section-header h2{font-size:18px;font-weight:800}
.btn{padding:10px 20px;border-radius:50px;font-size:14px;font-weight:700;cursor:pointer;border:none;font-family:inherit;text-decoration:none;display:inline-block;transition:.2s}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{background:#e55a26;transform:translateY(-1px)}
.sites-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px}
.site-card{background:#fff;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);border:2px solid transparent;transition:.3s;cursor:pointer}
.site-card:hover{border-color:var(--primary);transform:translateY(-3px)}
.site-domain{font-size:16px;font-weight:800;margin-bottom:4px;display:flex;align-items:center;gap:8px}
.site-domain .dot{width:8px;height:8px;border-radius:50%;background:var(--secondary)}
.site-meta{font-size:13px;color:var(--gray);margin-bottom:20px}
.site-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.mini-stat{background:var(--light);border-radius:10px;padding:10px;text-align:center}
.mini-stat .val{font-size:18px;font-weight:800}
.mini-stat .key{font-size:11px;color:var(--gray);font-weight:600}
.mini-stat.green .val{color:#06D6A0}
.mini-stat.red .val{color:#EF476F}
.site-actions{margin-top:16px;display:flex;gap:8px;border-top:1px solid var(--border);padding-top:16px}
.btn-xs{padding:7px 14px;font-size:12px;border-radius:50px;border:1px solid var(--border);background:#fff;cursor:pointer;font-family:inherit;font-weight:700;transition:.2s;text-decoration:none;color:var(--dark)}
.btn-xs:hover{background:var(--light);border-color:var(--primary);color:var(--primary)}
.empty-state{text-align:center;padding:80px 24px;background:#fff;border-radius:var(--radius);box-shadow:var(--shadow)}
.empty-state .em{font-size:64px;margin-bottom:16px;display:block}
.empty-state h3{font-size:22px;font-weight:800;margin-bottom:8px}
.empty-state p{color:var(--gray);margin-bottom:24px}
.code-block{background:var(--dark);border-radius:12px;padding:20px 24px;font-family:monospace;font-size:13px;color:#e8e9f0;position:relative;margin-top:8px;line-height:1.7}
.copy-btn{position:absolute;top:12px;right:12px;background:rgba(255,255,255,.1);border:none;color:#fff;padding:5px 10px;border-radius:6px;cursor:pointer;font-size:12px;font-family:inherit}
.copy-btn:hover{background:rgba(255,255,255,.2)}
@media(max-width:768px){.stats-row{grid-template-columns:1fr}.topbar-nav{display:none}}
</style>
</head>
<body>

<header class="topbar">
  <a href="<?= URL_APP ?>/dashboard" class="logo">
    <div class="logo-icon"><img src="https://cdn.axet.fr/favicon.ico" width="24" height="24" alt=""></div>
    <span class="logo-text">Axent</span>
  </a>
  <nav class="topbar-nav">
    <a href="<?= URL_APP ?>/dashboard" class="active">Mes sites</a>
    <a href="<?= URL_APP ?>/settings">Paramètres</a>
    <a href="<?= URL_APP ?>/rgpd">Mes données</a>
    <a href="<?= URL_DOCS ?>" target="_blank">Docs</a>
  </nav>
  <div class="topbar-right">
    <div class="avatar" title="<?= htmlspecialchars($user['display_name']) ?>">
      <?= strtoupper(substr($user['display_name'], 0, 2)) ?>
    </div>
    <a href="<?= URL_AUTH ?>/logout" style="color:var(--gray);font-size:13px;text-decoration:none;font-weight:600">Déconnexion</a>
  </div>
</header>

<main class="content">
  <div class="page-header">
    <h1>Bonjour <?= htmlspecialchars(explode(' ', $user['display_name'])[0]) ?> ! 👋</h1>
    <p>Voici l'état de vos cookies. La situation est sous contrôle.</p>
  </div>

  <!-- Stats globales -->
  <div class="stats-row">
    <div class="stat-box">
      <div class="num"><?= $totalSites ?></div>
      <div class="lbl">Sites actifs</div>
    </div>
    <div class="stat-box">
      <div class="num"><?= number_format($totalConsents) ?></div>
      <div class="lbl">Consentements enregistrés</div>
    </div>
    <div class="stat-box">
      <div class="num"><?= $totalSites > 0 ? round(array_sum(array_column($sites, 'accepted')) / max($totalConsents, 1) * 100) : 0 ?>%</div>
      <div class="lbl">Taux d'acceptation</div>
    </div>
  </div>

  <!-- Liste des sites -->
  <div class="section-header">
    <h2>Mes sites (<?= $totalSites ?>)</h2>
    <a href="<?= URL_APP ?>/sites/add" class="btn btn-primary">+ Ajouter un site</a>
  </div>

  <?php if (empty($sites)): ?>
  <div class="empty-state">
    <span class="em">🌐</span>
    <h3>Aucun site pour l'instant</h3>
    <p>Ajoutez votre premier site et commencez à gérer les cookies comme un·e pro.</p>
    <a href="<?= URL_APP ?>/sites/add" class="btn btn-primary">Ajouter mon premier site</a>
  </div>
  <?php else: ?>
  <div class="sites-grid">
    <?php foreach ($sites as $site): ?>
    <div class="site-card">
      <div class="site-domain">
        <span class="dot"></span>
        <?= htmlspecialchars($site['domain']) ?>
      </div>
      <div class="site-meta">
        <?= htmlspecialchars($site['name']) ?> · Plan <?= strtoupper($site['plan']) ?>
        · Ajouté le <?= date('d/m/Y', strtotime($site['created_at'])) ?>
      </div>
      <div class="site-stats">
        <div class="mini-stat">
          <div class="val"><?= number_format((int)$site['total_consents']) ?></div>
          <div class="key">Total</div>
        </div>
        <div class="mini-stat green">
          <div class="val"><?= number_format((int)$site['accepted']) ?></div>
          <div class="key">Acceptés</div>
        </div>
        <div class="mini-stat red">
          <div class="val"><?= number_format((int)$site['refused']) ?></div>
          <div class="key">Refusés</div>
        </div>
      </div>
      <div class="site-actions">
        <a href="<?= URL_APP ?>/sites/<?= $site['uuid'] ?>/stats" class="btn-xs">📊 Stats</a>
        <a href="<?= URL_APP ?>/sites/<?= $site['uuid'] ?>/settings" class="btn-xs">⚙️ Config</a>
        <button class="btn-xs" onclick="copySnippet('<?= htmlspecialchars($site['uuid']) ?>', '<?= htmlspecialchars($site['version_uuid'] ?? '') ?>')">📋 Snippet</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Guide rapide si credentials disponibles -->
  <?php if ($defaultSnippetClientId !== null && $defaultSnippetVersionId !== null): ?>
  <div style="margin-top:32px;background:#fff;border-radius:var(--radius);padding:32px;box-shadow:var(--shadow)">
    <h3 style="font-size:18px;font-weight:800;margin-bottom:8px">⚡ Intégrer le widget sur votre site</h3>
    <p style="color:var(--gray);font-size:14px;margin-bottom:16px">Copiez ces 2 lignes dans le <code>&lt;head&gt;</code> de votre site. C'est vraiment tout.</p>
    <div class="code-block" id="snippet-global">
      <button class="copy-btn" onclick="copyGlobal()">Copier</button>
&lt;script&gt;
window.axentSettings = {
  <span style="color:#f1fa8c">clientId</span>: <span style="color:#50fa7b">"<?= htmlspecialchars($defaultSnippetClientId) ?>"</span>,
  <span style="color:#f1fa8c">cookiesVersion</span>: <span style="color:#50fa7b">"<?= htmlspecialchars($defaultSnippetVersionId) ?>"</span>,
  <span style="color:#f1fa8c">lang</span>: <span style="color:#50fa7b">"<?= htmlspecialchars($defaultSnippetLang) ?>"</span>,
  <span style="color:#f1fa8c">position</span>: <span style="color:#50fa7b">"bottom-left"</span>
};
&lt;/script&gt;
&lt;script async src=<span style="color:#50fa7b">"//cdn.axet.fr/sdk.js"</span>&gt;&lt;/script&gt;
    </div>
  </div>
  <?php endif; ?>
</main>

<script>
function copySnippet(clientId, versionId) {
  const code = `<script>\nwindow.axentSettings = {\n  clientId: "${clientId}",\n  cookiesVersion: "${versionId}",\n  lang: "fr",\n  position: "bottom-left"\n};\n<\/script>\n<script async src="//cdn.axet.fr/sdk.js"><\/script>`;
  navigator.clipboard.writeText(code).then(() => alert('Snippet copié dans le presse-papiers ! 📋'));
}
function copyGlobal() {
  const code = document.getElementById('snippet-global').innerText.replace('Copier', '').trim();
  navigator.clipboard.writeText(code).then(() => alert('Copié ! 📋'));
}
</script>
</body>
</html>
