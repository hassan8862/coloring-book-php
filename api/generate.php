<?php 
// === DEEPAI API (FREE) ===
$DEEP_AI_KEY = getenv('DEEP_AI_KEY') ?: '';
if (empty($DEEP_AI_KEY)) {
    http_response_code(500);
    exit('DEEP_AI_KEY missing');
}

$full_prompt = "$prompt, coloring book page $page, lineart, black and white, thick outlines, no shading, high contrast, printable";

$ch = curl_init('https://api.deepai.org/api/text2img');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => ['text' => $full_prompt],
    CURLOPT_HTTPHEADER => ['api-key: ' . $DEEP_AI_KEY],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

error_log("DeepAI Response | HTTP: $http_code");

if ($http_code !== 200) {
    error_log("DeepAI Error: $response");
    http_response_code(502);
    exit("DeepAI service error");
}

$data = json_decode($response, true);
$img_url = $data['output_url'] ?? null;
if (!$img_url) {
    error_log("No image URL in response");
    http_response_code(502);
    exit("No image generated");
}

// Download the image
$ch = curl_init($img_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30
]);
$body = curl_exec($ch);
curl_close($ch);

if (strlen($body) < 1000) {
    http_response_code(502);
    exit("Empty image");
}

// Send PNG
header('Cache-Control: public, max-age=86400');
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="page-' . $page . '.png"');
echo $body;
exit;