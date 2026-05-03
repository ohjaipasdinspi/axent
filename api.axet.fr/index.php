<?php
/**
 * Axent - API REST
 * Point d'entrée : api.axet.fr
 * Routes disponibles :
 *   POST /consent          → Enregistrer un consentement
 *   GET  /consent/{id}     → Récupérer un consentement
 *   GET  /site/{uuid}      → Config d'un site
 *   GET  /health           → État de l'API
 */

declare(strict_types=1);

require_once __DIR__ . '/../httpdocs/config/config.php';
require_once __DIR__ . '/../httpdocs/core/Database.php';
require_once __DIR__ . '/../httpdocs/core/Security.php';

// Headers JSON + CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key');
header('X-Content-Type-Options: nosniff');
header_remove('X-Powered-By');

// Pré-vol OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Router basique
$route  = trim($_GET['_route'] ?? $_SERVER['PATH_INFO'] ?? '/', '/');
$method = $_SERVER['REQUEST_METHOD'];
$parts  = explode('/', $route);

function respond(int $code, array $data): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function getBody(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw ?: '{}', true) ?? [];
}

function getIp(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_X_REAL_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';
}

// ── Rate limiting API ────────────────────────────────────────────
$ipHash = Database::hashIP(getIp());
if (Security::isRateLimited($ipHash, 'api')) {
    respond(429, ['error' => 'Trop de requêtes. Ralentissez ! ☕', 'retry_after' => RATE_LIMIT_WINDOW]);
}

// ── Routes ───────────────────────────────────────────────────────

// GET /health
if ($route === 'health' && $method === 'GET') {
    try {
        Database::query('SELECT 1');
        respond(200, [
            'status'  => 'ok',
            'service' => 'Axent API',
            'version' => APP_VERSION,
            'time'    => date('c'),
        ]);
    } catch (\Exception $e) {
        respond(503, ['status' => 'degraded', 'error' => 'Database unavailable']);
    }
}

// POST /consent — Enregistrement d'un consentement
if ($route === 'consent' && $method === 'POST') {
    $body = getBody();

    $clientId  = $body['clientId']  ?? '';
    $versionId = $body['versionId'] ?? '';
    $choice    = $body['choice']    ?? '';
    $categories = $body['categories'] ?? [];

    // Validation
    if (empty($clientId) || empty($versionId) || empty($choice)) {
        respond(400, ['error' => 'Paramètres manquants : clientId, versionId, choice sont requis']);
    }
    if (!in_array($choice, ['accepted', 'refused', 'partial'], true)) {
        respond(400, ['error' => 'choice invalide. Valeurs : accepted | refused | partial']);
    }

    // Vérifier que le site existe
    $site = Database::fetchOne(
        'SELECT s.id, s.status, cv.id as version_db_id FROM axnt_sites s
         JOIN axnt_cookie_versions cv ON cv.site_id = s.id
         WHERE s.uuid = ? AND cv.uuid = ? AND s.status = "active"',
        [$clientId, $versionId]
    );

    if (!$site) {
        // En mode dev/démo, on accepte sans vérification
        if (APP_ENV === 'development' || str_starts_with($clientId, 'demo-')) {
            respond(200, ['status' => 'ok', 'mode' => 'demo', 'message' => 'Consentement reçu (mode démo)']);
        }
        respond(404, ['error' => 'Site ou version introuvable. Vérifiez clientId et cookiesVersion.']);
    }
    if ($site['status'] !== 'active') {
        respond(403, ['error' => 'Ce site est suspendu.']);
    }

    // Pseudonymisation du visiteur
    $visitorRaw = ($body['visitorId'] ?? '') ?: (getIp() . '-' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $visitorId  = hash('sha256', $visitorRaw . SECRET_KEY);
    $ipHashed   = Database::hashIP(getIp());
    $uaHash     = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . SECRET_KEY);

    // Upsert (mise à jour si le visiteur a déjà consenti pour cette version)
    $existing = Database::fetchOne(
        'SELECT id FROM axnt_consents WHERE site_id = ? AND version_id = ? AND visitor_id = ?',
        [$site['id'], $site['version_db_id'], $visitorId]
    );

    $expiresAt  = date('Y-m-d H:i:s', strtotime('+' . RGPD_COOKIE_LIFETIME_DAYS . ' days'));
    $categoriesJson = json_encode($categories);
    $gcmJson    = json_encode($body['gcm'] ?? null);
    $lang       = substr($body['lang'] ?? 'fr', 0, 5);
    $country    = substr($body['country'] ?? '', 0, 2);

    if ($existing) {
        Database::query(
            'UPDATE axnt_consents SET choice = ?, categories = ?, gcm_data = ?, consented_at = NOW(), expires_at = ? WHERE id = ?',
            [$choice, $categoriesJson, $gcmJson, $expiresAt, $existing['id']]
        );
        $consentId = $existing['id'];
    } else {
        $consentId = Database::insert(
            'INSERT INTO axnt_consents
             (site_id, version_id, visitor_id, choice, categories, ip_hashed, user_agent_hash, country_code, lang, gcm_data, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $site['id'], $site['version_db_id'], $visitorId,
                $choice, $categoriesJson, $ipHashed, $uaHash,
                $country ?: null, $lang, $gcmJson, $expiresAt,
            ]
        );
    }

    respond(200, [
        'status'     => 'ok',
        'consentId'  => (string) $consentId,
        'choice'     => $choice,
        'expiresAt'  => $expiresAt,
    ]);
}

