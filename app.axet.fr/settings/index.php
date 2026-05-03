<?php
declare(strict_types=1);
require_once __DIR__ . '/../../httpdocs/config/config.php';
require_once __DIR__ . '/../../httpdocs/core/Database.php';
require_once __DIR__ . '/../../httpdocs/core/Security.php';
require_once __DIR__ . '/../../httpdocs/core/Lang.php';
require_once __DIR__ . '/../../auth.axet.fr/Auth.php';

Security::setSecurityHeaders();
Auth::requireLogin();
Lang::init($_SESSION['user_lang'] ?? LANG_DEFAULT);

$userId  = (int) $_SESSION['user_id'];
$user    = Database::fetchOne('SELECT * FROM axnt_users WHERE id = ?', [$userId]);
$oauth   = Database::fetchAll('SELECT provider, created_at FROM axnt_oauth_accounts WHERE user_id = ?', [$userId]);
$message = '';
$error   = '';

if (!empty($_SESSION['flash_success'])) {
    $message = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $error = (string) $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = Security::sanitize($_POST['name'] ?? '');
        $lang = in_array($_POST['lang'] ?? '', LANG_AVAILABLE) ? $_POST['lang'] : LANG_DEFAULT;
        if (strlen($name) >= 2) {
            Database::query('UPDATE axnt_users SET display_name = ?, lang = ? WHERE id = ?', [$name, $lang, $userId]);
            $_SESSION['user_name'] = $name;
            $message = 'Profil mis à jour ! 🎉';
            $user['display_name'] = $name;
        } else {
            $error = 'Nom trop court.';
        }
    }

    if ($action === 'update_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$user['password_hash']) {
            $error = 'Votre compte utilise OAuth2. Impossible de définir un mot de passe.';
        } elseif (!password_verify($current, $user['password_hash'])) {
            $error = 'Mot de passe actuel incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'Nouveau mot de passe trop court (8 caractères minimum).';
        } elseif ($new !== $confirm) {
            $error = 'Les mots de passe ne correspondent pas.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            Database::query('UPDATE axnt_users SET password_hash = ? WHERE id = ?', [$hash, $userId]);
            $message = 'Mot de passe mis à jour ! 🔐';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(Lang::current()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Paramètres — Axent</title>
<link rel="icon" type="image/x-icon" href="https://axet.fr/favicon.ico">
<meta name="robots" content="noindex">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--primary:#FF6B35;--dark:#2D3047;--light:#F7F7FF;--border:#E8EAF0;--gray:#8891A4;--radius:16px;--shadow:0 4px 24px rgba(45,48,71,.08)}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#f4f5fb;color:var(--dark)}
.topbar{background:#fff;border-bottom:1px solid var(--border);padding:0 32px;height:64px;display:flex;align-items:center;justify-content:space-between}
.logo{display:flex;align-items:center;gap:8px;text-decoration:none}
.logo-icon{width:36px;height:36px;background:var(--primary);border-radius:10px;display:flex;align-items:center;justify-content:center}
.logo-text{font-size:18px;font-weight:800;color:var(--dark)}
.content{max-width:760px;margin:0 auto;padding:40px 24px}
.section-card{background:#fff;border-radius:var(--radius);padding:32px;box-shadow:var(--shadow);margin-bottom:24px}
.section-card h2{font-size:18px;font-weight:800;margin-bottom:4px}
.section-card .sub{color:var(--gray);font-size:14px;margin-bottom:24px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{margin-bottom:16px}
label{display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--dark);margin-bottom:6px}
input[type=text],input[type=email],input[type=password],select{width:100%;padding:11px 14px;border:2px solid var(--border);border-radius:10px;font-size:14px;font-family:inherit;color:var(--dark);outline:none;transition:.2s}
input:focus,select:focus{border-color:var(--primary)}
.btn{padding:11px 22px;border-radius:50px;font-size:14px;font-weight:700;cursor:pointer;border:none;font-family:inherit;transition:.2s}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{background:#e55a26}
.alert{padding:12px 16px;border-radius:10px;font-size:14px;font-weight:600;margin-bottom:20px}
.alert-success{background:#f0fff8;color:#0a7f5a;border:1px solid #b2edd6}
.alert-error{background:#fff0f0;color:#c0392b;border:1px solid #ffd5d5}
.oauth-list{display:flex;flex-direction:column;gap:8px}
.oauth-item{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border:1px solid var(--border);border-radius:10px}
.provider-name{display:inline-flex;align-items:center;gap:10px;font-weight:700;font-size:14px}
.provider-icon{width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;flex:0 0 18px}
.provider-icon svg{width:18px;height:18px;display:block}
.badge-linked{background:rgba(6,214,160,.12);color:#0a7f5a;font-size:12px;font-weight:700;padding:3px 10px;border-radius:20px}
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
    <a href="<?= URL_APP ?>/settings" class="active">Paramètres</a>
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
  <h1 style="font-size:26px;font-weight:800;margin-bottom:32px"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-4px;margin-right:8px"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Paramètres du compte</h1>

  <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- Profil -->
  <div class="section-card">
    <h2>Profil</h2>
    <p class="sub">Vos informations personnelles</p>
    <form method="POST">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="update_profile">
      <div class="form-row">
        <div class="form-group">
          <label>Nom affiché</label>
          <input type="text" name="name" value="<?= htmlspecialchars($user['display_name']) ?>" required>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled style="background:#f5f5f5;cursor:not-allowed">
        </div>
      </div>
      <div class="form-group">
        <label>Langue de l'interface</label>
        <select name="lang">
          <?php foreach (LANG_AVAILABLE as $l): ?>
          <option value="<?= $l ?>" <?= $user['lang'] === $l ? 'selected' : '' ?>>
            <?= ['fr'=>'FR — Français','en'=>'EN — English','es'=>'ES — Español','de'=>'DE — Deutsch'][$l] ?? $l ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Enregistrer le profil</button>
    </form>
  </div>

  <!-- Mot de passe -->
  <?php if ($user['password_hash']): ?>
  <div class="section-card">
    <h2>Mot de passe</h2>
    <p class="sub">Changez votre mot de passe ici</p>
    <form method="POST">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="update_password">
      <div class="form-group">
        <label>Mot de passe actuel</label>
        <input type="password" name="current_password" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Nouveau mot de passe</label>
          <input type="password" name="new_password" minlength="8" required>
        </div>
        <div class="form-group">
          <label>Confirmer</label>
          <input type="password" name="confirm_password" required>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Changer le mot de passe</button>
    </form>
  </div>
  <?php endif; ?>

  <!-- OAuth connectés -->
  <div class="section-card">
    <h2>Connexions externes</h2>
    <p class="sub">Vos comptes OAuth2 liés</p>
    <div class="oauth-list">
      <?php
      $providers = [
        'google' => [
          'name' => 'Google',
          'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#EA4335" d="M12 10.2v3.9h5.4c-.2 1.3-1.5 3.9-5.4 3.9a6 6 0 1 1 0-12c2.2 0 3.7.9 4.6 1.7l3.1-3A10.4 10.4 0 0 0 12 1.5a10.5 10.5 0 1 0 0 21c6 0 10-4.2 10-10.1 0-.7-.1-1.4-.2-2.2Z"/><path fill="#4285F4" d="M2.7 7.3 6 9.8A6 6 0 0 1 12 6c2.2 0 3.7.9 4.6 1.7l3.1-3A10.4 10.4 0 0 0 12 1.5a10.5 10.5 0 0 0-9.3 5.8Z"/><path fill="#FBBC05" d="M12 22.5c2.8 0 5.2-.9 7-2.6l-3.2-2.7c-.9.6-2.1 1-3.8 1-3.8 0-5.2-2.6-5.4-3.8l-3.3 2.5A10.5 10.5 0 0 0 12 22.5Z"/><path fill="#34A853" d="M2.7 16.7 6 14.2c.6 1.9 2.3 3.8 6 3.8 1.6 0 2.9-.4 3.8-1l3.2 2.7A10.4 10.4 0 0 1 12 22.5a10.5 10.5 0 0 1-9.3-5.8Z"/></svg>',
        ],
        'discord' => [
          'name' => 'Discord',
          'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#5865F2" d="M20.3 4.4A16.7 16.7 0 0 0 16.2 3l-.2.4c1.9.5 2.8 1.2 3.6 1.9a13.1 13.1 0 0 0-4.6-1.4 15.8 15.8 0 0 0-6 0A13.1 13.1 0 0 0 4.4 5.3c.8-.7 1.7-1.4 3.6-1.9L7.8 3a16.7 16.7 0 0 0-4.1 1.4C1.1 8.3.5 12.1.8 15.8A16.8 16.8 0 0 0 5.8 18l1.1-1.8c-.6-.2-1.1-.5-1.6-.8l.4-.3c3.1 1.4 6.5 1.4 9.6 0l.4.3c-.5.3-1 .6-1.6.8l1.1 1.8a16.8 16.8 0 0 0 5-2.2c.4-4.3-.6-8-1.9-11.4ZM9.5 13.5c-.9 0-1.6-.8-1.6-1.9s.7-1.9 1.6-1.9c1 0 1.7.8 1.6 1.9 0 1.1-.7 1.9-1.6 1.9Zm5 0c-.9 0-1.6-.8-1.6-1.9s.7-1.9 1.6-1.9c1 0 1.7.8 1.6 1.9 0 1.1-.6 1.9-1.6 1.9Z"/></svg>',
        ],
        'github' => [
          'name' => 'GitHub',
          'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#181717" d="M12 .5a12 12 0 0 0-3.8 23.4c.6.1.8-.3.8-.6v-2.1c-3.3.7-4-1.4-4-1.4-.5-1.3-1.3-1.7-1.3-1.7-1.1-.8.1-.8.1-.8 1.2.1 1.9 1.3 1.9 1.3 1.1 1.9 2.9 1.4 3.6 1.1.1-.8.4-1.4.8-1.7-2.7-.3-5.5-1.4-5.5-6a4.7 4.7 0 0 1 1.2-3.2c-.1-.3-.5-1.5.1-3.1 0 0 1-.3 3.3 1.2a11.2 11.2 0 0 1 6 0c2.3-1.5 3.3-1.2 3.3-1.2.6 1.6.2 2.8.1 3.1a4.7 4.7 0 0 1 1.2 3.2c0 4.6-2.8 5.7-5.5 6 .4.4.9 1.1.9 2.3v3.4c0 .3.2.7.8.6A12 12 0 0 0 12 .5Z"/></svg>',
        ],
        'microsoft' => [
          'name' => 'Microsoft',
          'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#F25022" d="M2 2h9.5v9.5H2Z"/><path fill="#7FBA00" d="M12.5 2H22v9.5h-9.5Z"/><path fill="#00A4EF" d="M2 12.5h9.5V22H2Z"/><path fill="#FFB900" d="M12.5 12.5H22V22h-9.5Z"/></svg>',
        ],
        'facebook' => [
          'name' => 'Facebook',
          'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#1877F2" d="M24 12a12 12 0 1 0-13.9 11.8v-8.4H7.1V12h3V9.4c0-3 1.8-4.7 4.5-4.7 1.3 0 2.7.2 2.7.2v3h-1.5c-1.5 0-2 .9-2 1.9V12h3.4l-.5 3.4h-2.9v8.4A12 12 0 0 0 24 12Z"/></svg>',
        ],
      ];
      $linked = array_column($oauth, 'provider');
      foreach ($providers as $p => $provider):
        $isLinked = in_array($p, $linked);
      ?>
      <div class="oauth-item">
        <span class="provider-name">
          <span class="provider-icon"><?= $provider['icon'] ?></span>
          <span><?= htmlspecialchars($provider['name']) ?></span>
        </span>
        <?php if ($isLinked): ?>
          <span class="badge-linked"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;margin-right:4px"><polyline points="20 6 9 17 4 12"/></svg>Connecté</span>
        <?php else: ?>
          <a href="<?= URL_AUTH ?>/auth/<?= $p ?>?mode=link&redirect=settings" style="font-size:13px;color:var(--primary);font-weight:700;text-decoration:none">Lier →</a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</main>
</body>
</html>
