<?php
declare(strict_types=1);

require_once __DIR__ . '/../httpdocs/config/config.php';
require_once __DIR__ . '/../httpdocs/core/Database.php';
require_once __DIR__ . '/../httpdocs/core/Security.php';
require_once __DIR__ . '/../httpdocs/mail/Mailer.php';
require_once __DIR__ . '/Auth.php';

Security::setSecurityHeaders();

$error = '';
$success = '';

if (Auth::isLoggedIn()) {
    $target = in_array($_SESSION['user_role'] ?? '', ['admin', 'superadmin'], true)
        ? URL_ADMIN . '/dashboard'
        : URL_APP . '/dashboard';
    header('Location: ' . $target);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Le formulaire a expire. Merci de reessayer.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
        $lang = (string) ($_POST['lang'] ?? LANG_DEFAULT);
        $newsletter = !empty($_POST['newsletter']);

        if ($password !== $passwordConfirm) {
            $error = 'Les mots de passe ne correspondent pas.';
        } else {
            $result = Auth::register($email, $password, $name, $lang);

            if (!empty($result['success'])) {
                if ($newsletter) {
                    Database::query(
                        'UPDATE axnt_users SET newsletter = 1 WHERE id = ?',
                        [$result['user_id']]
                    );
                }

                $mailSent = Mailer::sendVerification($email, $name, (string) $result['verify_token']);
                $success = $mailSent
                    ? 'Compte cree. Verifie maintenant ton email pour activer la connexion.'
                    : 'Compte cree. L email de verification n a pas pu etre envoye, mais le compte existe bien.';
            } else {
                $error = (string) ($result['error'] ?? 'Inscription impossible.');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inscription - Axent</title>
<link rel="icon" type="image/x-icon" href="https://axet.fr/favicon.ico">
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --primary:#FF6B35; --secondary:#4ECDC4; --dark:#2D3047; --light:#F7F7FF;
    --gray:#8891A4; --border:#E8EAF0; --radius:16px; --shadow:0 8px 40px rgba(45,48,71,.12);
  }
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--light);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;position:relative;overflow:hidden}
  body::before{content:'';position:fixed;top:-200px;right:-200px;width:600px;height:600px;background:radial-gradient(circle,rgba(255,107,53,.15) 0%,transparent 70%);pointer-events:none}
  body::after{content:'';position:fixed;bottom:-150px;left:-150px;width:500px;height:500px;background:radial-gradient(circle,rgba(78,205,196,.12) 0%,transparent 70%);pointer-events:none}
  .card{background:#fff;border-radius:24px;box-shadow:var(--shadow);width:100%;max-width:520px;overflow:hidden;position:relative;z-index:1}
  .card-header{background:linear-gradient(135deg,var(--primary) 0%,#ff9a5c 100%);padding:36px 40px 32px;text-align:center}
  .logo{display:inline-flex;align-items:center;gap:10px;margin-bottom:20px;text-decoration:none}
  .logo-icon{width:44px;height:44px;background:#fff;border-radius:12px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,.15)}
  .logo-text{font-size:24px;font-weight:800;color:#fff;letter-spacing:-.5px}
  .card-header h1{color:#fff;font-size:26px;font-weight:800;margin-bottom:6px}
  .card-header p{color:rgba(255,255,255,.85);font-size:15px}
  .card-body{padding:36px 40px 40px}
  .alert{padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:14px;font-weight:600}
  .alert-error{background:#fff0f0;color:#c0392b;border:1px solid #ffd5d5}
  .alert-success{background:#f0fff8;color:#0a7f5a;border:1px solid #b2edd6}
  .form-group{margin-bottom:16px}
  .form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  label{display:block;font-size:13px;font-weight:700;color:var(--dark);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
  input[type="email"],input[type="password"],input[type="text"],select{width:100%;padding:13px 16px;border:2px solid var(--border);border-radius:var(--radius);font-size:15px;font-family:inherit;color:var(--dark);background:#fff;transition:border-color .2s, box-shadow .2s;outline:none}
  input:focus,select:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(255,107,53,.1)}
  .check{display:flex;gap:10px;align-items:flex-start;font-size:14px;color:var(--gray);margin:16px 0 6px}
  .check input{margin-top:2px}
  .btn{display:block;width:100%;padding:14px;border:none;border-radius:50px;font-size:16px;font-weight:700;font-family:inherit;cursor:pointer;transition:all .2s;text-align:center;text-decoration:none}
  .btn-primary{background:var(--primary);color:#fff;margin-top:14px}
  .btn-primary:hover{background:#e55a26;transform:translateY(-1px);box-shadow:0 6px 20px rgba(255,107,53,.35)}
  .switch{text-align:center;margin-top:20px;font-size:14px;color:var(--gray)}
  .switch a{color:var(--primary);font-weight:700;text-decoration:none}
  .switch a:hover{text-decoration:underline}
  @media(max-width:600px){.card-header,.card-body{padding-left:24px;padding-right:24px}.form-row{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <a href="<?= htmlspecialchars(APP_URL) ?>" class="logo">
      <div class="logo-icon"><img src="https://cdn.axet.fr/favicon.ico" width="24" height="24" alt=""></div>
      <span class="logo-text">Axent</span>
    </a>
    <h1>Creer mon compte</h1>
    <p>Trois minutes, un compte, et les cookies seront ranges.</p>
  </div>

  <div class="card-body">
    <?php if ($error !== ''): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars(URL_AUTH) ?>/register" novalidate>
      <?= Security::csrfField() ?>

      <div class="form-group">
        <label for="name">Nom affiche</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required autocomplete="name">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="email">Adresse email</label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email">
        </div>
        <div class="form-group">
          <label for="lang">Langue</label>
          <select id="lang" name="lang">
            <?php foreach (LANG_AVAILABLE as $lang): ?>
              <option value="<?= htmlspecialchars($lang) ?>" <?= (($lang === ($_POST['lang'] ?? LANG_DEFAULT)) ? 'selected' : '') ?>><?= htmlspecialchars(strtoupper($lang)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="password">Mot de passe</label>
          <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        </div>
        <div class="form-group">
          <label for="password_confirm">Confirmation</label>
          <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
        </div>
      </div>

      <label class="check">
        <input type="checkbox" name="newsletter" value="1" <?= !empty($_POST['newsletter']) ? 'checked' : '' ?>>
        <span>Je veux recevoir les nouvelles et conseils Axent par email.</span>
      </label>
      <div class="check">
        <span>En m'inscrivant, j'accepte les <a href="<?= htmlspecialchars(APP_URL) ?>/terms" target="_blank">conditions d'utilisation</a> et la <a href="<?= htmlspecialchars(APP_URL) ?>/privacy" target="_blank">politique de confidentialite</a> de Axent.</span>
      </div>

      <button type="submit" class="btn btn-primary">Creer mon compte</button>
    </form>

    <div class="switch">
      Deja un compte ? <a href="<?= htmlspecialchars(URL_AUTH) ?>/login">Se connecter</a>
    </div>
  </div>
</div>
</body>
</html>
