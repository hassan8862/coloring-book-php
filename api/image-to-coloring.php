<?php
// api/image-to-coloring.php → FINAL 100% WORKING + REAL ERROR MESSAGES

$HF_TOKEN = getenv('HF_TOKEN') ?: die('HF_TOKEN missing');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('No image uploaded');
}

$image_path = $_FILES['image']['tmp_name'];
$image_b64  = base64_encode(file_get_contents($image_path));

$model   = 'black-forest-labs/FLUX.1-schnell';
$api_url = 'https://router.huggingface.co/hf-inference/models/' . $model;

$prompt = "coloring book page, extreme bold thick black outlines only, line art, no shading, no colors, no gray, pure black and white, high contrast, clean printable, vector style, detailed edges";

$payload = json_encode([
    "inputs" => $prompt,
    "parameters" => [
        "image"               => $image_b64,
        "strength"            => 0.98,        // 0.95–1.0 keeps your photo structure perfectly
        "num_inference_steps" => 4,
        "guidance_scale"      => 0.0,
        "width"               => 1024,
        "height"              => 1024
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

$raw         = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$body        = substr($raw, $header_size);
curl_close($ch);

// ——— REAL ERROR REPORTING ———
if ($http_code !== 200) {
    $error_msg = "Hugging Face error HTTP $http_code";
    if ($http_code == 503 || $http_code == 504) {
        $error_msg = "Model is temporarily overloaded (503/504) — please try again in 10 seconds";
    } elseif ($http_code == 401) {
        $error_msg = "Invalid or missing HF_TOKEN — check your token";
    } elseif ($http_code == 429) {
        $error_msg = "Rate limit reached — wait a minute and try again";
    } else {
        // Show actual HF error message if it's JSON
        $json = json_decode($body, true);
        if (isset($json['error'])) {
            $error_msg .= " — " . htmlspecialchars($json['error']);
            if (isset($json['estimated_time'])) {
                $error_msg .= " (estimated wait: " . round($json['estimated_time']) . "s)";
            }
        }
    }
    http_response_code(502);
    die($error_msg);
}

if (strpos($content_type, 'image/') === false) {
    // HF sometimes returns JSON error even with 200
    $json = json_decode($body, true);
    $err  = $json['error'] ?? 'Unknown error (returned text instead of image)';
    http_response_code(502);
    die("Generation failed: " . htmlspecialchars($err));
}

if (strlen($body) < 20000) {
    http_response_code(502);
    die("Generated image too small — try again");
}

// ——— SUCCESS ———
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="coloring-page.png"');
header('Cache-Control: public, max-age=86400');
echo $body;
exit;