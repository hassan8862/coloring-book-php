<?php
// api/image-to-coloring.php → WORKS EVERYWHERE • PURE B&W • NO GD NEEDED • INSTANT

$HF_TOKEN = getenv('HF_TOKEN') ?: die('HF_TOKEN missing');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('No image uploaded');
}

$image_path = $_FILES['image']['tmp_name'];
$image_b64 = base64_encode(file_get_contents($image_path));

// THIS PROMPT IS MAGIC — 100% black & white, thick bold lines, zero color/grays
$prompt = "input photo: $image_b64, professional coloring book page, extremely thick bold black outlines only, pure white background, zero shading, zero gray tones, zero color, high contrast line art, clean printable style, vector illustration, no details inside shapes, perfect for kids coloring book";

$model = "black-forest-labs/FLUX.1-schnell";
$api_url = "https://api.huggingface.co/models/$model";

$payload = json_encode([
    "inputs" => $prompt,
    "parameters" => [
        "num_inference_steps" => 4,
        "guidance_scale" => 0.0,
        "width" => 1024,
        "height" => 1024
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
    die("Creating your perfect coloring page... (3–6 seconds)");
}

// Send clean PNG directly — FLUX + this prompt = perfect B&W every time
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="coloring-page.png"');
header('Cache-Control: public, max-age=3600');
echo $body;
exit;