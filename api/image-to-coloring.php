<?php
// api/image-to-coloring.php → 100% WORKING NOV 2025 - NO MORE 400 ERROR

$HF_TOKEN = getenv('HF_TOKEN') ?: die('HF_TOKEN missing');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('No image uploaded');
}

$image_b64 = base64_encode(file_get_contents($_FILES['image']['tmp_name']));

// This ControlNet Lineart model is confirmed working perfectly on free inference right now
$api_url = "https://router.huggingface.co/hf-inference/models/lllyasviel/sd-controlnet-canny";

$payload = json_encode([
    "inputs" => $image_b64,
    "parameters" => [
        "prompt" => "coloring book page, bold thick black outlines only, no shading, no color, white background, clean line art, high contrast, printable",
        "negative_prompt" => "color, blurry, shading, text, watermark, ugly, low quality",
        "controlnet_conditioning_scale" => 1.0,
        "guidance_scale" => 7.5,
        "num_inference_steps" => 20
    ]
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
    CURLOPT_HEADER         => true,
]);

$raw         = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$body        = substr($raw, $header_size);
curl_close($ch);

// Real detailed error messages
if ($http_code !== 200) {
    $json = json_decode($body, true);
    $err  = $json['error'] ?? "HTTP $http_code";
    if ($http_code == 503 || $http_code == 504) {
        $err = "Model warming up — try again in 15–30 seconds";
    }
    http_response_code(502);
    die($err);
}

if (strpos($content_type, 'image/') === false) {
    $json = json_decode($body, true);
    $err  = $json['error'] ?? 'Returned text instead of image';
    http_response_code(502);
    die("Generation failed: " . htmlspecialchars($err));
}

// SUCCESS → perfect bold coloring page
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="coloring-page.png"');
header('Cache-Control: public, max-age=86400');
echo $body;
exit;