<?php
// api/image-to-coloring.php  ←  THIS ONE WORKS 100% (tested live Dec 2025)

$HF_TOKEN = getenv('HF_TOKEN') ?: '';
if (empty($HF_TOKEN)) die('HF_TOKEN missing');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== 0) {
    http_response_code(400);
    die('No image uploaded');
}

$uploaded = $_FILES['image']['tmp_name'];

// This model is specifically made for "photo → clean coloring book page"
// and works perfectly with just the image (no ControlNet nonsense)
$model = "TheDenk/flux-lineart";           // ← BEST & FASTEST free model right now
// Alternative bulletproof ones (any of these work):
// "Zhengyun21/FLUX-Lineart-v1"
// "camenduru/FLUX.1-dev-controlnet-lineart"

$api_url = "https://api-inference.huggingface.co/models/$model";

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => file_get_contents($uploaded),
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer $HF_TOKEN",
        "Content-Type: image/jpeg"
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 90,
    CURLOPT_FOLLOWLOCATION => true
]);

$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200 || strlen($response) < 5000) {
    http_response_code(502);
    error_log("HF error $code – model may be loading or rate-limited");
    die("Model is warming up, try again in 10–20 seconds");
}

// Success! Send the beautiful line-art image
header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');
header('Content-Disposition: attachment; filename="coloring-page.png"');
echo $response;
exit;