<?php
declare(strict_types=1);
require_once __DIR__ . '/../../httpdocs/config/config.php';
require_once __DIR__ . '/../../httpdocs/core/Database.php';
require_once __DIR__ . '/../../httpdocs/core/Security.php';
require_once __DIR__ . '/../../auth.axet.fr/Auth.php';

Security::setSecurityHeaders();
Auth::requireLogin();

$userId  = (int) $_SESSION['user_id'];
$user    = Database::fetchOne('SELECT * FROM axnt_users WHERE id = ?', [$userId]);
$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $type = $_POST['type'] ?? '';
    if (in_array($type, ['export', 'delete', 'rectify', 'oppose'], true)) {
        // Vérifier qu'il n'y a pas déjà une demande en cours
        $existing = Database::fetchOne(
            'SELECT id FROM axnt_rgpd_requests WHERE email = ? AND type = ? AND status IN ("pending","in_progress")',
            [$user['email'], $type]
        );
        if ($existing) {
            $error = 'Une demande de ce type est déjà en cours de traitement. Patience ! ⏳';
        } else {
            $token = Security::generateToken(32);
            Database::insert(
                'INSERT INTO axnt_rgpd_requests (user_id, email, type, token) VALUES (?, ?, ?, ?)',
                [$userId, $user['email'], $type, $token]
            );
            // Envoyer email de confirmation
            require_once __DIR__ . '/../../httpdocs/mail/Mailer.php';
            Mailer::sendRgpdRequestConfirmation($user['email'], $user['display_name'], $type);
            $message = 'Votre demande a été enregistrée. Vous recevrez une réponse sous 30 jours (délai légal RGPD).';
        }
    }
}

