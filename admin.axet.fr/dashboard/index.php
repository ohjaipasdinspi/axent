<?php
declare(strict_types=1);

require_once __DIR__ . '/../../httpdocs/config/config.php';
require_once __DIR__ . '/../../httpdocs/core/Security.php';
require_once __DIR__ . '/../../auth.axet.fr/Auth.php';

Security::setSecurityHeaders();
Auth::requireAdmin();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Administration — Axent</title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
:root {
  --primary:    #FF6B35;
  --secondary:  #4ECDC4;
  --dark:       #2D3047;
  --sidebar-bg: #1e2035;
  --sidebar-w:  260px;
  --light:      #F7F7FF;
  --border:     #E8EAF0;
  --gray:       #8891A4;
  --success:    #06D6A0;
  --warning:    #FFD166;
  --danger:     #EF476F;
  --radius:     14px;
  --shadow:     0 2px 20px rgba(45,48,71,.08);
  --font:       'Plus Jakarta Sans', sans-serif;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--font); background: #f4f5fb; color: var(--dark); display: flex; min-height: 100vh; }

/* ── SIDEBAR ───────────────────────────────────────────────── */
.sidebar {
  width: var(--sidebar-w);
  background: var(--sidebar-bg);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 100;
  overflow-y: auto;
}
.sidebar-logo {
  display: flex; align-items: center; gap: 10px;
  padding: 28px 24px 24px;
  border-bottom: 1px solid rgba(255,255,255,.06);
}
.logo-icon { width: 40px; height: 40px; background: var(--primary); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
.logo-name { color: #fff; font-size: 20px; font-weight: 800; letter-spacing: -.5px; }
.logo-badge { font-size: 10px; background: var(--primary); color: #fff; padding: 2px 8px; border-radius: 20px; font-weight: 700; margin-left: 4px; }

.sidebar-section { padding: 20px 16px 8px; }
.sidebar-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1.2px; color: rgba(255,255,255,.3); font-weight: 700; padding: 0 8px; margin-bottom: 8px; }
.nav-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px;
  border-radius: 10px;
  color: rgba(255,255,255,.6);
  text-decoration: none;
  font-size: 14px;
  font-weight: 600;
  transition: all .2s;
  margin-bottom: 2px;
  cursor: pointer;
}
.nav-item:hover { background: rgba(255,255,255,.06); color: #fff; }
.nav-item.active { background: var(--primary); color: #fff; }
.nav-item .nav-icon { width: 20px; text-align: center; font-size: 16px; }
.nav-badge { margin-left: auto; background: var(--primary); color: #fff; font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 20px; }
.nav-item.active .nav-badge { background: rgba(255,255,255,.3); }

.sidebar-footer {
  margin-top: auto;
  padding: 16px;
  border-top: 1px solid rgba(255,255,255,.06);
}
.user-card {
  display: flex; align-items: center; gap: 10px;
  padding: 10px;
  border-radius: 10px;
  background: rgba(255,255,255,.05);
}
.user-avatar { width: 36px; height: 36px; border-radius: 10px; background: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 800; color: #fff; }
.user-name { font-size: 13px; font-weight: 700; color: #fff; }
.user-role { font-size: 11px; color: rgba(255,255,255,.4); }

/* ── MAIN ──────────────────────────────────────────────────── */
.main {
  margin-left: var(--sidebar-w);
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}
.topbar {
  background: #fff;
  padding: 16px 32px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--border);
  position: sticky; top: 0; z-index: 50;
}
.topbar-title { font-size: 20px; font-weight: 800; color: var(--dark); }
.topbar-actions { display: flex; align-items: center; gap: 12px; }
.search-bar {
  display: flex; align-items: center; gap: 8px;
  background: var(--light);
  border: 1px solid var(--border);
  border-radius: 50px;
  padding: 8px 16px;
}
.search-bar input { border: none; background: transparent; font-family: var(--font); font-size: 14px; color: var(--dark); outline: none; width: 200px; }
.btn-icon { width: 38px; height: 38px; border-radius: 50%; border: 1px solid var(--border); background: #fff; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 16px; transition: .2s; }
.btn-icon:hover { background: var(--light); }
.notif-dot { position: relative; }
.notif-dot::after { content: ''; position: absolute; top: 6px; right: 6px; width: 8px; height: 8px; background: var(--danger); border-radius: 50%; border: 2px solid #fff; }

/* ── CONTENT ───────────────────────────────────────────────── */
.content { padding: 32px; flex: 1; }
.page-header { margin-bottom: 28px; }
.page-header h1 { font-size: 28px; font-weight: 800; margin-bottom: 4px; }
.page-header p { color: var(--gray); font-size: 15px; }

/* ── STATS CARDS ────────────────────────────────────────────── */
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 28px; }
.stat-card {
  background: #fff;
  border-radius: var(--radius);
  padding: 24px;
  box-shadow: var(--shadow);
  position: relative;
  overflow: hidden;
}
.stat-card::before {
  content: '';
  position: absolute;
  top: 0; right: 0;
  width: 80px; height: 80px;
  border-radius: 0 var(--radius) 0 80px;
}
.stat-card:nth-child(1)::before { background: rgba(255,107,53,.08); }
.stat-card:nth-child(2)::before { background: rgba(6,214,160,.08); }
.stat-card:nth-child(3)::before { background: rgba(239,71,111,.08); }
.stat-card:nth-child(4)::before { background: rgba(78,205,196,.08); }
.stat-icon { font-size: 28px; margin-bottom: 12px; }
.stat-value { font-size: 32px; font-weight: 800; margin-bottom: 4px; }
.stat-label { font-size: 13px; color: var(--gray); font-weight: 600; margin-bottom: 10px; }
.stat-trend { font-size: 12px; font-weight: 700; }
.trend-up { color: var(--success); }
.trend-down { color: var(--danger); }

/* ── CHARTS ─────────────────────────────────────────────────── */
.charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 28px; }
.chart-card { background: #fff; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); }
.chart-card h3 { font-size: 16px; font-weight: 800; margin-bottom: 4px; }
.chart-card .chart-sub { font-size: 13px; color: var(--gray); margin-bottom: 20px; }
.chart-wrap { position: relative; height: 240px; }

/* ── TABLE ──────────────────────────────────────────────────── */
.table-card { background: #fff; border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
.table-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid var(--border); }
.table-header h3 { font-size: 16px; font-weight: 800; }
.btn-sm { padding: 8px 16px; border-radius: 50px; border: none; font-family: var(--font); font-size: 13px; font-weight: 700; cursor: pointer; transition: .2s; }
.btn-primary { background: var(--primary); color: #fff; }
.btn-primary:hover { background: #e55a26; }
.btn-outline { background: transparent; border: 2px solid var(--border); color: var(--dark); }
.btn-outline:hover { border-color: var(--primary); color: var(--primary); }

table { width: 100%; border-collapse: collapse; }
th { padding: 12px 24px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .8px; color: var(--gray); font-weight: 700; background: #fafbff; border-bottom: 1px solid var(--border); }
td { padding: 14px 24px; font-size: 14px; border-bottom: 1px solid #f5f5f5; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #fafbff; }

.badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
.badge-success { background: rgba(6,214,160,.12); color: #0a7f5a; }
.badge-danger  { background: rgba(239,71,111,.12); color: #b82050; }
.badge-warning { background: rgba(255,209,102,.2);  color: #8a6800; }
.badge-info    { background: rgba(78,205,196,.12);  color: #0a7f78; }

.user-cell { display: flex; align-items: center; gap: 10px; }
.user-thumb { width: 32px; height: 32px; border-radius: 8px; background: var(--primary); color: #fff; font-size: 12px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.user-info-name { font-weight: 700; font-size: 14px; }
.user-info-email { font-size: 12px; color: var(--gray); }

.action-btn { width: 30px; height: 30px; border-radius: 8px; border: none; cursor: pointer; font-size: 14px; transition: .2s; background: transparent; }
.action-btn:hover { background: var(--light); }

/* ── RESPONSIVE ─────────────────────────────────────────────── */
@media(max-width:1200px) { .stats-grid { grid-template-columns: repeat(2,1fr); } .charts-grid { grid-template-columns: 1fr; } }
@media(max-width:768px) { .sidebar { transform: translateX(-100%); } .main { margin-left: 0; } .content { padding: 20px; } .stats-grid { grid-template-columns: 1fr 1fr; } }
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon"><img src="https://cdn.axet.fr/favicon.ico" width="24" height="24" alt=""></div>
    <span class="logo-name">Axent</span>
    <span class="logo-badge">Admin</span>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-label">Principal</div>
    <a href="<?= URL_ADMIN ?>/dashboard" class="nav-item active">
      <span class="nav-icon">📊</span> Tableau de bord
    </a>
    <a href="<?= URL_ADMIN ?>/users" class="nav-item">
      <span class="nav-icon">👥</span> Utilisateurs
      <span class="nav-badge">1 204</span>
    </a>
    <a href="<?= URL_ADMIN ?>/sites" class="nav-item">
      <span class="nav-icon">🌐</span> Sites
    </a>
    <a href="<?= URL_ADMIN ?>/consents" class="nav-item">
      <span class="nav-icon">✅</span> Consentements
    </a>
    <a href="<?= URL_ADMIN ?>/stats" class="nav-item">
      <span class="nav-icon">📈</span> Statistiques
    </a>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-label">RGPD</div>
    <a href="<?= URL_ADMIN ?>/rgpd" class="nav-item">
      <span class="nav-icon">🔒</span> Demandes RGPD
      <span class="nav-badge" style="background:var(--danger)">3</span>
    </a>
    <a href="<?= URL_ADMIN ?>/audit" class="nav-item">
      <span class="nav-icon">📋</span> Journal d'audit
    </a>
    <a href="<?= URL_ADMIN ?>/emails" class="nav-item">
      <span class="nav-icon">📧</span> Emails envoyés
    </a>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-label">Système</div>
    <a href="<?= URL_ADMIN ?>/settings" class="nav-item">
      <span class="nav-icon">⚙️</span> Paramètres
    </a>
    <a href="<?= URL_DOCS ?>" class="nav-item" target="_blank">
      <span class="nav-icon">📚</span> Documentation
    </a>
    <a href="<?= URL_ADMIN ?>/health" class="nav-item">
      <span class="nav-icon">💚</span> Santé système
    </a>
  </div>

  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar">SA</div>
      <div>
        <div class="user-name">Super Admin</div>
        <div class="user-role">admin@axet.fr</div>
      </div>
    </div>
  </div>
</aside>

<!-- MAIN -->
<main class="main">
  <!-- Topbar -->
  <header class="topbar">
    <div class="topbar-title">Tableau de bord 👋</div>
    <div class="topbar-actions">
      <div class="search-bar">
        <span>🔍</span>
        <input type="text" placeholder="Rechercher un utilisateur, site...">
      </div>
      <button class="btn-icon notif-dot" title="Notifications">🔔</button>
      <a href="<?= URL_AUTH ?>/logout" class="btn-icon" title="Déconnexion">🚪</a>
    </div>
  </header>

  <!-- Content -->
  <div class="content">
    <div class="page-header">
      <h1>Vue d'ensemble</h1>
      <p>Bienvenue dans le QG des cookies. Tout va bien ? 🍪</p>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-value">1 204</div>
        <div class="stat-label">Utilisateurs inscrits</div>
        <div class="stat-trend trend-up">↑ +12% ce mois</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-value">84 763</div>
        <div class="stat-label">Consentements enregistrés</div>
        <div class="stat-trend trend-up">↑ +8% ce mois</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">🌐</div>
        <div class="stat-value">347</div>
        <div class="stat-label">Sites actifs</div>
        <div class="stat-trend trend-up">↑ +5 cette semaine</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📧</div>
        <div class="stat-value">3</div>
        <div class="stat-label">Demandes RGPD en attente</div>
        <div class="stat-trend trend-down">⚠️ À traiter sous 30j</div>
      </div>
    </div>

    <!-- Charts -->
    <div class="charts-grid">
      <div class="chart-card">
        <h3>Consentements — 30 derniers jours</h3>
        <div class="chart-sub">Acceptés, refusés et partiels</div>
        <div class="chart-wrap">
          <canvas id="chartLine"></canvas>
        </div>
      </div>
      <div class="chart-card">
        <h3>Répartition des choix</h3>
        <div class="chart-sub">Sur les 30 derniers jours</div>
        <div class="chart-wrap">
          <canvas id="chartDonut"></canvas>
        </div>
      </div>
    </div>

    <!-- Tableau utilisateurs récents -->
    <div class="table-card">
      <div class="table-header">
        <h3>Derniers utilisateurs inscrits</h3>
        <div style="display:flex;gap:8px">
          <button class="btn-sm btn-outline">Exporter CSV</button>
          <button class="btn-sm btn-primary">Voir tous</button>
        </div>
      </div>
      <table>
        <thead>
          <tr>
            <th>Utilisateur</th>
            <th>Rôle</th>
            <th>Statut</th>
            <th>Connexion OAuth</th>
            <th>Inscription</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><div class="user-cell"><div class="user-thumb">ML</div><div><div class="user-info-name">Marie Laurent</div><div class="user-info-email">marie@exemple.fr</div></div></div></td>
            <td><span class="badge badge-info">user</span></td>
            <td><span class="badge badge-success">● Actif</span></td>
            <td>🔵 Google</td>
            <td>Il y a 2h</td>
            <td><button class="action-btn" title="Voir">👁️</button> <button class="action-btn" title="Éditer">✏️</button> <button class="action-btn" title="Suspendre">⏸️</button></td>
          </tr>
          <tr>
            <td><div class="user-cell"><div class="user-thumb" style="background:#5865F2">TD</div><div><div class="user-info-name">Tom Dupont</div><div class="user-info-email">tom@exemple.fr</div></div></div></td>
            <td><span class="badge badge-info">user</span></td>
            <td><span class="badge badge-success">● Actif</span></td>
            <td>🟣 Discord</td>
            <td>Il y a 5h</td>
            <td><button class="action-btn">👁️</button> <button class="action-btn">✏️</button> <button class="action-btn">⏸️</button></td>
          </tr>
          <tr>
            <td><div class="user-cell"><div class="user-thumb" style="background:#333">SB</div><div><div class="user-info-name">Sophie Bernard</div><div class="user-info-email">sophie@exemple.fr</div></div></div></td>
            <td><span class="badge badge-warning">admin</span></td>
            <td><span class="badge badge-success">● Actif</span></td>
            <td>⚫ GitHub</td>
            <td>Il y a 1j</td>
            <td><button class="action-btn">👁️</button> <button class="action-btn">✏️</button> <button class="action-btn">⏸️</button></td>
          </tr>
          <tr>
            <td><div class="user-cell"><div class="user-thumb" style="background:#0078d4">PM</div><div><div class="user-info-name">Pierre Martin</div><div class="user-info-email">pierre@exemple.fr</div></div></div></td>
            <td><span class="badge badge-info">user</span></td>
            <td><span class="badge badge-danger">⏸ Suspendu</span></td>
            <td>🔷 Microsoft</td>
            <td>Il y a 3j</td>
            <td><button class="action-btn">👁️</button> <button class="action-btn">✏️</button> <button class="action-btn">▶️</button></td>
          </tr>
        </tbody>
      </table>
    </div>

  </div>
</main>

<script>
// Graphique courbe - Consentements 30j
const ctxLine = document.getElementById('chartLine').getContext('2d');
const labels  = Array.from({length:30}, (_, i) => {
  const d = new Date(); d.setDate(d.getDate() - (29 - i));
  return d.getDate() + '/' + (d.getMonth()+1);
});
new Chart(ctxLine, {
  type: 'line',
  data: {
    labels,
    datasets: [
      {
        label: 'Acceptés',
        data: Array.from({length:30}, () => Math.floor(Math.random()*1500+800)),
        borderColor: '#06D6A0', backgroundColor: 'rgba(6,214,160,.08)',
        tension: .4, fill: true, pointRadius: 0, borderWidth: 2,
      },
      {
        label: 'Refusés',
        data: Array.from({length:30}, () => Math.floor(Math.random()*600+200)),
        borderColor: '#EF476F', backgroundColor: 'rgba(239,71,111,.08)',
        tension: .4, fill: true, pointRadius: 0, borderWidth: 2,
      },
      {
        label: 'Partiels',
        data: Array.from({length:30}, () => Math.floor(Math.random()*400+100)),
        borderColor: '#FFD166', backgroundColor: 'rgba(255,209,102,.08)',
        tension: .4, fill: true, pointRadius: 0, borderWidth: 2,
      },
    ],
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16, font: { family: "'Plus Jakarta Sans'" } } } },
    scales: {
      x: { grid: { display: false }, ticks: { maxTicksLimit: 8, font: { family: "'Plus Jakarta Sans'" } } },
      y: { grid: { color: '#f0f0f0' }, ticks: { font: { family: "'Plus Jakarta Sans'" } } },
    },
  },
});

// Graphique donut - Répartition
const ctxDonut = document.getElementById('chartDonut').getContext('2d');
new Chart(ctxDonut, {
  type: 'doughnut',
  data: {
    labels: ['Acceptés', 'Refusés', 'Partiels'],
    datasets: [{
      data: [62, 24, 14],
      backgroundColor: ['#06D6A0', '#EF476F', '#FFD166'],
      borderWidth: 0,
      hoverOffset: 8,
    }],
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    cutout: '72%',
    plugins: {
      legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16, font: { family: "'Plus Jakarta Sans'" } } },
      tooltip: { callbacks: { label: ctx => ' ' + ctx.parsed + '%' } },
    },
  },
});
</script>
</body>
</html>
