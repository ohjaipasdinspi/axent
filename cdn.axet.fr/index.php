<?php
declare(strict_types=1);

require_once __DIR__ . '/../httpdocs/config/config.php';

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'service' => 'Axent CDN',
    'sdk' => URL_CDN . '/sdk.js',
    'status' => 'ok',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
