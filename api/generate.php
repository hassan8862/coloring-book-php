<?php
header('Content-Type: application/json');

// === Use Vercel env var ===
$HF_TOKEN = getenv('HF_TOKEN') ?: '';
if (!$HF_TOKEN) {
    echo json_encode(['error' => 'HF_TOKEN not set']);
    exit;
} ?>
<p> mytoken:  <?php echo $HF_TOKEN ?></p>
<?php
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

// === EXACT SAME AS YOUR WORKING CURL ===
$model = 'stabilityai/stable-diffusion-xl-base-1.0';
$api_url = 'https://router.huggingface.co/hf-inference/models/' . $model;

$images = [];
$tmp_dir = sys_get_temp_dir();
$pdf_name = 'coloring-book-' . uniqid() . '.pdf';
$pdf_path = "$tmp_dir/$pdf_name";

for ($i = 1; $i <= $pages; $i++) {
    $full_prompt = "$prompt, coloring book page $i, lineart, black and white, thick outlines, no shading, high contrast, printable";

    $ch = curl_init($api_url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['inputs' => $full_prompt]),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $HF_TOKEN",
            "Content-Type: application/json"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("Page $i | HTTP: $http_code");

    if ($http_code !== 200) {
        error_log("API Error: $response");
        sleep(5);
        continue;
    }

    $img_data = json_decode($response, true);
    $img_b64 = $img_data[0]['generated_image'] ?? null;

    if (!$img_b64) {
        error_log("No image for page $i: " . substr($response, 0, 200));
        continue;
    }

    $img_bin = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $img_b64));
    if ($img_bin && file_put_contents("$tmp_dir/page_$i.png", $img_bin)) {
        $images[] = "$tmp_dir/page_$i.png";
        error_log("Saved page $i");
    }

    sleep(3);
}

if (empty($images)) {
    echo json_encode(['error' => 'No images generated. Try again in 1 minute.']);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
$mpdf = new \Mpdf\Mpdf(['format' => 'A4', 'margin' => 5]);
foreach ($images as $img) {
    $mpdf->AddPage();
    $mpdf->Image($img, 0, 0, 200, 277);
    @unlink($img);
}
$mpdf->Output($pdf_path, 'F');

echo json_encode([
    'success' => true,
    'download_url' => "/api/download.php?file=" . urlencode(basename($pdf_path)),
    'pages' => count($images)
]);
?>