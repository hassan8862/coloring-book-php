<?php
// api/image-to-coloring.php → NEW 2025 INSTANT VERSION (no more "waking up")

$HF_TOKEN = getenv('HF_TOKEN') ?: die('HF_TOKEN missing');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('No image uploaded');
}

$image_path = $_FILES['image']['tmp_name'];
$image_b64 = base64_encode(file_get_contents($image_path));

// Use FLUX.1-schnell (always hot, 1–4 sec response)
$model = "black-forest-labs/FLUX.1-schnell";
$api_url = "https://router.huggingface.co/hf-inference/models/$model";

$payload = json_encode([
    "inputs" => "photo of " . $image_b64 . ", convert to coloring book page, bold black outlines only, thick lines, white background, no shading, no color, high contrast, line art, printable, clean vector style",
    "parameters" => [
        "num_inference_steps" => 4,
        "guidance_scale" => 0.0
    ]
]);

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $HF_TOKEN",
        "Content-Type: application/json",
        "Accept: image/png"
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 90,
    CURLOPT_HEADER => true
]);

$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$body = substr($response, $header_size);
curl_close($ch);

if ($http_code !== 200 || strlen($body) < 10000) {
    http_response_code(502);
    die("Generating… (usually 3–6 seconds)");
}

header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="coloring-page.png"');
echo $body;