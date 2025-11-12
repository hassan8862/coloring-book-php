<?php
$HF_TOKEN = getenv('HF_TOKEN') ?: '';
if (empty($HF_TOKEN)) {
    http_response_code(500);
    exit('HF_TOKEN missing');
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Support both POST (form) and GET (redirect)
$prompt = trim($_POST['prompt'] ?? $_GET['prompt'] ?? '');
$pages = min(22, max(1, (int)($_POST['pages'] ?? $_GET['pages'] ?? 1)));
if (empty($prompt)) {
    http_response_code(400);
    exit('Prompt required');
}

$model = 'stabilityai/stable-diffusion-xl-base-1.0';
$api_url = 'https://router.huggingface.co/hf-inference/models/' . $model;

$images = [];
$tmp_dir = '/tmp';

for ($i = 1; $i <= $pages; $i++) {
    $full_prompt = "$prompt, coloring book page $i, lineart, black and white, thick outlines, no shading, high contrast, printable, bold lines";

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
        error_log("Page $i failed: HTTP $http_code, size " . strlen($body));
        sleep(5);
        continue;
    }

    $img_path = "$tmp_dir/page_$i.png";
    if (file_put_contents($img_path, $body)) {
        $images[] = $img_path;
    }
    sleep(3);
}

if (empty($images)) {
    http_response_code(500);
    exit('No images generated');
}

// === GENERATE PDF ===
require_once __DIR__ . '/../vendor/autoload.php';

$mpdf = new \Mpdf\Mpdf([
    'format' => 'A4',
    'margin' => 5,
    'tempDir' => '/tmp/mpdf',
    'img_dpi' => 96
]);
@mkdir('/tmp/mpdf', 0755, true);

foreach ($images as $img) {
    $mpdf->AddPage();
    $mpdf->Image($img, 10, 10, 190, 277);
    @unlink($img);
}

// === SEND PDF DIRECTLY ===
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="coloring-book.pdf"');
$mpdf->Output('coloring-book.pdf', 'D');
exit;