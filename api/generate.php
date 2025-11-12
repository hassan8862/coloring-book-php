<?php
header('Content-Type: application/json');

// Use Vercel env var
$HF_TOKEN = getenv('HF_TOKEN') ?: '';
if (!$HF_TOKEN) {
    echo json_encode(['error' => 'HF_TOKEN not set']);
    exit;
}

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

// Correct API URL
$model = 'stabilityai/stable-diffusion-xl-base-1.0';
$api_url = 'https://router.huggingface.co/hf-inference/models/' . $model;

$images = [];
$tmp_dir = sys_get_temp_dir();
$pdf_name = 'coloring-book-' . uniqid() . '.pdf';
$pdf_path = "$tmp_dir/$pdf_name";

for ($i = 1; $i <= $pages; $i++) {
    $full_prompt = "$prompt, coloring book page $i, lineart, black and white, thick outlines, no shading, high contrast, printable";

    // Payload with parameters for better control
    $payload = [
        'inputs' => $full_prompt,
        'parameters' => [
            'width' => 1024,
            'height' => 1024,
            'num_inference_steps' => 30,  // Adjust for quality/speed
            'guidance_scale' => 7.5
        ],
        'options' => ['wait_for_model' => true]  // Wait if model is loading
    ];

    $ch = curl_init($api_url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $HF_TOKEN",
            "Content-Type: application/json",
            "Accept: image/png"  // Ensure binary response
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("Page $i | HTTP: $http_code");

    if ($http_code !== 200) {
        $error_data = json_decode($response, true);
        $error_msg = $error_data['error'] ?? 'Unknown error';
        error_log("API Error for page $i: $error_msg | Response: " . substr($response, 0, 200));
        if ($http_code === 503 || $http_code === 429) {  // Retry on rate limit or model loading
            sleep(10);
            $i--;  // Retry this page
            continue;
        }
        sleep(5);
        continue;
    }

    // Response is binary image data
    $img_bin = $response;
    if (strlen($img_bin) > 0 && file_put_contents("$tmp_dir/page_$i.png", $img_bin)) {
        $images[] = "$tmp_dir/page_$i.png";
        error_log("Saved page $i");
    } else {
        error_log("Empty image data for page $i");
    }

    sleep(3);  // Rate limit buffer
}

if (empty($images)) {
    echo json_encode(['error' => 'No images generated. Check token/URL or try again later.']);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
$mpdf = new \Mpdf\Mpdf(['format' => 'A4', 'margin' => 5]);
foreach ($images as $img) {
    $mpdf->AddPage();
    $mpdf->Image($img, 0, 0, 210, 297);  // Full A4 size in mm (adjust if needed)
    @unlink($img);
}
$mpdf->Output($pdf_path, 'F');

echo json_encode([
    'success' => true,
    'download_url' => "/api/download.php?file=" . urlencode(basename($pdf_path)),
    'pages' => count($images)
]);
?>