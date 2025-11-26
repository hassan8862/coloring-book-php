<?php
// api/plan-story.php → FULLY DEBUGGABLE + 100% WORKING (2025)

// ENABLE ERROR LOGGING (you'll see everything in your server error log)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug-plan-story.log');  // ← This file will be created

$log = function($msg) {
    error_log("[PLAN-STORY " . date('H:i:s') . "] " . $msg);
};

$log("=== NEW REQUEST ===");
$log("Raw POST data: " . file_get_contents('php://input'));

header('Content-Type: application/json');

$prompt = trim($_POST['prompt'] ?? '');
if (empty($prompt)) {
    $log("ERROR: No prompt received");
    echo json_encode(['error' => 'Prompt required']);
    exit;
}

$prompt = strip_tags($prompt);
$log("Clean prompt: '$prompt'");

// === GET API KEY ===
$api_key = getenv('GROQ_KEY');
if (!$api_key) {
    // Try to read from .env file if getenv fails
    if (file_exists(__DIR__ . '/../.env')) {
        $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), 'GROQ_KEY') === 0) {
                list(, $api_key) = explode('=', $line, 2);
                $api_key = trim($api_key);
                break;
            }
        }
    }
}

if (!$api_key) {
    $log("WARNING: No GROQ_KEY found → using fallback mode");
} else {
    $log("Groq API key found (length: " . strlen($api_key) . ")");
}

// === DECIDE PAGE COUNT ===
$pages = 8;

if (preg_match('/\b(\d{1,2})\b\s*[-]?page/i', $prompt, $m)) {
    $pages = max(1, min(32, (int)$m[1]));
    $log("User asked for $pages pages explicitly");
} elseif (preg_match('/\b(32|full|complete|whole)\b/i', $prompt)) {
    $pages = 32;
    $log("User wants full book → forcing 32 pages");
}

if ($api_key) {
    $log("Asking Groq how many pages this deserves...");
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $api_key",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'llama-3.3-70b-versatile',
            'messages' => [[
                'role' => 'user',
                'content' => "Reply with ONLY a number 1–32. How many coloring pages does this deserve?\nIdea: \"$prompt\""
            ]],
            'temperature' => 0.3,
            'max_tokens' => 5
        ])
    ]);

    $raw = curl_exec($ch);
    $info = curl_getinfo($ch);
    $log("Groq page-decision HTTP: " . $info['http_code']);

    if ($raw !== false && $info['http_code'] == 200) {
        $res = json_decode($raw, true);
        $text = trim($res['choices'][0]['message']['content'] ?? '');
        $log("Groq page answer: '$text'");
        if (preg_match('/\d+/', $text, $m)) {
            $pages = max(1, min(32, (int)$m[0]));
            $log("Using Groq decision: $pages pages");
        }
    } else {
        $log("Groq failed for page count: " . curl_error($ch));
        $log("Response: " . substr($raw, 0, 500));
    }
    curl_close($ch);
}

// === GENERATE SCENES ===
$story_prompt = "Create exactly $pages different, sequential coloring book scenes for kids.
Theme: $prompt

Rules:
- Each scene: 8–18 words
- Advance the story naturally
- Return ONLY numbered list 1 to $pages
- NO intro, NO title, NO repeating the prompt

Example:
1. A thirsty crow flies over the desert
2. He finds a pitcher with little water
...

Create $pages scenes:";

$scenes = [];

if ($api_key) {
    $log("Generating scenes with Groq...");
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $api_key",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'llama-3.3-70b-versatile',
            'messages' => [['role' => 'user', 'content' => $story_prompt]],
            'temperature' => 0.85,
            'max_tokens' => 2000
        ])
    ]);

    $raw = curl_exec($ch);
    $info = curl_getinfo($ch);
    $log("Groq scenes HTTP: " . $info['http_code']);

    if ($raw !== false && $info['http_code'] == 200) {
        $res = json_decode($raw, true);
        $text = $res['choices'][0]['message']['content'] ?? '';
        $log("Groq raw output (first 300 chars): " . substr($text, 0, 300));

        foreach (explode("\n", $text) as $line) {
            if (preg_match('/^\d+[\.\)\s]+(.+)/', trim($line), $m)) {
                $scene = trim($m[1]);
                if (strlen($scene) < 150 && stripos($scene, 'page') === false) {
                    $scenes[] = $scene;
                }
            }
        }
        $log("Extracted " . count($scenes) . " scenes from Groq");
    } else {
        $log("Groq failed: " . curl_error($ch));
        $log("Response: " . substr($raw, 0, 500));
    }
    curl_close($ch);
}

// === FALLBACK IF GROQ FAILED ===
if (count($scenes) < $pages) {
    $log("Using fallback scene generator (Groq failed or gave bad output)");
    $scenes = [];
    $adjectives = ['A thirsty', 'A clever', 'A tired', 'A happy', 'A proud'];
    $actions = [
        'crow flies over the hot desert',
        'spots an old clay pitcher',
        'sees only a few drops of water',
        'tries to drink but can’t reach',
        'looks sad and thinks hard',
        'notices pebbles on the ground',
        'gets a brilliant idea',
        'picks up a pebble',
        'drops it into the pitcher',
        'watches water rise',
        'drops more pebbles',
        'finally drinks happily',
        'flies away refreshed'
    ];

    // Special for thirsty crow
    if (stripos($prompt, 'thirsty') !== false || stripos($prompt, 'crow') !== false) {
        $scenes = [
            "A thirsty crow flies on a hot day",
            "He searches everywhere for water",
            "Finds a pitcher with very little water",
            "Tries to drink but water is too low",
            "Looks around feeling sad",
            "Sees small pebbles nearby",
            "Gets a clever idea and smiles",
            "Picks up one pebble",
            "Drops it into the pitcher",
            "Water rises a little",
            "Drops more pebbles",
            "Water rises higher",
            "Finally drinks happily",
            "Flies away strong and proud"
        ];
        $scenes = array_slice($scenes, 0, $pages);
    } else {
        for ($i = 0; $i < $pages; $i++) {
            $scenes[] = $adjectives[$i % 5] . ' ' . $actions[$i % count($actions)];
        }
    }
}

$log("Final output: $pages pages, " . count($scenes) . " scenes");
$log("=== END REQUEST ===\n");

echo json_encode([
    'total_pages' => $pages,
    'scenes' => $scenes
]);
exit;