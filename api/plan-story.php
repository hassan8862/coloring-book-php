<?php
// api/plan-story.php – Uses Grok or any free LLM to split story into 32 scenes

$prompt = trim($_POST['prompt'] ?? '');

if (empty($prompt)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Prompt required']));
}

$user_prompt = "Break this children's coloring book idea into exactly 32 sequential scenes (one per page). 
Make each scene different and tell a complete little story from beginning to end.
Return ONLY a numbered list 1 to 32 with a short 8–15 word description for each page.
Theme: $prompt

Example:
1. A curious fox wakes up in his cozy forest den
2. He discovers a glowing golden acorn on the path
...";

# You can use any free LLM API here. Here using Groq (super fast & free tier)
$api_key = getenv('GROQ_KEY') ?: ''; // Put your Groq key in env
if (empty($api_key)) {
    // Fallback: simple dumb splitter (still works!)
    $scenes = [];
    for ($i = 1; $i <= 32; $i++) {
        $scenes[] = "$i. Scene $i of the story: $prompt";
    }
} else {
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
            'messages' => [['role' => 'user', 'content' => $user_prompt]],
            'temperature' => 0.8,
            'max_tokens' => 1500
        ])
    ]);
    $res = curl_exec($ch);
    $data = json_decode($res, true);
    $text = $data['choices'][0]['message']['content'] ?? '';

    $scenes = [];
    foreach (explode("\n", $text) as $line) {
        if (preg_match('/^\d+[\.\)]?\s*(.+)/', trim($line), $m)) {
            $scenes[] = trim($m[1]);
        }
    }
    while (count($scenes) < 32) {
        $scenes[] = end($scenes); // duplicate last if too few
    }
    $scenes = array_slice($scenes, 0, 32);
}

header('Content-Type: application/json');
echo json_encode(['scenes' => $scenes]);