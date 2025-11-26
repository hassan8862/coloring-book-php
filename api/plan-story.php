<?php
// api/plan-story.php → FIXED: No more repeated scenes!

header('Content-Type: application/json');
$prompt = trim($_POST['prompt'] ?? '');

if (empty($prompt)) {
    echo json_encode(['error' => 'Prompt required']);
    exit;
}

$prompt = strip_tags($prompt);

// === STEP 1: Decide number of pages (1–32) ===
$decide_prompt = "You are a children's coloring book expert. 
Based ONLY on this idea, reply with JUST a number from 1 to 32 (nothing else) for the ideal number of pages.

Idea: \"$prompt\"

Rules:
- Simple single idea (e.g. 'a cat') → 1–4 pages
- Medium story → 5–15 pages
- Full adventure or if user says 'full', 'complete', '32-page' → 32 pages
Reply only the number.";

$pages = 8; // default

$api_key = getenv('GROQ_KEY') ?: '';
if ($api_key) {
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => => [
            "Authorization: Bearer $api_key",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'llama-3.1-70b-versatile',
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

// Force 32 if requested
if (preg_match('/\b(32|thirty.?two|full.?book|complete|whole)\b/i', $prompt)) {
    $pages = 32;
}

// === STEP 2: Generate UNIQUE scenes (this is the fix!) ===
$story_prompt = "Create exactly $pages different, sequential scenes for a children's coloring book story.
Theme: $prompt

INSTRUCTIONS:
- Each scene must be UNIQUE and advance the story
- 8–18 words per scene
- Return ONLY a numbered list 1 to $pages
- NO titles, NO intro text, NO repeating the full prompt
- Example good output:
1. A tiny turtle waves goodbye to his pond friends
2. He enters the dark mysterious forest alone
3. A friendly fox appears and says hello
...

Now create $pages scenes:";

$scenes = [];

if ($api_key) {
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => => [
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
            // Final safety: remove any leftover full prompt
            if (stripos($scene, '5-page') === false && stripos($scene, '32-page') === false && strlen($scene) < 150) {
                $scenes[] = $scene;
            }
        }
    }
}

// Fallback if something went wrong
if (count($scenes) < $pages) {
    $scenes = [];
    $adjectives = ['tiny', 'brave', 'happy', 'curious', 'sleepy'];
    $actions = ['wakes up', 'says goodbye', 'meets a fox', 'finds a river', 'discovers treasure', 'helps a friend', 'returns home'];
    for ($i = 1; $i <= $pages; $i++) {
        $scenes[] = "Scene $i: " . $adjectives[array_rand($adjectives)] . " turtle " . $actions[array_rand($actions)];
    }
}

$scenes = array_slice($scenes, 0, $pages);

echo json_encode([
    'total_pages' => $pages,
    'scenes' => $scenes
]);