<?php
// api/generate.php – UPGRADED FOR 32-PAGE STORYBOOKS

$HF_TOKEN = getenv('HF_TOKEN') ?: '';
if (empty($HF_TOKEN)) {
    http_response_code(500);
    exit('HF_TOKEN missing');
}

$prompt = trim($_GET['prompt'] ?? '');
$page   = max(1, min(32, (int)($_GET['page'] ?? 1))); // 1 to 32 only
$story  = trim($_GET['story'] ?? ''); // full story context

if (empty($prompt)) {
    http_response_code(400);
    exit('Prompt required');
}

$model = 'black-forest-labs/FLUX.1-schnell';
$api_url = 'https://router.huggingface.co/hf-inference/models/' . $model;

// Enhanced prompt for story consistency + page number
$scene_prompt = $prompt;
if (!empty($story)) {
    $scene_prompt = "Page $page of 32: $prompt, part of this story: \"$story\"";
}

$full_prompt = "$scene_prompt, coloring book page, bold black outlines, white background, no shading, high contrast, clean line art, printable, detailed but not too complex for kids, vector style";

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

if ($http_code !== 200 || strpos($content_type, 'image/') === false || strlen($body) < 10000) {
    http_response_code(502);
    exit("AI error");
}

header('Cache-Control: public, max-age=86400');
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="page-'.str_pad($page, 2, '0', STR_PAD_LEFT).'.png"');
echo $body;
exit;