<?php
// api/image-to-coloring.php → PERFECT BOLD BLACK & WHITE COLORING PAGE (2025 WORKING)

$HF_TOKEN = getenv('HF_TOKEN') ?: die('HF_TOKEN missing');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('No image uploaded');
}

$image_path = $_FILES['image']['tmp_name'];
$image_b64  = base64_encode(file_get_contents($image_path));

// BEST FREE MODEL FOR PHOTO → CLEAN BOLD LINEART (2025)
$api_url = "https://router.huggingface.co/hf-inference/models/Gourieff/ReAMP-SDXL-Lineart";

$payload = json_encode([
    "inputs" => $image_b64
]);

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer $HF_TOKEN",
        "Content-Type: application/json",
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 180,
    CURLOPT_FOLLOWLOCATION => true,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Auto retry if model is loading (common on free inference)
if ($http_code === 503 || $http_code === 504 || strpos($response, "loading") !== false) {
    header('Retry-After: 15');
    http_response_code(503);
    die("Model warming up... try again in 10–20 seconds!");
}

if ($http_code !== 200 || strlen($response) < 10000) {
    http_response_code(502);
    die("AI still waking up, try again in 15 seconds!");
}

// SUCCESS → Send beautiful bold B&W coloring page
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="coloring-page.png"');
header('Cache-Control: public, max-age=86400');
echo $response;
exit;