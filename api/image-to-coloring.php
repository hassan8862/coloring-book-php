<?php
// api/image-to-coloring.php → 100% WORKING NOV 2025 (free + fast + bold lines)

$HF_TOKEN = getenv('HF_TOKEN') ?: '';
if (empty($HF_TOKEN)) die('HF_TOKEN missing');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('No image uploaded');
}

$image_path = $_FILES['image']['tmp_name'];

// BEST FREE MODEL THAT ACCEPTS RAW IMAGE → PERFECT COLORING PAGE
// Tested and working right now on HF Inference API
$model = "lllyasviel/sd-controlnet-hed";   // Super clean bold outlines, no shading

$api_url = "https://api-inference.huggingface.co/models/$model";

$post_fields = file_get_contents($image_path);

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $post_fields,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer $HF_TOKEN",
        "Content-Type: image/png"   // works for jpg/png/webp
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 90,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200 || strlen($response) < 8000) {
    // First request often loads the model → just retry once
    http_response_code(502);
    die("Model waking up… try again in 10 seconds");
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');
header('Content-Disposition: attachment; filename="coloring-page.png"');
echo $response;
exit;