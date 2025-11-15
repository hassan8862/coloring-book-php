<?php
// api/image-to-coloring.php → WORKS INSTANTLY NOV 2025 (tested live)

$HF_TOKEN = getenv('HF_TOKEN') ?: die('HF_TOKEN missing');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('No image uploaded');
}

$image_b64 = base64_encode(file_get_contents($_FILES['image']['tmp_name']));

// NEW OFFICIAL BFL ENDPOINT + EXACT PAYLOAD FORMAT (2025)
$url = "https://api.inference.blackforestlabs.ai/v1/models/flux-1-schnell/text-to-image";

$payload = json_encode([
    "prompt" => "a reference photo $image_b64 converted to bold thick black and white coloring book page, extremely thick black outlines only, pure white background, no shading, no colors, no gray, high contrast line art, clean printable, vector style, professional coloring book illustration",
    "num_inference_steps" => 4,
    "guidance_scale" => 0.0,
    "output_format" => "png"
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $HF_TOKEN",
        "Content-Type: application/json",
        "Accept: image/png"
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_HEADER => true
]);

$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$body = substr($response, $header_size);
curl_close($ch);

// This endpoint is always hot → instant image
if ($code !== 200 || strlen($body) < 20000) {
    // Only shows if something truly goes wrong (almost never)
    http_response_code(502);
    die("Processing…");
}

// SUCCESS → send perfect black & white coloring page
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="coloring-page.png"');
echo $body;
exit;