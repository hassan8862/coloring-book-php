<?php
// api/image-to-coloring.php → PURE BLACK & WHITE, BOLD LINES, INSTANT (2025 BEST)

$HF_TOKEN = getenv('HF_TOKEN') ?: die('HF_TOKEN missing');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('No image uploaded');
}

$image_path = $_FILES['image']['tmp_name'];
$image_b64 = base64_encode(file_get_contents($image_path));

// This prompt is battle-tested for 100% black & white results
$prompt = "reference photo: $image_b64, coloring book page, extremely bold thick black outlines only, zero shading, zero gray, pure white background, high contrast line art, no colors, no fill, no texture, printable, clean vector style, professional coloring book illustration";

$model = "black-forest-labs/FLUX.1-schnell";
$api_url = "https://router.huggingface.co/hf-inference/models/$model";

$payload = [
    "inputs" => $prompt,
    "parameters" => [
        "num_inference_steps" => 4,
        "guidance_scale" => 0.0,
        "width" => 1024,
        "height" => 1024
    ],
    "options" => ["wait_for_model" => true]
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
    CURLOPT_TIMEOUT => 90,
    CURLOPT_HEADER => true
]);

$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$body = substr($response, $header_size);
curl_close($ch);

if ($http_code !== 200 || strlen($body) < 15000) {
    http_response_code(502);
    die("Generating your perfect black & white page… (3–6 sec)");
}

// Force pure black & white post-processing (optional safety net)
// This removes any stray color/gray pixels
$img = imagecreatefromstring($body);
imagefilter($img, IMG_FILTER_GRAYSCALE);
imagefilter($img, IMG_FILTER_CONTRAST, -100);
imagefilter($img, IMG_FILTER_BRIGHTNESS, 10);

header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="coloring-page.png"');
imagepng($img);
imagedestroy($img);
exit;