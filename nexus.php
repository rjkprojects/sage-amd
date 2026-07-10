<?php
require_once 'handshake.php';
require_once 'tools.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'academic_lead') {
    die(json_encode(['error' => 'Unauthorized']));
}

// ========================================
// INPUT
// ========================================

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

$message = $input['message'] ?? $_POST['message'] ?? '';
$encoded = $input['encoded'] ?? $_POST['encoded'] ?? false;

if ($encoded === 'true' || $encoded === true) {
    $message = base64_decode($message);
}

$context = $input['context'] ??
    (isset($_POST['context'])
        ? json_decode(($encoded ? base64_decode($_POST['context']) : $_POST['context']), true)
        : []);

if (empty($message)) {
    die(json_encode(['error' => 'No message provided']));
}

// ========================================
// LOG USER MESSAGE
// ========================================

$stmt = $pdo->prepare("
    INSERT INTO chat_logs
    (user_id, role, message_type, message_text)
    VALUES (?, ?, ?, ?)
");

$stmt->execute([
    $_SESSION['user_id'] ?? null,
    $_SESSION['role'],
    'user',
    $message
]);

// ========================================
// SESSION HISTORY
// ========================================

if (!isset($_SESSION['sage_chat_history'])) {
    $_SESSION['sage_chat_history'] = [];
}

$_SESSION['sage_chat_history'][] = [
    'role' => 'user',
    'content' => $message
];

// ========================================
// BUILD CONTEXT
// ========================================

$currentDateTime = date('Y-m-d H:i:s');

$contextualNote =
    "[SYSTEM CONTEXT]\n" .
    "Current DateTime: {$currentDateTime} Asia/Jakarta\n" .
    "Location: Indonesia";

$messages = [
    [
        'role' => 'system',
        'content' => $sage_system_prompt
    ],
    [
        'role' => 'user',
        'content' => $contextualNote
    ]
];

foreach ($context as $msg) {

    $messages[] = [
        'role' => $msg['role'],
        'content' => $msg['content']
    ];
}

$messages[] = [
    'role' => 'user',
    'content' => $message
];

// ========================================
// FIREWORKS INFERENCE
// ========================================

header('Content-Type: application/json');

function callFireworks($messages) {

    $payload = [
        'model'             => FIREWORKS_MODEL,
        'max_tokens'        => 131072,
        'top_k'             => 40,
        'presence_penalty'  => 0,
        'frequency_penalty' => 0,
        'messages'          => $messages
    ];

    $ch = curl_init(FIREWORKS_API_URL);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Authorization: Bearer ' . FIREWORKS_API_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $res = curl_exec($ch);

    curl_close($ch);

    $data = json_decode($res, true);

    return $data['choices'][0]['message']['content']
        ?? '';
}

// ========================================
// TOOL LOOP
// ========================================

$maxTurns = 3;
$currentTurn = 0;
$finalReply = '';

while ($currentTurn < $maxTurns) {

    $reply = callFireworks($messages);

    if (preg_match(
        '/<call\s+name="([^"]+)">(.*?)<\/call>/is',
        $reply,
        $match
    )) {

        $toolName = $match[1];
        $toolArgs = trim(strip_tags($match[2]));

        $toolFunction = 'tool_' . $toolName;

        if (function_exists($toolFunction)) {

            $result = $toolFunction($toolArgs);

            $messages[] = [
                'role' => 'assistant',
                'content' => $reply
            ];

            $messages[] = [
                'role' => 'user',
                'content' =>
                    "[TOOL RESULT: $toolName]\n" .
                    $result .
                    "\n[END RESULT]"
            ];

            $currentTurn++;

        } else {

            $finalReply =
                $reply .
                "\n\nTool not found: $toolName";

            break;
        }

    } else {

        $finalReply = $reply;
        break;
    }
}

// ========================================
// SAVE ASSISTANT RESPONSE
// ========================================

$stmt = $pdo->prepare("
    INSERT INTO chat_logs
    (user_id, role, message_type, message_text)
    VALUES (?, ?, ?, ?)
");

$stmt->execute([
    $_SESSION['user_id'] ?? null,
    $_SESSION['role'],
    'assistant',
    $finalReply
]);

$_SESSION['sage_chat_history'][] = [
    'role' => 'assistant',
    'content' => $finalReply
];

if (count($_SESSION['sage_chat_history']) > 20) {

    $_SESSION['sage_chat_history'] =
        array_slice(
            $_SESSION['sage_chat_history'],
            -20
        );
}

echo json_encode([
    'response' => $finalReply
]);

exit();