// Récupérer les demandes existantes
$requests = Database::fetchAll(
    'SELECT * FROM axnt_rgpd_requests WHERE user_id = ? ORDER BY created_at DESC',
    [$userId]
);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mes données RGPD — Axent</title>
<link rel="icon" type="image/x-icon" href="https://axet.fr/favicon.ico">
<meta name="robots" content="noindex">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--primary:#FF6B35;--dark:#2D3047;--light:#F7F7FF;--border:#E8EAF0;--gray:#8891A4;--radius:16px;--shadow:0 4px 24px rgba(45,48,71,.08)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#f4f5fb;color:var(--dark)}
.topbar{background:#fff;border-bottom:1px solid var(--border);padding:0 32px;height:64px;display:flex;align-items:center;justify-content:space-between}
.logo{display:flex;align-items:center;gap:8px;text-decoration:none}
.logo-icon{width:36px;height:36px;background:var(--primary);border-radius:10px;display:flex;align-items:center;justify-content:center}
.logo-text{font-size:18px;font-weight:800;color:var(--dark)}
.content{max-width:760px;margin:0 auto;padding:40px 24px}
.card{background:#fff;border-radius:var(--radius);padding:32px;box-shadow:var(--shadow);margin-bottom:24px}
.card h2{font-size:18px;font-weight:800;margin-bottom:4px}
.card .sub{color:var(--gray);font-size:14px;margin-bottom:24px}
.rights-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.right-item{border:2px solid var(--border);border-radius:12px;padding:20px;cursor:pointer;transition:.2s}
.right-item:hover{border-color:var(--primary)}
.right-item h3{font-size:15px;font-weight:800;margin-bottom:4px}
.right-item p{font-size:13px;color:var(--gray);line-height:1.5;margin-bottom:16px}
.btn{padding:10px 20px;border-radius:50px;font-size:13px;font-weight:700;cursor:pointer;border:none;font-family:inherit;transition:.2s;text-align:center}
.btn-primary{background:var(--primary);color:#fff;display:block;width:100%}
.btn-primary:hover{background:#e55a26}
.btn-danger{background:#EF476F;color:#fff;display:block;width:100%}
.btn-danger:hover{background:#d03460}
.alert{padding:12px 16px;border-radius:10px;font-size:14px;font-weight:600;margin-bottom:20px}
.alert-success{background:#f0fff8;color:#0a7f5a;border:1px solid #b2edd6}
.alert-error{background:#fff0f0;color:#c0392b;border:1px solid #ffd5d5}
table{width:100%;border-collapse:collapse}
th{padding:10px 16px;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:var(--gray);font-weight:700;border-bottom:1px solid var(--border);text-align:left}
td{padding:12px 16px;font-size:14px;border-bottom:1px solid #f5f5f5}
.status-badge{display:inline-flex;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}
.status-pending{background:rgba(255,209,102,.2);color:#8a6800}
.status-completed{background:rgba(6,214,160,.12);color:#0a7f5a}
.status-in_progress{background:rgba(78,205,196,.12);color:#0a7f78}
.page-header{margin-bottom:32px}
.page-header h1{font-size:28px;font-weight:800;margin-bottom:4px}
.page-header p{color:var(--gray);font-size:15px}
.topbar{background:#fff;border-bottom:1px solid var(--border);padding:0 32px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
.logo{display:flex;align-items:center;gap:8px;text-decoration:none}
.logo-icon{width:36px;height:36px;background:var(--primary);border-radius:10px;display:flex;align-items:center;justify-content:center}
.logo-text{font-size:18px;font-weight:800;color:var(--dark)}
.topbar-nav{display:flex;align-items:center;gap:24px}
.topbar-nav a{color:var(--gray);text-decoration:none;font-size:14px;font-weight:600;transition:.2s}
.topbar-nav a:hover,.topbar-nav a.active{color:var(--dark)}
.topbar-right{display:flex;align-items:center;gap:12px}
</style>
</head>
<body>

<header class="topbar">
  <a href="<?= URL_APP ?>/dashboard" class="logo">
    <div class="logo-icon"><img src="https://cdn.axet.fr/favicon.ico" width="24" height="24" alt=""></div>
    <span class="logo-text">Axent</span>
  </a>
  <nav class="topbar-nav">
    <a href="<?= URL_APP ?>/dashboard">Mes sites</a>
    <a href="<?= URL_APP ?>/settings">Paramètres</a>
    <a href="<?= URL_APP ?>/rgpd" class="active">Mes données</a>
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
  <h1 style="font-size:26px;font-weight:800;margin-bottom:8px"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-4px;margin-right:8px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Mes données personnelles</h1>
  <p style="color:var(--gray);font-size:15px;margin-bottom:32px">Exercez vos droits RGPD. Nous répondons sous 30 jours maximum.</p>

  <?php if ($message): ?><div class="alert alert-success"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:6px"><polyline points="20 6 9 17 4 12"/></svg><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:6px"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="card">
    <h2>Vos droits RGPD</h2>
    <p class="sub">Conformément au RGPD (articles 15 à 22), vous disposez des droits suivants</p>
    <div class="rights-grid">
      <div class="right-item">
        <h3><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:6px"><path d="M16.5 9.4 7.55 4.24"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.29 7 12 12 20.71 7"/><line x1="12" y1="22" x2="12" y2="12"/></svg>Droit d'accès</h3>
        <p>Recevoir une copie de toutes vos données personnelles au format JSON.</p>
        <form method="POST">
          <?= Security::csrfField() ?>
          <input type="hidden" name="type" value="export">
          <button type="submit" class="btn btn-primary">Demander l'export</button>
        </form>
      </div>
      <div class="right-item">
        <h3><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:6px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>Droit de rectification</h3>
        <p>Demander la correction de données inexactes vous concernant.</p>
        <form method="POST">
          <?= Security::csrfField() ?>
          <input type="hidden" name="type" value="rectify">
          <button type="submit" class="btn btn-primary">Demander la rectification</button>
        </form>
      </div>
      <div class="right-item">
        <h3><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:6px"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>Droit d'opposition</h3>
        <p>S'opposer au traitement de vos données pour des motifs légitimes.</p>
        <form method="POST">
          <?= Security::csrfField() ?>
          <input type="hidden" name="type" value="oppose">
          <button type="submit" class="btn btn-primary">Demander l'opposition</button>
        </form>
      </div>
      <div class="right-item">
        <h3><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:6px"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>Droit à l'effacement</h3>
        <p>Supprimer définitivement votre compte et toutes vos données. Sans retour possible.</p>
        <form method="POST" onsubmit="return confirm('⚠️ Suppression définitive. Êtes-vous sûr(e) ? Cette action est irréversible.')">
          <?= Security::csrfField() ?>
          <input type="hidden" name="type" value="delete">
          <button type="submit" class="btn btn-danger">Supprimer mon compte</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Historique des demandes -->
  <?php if (!empty($requests)): ?>
  <div class="card">
    <h2>Historique de mes demandes</h2>
    <p class="sub"><?= count($requests) ?> demande(s) enregistrée(s)</p>
    <table>
      <thead>
        <tr><th>Type</th><th>Statut</th><th>Déposée le</th><th>Traitée le</th></tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $req): ?>
        <tr>
          <td><?= [
            'export'  => '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M16.5 9.4 7.55 4.24"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.29 7 12 12 20.71 7"/><line x1="12" y1="22" x2="12" y2="12"/></svg>Export',
            'delete'  => '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>Suppression',
            'rectify' => '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>Rectification',
            'oppose'  => '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>Opposition',
          ][$req['type']] ?? $req['type'] ?></td>
          <td><span class="status-badge status-<?= $req['status'] ?>"><?= ucfirst($req['status']) ?></span></td>
          <td><?= date('d/m/Y H:i', strtotime($req['created_at'])) ?></td>
          <td><?= $req['handled_at'] ? date('d/m/Y', strtotime($req['handled_at'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</main>
</body>
</html>
