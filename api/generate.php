<?php
header('Content-Type: application/json');

// === Use getenv() — more reliable on Vercel ===
$HF_TOKEN = getenv('HF_TOKEN') ?: '';

// === Debug: Log token status (remove later) ===
error_log("HF_TOKEN: " . ($HF_TOKEN ? 'LOADED (' . substr($HF_TOKEN, 0, 10) . '...)' : 'MISSING'));

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

// === Hugging Face API Setup ===
$model = 'MrHup/coloring-book'; // Great for coloring books
$api_url = "https://router.huggingface.co/hf-inference/models/$model";

$images = [];
$tmp_dir = sys_get_temp_dir();
$pdf_name = 'coloring-book-' . uniqid() . '.pdf';
$pdf_path = "$tmp_dir/$pdf_name";

// === Generate Images ===
for ($i = 1; $i <= $pages; $i++) {
    $full_prompt = "$prompt, coloring book page $i, black and white line art, thick outlines, no shading, printable, high contrast";

    $ch = curl_init($api_url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['inputs' => $full_prompt]),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $HF_TOKEN",
            "Content-Type: application/json"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'Vercel-PHP-Coloring-Book/1.0'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // === DEBUG: Log response ===
    error_log("Page $i | HTTP: $http_code | Response: " . substr($response, 0, 300));

    if ($http_code !== 200) {
        error_log("HF API failed (HTTP $http_code) for page $i");
        sleep(3);
        continue;
    }

    $img_data = json_decode($response, true);

    // === Fix: HF returns array [ { "generated_image": "data:..." } ] ===
    if (is_array($img_data) && isset($img_data[0]['generated_image'])) {
        $img_b64 = $img_data[0]['generated_image'];
        $img_bin = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $img_b64));

        if ($img_bin === false) {
            error_log("Base64 decode failed for page $i");
            continue;
        }

        $img_file = "$tmp_dir/page_$i.png";
        if (file_put_contents($img_file, $img_bin)) {
            $images[] = $img_file;
            error_log("Saved image: $img_file");
        } else {
            error_log("Failed to save image: $img_file");
        }
    } else {
        error_log("Invalid HF response format for page $i: " . substr($response, 0, 200));
    }

    sleep(4); // Respect rate limits (free tier)
}

// === Check if any images were generated ===
if (empty($images)) {
    echo json_encode(['error' => 'No images generated. Try a simpler prompt or wait a minute.']);
    exit;
}

// === Generate PDF with mPDF ===
require_once __DIR__ . '/../vendor/autoload.php';

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 5,
        'margin_right' => 5,
        'margin_top' => 5,
        'margin_bottom' => 5
    ]);

    foreach ($images as $img) {
        $mpdf->AddPage();
        $mpdf->Image($img, 0, 0, 200, 277, 'png', '', true, false); // Full A4
        @unlink($img); // Clean up
    }

    $mpdf->Output($pdf_path, 'F');
} catch (Exception $e) {
    error_log("mPDF Error: " . $e->getMessage());
    echo json_encode(['error' => 'PDF generation failed']);
    exit;
}

// === Return Download URL ===
$download_url = "/api/download.php?file=" . urlencode(basename($pdf_path));
echo json_encode([
    'success' => true,
    'download_url' => $download_url,
    'pages' => count($images)
]);

// === Optional: Clean up PDF after 10 minutes ===
register_shutdown_function(function () use ($pdf_path) {
    if (file_exists($pdf_path)) {
        sleep(600); // 10 minutes
        @unlink($pdf_path);
    }
});
?>