<?php
// api/generate.php – DEEPAI VERSION (100% WORKING)

// === 1. GET INPUT SAFELY ===
$prompt = trim($_GET['prompt'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));

if (empty($prompt)) {
    http_response_code(400);
    exit('Error: Missing prompt');
}

// === 2. DEEPAI API KEY ===
$DEEP_AI_KEY = getenv('DEEP_AI_KEY') ?: '';
if (empty($DEEP_AI_KEY)) {
    error_log("DEEP_AI_KEY not set in Vercel");
    http_response_code(500);
    exit('Error: API key missing');
}

// === 3. BUILD PROMPT ===
$full_prompt = "$prompt, coloring book page $page, lineart, black and white, thick outlines, no shading, high contrast, printable, clean edges";

// === 4. CALL DEEPAI ===
$ch = curl_init('https://api.deepai.org/api/text2img');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => ['text' => $full_prompt],
    CURLOPT_HTTPHEADER => ['api-key: ' . $DEEP_AI_KEY],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_HEADER => true,
]);

$raw_response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$response_body = substr($raw_response, $header_size);
curl_close($ch);

error_log("DeepAI | HTTP: $http_code | Type: $content_type | Size: " . strlen($response_body));

// === 5. CHECK RESPONSE ===
if ($http_code !== 200) {
    $error = json_decode($response_body, true);
    $msg = $error['error'] ?? 'Unknown error';
    error_log("DeepAI API Error: $msg");
    http_response_code(502);
    exit("DeepAI error: $msg");
}

// Parse JSON to get image URL
$data = json_decode($response_body, true);
$img_url = $data['output_url'] ?? null;

if (!$img_url || !filter_var($img_url, FILTER_VALIDATE_URL)) {
    error_log("No valid image URL: " . substr($response_body, 0, 200));
    http_response_code(502);
    exit("Failed to get image URL");
}

// === 6. DOWNLOAD IMAGE ===
$ch = curl_init($img_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false
]);

$img_data = curl_exec($ch);
$img_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($img_http_code !== 200 || strlen($img_data) < 5000) {
    error_log("Image download failed | HTTP: $img_http_code | Size: " . strlen($img_data));
    http_response_code(502);
    exit("Failed to download image");
}

// === 7. SEND PNG ===
header('Cache-Control: public, max-age=86400');
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="page-'.$page.'.png"');
echo $img_data;
exit;