// GET /consent/{visitorToken} — Récupérer un consentement
if ($parts[0] === 'consent' && isset($parts[1]) && $method === 'GET') {
    $clientId  = $_GET['clientId']  ?? '';
    $versionId = $_GET['versionId'] ?? '';
    $visitorId = hash('sha256', $parts[1] . SECRET_KEY);

    if (empty($clientId) || empty($versionId)) {
        respond(400, ['error' => 'clientId et versionId requis en query params']);
    }

    $consent = Database::fetchOne(
        'SELECT c.choice, c.categories, c.consented_at, c.expires_at
         FROM axnt_consents c
         JOIN axnt_sites s ON s.id = c.site_id
         JOIN axnt_cookie_versions cv ON cv.id = c.version_id
         WHERE s.uuid = ? AND cv.uuid = ? AND c.visitor_id = ? AND c.anonymized_at IS NULL',
        [$clientId, $versionId, $visitorId]
    );

    if (!$consent) {
        respond(404, ['status' => 'not_found', 'message' => 'Aucun consentement enregistré pour ce visiteur']);
    }

    respond(200, [
        'status'      => 'ok',
        'choice'      => $consent['choice'],
        'categories'  => json_decode($consent['categories'], true),
        'consentedAt' => $consent['consented_at'],
        'expiresAt'   => $consent['expires_at'],
    ]);
}

// GET /site/{uuid} — Config publique d'un site (pour le widget)
if ($parts[0] === 'site' && isset($parts[1]) && $method === 'GET') {
    $uuid = $parts[1];

    $site = Database::fetchOne(
        'SELECT s.name, s.domain, s.widget_config, cv.uuid as version_uuid, cv.config as cookie_config
         FROM axnt_sites s
         JOIN axnt_cookie_versions cv ON cv.site_id = s.id AND cv.is_active = 1
         WHERE s.uuid = ? AND s.status = "active"',
        [$uuid]
    );

    if (!$site) {
        respond(404, ['error' => 'Site introuvable ou inactif']);
    }

    respond(200, [
        'name'         => $site['name'],
        'domain'       => $site['domain'],
        'widgetConfig' => json_decode($site['widget_config'] ?? '{}', true),
        'version'      => $site['version_uuid'],
        'cookies'      => json_decode($site['cookie_config'], true),
    ]);
}

// Route inconnue
respond(404, [
    'error'   => 'Route introuvable. Vous cherchez peut-être la documentation ?',
    'docs'    => 'https://docs.axet.fr/api',
    'routes'  => [
        'GET  /health',
        'POST /consent',
        'GET  /consent/{token}?clientId=&versionId=',
        'GET  /site/{uuid}',
    ],
]);
