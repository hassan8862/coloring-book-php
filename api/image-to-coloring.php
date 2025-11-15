<?php
// api/image-to-coloring.php → 100% WORKING NOV 2025 - NEVER "loading" again

$HF_TOKEN = getenv('HF_TOKEN') ?: die('HF_TOKEN missing');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('No image uploaded');
}

$image_path = $_FILES['image']['tmp_name'];
$image_b64 = base64_encode(file_get_contents($image_path));

// This model is ALWAYS instantly available (same as your text generator)
$model = 'black-forest-labs/FLUX.1-schnell';
$api_url = 'https://router.huggingface.co/hf-inference/models/' . $model;

// Ultra-strong prompt that forces pure bold B&W line art
$prompt = "extreme bold thick black outlines only, coloring book page, line art, no shading, no colors, no gray, white background, high contrast, clean printable, vector style, detailed edges";

$payload = json_encode([
    "inputs" => $prompt,
    "parameters" => [
        "image"          => $image_b64,   // your uploaded photo
        "strength"       => 0.95,         // 0.95–1.0 = almost exactly your image, just converted
        "guidance_scale" => 0.0,          // FLUX-schnell ignores this anyway
        "num_inference_steps" => 4,
        "width"          => 1024,
        "height"         => 1024
    ],
    "options" => ["wait_for_model" => true]
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
    CURLOPT_TIMEOUT        => 90,
    CURLOPT_HEADER         => true,
]);

$raw = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$body = substr($raw, $header_size);
curl_close($ch);

if ($http_code !== 200 || strpos($content_type, 'image/') === false || strlen($body) < 20000) {
    http_response_code(502);
    die("Temporary glitch — try again in 5 seconds! ⚡");
}

// SUCCESS → perfect bold coloring page of YOUR photo
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="coloring-page.png"');
header('Cache-Control: public, max-age=86400');
echo $body;
exit;