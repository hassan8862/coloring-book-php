<?php
// api/image-to-coloring.php
// Converts any uploaded photo into a bold black & white coloring-book line art using a free HF model

$HF_TOKEN = getenv('HF_TOKEN') ?: '';
if (empty($HF_TOKEN)) {
    http_response_code(500);
    die('HF_TOKEN missing');
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('No image uploaded');
}

$imagePath = $_FILES['image']['tmp_name'];

// We use a fast & free image-to-line-art model
$model = 'Zhengyun21/FLUX.1-dev-Realism-LoRA';   // good realism → line art conversion
// Alternative excellent free model: 'takuma104/sd-webui-lineart' or 'lllyasviel/control_v11p_sd15_lineart'

$api_url = "https://api-inference.huggingface.co/models/lllyasviel/control_v11p_sd15_lineart";

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => file_get_contents($imagePath),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $HF_TOKEN",
        "Content-Type: image/jpeg"
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200 || strlen($response) < 1000) {
    http_response_code(502);
    error_log("HF lineart error: HTTP $http_code");
    die("AI conversion failed");
}

// Output the resulting line art image
header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');
echo $response;
exit;