<?php
declare(strict_types=1);
require_once __DIR__ . '/../../httpdocs/config/config.php';
require_once __DIR__ . '/../../httpdocs/core/Database.php';
require_once __DIR__ . '/../../httpdocs/core/Security.php';
require_once __DIR__ . '/../../auth.axet.fr/Auth.php';

Security::setSecurityHeaders();
Auth::requireAdmin();

$message = '';
$error   = '';

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($action === 'suspend' && $userId) {
        Database::query("UPDATE axnt_users SET status = 'suspended' WHERE id = ? AND role != 'superadmin'", [$userId]);
        $message = 'Utilisateur suspendu.';
    } elseif ($action === 'activate' && $userId) {
        Database::query("UPDATE axnt_users SET status = 'active' WHERE id = ?", [$userId]);
        $message = 'Utilisateur réactivé.';
    } elseif ($action === 'make_admin' && $userId) {
        Database::query("UPDATE axnt_users SET role = 'admin' WHERE id = ? AND role = 'user'", [$userId]);
        $message = 'Utilisateur promu admin.';
    }
}

// Filtres
$search   = Security::sanitize($_GET['q'] ?? '');
$status   = in_array($_GET['status'] ?? '', ['active','suspended','anonymized']) ? $_GET['status'] : '';
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 25;
$offset   = ($page - 1) * $perPage;

