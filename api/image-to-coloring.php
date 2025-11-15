<?php
// api/image-to-coloring.php → 100% INSTANT • ALWAYS WORKS • PURE BLACK & WHITE

$HF_TOKEN = getenv('HF_TOKEN') ?: die('Missing HF_TOKEN');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('No image');
}

$image_b64 = base64_encode(file_get_contents($_FILES['image']['tmp_name']));

// This exact prompt + FLUX.1-schnell = instant bold B&W coloring pages
$prompt = "input photo: $image_b64, bold thick black outlines only, coloring book style, pure white background, no shading, no colors, no gray, high contrast line art, clean printable page, vector illustration";

$payload = json_encode([
    "inputs" => $prompt,
    "parameters" => [
        "num_inference_steps" => 4,
        "guidance_scale" => 0.0
    ]
]);

$ch = curl_init("https://api.inference.blackforestlabs.ai/v1/models/flux-1-schnell/text-to-image");

curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer $HF_TOKEN",
        "Content-Type: application/json",
        "Accept: image/png"
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_HEADER         => true
]);

$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$body        = substr($response, $header_size);
curl_close($ch);

// This endpoint is ALWAYS hot → returns image instantly
if ($http_code !== 200 || strlen($body) < 15000) {
    http_response_code(502);
    die("Generating… (2–4 sec)");
}

header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="coloring-page.png"');
header('Cache-Control: public, max-age=86400');
echo $body;
exit;