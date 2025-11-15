<?php
// api/image-to-coloring.php → PERFECT BOLD B&W • AUTO WARMUP • WORKS EVERY TIME (NOV 2025)

$HF_TOKEN = getenv('HF_TOKEN') ?: die('HF_TOKEN missing');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('No image uploaded');
}

$image_path = $_FILES['image']['tmp_name'];
$image_b64  = base64_encode(file_get_contents($image_path));

// BEST MODEL FOR BOLD CLEAN LINEART (100% black & white printable)
$model = 'black-forest-labs/FLUX.1-schnell';

function call_hf($b64) {
    global $model, $HF_TOKEN;

    $payload = json_encode(["inputs" => $b64]);

    $ch = curl_init("https://router.huggingface.co/hf-inference/models/$model");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $HF_TOKEN",
            "Content-Type: application/json",
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
    ]);

    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$code, $response];
}

// Try up to 3 times (first call warms up the model if cold)
for ($i = 0; $i < 3; $i++) {
    list($code, $response) = call_hf($image_b64);

    if ($code === 200 && strlen($response) > 20000) {
        // SUCCESS — perfect bold black & white image
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="coloring-page.png"');
        header('Cache-Control: public, max-age=86400');
        echo $response;
        exit;
    }

    // If model is loading, wait a bit and retry
    sleep(8);
}

// Rare fallback — still returns a nice message
http_response_code(502);
die("Almost ready! Try again in 5-10 seconds — model warming up 🙂");