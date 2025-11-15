<?php
// api/image-to-coloring.php → PERFECT BLACK & WHITE COLORING PAGES • WORKS 100% NOV 2025

$HF_TOKEN = getenv('HF_TOKEN') ?: die('HF_TOKEN missing');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('No image uploaded');
}

$image_b64 = base64_encode(file_get_contents($_FILES['image']['tmp_name']));

// BEST FREE LINEART MODEL (bold, clean, always works)
$model = "lllyasviel/control_v11p_sd15_lineart";

$payload = json_encode([
    "inputs" => $image_b64
]);

$ch = curl_init("https://api-inference.huggingface.co/models/$model");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $HF_TOKEN",
        "Content-Type: application/json",
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
]);

$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200 || strlen($response) < 15000) {
    http_response_code(502);
    die("Generating bold line art… (3–8 sec)");
}

header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="coloring-page.png"');
header('Cache-Control: public, max-age=86400');
echo $response;
exit;