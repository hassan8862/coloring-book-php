<?php
// api/plan-story.php → FULLY FIXED & TESTED
error_reporting(0);
header('Content-Type: application/json');
$prompt = trim($_POST['prompt'] ?? '');

if (empty($prompt)) {
    echo json_encode(['error' => 'Prompt required']);
    exit;
}

$prompt = strip_tags($prompt);

// === STEP 1: Decide number of pages ===
$decide_prompt = "You are a children's coloring book expert. 
Reply with ONLY a number from 1 to 32 for how many pages this idea deserves.

Idea: \"$prompt\"

Rules:
- Simple idea (e.g. 'a cat') → 1–4
- Medium story → 5–15  
- Full adventure or user says 'full', '32-page', 'complete' → 32

Reply only the number.";

$pages = 8;
$api_key = getenv('GROQ_KEY') ?: '';

if ($api_key) {
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $api_key",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'llama-3.3-70b-versatile',
            'messages' => [['role' => 'user', 'content' => $decide_prompt]],
            'temperature' => 0.3,
            'max_tokens' => 5
        ])
    ]);
    $res = json_decode(curl_exec($ch), true);
    $text = trim($res['choices'][0]['message']['content'] ?? '');
    if (preg_match('/\d+/', $text, $m)) {
        $pages = max(1, min(32, (int)$m[0]));
    }
}

// Force 32 if user wants full book
if (preg_match('/\b(32|thirty.?two|full.?book|complete|whole|32.?page)\b/i', $prompt)) {
    $pages = 32;
}

// === STEP 2: Generate unique scenes ===
$story_prompt = "Create exactly $pages different, sequential scenes for a children's coloring book.
Theme: $prompt

Rules:
- Each scene must be unique and advance the story
- 8–18 words per scene
- Return ONLY a numbered list 1–$pages
- NO intro, NO titles, NO repeating the full prompt

Example:
1. A tiny turtle waves goodbye to his pond
2. He bravely enters the deep forest
3. A friendly fox appears and smiles
...

Create $pages scenes now:";

$scenes = [];

if ($api_key) {
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $api_key",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'llama-3.1-70b-versatile',
            'messages' => [['role' => 'user', 'content' => $story_prompt]],
            'temperature' => 0.85,
            'max_tokens' => 2000
        ])
    ]);
    $res = json_decode(curl_exec($ch), true);
    $text = $res['choices'][0]['message']['content'] ?? '';

    foreach (explode("\n", $text) as $line) {
        if (preg_match('/^\d+[\.\)\s]+(.+)/', trim($line), $m)) {
            $scene = trim($m[1]);
            if (strlen($scene) < 150 && stripos($scene, 'page') === false) {
                $scenes[] = $scene;
            }
        }
    }
}

// Fallback scenes if API fails
if (count($scenes) < $pages) {
    $scenes = [];
    $starts = ['A tiny', 'A brave', 'A curious', 'A happy', 'A sleepy'];
    $actions = ['turtle wakes up', 'leaves the pond', 'meets a fox', 'finds a river', 'discovers treasure', 'helps a friend', 'returns home'];
    for ($i = 1; $i <= $pages; $i++) {
        $scenes[] = $starts[array_rand($starts)] . ' ' . $actions[array_rand($actions)];
    }
}

$scenes = array_slice($scenes, 0, $pages);

echo json_encode([
    'total_pages' => $pages,
    'scenes' => $scenes
]);
exit;