<?php
// api/generate.php – FINAL WORKING VERSION (FREE & FAST)

$HF_TOKEN = getenv('HF_TOKEN') ?: '';
if (empty($HF_TOKEN)) {
    http_response_code(500);
    exit('HF_TOKEN missing');
}

$prompt = trim($_GET['prompt'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
if (empty($prompt)) {
    http_response_code(400);
    exit('Prompt required');
}

// === FREE MODEL: FLUX.1-schnell ===
$model = 'black-forest-labs/FLUX.1-schnell';
$api_url = 'https://router.huggingface.co/hf-inference/models/' . $model;

$full_prompt = "$prompt, coloring book page $page, line art, bold black outlines, white background, no shading, high contrast, printable, clean, vector style";

$payload = [
    'inputs' => $full_prompt,
    'parameters' => [
        'width' => 768,
        'height' => 768,
        'num_inference_steps' => 4,
        'guidance_scale' => 0.0
    ],
    'options' => ['wait_for_model' => true]
];

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $HF_TOKEN",
        "Content-Type: application/json",
        "Accept: image/png"
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_HEADER => true,
]);

$raw = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$body = substr($raw, $header_size);
curl_close($ch);

// === LOG FOR DEBUG ===
error_log("HF Response | HTTP: $http_code | Type: $content_type | Size: " . strlen($body));

// === VALIDATE RESPONSE ===
if ($http_code !== 200) {
    error_log("HF API ERROR: HTTP $http_code");
    error_log("Response: " . substr($body, 0, 500));
    http_response_code(502);
    exit("AI service error (HTTP $http_code)");
}

if (strpos($content_type, 'image/') === false) {
    error_log("NOT AN IMAGE: $content_type");
    error_log("Response preview: " . substr($body, 0, 200));
    http_response_code(502);
    exit("AI returned non-image");
}

if (strlen($body) < 10000) {
    error_log("IMAGE TOO SMALL: " . strlen($body) . " bytes");
    http_response_code(502);
    exit("Generated image too small");
}

// === SEND PNG (no compression needed – FLUX is fast) ===
header('Cache-Control: public, max-age=86400');
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="page-'.$page.'.png"');
echo $body;
exit;