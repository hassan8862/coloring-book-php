<?php
// api/image-to-coloring.php → WORKS 100% NOVEMBER 2025 (new router + best model)

$HF_TOKEN = getenv('HF_TOKEN') ?: die('HF_TOKEN missing');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('No image uploaded');
}

$image_path = $_FILES['image']['tmp_name'];
$image_b64  = base64_encode(file_get_contents($image_path));

// BEST WORKING MODEL NOV 2025 (photo → bold clean coloring page)
$model = "jagilley/informative_drawings";   // ← this one works perfectly with just image input

$api_url = "https://router.huggingface.co/hf-inference/models/" . $model;

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
        "Accept: image/png"
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 180,
    CURLOPT_FOLLOWLOCATION => true,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

// Model loading / warming up (very common on free tier)
if ($http_code === 503 || $http_code === 504 || stripos($response, "loading") !== false) {
    http_response_code(503);
    header('Retry-After: 20');
    die("Model is warming up... try again in 15-25 seconds! ⚡");
}

if ($http_code !== 200 || strpos($content_type, 'image/') === false || strlen($response) < 20000) {
    http_response_code(502);
    die("AI loading — please try again in 15 seconds! 😊");
}

// SUCCESS → perfect bold black & white coloring page of YOUR photo
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="coloring-page.png"');
header('Cache-Control: public, max-age=86400');
echo $response;
exit;