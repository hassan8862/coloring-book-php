<?php
header('Content-Type: application/json; charset=utf-8');


// === STEP 1: LOG TOKEN STATUS ===
$HF_TOKEN = getenv('HF_TOKEN') ?: '';
if (empty($HF_TOKEN)) {
    error_log("ERROR: HF_TOKEN is MISSING in environment");
    echo json_encode(['error' => 'HF_TOKEN not set in Vercel']);
    exit;
}
if (strlen($HF_TOKEN) < 30 || !str_starts_with($HF_TOKEN, 'hf_')) {
    error_log("ERROR: HF_TOKEN looks invalid: " . substr($HF_TOKEN, 0, 10) . "...");
    echo json_encode(['error' => 'Invalid HF_TOKEN format']);
    exit;
}
error_log("HF_TOKEN loaded: " . substr($HF_TOKEN, 0, 10) . "... (valid)");

// === INPUT VALIDATION ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("ERROR: Not a POST request");
    echo json_encode(['error' => 'POST required']);
    exit;
}

$prompt = trim($_POST['prompt'] ?? '');
$pages = min(22, max(1, (int)($_POST['pages'] ?? 1)));
if (empty($prompt)) {
    error_log("ERROR: Prompt is empty");
    echo json_encode(['error' => 'Prompt required']);
    exit;
}

error_log("Request: prompt='$prompt', pages=$pages");

// === CONFIG ===
$model = 'stabilityai/stable-diffusion-xl-base-1.0';
$api_url = 'https://router.huggingface.co/hf-inference/models/' . $model;

error_log("Using API URL: $api_url");
error_log("Using model: $model");

$images = [];
$tmp_dir = sys_get_temp_dir();
$pdf_name = 'coloring-book-' . uniqid() . '.pdf';
$pdf_path = "$tmp_dir/$pdf_name";

for ($i = 1; $i <= $pages; $i++) {
    $full_prompt = "$prompt, coloring book page $i, lineart, black and white, thick outlines, no shading, high contrast, printable, clean edges";

    $payload = [
        'inputs' => $full_prompt,
        'parameters' => [
            'width' => 1024,
            'height' => 1024,
            'num_inference_steps' => 30,
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
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => true,  // Get headers
    ]);

    $raw_response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $response_body = substr($raw_response, $header_size);

    curl_close($ch);

    // === LOG EVERYTHING ===
    error_log("--- PAGE $i ---");
    error_log("HTTP Code: $http_code");
    error_log("Content-Type: $content_type");
    error_log("Response size: " . strlen($response_body) . " bytes");

    if ($http_code !== 200) {
        $error = json_decode($response_body, true);
        $err_msg = $error['error'] ?? 'Unknown error';
        $err_detail = $error['estimated_time'] ?? $error['message'] ?? '';
        error_log("API ERROR: $err_msg | Detail: $err_detail");

        if ($http_code == 401) {
            error_log("TOKEN REJECTED: Check HF_TOKEN in Vercel settings");
        }
        if ($http_code == 403) {
            error_log("MODEL ACCESS DENIED: You may need to accept terms on HF model page");
        }
        if ($http_code == 429 || $http_code == 503) {
            error_log("RATE LIMIT or MODEL LOADING: Retrying in 15s...");
            sleep(15);
            $i--; // retry
            continue;
        }

        sleep(5);
        continue;
    }

    // === SUCCESS: Check image ===
    if (strpos($content_type, 'image/') === false) {
        error_log("NOT AN IMAGE: Content-Type is $content_type");
        error_log("Response preview: " . substr($response_body, 0, 200));
        continue;
    }

    if (strlen($response_body) < 5000) {
        error_log("IMAGE TOO SMALL: " . strlen($response_body) . " bytes (corrupted?)");
        continue;
    }

    $img_path = "$tmp_dir/page_$i.png";
    if (file_put_contents($img_path, $response_body)) {
        $images[] = $img_path;
        error_log("IMAGE SAVED: $img_path (" . filesize($img_path) . " bytes)");
    } else {
        error_log("FAILED to save image to $img_path");
    }

    sleep(3); // Respect rate limits
}

// === FINAL RESULT ===
if (empty($images)) {
    error_log("FAILED: No images generated after $pages attempts");
    echo json_encode(['error' => 'No images generated. Check logs.']);
    exit;
}

error_log("SUCCESS: Generated " . count($images) . " pages. Creating PDF...");

require_once __DIR__ . '/../vendor/autoload.php';
$mpdf = new \Mpdf\Mpdf(['format' => 'A4', 'margin' => 5]);
foreach ($images as $img) {
    $mpdf->AddPage();
    $mpdf->Image($img, 0, 0, 210, 297);
    @unlink($img);
}
$mpdf->Output($pdf_path, 'F');

error_log("PDF created: $pdf_path");

echo json_encode([
    'success' => true,
    'download_url' => "/api/download.php?file=" . urlencode(basename($pdf_path)),
    'pages' => count($images)
]);
exit;  // Critical!
?>