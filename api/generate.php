<?php
header('Content-Type: application/json');
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

// === Hugging Face Setup ===
$HF_TOKEN = $_ENV['HF_TOKEN'] ?? ''; // Set in Vercel Dashboard
if (!$HF_TOKEN) {
    echo json_encode(['error' => 'HF_TOKEN not set']);
    exit;
}

$model = 'MrHup/coloring-book';
$api_url = "https://api-inference.huggingface.co/models/$model";

$images = [];
$tmp_dir = sys_get_temp_dir();
$pdf_name = 'coloring-book-' . uniqid() . '.pdf';
$pdf_path = "$tmp_dir/$pdf_name";

// Generate images
for ($i = 1; $i <= $pages; $i++) {
    $full_prompt = "$prompt, coloring book page, black and white line art, thick outlines, printable, no shading";

    $ch = curl_init($api_url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['inputs' => $full_prompt]),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $HF_TOKEN",
            "Content-Type: application/json"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        sleep(3);
        continue;
    }

    $img_data = json_decode($response, true);
    if (isset($img_data[0]['generated_image'])) {
        $img_b64 = $img_data[0]['generated_image'];
        $img_bin = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $img_b64));
        $img_file = "$tmp_dir/page_$i.png";
        file_put_contents($img_file, $img_bin);
        $images[] = $img_file;
    }
    sleep(4); // Avoid rate limits
}

if (empty($images)) {
    echo json_encode(['error' => 'No images generated']);
    exit;
}

// === Generate PDF with mPDF ===
require_once __DIR__ . '/../vendor/autoload.php';
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 10,
    'margin_right' => 10,
    'margin_top' => 10,
    'margin_bottom' => 10
]);

foreach ($images as $img) {
    $mpdf->AddPage();
    $mpdf->Image($img, 0, 0, 190, 277, 'png', '', true, false);
    @unlink($img);
}

$mpdf->Output($pdf_path, 'F');

echo json_encode([
    'success' => true,
    'download_url' => "/api/download.php?file=" . urlencode(basename($pdf_path))
]);