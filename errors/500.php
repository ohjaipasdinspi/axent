<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>500 — Erreur serveur · Axent</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800;900&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F7F7FF; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; text-align: center; padding: 24px; }
  .card { background: #fff; border-radius: 24px; padding: 60px 48px; box-shadow: 0 8px 40px rgba(45,48,71,.12); max-width: 520px; }
  .emoji { font-size: 80px; margin-bottom: 24px; display: block; animation: spin 2s linear infinite; }
  @keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
  h1 { font-size: 48px; font-weight: 900; color: #2D3047; margin: 0 0 8px; }
  p { color: #8891A4; font-size: 17px; margin: 0 0 32px; line-height: 1.6; }
  a { display: inline-block; background: #FF6B35; color: #fff; padding: 12px 28px; border-radius: 50px; text-decoration: none; font-weight: 700; font-size: 15px; transition: .2s; }
  a:hover { background: #e55a26; transform: translateY(-2px); }
  .code { background: #f5f5f5; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #8891A4; margin-top: 24px; font-family: monospace; }
</style>
</head>
<body>
<div class="card">
  <span class="emoji">⚙️</span>
  <h1>500</h1>
  <p>Quelque chose a mal tourné côté serveur. L'équipe technique a été alertée automatiquement.<br>(C'est-à-dire que ça a été loggé quelque part.)</p>
  <a href="/">← Retour à l'accueil</a>
  <div class="code">Erreur enregistrée · <?= date('Y-m-d H:i:s') ?></div>
</div>
</body>
</html>
