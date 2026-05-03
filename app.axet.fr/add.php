<?php
/**
 * Axent - Ajouter un site
 * URL : app.axet.fr/sites/add
 */
declare(strict_types=1);

// These files are already included by app.axet.fr/index.php, but kept here for clarity/standalone testing.
// require_once __DIR__ . '/../../httpdocs/config/config.php';
// require_once __DIR__ . '/../../httpdocs/core/Database.php';
// require_once __DIR__ . '/../../httpdocs/core/Security.php';
// require_once __DIR__ . '/../../httpdocs/core/Lang.php';
// require_once __DIR__ . '/../../auth.axet.fr/Auth.php';

Auth::requireLogin(); // Ensure user is logged in
Lang::init($_SESSION['user_lang'] ?? LANG_DEFAULT);

$userId = (int) $_SESSION['user_id'];
$error = '';
$success = '';

// Helper function for UUID generation (copied from Auth.php as it's private)
function generateUUID(): string
{
    return sprintf('%04x%04x%04x%04x%04x%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Check site limit for free plan
$setting = Database::fetchOne("SELECT value FROM axnt_settings WHERE `key` = 'max_sites_free'");
$maxSitesFree = $setting ? (int) $setting['value'] : 1; // Default to 1 if not set
$userSitesCount = (int) Database::fetchOne("SELECT COUNT(*) as count FROM axnt_sites WHERE user_id = ?", [$userId])['count'];

if ($userSitesCount >= $maxSitesFree) {
    $error = Lang::get('error.site_limit_reached', ['limit' => $maxSitesFree]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    if (!Security::validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = Lang::get('error.csrf_expired');
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $domain = trim((string) ($_POST['domain'] ?? ''));

        // Basic validation
        if (empty($name)) {
            $error = Lang::get('error.site_name_empty');
        } elseif (empty($domain)) {
            $error = Lang::get('error.site_domain_empty');
        } elseif (!filter_var('http://' . $domain, FILTER_VALIDATE_URL)) { // Prepend http:// for validation
            $error = Lang::get('error.site_domain_invalid');
        } else {
            // Check if domain already exists
            $existingDomain = Database::fetchOne('SELECT id FROM axnt_sites WHERE domain = ?', [$domain]);
            if ($existingDomain) {
                $error = Lang::get('error.site_domain_exists');
            }
        }

        if (empty($error)) {
            try {
                $siteUuid = generateUUID();
                $versionUuid = generateUUID();

                // Default widget config (empty for now, can be expanded later)
                $widgetConfig = json_encode([]);

                // Default cookie config
                $cookieConfig = json_encode([
                    "essential" => [
                        "name" => Lang::get('widget.essential'),
                        "description" => Lang::get('widget.essential_desc'),
                        "required" => true,
                        "cookies" => []
                    ],
                    "analytics" => [
                        "name" => Lang::get('widget.analytics'),
                        "description" => Lang::get('widget.analytics_desc'),
                        "required" => false,
                        "cookies" => []
                    ],
                    "marketing" => [
                        "name" => Lang::get('widget.marketing'),
                        "description" => Lang::get('widget.marketing_desc'),
                        "required" => false,
                        "cookies" => []
                    ]
                ]);

                // Insert new site
                $siteId = Database::insert(
                    'INSERT INTO axnt_sites (user_id, uuid, name, domain, widget_config, plan, status) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$userId, $siteUuid, $name, $domain, $widgetConfig, 'free', 'active']
                );

                // Insert initial cookie version
                Database::insert(
                    'INSERT INTO axnt_cookie_versions (site_id, uuid, name, config, is_active) VALUES (?, ?, ?, ?, ?)',
                    [$siteId, $versionUuid, 'Version 1.0', $cookieConfig, 1]
                );

                $success = Lang::get('success.site_added');
                header('Location: ' . URL_APP . '/dashboard?success=' . urlencode($success));
                exit;

            } catch (Exception $e) {
                error_log('Error adding site: ' . $e->getMessage());
                $error = Lang::get('error.generic');
            }
        }
    }
}

// HTML structure for the form
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(Lang::current()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= Lang::get('dash.add_site') ?> — Axent</title>
<link rel="icon" type="image/x-icon" href="https://axet.fr/favicon.ico">
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
/* Reusing styles from dashboard/index.php */
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
.btn{padding:10px 20px;border-radius:50px;font-size:14px;font-weight:700;cursor:pointer;border:none;font-family:inherit;text-decoration:none;display:inline-block;transition:.2s}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{background:#e55a26;transform:translateY(-1px)}
.card{background:#fff;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);margin-bottom:24px;}
.form-group{margin-bottom:16px;}
label{display:block;font-size:13px;font-weight:700;color:var(--dark);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;}
input[type="text"], input[type="url"]{width:100%;padding:13px 16px;border:2px solid var(--border);border-radius:var(--radius);font-size:15px;font-family:inherit;color:var(--dark);background:#fff;transition:border-color .2s, box-shadow .2s;outline:none;}
input:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(255,107,53,.1);}
.alert{padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:14px;font-weight:600}
.alert-error{background:#fff0f0;color:#c0392b;border:1px solid #ffd5d5}
.alert-success{background:#f0fff8;color:#0a7f5a;border:1px solid #b2edd6}
</style>
</head>
<body>

<header class="topbar">
  <a href="<?= URL_APP ?>/dashboard" class="logo">
    <div class="logo-icon"><img src="https://cdn.axet.fr/favicon.ico" width="24" height="24" alt=""></div>
    <span class="logo-text">Axent</span>
  </a>
  <nav class="topbar-nav">
    <a href="<?= URL_APP ?>/dashboard" class="active"><?= Lang::get('nav.sites') ?></a>
    <a href="<?= URL_APP ?>/settings"><?= Lang::get('nav.settings') ?></a>
    <a href="<?= URL_APP ?>/rgpd"><?= Lang::get('rgpd.title') ?></a>
    <a href="<?= URL_DOCS ?>" target="_blank"><?= Lang::get('nav.docs') ?></a>
  </nav>
  <div class="topbar-right">
    <div class="avatar" title="<?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>">
      <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 2)) ?>
    </div>
    <a href="<?= URL_AUTH ?>/logout" style="color:var(--gray);font-size:13px;text-decoration:none;font-weight:600"><?= Lang::get('nav.logout') ?></a>
  </div>
</header>

<main class="content">
  <div class="page-header">
    <h1><?= Lang::get('dash.add_site') ?></h1>
    <p><?= Lang::get('dash.add_site_desc') ?></p>
  </div>

  <?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <?php if ($userSitesCount >= $maxSitesFree): ?>
    <div class="alert alert-error">
      <?= Lang::get('error.site_limit_reached', ['limit' => $maxSitesFree]) ?>
      <br>
      <?= Lang::get('error.upgrade_plan') ?>
    </div>
  <?php else: ?>
    <div class="card">
      <form method="POST" action="<?= htmlspecialchars(URL_APP) ?>/sites/add" novalidate>
        <?= Security::csrfField() ?>

        <div class="form-group">
          <label for="name"><?= Lang::get('form.site_name') ?></label>
          <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required placeholder="Mon super site web">
        </div>

        <div class="form-group">
          <label for="domain"><?= Lang::get('form.site_domain') ?></label>
          <input type="text" id="domain" name="domain" value="<?= htmlspecialchars($_POST['domain'] ?? '') ?>" required placeholder="monsite.fr">
          <small style="color:var(--gray);font-size:12px;margin-top:4px;display:block;"><?= Lang::get('form.site_domain_help') ?></small>
        </div>

        <button type="submit" class="btn btn-primary"><?= Lang::get('dash.add_site') ?></button>
      </form>
    </div>
  <?php endif; ?>
</main>

</body>
</html>