$where    = 'WHERE 1=1';
$params   = [];
if ($search) { $where .= ' AND (email LIKE ? OR display_name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status) { $where .= ' AND status = ?'; $params[] = $status; }

$total = Database::fetchOne("SELECT COUNT(*) as c FROM axnt_users $where", $params)['c'];
$users = Database::fetchAll("SELECT * FROM axnt_users $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", $params);
$pages = ceil($total / $perPage);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Utilisateurs — Admin Axent</title>
<meta name="robots" content="noindex">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--primary:#FF6B35;--dark:#2D3047;--sidebar-bg:#1e2035;--sidebar-w:260px;--light:#F7F7FF;--border:#E8EAF0;--gray:#8891A4;--success:#06D6A0;--danger:#EF476F;--radius:14px;--shadow:0 2px 20px rgba(45,48,71,.08);--font:'Plus Jakarta Sans',sans-serif}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font);background:#f4f5fb;color:var(--dark);display:flex;min-height:100vh}
.sidebar{width:var(--sidebar-w);background:var(--sidebar-bg);position:fixed;top:0;left:0;bottom:0;z-index:100;display:flex;flex-direction:column}
.sidebar-logo{display:flex;align-items:center;gap:10px;padding:28px 24px 24px;border-bottom:1px solid rgba(255,255,255,.06)}
.logo-icon{width:40px;height:40px;background:var(--primary);border-radius:12px;display:flex;align-items:center;justify-content:center}
.logo-name{color:#fff;font-size:20px;font-weight:800}
.sidebar-section{padding:20px 16px 8px}
.sidebar-label{font-size:10px;text-transform:uppercase;letter-spacing:1.2px;color:rgba(255,255,255,.3);font-weight:700;padding:0 8px;margin-bottom:8px}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:rgba(255,255,255,.6);text-decoration:none;font-size:14px;font-weight:600;transition:.2s;margin-bottom:2px}
.nav-item:hover{background:rgba(255,255,255,.06);color:#fff}
.nav-item.active{background:var(--primary);color:#fff}
.main{margin-left:var(--sidebar-w);flex:1}
.topbar{background:#fff;padding:16px 32px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border)}
.content{padding:32px;max-width:1200px}
.filters{display:flex;gap:12px;margin-bottom:20px;align-items:center;flex-wrap:wrap}
.search-input{padding:9px 16px;border:1px solid var(--border);border-radius:50px;font-size:14px;font-family:var(--font);outline:none;width:280px}
.search-input:focus{border-color:var(--primary)}
select.filter-select{padding:9px 16px;border:1px solid var(--border);border-radius:50px;font-size:14px;font-family:var(--font);outline:none;cursor:pointer}
.btn{padding:9px 18px;border-radius:50px;font-size:13px;font-weight:700;cursor:pointer;border:none;font-family:var(--font);transition:.2s}
.btn-primary{background:var(--primary);color:#fff}
.table-card{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden}
table{width:100%;border-collapse:collapse}
th{padding:12px 20px;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:var(--gray);font-weight:700;background:#fafbff;border-bottom:1px solid var(--border);text-align:left}
td{padding:13px 20px;font-size:14px;border-bottom:1px solid #f5f5f5}
tr:hover td{background:#fafbff}
.badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}
.badge-success{background:rgba(6,214,160,.12);color:#0a7f5a}
.badge-danger{background:rgba(239,71,111,.12);color:#b82050}
.badge-warning{background:rgba(255,209,102,.2);color:#8a6800}
.badge-info{background:rgba(78,205,196,.12);color:#0a7f78}
.user-cell{display:flex;align-items:center;gap:10px}
.avatar{width:32px;height:32px;border-radius:8px;background:var(--primary);color:#fff;font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.u-name{font-weight:700}
.u-email{font-size:12px;color:var(--gray)}
.action-form{display:inline}
.action-btn{width:28px;height:28px;border-radius:6px;border:none;cursor:pointer;font-size:13px;background:transparent;transition:.2s}
.action-btn:hover{background:var(--light)}
.pagination{display:flex;gap:8px;justify-content:center;padding:20px}
.page-btn{width:36px;height:36px;border-radius:8px;border:1px solid var(--border);background:#fff;cursor:pointer;font-family:var(--font);font-weight:700;font-size:14px;transition:.2s;text-decoration:none;display:flex;align-items:center;justify-content:center;color:var(--dark)}
.page-btn:hover,.page-btn.current{background:var(--primary);color:#fff;border-color:var(--primary)}
.alert{padding:12px 16px;border-radius:10px;font-size:14px;font-weight:600;margin-bottom:20px}
.alert-success{background:#f0fff8;color:#0a7f5a;border:1px solid #b2edd6}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo"><div class="logo-icon"><img src="https://cdn.axet.fr/favicon.ico" width="24" height="24" alt=""></div><span class="logo-name">Axent Admin</span></div>
  <div class="sidebar-section">
    <div class="sidebar-label">Principal</div>
    <a href="<?= URL_ADMIN ?>/dashboard" class="nav-item">📊 Dashboard</a>
    <a href="<?= URL_ADMIN ?>/users" class="nav-item active">👥 Utilisateurs</a>
    <a href="<?= URL_ADMIN ?>/sites" class="nav-item">🌐 Sites</a>
    <a href="<?= URL_ADMIN ?>/consents" class="nav-item">✅ Consentements</a>
    <a href="<?= URL_ADMIN ?>/stats" class="nav-item">📈 Statistiques</a>
  </div>
  <div class="sidebar-section">
    <div class="sidebar-label">RGPD</div>
    <a href="<?= URL_ADMIN ?>/rgpd" class="nav-item">🔒 Demandes RGPD</a>
    <a href="<?= URL_ADMIN ?>/audit" class="nav-item">📋 Audit</a>
    <a href="<?= URL_ADMIN ?>/emails" class="nav-item">📧 Emails</a>
  </div>
</aside>

<main class="main">
  <header class="topbar">
    <h1 style="font-size:20px;font-weight:800">👥 Utilisateurs</h1>
    <a href="<?= URL_AUTH ?>/logout" style="color:var(--gray);font-size:13px;font-weight:600;text-decoration:none">Déconnexion</a>
  </header>
  <div class="content">
    <?php if ($message): ?><div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div><?php endif; ?>

    <!-- Filtres -->
    <form method="GET" class="filters">
      <input type="text" name="q" class="search-input" placeholder="🔍 Email, nom..." value="<?= htmlspecialchars($search) ?>">
      <select name="status" class="filter-select">
        <option value="">Tous les statuts</option>
        <option value="active" <?= $status==='active' ? 'selected' : '' ?>>✅ Actif</option>
        <option value="suspended" <?= $status==='suspended' ? 'selected' : '' ?>>⏸ Suspendu</option>
        <option value="anonymized" <?= $status==='anonymized' ? 'selected' : '' ?>>🔒 Anonymisé</option>
      </select>
      <button type="submit" class="btn btn-primary">Filtrer</button>
      <span style="color:var(--gray);font-size:14px"><?= number_format($total) ?> utilisateur(s)</span>
    </form>

    <!-- Table -->
    <div class="table-card">
      <table>
        <thead>
          <tr>
            <th>Utilisateur</th>
            <th>Rôle</th>
            <th>Statut</th>
            <th>Connexion</th>
            <th>Inscription</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div class="user-cell">
                <div class="avatar"><?= strtoupper(substr($u['display_name'], 0, 2)) ?></div>
                <div><div class="u-name"><?= htmlspecialchars($u['display_name']) ?></div><div class="u-email"><?= htmlspecialchars($u['email']) ?></div></div>
              </div>
            </td>
            <td>
              <?php $roleColors = ['user'=>'info','admin'=>'warning','superadmin'=>'danger']; ?>
              <span class="badge badge-<?= $roleColors[$u['role']] ?? 'info' ?>"><?= $u['role'] ?></span>
            </td>
            <td>
              <?php if ($u['status']==='active'): ?>
                <span class="badge badge-success">● Actif</span>
              <?php elseif ($u['status']==='suspended'): ?>
                <span class="badge badge-warning">⏸ Suspendu</span>
              <?php else: ?>
                <span class="badge badge-danger">🔒 Anonymisé</span>
              <?php endif; ?>
            </td>
            <td><?= $u['last_login_at'] ? date('d/m/Y', strtotime($u['last_login_at'])) : 'Jamais' ?></td>
            <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
            <td>
              <?php if ($u['role'] !== 'superadmin'): ?>
              <?php if ($u['status'] === 'active'): ?>
              <form class="action-form" method="POST">
                <?= Security::csrfField() ?><input type="hidden" name="action" value="suspend"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="action-btn" title="Suspendre">⏸️</button>
              </form>
              <?php else: ?>
              <form class="action-form" method="POST">
                <?= Security::csrfField() ?><input type="hidden" name="action" value="activate"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="action-btn" title="Réactiver">▶️</button>
              </form>
              <?php endif; ?>
              <?php if ($u['role'] === 'user'): ?>
              <form class="action-form" method="POST">
                <?= Security::csrfField() ?><input type="hidden" name="action" value="make_admin"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="action-btn" title="Promouvoir admin" onclick="return confirm('Rendre cet utilisateur admin ?')">⬆️</button>
              </form>
              <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&status=<?= $status ?>" class="page-btn <?= $p === $page ? 'current' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>
</body>
</html>
