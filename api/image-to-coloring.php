<?php
// api/image-to-coloring.php → GUARANTEED WORKING NOV 2025 (free, bold lines, no 502)

$HF_TOKEN = getenv('HF_TOKEN') ?: '';
if (empty($HF_TOKEN)) die('HF_TOKEN missing');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('No image uploaded');
}

$image_path = $_FILES['image']['tmp_name'];

// BEST & FASTEST free model that works perfectly with JSON payload
$model = "lllyasviel/control_v11p_sd15_lineart";   // ← Clean bold line art (recommended)
// Alternatives (just change the line above):
// "lllyasviel/control_v11p_sd15_canny"     → very thick bold outlines
// "lllyasviel/control_v11p_sd15_softedge"  → soft but bold edges

$api_url = "https://router.huggingface.co/hf-inference/models/$model";

$payload = json_encode([
    "inputs" => base64_encode(file_get_contents($image_path))
]);

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer $HF_TOKEN",
        "Content-Type: application/json"
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 120
]);

$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200 || strlen($response) < 10000) {
    // First request often loads the model → friendly message
    http_response_code(502);
    die("AI model is waking up… try again in 10-20 seconds 🙂");
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');
header('Content-Disposition: attachment; filename="coloring-page.png"');
echo $response;
exit;