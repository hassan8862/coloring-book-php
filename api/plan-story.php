<?php
// api/plan-story.php → SMART: decides 1–32 pages automatically

$prompt = trim($_POST['prompt'] ?? '');
if (empty($prompt)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Prompt required']));
}

header('Content-Type: application/json');

// First, ask LLM how many pages this story deserves (1–32)
$decide_prompt = "You are a children's coloring book expert.
Based on this idea, decide the ideal number of pages (1 to 32 max).
Short simple ideas = 1–5 pages
Medium stories = 6–18 pages
Full adventure/epic stories = 19–32 pages
If user explicitly says '32-page', 'full book', 'complete storybook', force 32.

Idea: \"$prompt\"

Reply with ONLY a number from 1 to 32. Nothing else.";

$pages = 8; // default fallback

$api_key = getenv('GROQ_KEY') ?: '';
if ($api_key) {
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $api_key",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'llama-3.1-70b-versatile',
            'messages' => [['role' => 'user', 'content' => $decide_prompt]],
            'temperature' => 0.3,
            'max_tokens' => 10
        ])
    ]);
    $res = json_decode(curl_exec($ch), true);
    $text = trim($res['choices'][0]['message']['content'] ?? '');
    if (preg_match('/\d+/', $text, $m)) {
        $pages = max(1, min(32, (int)$m[0]));
    }
}

// Force 32 if user clearly wants it
if (preg_match('/\b(32|thirty.?two|full.?book|complete.?storybook|whole.?book)\b/i', $prompt)) {
    $pages = 32;
}

$story_prompt = "Create a beautiful children's coloring book story with exactly $pages pages.
Break \"$prompt\" into exactly $pages sequential, unique scenes suitable for coloring pages.
Each scene must be different and flow naturally.
Return ONLY a numbered list from 1 to $pages with 8–18 word descriptions per page.
No intro, no title, no extra text.

Example:
1. A happy unicorn waking up in a magical meadow
2. The unicorn discovers a glowing rainbow bridge
...";

$scenes = [];
if ($api_key) {
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
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
            $scenes[] = trim($m[1]);
        }
    }
}

// Fallback: generate simple scenes if no LLM
if (empty($scenes)) {
    for ($i = 1; $i <= $pages; $i++) {
        $scenes[] = "Scene $i: $prompt";
    }
}

echo json_encode([
    'total_pages' => $pages,
    'scenes' => array_slice($scenes, 0, $pages)
]);