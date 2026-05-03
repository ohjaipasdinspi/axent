<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Security.php';
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
            $target = in_array($_SESSION['user_role'] ?? '', ['admin', 'superadmin'], true)
                ? URL_ADMIN . '/dashboard'
                : URL_APP . '/dashboard';
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
<title>Connexion - Axent</title>
<meta name="robots" content="noindex">
<style>
body{font-family:"Plus Jakarta Sans",system-ui,sans-serif;background:#f5f7fb;color:#2D3047;margin:0;padding:24px;min-height:100vh;display:flex;align-items:center;justify-content:center}
.card{width:100%;max-width:460px;background:#fff;border-radius:24px;box-shadow:0 20px 60px rgba(45,48,71,.10);padding:32px}
h1{margin:0 0 8px}
p{color:#667085;line-height:1.6}
.alert{padding:12px 14px;border-radius:12px;margin:16px 0;font-size:14px}
.error{background:#fff1f1;color:#b42318}
.success{background:#edfdf3;color:#027a48}
label{display:block;margin:16px 0 6px;font-weight:700;font-size:14px}
input{width:100%;padding:12px 14px;border:1px solid #d0d5dd;border-radius:12px;font:inherit;box-sizing:border-box}
button,.oauth{display:block;width:100%;margin-top:16px;padding:12px 14px;border:none;border-radius:999px;font:inherit;font-weight:700;text-decoration:none;text-align:center;box-sizing:border-box}
button{background:#FF6B35;color:#fff;cursor:pointer}
.oauth{background:#f7f7ff;color:#2D3047;border:1px solid #e4e7ec}
.stack{display:grid;gap:10px;margin-top:18px}
</style>
</head>
<body>
<main class="card">
  <h1>Connexion</h1>
  <p>Connecte-toi a ton espace Axent.</p>

  <?php if ($error !== ''): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success !== ''): ?>
    <div class="alert success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <form method="POST" action="<?= htmlspecialchars(URL_AUTH) ?>/login">
    <?= Security::csrfField() ?>
    <label for="email">Adresse email</label>
    <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email">

    <label for="password">Mot de passe</label>
    <input type="password" id="password" name="password" required autocomplete="current-password">

    <button type="submit">Se connecter</button>
  </form>

  <div class="stack">
    <?php if (OAUTH_GOOGLE_ENABLED): ?><a class="oauth" href="<?= htmlspecialchars(URL_AUTH) ?>/auth/google">Continuer avec Google</a><?php endif; ?>
    <?php if (OAUTH_DISCORD_ENABLED): ?><a class="oauth" href="<?= htmlspecialchars(URL_AUTH) ?>/auth/discord">Continuer avec Discord</a><?php endif; ?>
    <?php if (OAUTH_GITHUB_ENABLED): ?><a class="oauth" href="<?= htmlspecialchars(URL_AUTH) ?>/auth/github">Continuer avec GitHub</a><?php endif; ?>
    <?php if (OAUTH_MICROSOFT_ENABLED): ?><a class="oauth" href="<?= htmlspecialchars(URL_AUTH) ?>/auth/microsoft">Continuer avec Microsoft</a><?php endif; ?>
    <?php if (OAUTH_FACEBOOK_ENABLED): ?><a class="oauth" href="<?= htmlspecialchars(URL_AUTH) ?>/auth/facebook">Continuer avec Facebook</a><?php endif; ?>
  </div>
</main>
</body>
</html>
