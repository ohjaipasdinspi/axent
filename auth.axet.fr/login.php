<?php
declare(strict_types=1);

require_once __DIR__ . '/../httpdocs/config/config.php';
require_once __DIR__ . '/../httpdocs/core/Database.php';
require_once __DIR__ . '/../httpdocs/core/Security.php';
require_once __DIR__ . '/Auth.php';

Security::setSecurityHeaders();

$error = $_SESSION['flash_error'] ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

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
        $result = Auth::login(
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')
        );

        if (!empty($result['success'])) {
            $redirect = trim((string) ($_GET['redirect'] ?? ''), '/');
            if (in_array($_SESSION['user_role'] ?? '', ['admin', 'superadmin'], true)) {
                $target = URL_ADMIN . '/dashboard';
            } else {
                $target = URL_APP . '/dashboard';
            }
            if ($redirect !== '' && !str_starts_with($redirect, 'http')) {
                $target = 'https://' . parse_url($target, PHP_URL_HOST) . '/' . $redirect;
            }
            Security::safeRedirect($target);
        }

        $error = (string) ($result['error'] ?? 'Connexion impossible.');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — Axent</title>
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --primary:   #FF6B35;
    --secondary: #4ECDC4;
    --dark:      #2D3047;
    --light:     #F7F7FF;
    --gray:      #8891A4;
    --border:    #E8EAF0;
    --radius:    16px;
    --shadow:    0 8px 40px rgba(45,48,71,.12);
  }
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: var(--light);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    position: relative;
    overflow: hidden;
  }
  /* Formes décoratives */
  body::before {
    content: '';
    position: fixed;
    top: -200px; right: -200px;
    width: 600px; height: 600px;
    background: radial-gradient(circle, rgba(255,107,53,.15) 0%, transparent 70%);
    pointer-events: none;
  }
  body::after {
    content: '';
    position: fixed;
    bottom: -150px; left: -150px;
    width: 500px; height: 500px;
    background: radial-gradient(circle, rgba(78,205,196,.12) 0%, transparent 70%);
    pointer-events: none;
  }

  .card {
    background: #fff;
    border-radius: 24px;
    box-shadow: var(--shadow);
    width: 100%;
    max-width: 460px;
    overflow: hidden;
    position: relative;
    z-index: 1;
    animation: slideUp .4s cubic-bezier(.34,1.56,.64,1);
  }
  @keyframes slideUp {
    from { opacity: 0; transform: translateY(30px) scale(.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
  }

  .card-header {
    background: linear-gradient(135deg, var(--primary) 0%, #ff9a5c 100%);
    padding: 36px 40px 32px;
    text-align: center;
    position: relative;
  }
  .logo {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    text-decoration: none;
  }
  .logo-icon {
    width: 44px; height: 44px;
    background: #fff;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 12px rgba(0,0,0,.15);
  }
  .logo-text {
    font-size: 24px;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.5px;
  }
  .card-header h1 {
    color: #fff;
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
  }
  .card-header p {
    color: rgba(255,255,255,.8);
    font-size: 15px;
  }

  .card-body { padding: 36px 40px 40px; }

  /* Alert */
  .alert {
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
    font-weight: 600;
  }
  .alert-error { background: #fff0f0; color: #c0392b; border: 1px solid #ffd5d5; }
  .alert-success { background: #f0fff8; color: #0a7f5a; border: 1px solid #b2edd6; }

  /* Formulaire */
  .form-group { margin-bottom: 18px; }
  label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: .5px;
  }
  input[type="email"],
  input[type="password"],
  input[type="text"] {
    width: 100%;
    padding: 13px 16px;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-size: 15px;
    font-family: inherit;
    color: var(--dark);
    background: #fff;
    transition: border-color .2s, box-shadow .2s;
    outline: none;
  }
  input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(255,107,53,.1);
  }

  .forgot {
    display: block;
    text-align: right;
    font-size: 13px;
    color: var(--primary);
    text-decoration: none;
    margin-top: 6px;
    font-weight: 600;
  }
  .forgot:hover { text-decoration: underline; }

  .btn {
    display: block;
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 50px;
    font-size: 16px;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    transition: all .2s;
    text-align: center;
    text-decoration: none;
  }
  .btn-primary {
    background: var(--primary);
    color: #fff;
    margin-bottom: 16px;
  }
  .btn-primary:hover { background: #e55a26; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(255,107,53,.35); }
  .btn-primary:active { transform: translateY(0); }

  /* Séparateur */
  .separator {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 20px 0;
    color: var(--gray);
    font-size: 13px;
    font-weight: 600;
  }
  .separator::before, .separator::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
  }

  /* Boutons OAuth */
  .oauth-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 8px; }
  .oauth-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 12px;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    background: #fff;
    font-size: 13px;
    font-weight: 700;
    color: var(--dark);
    text-decoration: none;
    cursor: pointer;
    transition: all .2s;
    font-family: inherit;
  }
  .oauth-btn:hover { border-color: var(--primary); color: var(--primary); background: #fff8f5; transform: translateY(-1px); }
  .oauth-btn svg, .oauth-btn img { width: 18px; height: 18px; }
  .oauth-btn-full { grid-column: 1 / -1; }

  .switch {
    text-align: center;
    font-size: 14px;
    color: var(--gray);
    margin-top: 24px;
  }
  .switch a { color: var(--primary); font-weight: 700; text-decoration: none; }
  .switch a:hover { text-decoration: underline; }

  /* Lang switcher */
  .lang-bar {
    position: fixed;
    top: 16px; right: 16px;
    display: flex;
    gap: 6px;
    z-index: 100;
  }
  .lang-btn {
    padding: 5px 10px;
    border-radius: 20px;
    border: 2px solid #fff;
    background: rgba(255,255,255,.85);
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: .2s;
    color: var(--dark);
    text-decoration: none;
  }
  .lang-btn:hover, .lang-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }

  @media(max-width:480px) {
    .card-header { padding: 28px 24px; }
    .card-body { padding: 28px 24px; }
    .oauth-grid { grid-template-columns: 1fr; }
    .oauth-btn-full { grid-column: 1; }
  }
</style>
</head>
<body>

<!-- Sélecteur de langue -->
<div class="lang-bar">
  <a href="?lang=fr" class="lang-btn active">FR</a>
  <a href="?lang=en" class="lang-btn">EN</a>
  <a href="?lang=es" class="lang-btn">ES</a>
  <a href="?lang=de" class="lang-btn">DE</a>
</div>

<div class="card">
  <div class="card-header">
    <a href="https://axet.fr" class="logo">
      <div class="logo-icon"><img src="https://cdn.axet.fr/favicon.ico" width="24" height="24" alt=""></div>
      <span class="logo-text">Axent</span>
    </a>
    <h1>Content de vous revoir ! 👋</h1>
    <p>Connectez-vous pour gérer vos cookies proprement.</p>
  </div>

  <div class="card-body">

    <?php if (!empty($error)): ?>
      <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
      <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="/login" novalidate>
      <?= Security::csrfField() ?>

      <div class="form-group">
        <label for="email">Adresse email</label>
        <input type="email" id="email" name="email"
               placeholder="vous@exemple.fr"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               required autocomplete="email">
      </div>

      <div class="form-group">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password"
               placeholder="••••••••"
               required autocomplete="current-password">
        <a href="/forgot-password" class="forgot">Mot de passe oublié ?</a>
      </div>

      <button type="submit" class="btn btn-primary">
        Se connecter →
      </button>
    </form>

    <div class="separator">ou continuer avec</div>

    <div class="oauth-grid">
      <?php if (OAUTH_GOOGLE_ENABLED): ?>
      <a href="/auth/google" class="oauth-btn">
        <svg viewBox="0 0 24 24" fill="none"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
        Google
      </a>
      <?php endif; ?>

      <?php if (OAUTH_DISCORD_ENABLED): ?>
      <a href="/auth/discord" class="oauth-btn">
        <svg viewBox="0 0 24 24" fill="#5865F2"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057.1 18.08.118 18.1.138 18.11a19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z"/></svg>
        Discord
      </a>
      <?php endif; ?>

      <?php if (OAUTH_GITHUB_ENABLED): ?>
      <a href="/auth/github" class="oauth-btn">
        <svg viewBox="0 0 24 24" fill="#1a1a1a"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/></svg>
        GitHub
      </a>
      <?php endif; ?>

      <?php if (OAUTH_MICROSOFT_ENABLED): ?>
      <a href="/auth/microsoft" class="oauth-btn">
        <svg viewBox="0 0 24 24" fill="none"><path fill="#F25022" d="M1 1h10v10H1z"/><path fill="#7FBA00" d="M13 1h10v10H13z"/><path fill="#00A4EF" d="M1 13h10v10H1z"/><path fill="#FFB900" d="M13 13h10v10H13z"/></svg>
        Microsoft
      </a>
      <?php endif; ?>

      <?php if (OAUTH_FACEBOOK_ENABLED): ?>
      <a href="/auth/facebook" class="oauth-btn oauth-btn-full">
        <svg viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
        Facebook
      </a>
      <?php endif; ?>
    </div>

    <div class="switch">
      Pas encore de compte ? <a href="/register">Créez-en un — c'est gratuit ! 🎉</a>
    </div>

  </div>
</div>

</body>
</html>
