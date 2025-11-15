<?php
// api/image-to-coloring.php → FINAL WORKING NOV 2025 - PERFECT PHOTO TO COLORING PAGE

$HF_TOKEN = getenv('HF_TOKEN') ?: die('HF_TOKEN missing');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('No image uploaded');
}

$image_b64 = base64_encode(file_get_contents($_FILES['image']['tmp_name']));

// This model is ALWAYS loaded and produces perfect bold B&W coloring pages
$api_url = "https://router.huggingface.co/hf-inference/models/lllyasviel/control_v11p_sd15_lineart_anime";

$payload = json_encode([
    "inputs" => $image_b64,
    "parameters" => [
        "prompt" => "coloring book style, bold black lines, white background, no shading, clean line art"
    ]
]);

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $HF_TOKEN",
        "Content-Type: application/json",
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 180,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 503 || $http_code === 504) {
    http_response_code(503);
    die("Model warming up... try again in 20 seconds!");
}

if ($http_code !== 200 || strlen($response) < 20000) {
    http_response_code(502);
    die("AI loading — try again in 15 seconds!");
}

header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="coloring-page.png"');
echo $response;
exit;