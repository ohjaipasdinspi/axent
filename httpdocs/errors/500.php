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
  .emoji { width: 80px; height: 80px; margin: 0 auto 24px; display: block; color: #8891A4; animation: spin 2s linear infinite; }
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
  <svg class="emoji" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
  <h1>500</h1>
  <p>Quelque chose a mal tourné côté serveur. L'équipe technique a été alertée automatiquement.<br>(C'est-à-dire que ça a été loggé quelque part.)</p>
  <a href="/">← Retour à l'accueil</a>
  <div class="code">Erreur enregistrée · <?= date('Y-m-d H:i:s') ?></div>
</div>
</body>
</html>
