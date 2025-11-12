<?php
$HF_TOKEN = getenv('HF_TOKEN') ?: '';
if (empty($HF_TOKEN)) {
    http_response_code(500);
    exit('HF_TOKEN missing');
}

$prompt = trim($_GET['prompt'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
if (empty($prompt)) {
    http_response_code(400);
    exit('Prompt required');
}

$model = 'stabilityai/stable-diffusion-xl-base-1.0';
$api_url = 'https://router.huggingface.co/hf-inference/models/' . $model;

$full_prompt = "$prompt, coloring book page $page, lineart, black and white, thick outlines, no shading, high contrast, printable, bold lines";

$payload = [
    'inputs' => $full_prompt,
    'parameters' => [
        'width' => 1024,
        'height' => 1024,
        'num_inference_steps' => 25,
        'guidance_scale' => 7.5
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
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$body = substr($raw, $header_size);
curl_close($ch);

if ($http_code !== 200 || strlen($body) < 5000) {
    http_response_code(500);
    exit('Image generation failed');
}

// === SEND PNG DIRECTLY ===
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="page-' . $page . '.png"');
echo $body;
exit;