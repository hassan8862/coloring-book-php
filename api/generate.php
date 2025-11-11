<?php
header('Content-Type: application/json');

// === Use getenv() — reliable on Vercel ===
$HF_TOKEN = getenv('HF_TOKEN') ?: '';

if (!$HF_TOKEN) {
    error_log("ERROR: HF_TOKEN not set");
    echo json_encode(['error' => 'HF_TOKEN not set']);
    exit;
}

// === Debug: Log token loaded ===
error_log("HF_TOKEN loaded: " . substr($HF_TOKEN, 0, 10) . '...');

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

// === WORKING MODEL: dreamlike-photoreal-2.0 (fully deployed on HF API) ===
$model = 'dreamlike-art/dreamlike-photoreal-2.0';
$api_url = "https://api-inference.huggingface.co/models/$model";

$images = [];
$tmp_dir = sys_get_temp_dir();
$pdf_name = 'coloring-book-' . uniqid() . '.pdf';
$pdf_path = "$tmp_dir/$pdf_name";

// === Generate Images ===
for ($i = 1; $i <= $pages; $i++) {
    // === PROMPT: Optimized for clean line art ===
    $full_prompt = "$prompt coloring book page $i, lineart, black and white, no color, thick black outlines, high contrast, clean lines, printable, intricate details, no shading, no background";

    $ch = curl_init($api_url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['inputs' => $full_prompt]),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $HF_TOKEN",
            "Content-Type: application/json"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_USERAGENT => 'ColoringBook-PHP/1.0'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // === DEBUG LOGS ===
    error_log("Page $i | HTTP: $http_code | Response preview: " . substr($response, 0, 300));

    if ($http_code !== 200) {
        error_log("HF API failed (HTTP $http_code) for page $i");
        sleep(5);
        continue;
    }

    $img_data = json_decode($response, true);

    // === PARSE IMAGE (supports 'generated_image' or 'image') ===
    $img_b64 = null;
    if (is_array($img_data)) {
        if (isset($img_data[0]['generated_image'])) {
            $img_b64 = $img_data[0]['generated_image'];
        } elseif (isset($img_data[0]['image'])) {
            $img_b64 = $img_data[0]['image'];
        }
    }

    if (!$img_b64) {
        error_log("No image data for page $i: " . substr($response, 0, 200));
        continue;
    }

    $img_bin = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $img_b64));

    if ($img_bin === false) {
        error_log("Base64 decode failed for page $i");
        continue;
    }

    $img_file = "$tmp_dir/page_$i.png";
    if (file_put_contents($img_file, $img_bin)) {
        $images[] = $img_file;
        error_log("Saved image: $img_file | Size: " . number_format(strlen($img_bin)) . " bytes");
    } else {
        error_log("Failed to save: $img_file");
    }

    sleep(5); // Respect free tier rate limits
}

// === NO IMAGES? ===
if (empty($images)) {
    echo json_encode(['error' => 'No images generated. Try a simpler prompt or wait 1 minute.']);
    exit;
}

// === GENERATE PDF with mPDF ===
require_once __DIR__ . '/../vendor/autoload.php';

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 5,
        'margin_right' => 5,
        'margin_top' => 5,
        'margin_bottom' => 5,
        'tempDir' => $tmp_dir
    ]);

    foreach ($images as $img) {
        $mpdf->AddPage();
        $mpdf->Image($img, 0, 0, 200, 277, 'png', '', true, false);
        @unlink($img); // Clean up image
    }

    $mpdf->Output($pdf_path, 'F');
    error_log("PDF created: $pdf_path");
} catch (Exception $e) {
    error_log("mPDF Error: " . $e->getMessage());
    echo json_encode(['error' => 'PDF generation failed']);
    exit;
}

// === RETURN DOWNLOAD LINK ===
$download_url = "/api/download.php?file=" . urlencode(basename($pdf_path));
echo json_encode([
    'success' => true,
    'download_url' => $download_url,
    'pages' => count($images),
    'prompt' => $prompt
]);

// === Auto-delete PDF after 10 minutes ===
register_shutdown_function(function () use ($pdf_path) {
    if (file_exists($pdf_path)) {
        sleep(600);
        @unlink($pdf_path);
    }
});
?>