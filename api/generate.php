<?php
// === NO ob_clean(), NO manual headers — Vercel handles it ===

$HF_TOKEN = getenv('HF_TOKEN') ?: '';
if (empty($HF_TOKEN)) {
    echo json_encode(['error' => 'HF_TOKEN not set in Vercel']);
    exit;
}
if (!str_starts_with($HF_TOKEN, 'hf_') || strlen($HF_TOKEN) < 30) {
    echo json_encode(['error' => 'Invalid HF_TOKEN']);
    exit;
}
error_log("HF_TOKEN loaded: " . substr($HF_TOKEN, 0, 10) . "... (valid)");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST required']);
    exit;
}

$prompt = trim($_POST['prompt'] ?? '');
$pages = min(22, max(1, (int)($_POST['pages'] ?? 1)));
if (empty($prompt)) {
    echo json_encode(['error' => 'Prompt required']);
    exit;
}

error_log("Request: prompt='$prompt', pages=$pages");

$model = 'stabilityai/stable-diffusion-xl-base-1.0';
$api_url = 'https://router.huggingface.co/hf-inference/models/' . $model;

error_log("Using API URL: $api_url");
error_log("Using model: $model");

$images = [];
$tmp_dir = '/tmp';
$pdf_name = 'coloring-book-' . uniqid() . '.pdf';
$pdf_path = "$tmp_dir/$pdf_name";

for ($i = 1; $i <= $pages; $i++) {
    $full_prompt = "$prompt, coloring book page $i, lineart, black and white, thick outlines, no shading, high contrast, printable, clean edges, bold lines";

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
        CURLOPT_HEADER => true,
    ]);

    $raw_response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $response_body = substr($raw_response, $header_size);
    curl_close($ch);

    error_log("--- PAGE $i ---");
    error_log("HTTP Code: $http_code");
    error_log("Content-Type: $content_type");
    error_log("Response size: " . strlen($response_body) . " bytes");

    if ($http_code !== 200) {
        $error = json_decode($response_body, true);
        $msg = $error['error'] ?? 'Unknown error';
        error_log("API ERROR: $msg");
        if (in_array($http_code, [401, 403])) {
            error_log("TOKEN or MODEL ACCESS DENIED");
        }
        if ($http_code == 503 || $http_code == 429) {
            error_log("Rate limit / model loading — retrying...");
            sleep(15);
            $i--;
            continue;
        }
        sleep(5);
        continue;
    }

    if (strpos($content_type, 'image/') === false) {
        error_log("NOT AN IMAGE: $content_type");
        continue;
    }

    if (strlen($response_body) < 5000) {
        error_log("IMAGE TOO SMALL: corrupted?");
        continue;
    }

    $img_path = "$tmp_dir/page_$i.png";
    if (file_put_contents($img_path, $response_body)) {
        $images[] = $img_path;
        error_log("IMAGE SAVED: $img_path (" . filesize($img_path) . " bytes)");
    } else {
        error_log("FAILED to save image");
    }

    sleep(3);
}

if (empty($images)) {
    error_log("FAILED: No images generated");
    echo json_encode(['error' => 'No images generated. Check logs.']);
    exit;
}

error_log("SUCCESS: Generated " . count($images) . " images. Creating PDF...");

require_once __DIR__ . '/../vendor/autoload.php';

// === mPDF: Use /tmp for temp files ===
$mpdf_config = [
    'format' => 'A4',
    'margin' => 5,
    'tempDir' => '/tmp/mpdf'
];

$mpdf = new \Mpdf\Mpdf($mpdf_config);

// Ensure temp dir exists
@mkdir('/tmp/mpdf', 0755, true);

foreach ($images as $img) {
    $mpdf->AddPage();
    $mpdf->Image($img, 10, 10, 190, 277);  // Fit to A4
    @unlink($img);
}

$mpdf->Output($pdf_path, 'F');
error_log("PDF created: $pdf_path");

echo json_encode([
    'success' => true,
    'download_url' => "/api/download.php?file=" . urlencode(basename($pdf_path)),
    'pages' => count($images)
]);
exit;