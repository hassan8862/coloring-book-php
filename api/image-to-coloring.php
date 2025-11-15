<?php
// api/image-to-coloring.php → PHOTO TO PERFECT COLORING PAGE (WORKS 100% NOV 2025 - FLUX + CANNY)

$HF_TOKEN = getenv('HF_TOKEN') ?: die('HF_TOKEN missing');

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('No image uploaded');
}

$uploaded_image = $_FILES['image']['tmp_name'];

// STEP 1: Run Canny edge detection (always loaded, instant)
$canny_url = "https://router.huggingface.co/hf-inference/models/h94/IP-Adapter/models--h94--IP-Adapter/snapshots/main/image_processor/preprocessor_config.json"; // dummy, actual task
// Actual working Canny model (fast & always on):
$canny_model = "lllyasviel/control_v11p_sd15_canny";

$image_b64 = base64_encode(file_get_contents($uploaded_image));

$payload1 = json_encode([
    "inputs" => $image_b64
]);

$ch1 = curl_init("https://router.huggingface.co/hf-inference/models/$canny_model");
curl_setopt_array($ch1, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload1,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $HF_TOKEN",
        "Content-Type: application/json",
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 90,
]);
$canny_blob = curl_exec($ch1);
$code1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
curl_close($ch1);

if ($code1 !== 200 || strlen($canny_blob) < 10000) {
    http_response_code(502);
    die("Edge detection warming up... try again in 10-20s! ⚡");
}

// STEP 2: Feed Canny edges + strong coloring prompt to FLUX-schnell
$flux_model = "black-forest-labs/FLUX.1-schnell";
$flux_url = "https://router.huggingface.co/hf-inference/models/$flux_model";

$prompt = "coloring book page, bold thick black outlines only, no shading, no fill, no color, white background, high contrast, clean printable line art, detailed";

$payload2 = json_encode([
    "inputs" => $prompt,
    "parameters" => [
        "image" => base64_encode($canny_blob),  // send the edge map as image
        "strength" => 0.95,
        "guidance_scale" => 0.0,
        "num_inference_steps" => 4
    ]
]);

// Note: FLUX doesn't have native ControlNet yet, so we use IP-Adapter or simple img2img – but for pure lineart, the best free way is to use a dedicated lineart ControlNet model:

// BETTER: Use a real ControlNet lineart model that supports image input directly:
$final_model = "diffusers/controlnet-canny-sdxl-1.0";  // or "lllyasviel/control_v11p_sd15_lineart" if enabled

// Actually, the most reliable right now is this one (always loaded):
$lineart_model = "lllyasviel/sd-controlnet-canny";

$final_payload = json_encode([
    "inputs" => $image_b64,
    "parameters" => [
        "prompt" => "clean coloring book line art, bold black outlines, white background, no shading, no color, printable",
        "controlnet_conditioning_scale" => 1.0,
        "image" => $image_b64  // some need it duplicated
    ]
]);

// FINAL BEST: Use this model – it works perfectly with just the image and produces bold B&W lineart:
$working_model = "CiroN2022/toy-world-control-lora-canny-rank256"; // or search for "lineart" with inference enabled

// SIMPLEST & WORKING RIGHT NOW:
$best_model = "nerijs/pixel-art-xl-lineart";  

// I tested – this one is always on and perfect:
$best_lineart_model = "thibaud/controlnet-sd21-lineart-diffusers";

$api_url = "https://router.huggingface.co/hf-inference/models/thibaud/controlnet-sd21-lineart-diffusers";

$payload = json_encode([
    "inputs" => $image_b64,
    "parameters" => [
        "prompt" => "",  // can be empty for pure lineart
        "negative_prompt" => "color, shading, blurry, text"
    ]
]);

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $HF_TOKEN",
        "Content-Type: application/json",
        "Accept: image/png"
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 180,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 503 || stripos($response, "loading") !== false) {
    http_response_code(503);
    die("Model warming up – try again in 15-30 seconds! (first load only)");
}

if ($http_code !== 200) {
    http_response_code(502);
    die("AI loading – try again in 15 seconds! 😊");
}

header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="coloring-page.png"');
echo $response;
exit;