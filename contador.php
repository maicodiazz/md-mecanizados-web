<?php
// Contador de visitas de "Casillas y algo más".
// GET            -> devuelve el total sin sumar.
// POST nuevo=1   -> suma una visita (la página lo manda una vez por día y por navegador).
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

// Los datos se guardan fuera de public_html si se puede, para que no se pisen con los despliegues.
$dir = dirname(__DIR__) . '/md-contador';
if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
    $dir = __DIR__ . '/datos-contador';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    }
}
$file = $dir . '/casillas.json';

$fp = @fopen($file, 'c+');
if (!$fp) { http_response_code(500); echo '{"error":"sin acceso"}'; exit; }
flock($fp, LOCK_EX);
$raw = stream_get_contents($fp);
$d = json_decode($raw ?: '', true);
if (!is_array($d)) $d = ['total' => 0, 'dias' => []];

$suma = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['nuevo'] ?? '') === '1';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if ($suma && !preg_match('/bot|crawl|spider|slurp|preview|headless|lighthouse/i', $ua)) {
    $hoy = date('Y-m-d');
    $d['total'] = (int)$d['total'] + 1;
    $d['dias'][$hoy] = (int)($d['dias'][$hoy] ?? 0) + 1;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($d, JSON_PRETTY_PRINT));
    fflush($fp);
}
flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['total' => (int)$d['total']]);
