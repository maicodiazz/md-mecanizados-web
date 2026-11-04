<?php
// Envía el formulario "Pedir presupuesto" por email (con plano adjunto opcional).
header('Content-Type: application/json; charset=utf-8');

$DESTINO   = 'mecanizadosdiaz@live.com.ar';
$REMITENTE = 'web@mdmecanizadoscnc.org';
$MAX_BYTES = 8 * 1024 * 1024;
$EXT_OK    = ['pdf','dwg','dxf','step','stp','igs','iges','sldprt','sldasm','x_t','zip','jpg','jpeg','png'];

function salir($ok, $error = '') {
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    exit;
}
function campo($k, $max = 200) {
    $v = isset($_POST[$k]) ? trim((string)$_POST[$k]) : '';
    $v = str_replace(["\r", "\0"], '', $v);
    return mb_substr($v, 0, $max);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); salir(false, 'Método no permitido.'); }
if (campo('website') !== '') salir(true); // trampa para bots

$nombre   = str_replace("\n", ' ', campo('name', 120));
$empresa  = str_replace("\n", ' ', campo('company', 120));
$email    = str_replace("\n", '', campo('email', 160));
$telefono = str_replace("\n", ' ', campo('phone', 60));
$material = str_replace("\n", ' ', campo('material', 60));
$cantidad = str_replace("\n", ' ', campo('qty', 20));
$mensaje  = campo('msg', 5000);

if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    salir(false, 'Revisá tu nombre y que el email esté bien escrito.');
}
if ($cantidad !== '' && !preg_match('/^[1-9][0-9]{0,6}$/', $cantidad)) {
    salir(false, 'La cantidad tiene que ser un número entero mayor que 0.');
}
if ($material === '') $material = '(sin indicar)';

$texto = "Nuevo pedido de presupuesto desde la web\n\n"
       . "Nombre:      $nombre\n"
       . "Empresa:     $empresa\n"
       . "Email:       $email\n"
       . "Teléfono:    $telefono\n"
       . "Material:    $material\n"
       . "Cantidad:    $cantidad\n\n"
       . "Descripción:\n$mensaje\n";

$asunto  = '=?UTF-8?B?' . base64_encode("Pedido de presupuesto: $nombre" . ($empresa ? " ($empresa)" : '')) . '?=';
$headers = "From: MD Mecanizados Web <$REMITENTE>\r\n"
         . "Reply-To: $email\r\n"
         . "MIME-Version: 1.0\r\n";

$adj = isset($_FILES['file']) ? $_FILES['file'] : null;
if ($adj && $adj['error'] === UPLOAD_ERR_OK && $adj['size'] > 0) {
    if ($adj['size'] > $MAX_BYTES) salir(false, 'El archivo supera los 8 MB.');
    $nombreArch = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($adj['name']));
    $ext = strtolower(pathinfo($nombreArch, PATHINFO_EXTENSION));
    if (!in_array($ext, $EXT_OK, true)) salir(false, 'Ese tipo de archivo no lo podemos recibir. Mandá PDF, DWG, DXF, STEP, IGES, SolidWorks, ZIP o una foto.');
    $datos = chunk_split(base64_encode(file_get_contents($adj['tmp_name'])));
    $limite = 'md_' . md5(uniqid('', true));
    $headers .= "Content-Type: multipart/mixed; boundary=\"$limite\"\r\n";
    $cuerpo  = "--$limite\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: 8bit\r\n\r\n"
             . $texto . "\r\n"
             . "--$limite\r\n"
             . "Content-Type: application/octet-stream; name=\"$nombreArch\"\r\n"
             . "Content-Transfer-Encoding: base64\r\n"
             . "Content-Disposition: attachment; filename=\"$nombreArch\"\r\n\r\n"
             . $datos . "\r\n"
             . "--$limite--";
} elseif ($adj && $adj['error'] !== UPLOAD_ERR_NO_FILE) {
    salir(false, 'No se pudo subir el archivo. Probá con uno más liviano.');
} else {
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $cuerpo = $texto;
}

$ok = mail($DESTINO, $asunto, $cuerpo, $headers, '-f' . $REMITENTE);
salir($ok, 'No se pudo enviar. Escribinos por WhatsApp al +54 9 264 530-8659.');
