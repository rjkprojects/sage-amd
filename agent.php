<?php
require_once 'handshake.php';
require_once 'tools.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'coordinator') {

    die(json_encode([
        'error' => 'Unauthorized'
    ]));
}

// ========================================
// INPUT
// ========================================

$input = json_decode(
    file_get_contents('php://input'),
    true
);

$filePath =
    $input['file_path']
    ?? $_POST['file_path']
    ?? '';

$moduleCode =
    $input['module_code']
    ?? $_POST['module_code']
    ?? '';

$docType =
    $input['doc_type']
    ?? $_POST['doc_type']
    ?? '';

if (!$filePath || !$moduleCode) {

    die(json_encode([
        'error' => 'Missing data'
    ]));
}

// ========================================
// MODULE LOOKUP
// ========================================

$stmt = $pdo->prepare("
    SELECT id
    FROM modules
    WHERE module_code = ?
");

$stmt->execute([$moduleCode]);

$mod = $stmt->fetch();

if (!$mod) {

    die(json_encode([
        'error' => "Module $moduleCode not found"
    ]));
}

// ========================================
// AUDIT RECORD
// ========================================

$stmt = $pdo->prepare("
    SELECT id
    FROM audit_results
    WHERE file_path = ?
");

$stmt->execute([$filePath]);

$audit = $stmt->fetch();

if (!$audit) {

    $stmt = $pdo->prepare("
        INSERT INTO audit_results
        (module_code, doc_type, file_path, status)
        VALUES (?, ?, ?, 'pending')
    ");

    $stmt->execute([
        $moduleCode,
        $docType,
        $filePath
    ]);

    $auditId = $pdo->lastInsertId();

} else {

    $auditId = $audit['id'];
}

// ========================================
// SESSION CONTEXT
// ========================================

if (!isset($_SESSION["history_$auditId"])) {

    if (!file_exists($filePath)) {

        die(json_encode([
            'error' => 'File missing'
        ]));
    }

    $fileData = file_get_contents($filePath);

    $_SESSION["history_$auditId"] = [
        [
            'role' => 'user',
            'content' =>
                "Audit this document.\n\n" .
                "Module: $moduleCode\n" .
                "Audit ID: $auditId\n\n" .
                substr($fileData, 0, 20000)
        ]
    ];
}

// ========================================
// PROVIDER FUNCTIONS
// ========================================

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
// BUILD MESSAGES
// ========================================

$systemPrompt = $sage_system_prompt;

// Global audit philosophy appended to all document types
$systemPrompt .= "\n\n" .
    "<SAGE_AUDIT_PHILOSOPHY>\n" .
    "PRIMARY ANCHOR — MODULE LEARNING OUTCOMES (MLOs):\n" .
    "- ALWAYS start by calling get_module_baseline to retrieve the module_learning_outcomes.\n" .
    "- The MLOs are the PRIMARY and MOST IMPORTANT benchmark for all audit assessments.\n" .
    "- Your core question is: does this document broadly address the learning outcomes? A thematic, loose match is sufficient — you are NOT looking for word-for-word alignment.\n" .
    "- module_assessments and module_weeks are SECONDARY REFERENCE CONTEXT ONLY. Use them to understand the broader module structure, but do NOT penalize a document for not matching them precisely.\n\n" .
    "LANGUAGE APPROPRIATENESS:\n" .
    "- Check whether the language used is appropriate for an academic/professional setting.\n" .
    "- Flag if the language is unprofessional, overly casual, ambiguous, or inconsistent in a way that could impact students.\n" .
    "- If the document mixes languages without clear rationale, note it constructively.\n\n" .
    "ASSESSMENT VERDICT:\n" .
    "- The final verdict is EITHER 'ok' OR 'need-review'. Do NOT use any other rating or label.\n" .
    "- 'ok' = the content broadly covers the learning outcomes and is reasonably current. Internally score >= 60.\n" .
    "- 'need-review' = one or more learning outcomes are significantly unaddressed, or content is severely outdated. Internally score < 60.\n" .
    "- You MUST still include a numeric score (0-100) in the save_audit JSON for database record-keeping, but the displayed verdict in your report language should use 'ok' or 'need-review'.\n\n" .
    "REPORT STRUCTURE — follow this order strictly:\n" .
    "1. Module Learning Outcomes: list all MLOs from the baseline.\n" .
    "2. Assessment Summary: a concise paragraph on how well the document covers the MLOs and its currency.\n" .
    "3. Strengths: what the document does well in relation to the MLOs.\n" .
    "4. Room for Improvement: where coverage is weak or content could be updated — use 'Room for Improvement', never 'weaknesses' or 'needs improvement'.\n" .
    "5. Live Update Suggestions: specific, actionable suggestions with reference URLs where applicable.\n\n" .
    "TONE:\n" .
    "- Do NOT roast or embarrass the lecturer. The goal is supportive, constructive review.\n" .
    "- Lecturers have creative freedom week-to-week. Respect pedagogical choices unless they directly fail the learning outcomes.\n" .
    "</SAGE_AUDIT_PHILOSOPHY>";

if (strpos(strtolower($docType), 'lecture') !== false) {

    // Detect week number from filename (e.g. W1_, Week2_, w-3_, etc.)
    $weekNum = null;
    $filename = basename($filePath);
    if (preg_match('/(?:week[-_]?(\d+)|[Ww](\d+))[_\-\s\.]/i', $filename, $wm)) {
        $weekNum = intval($wm[1] ?: $wm[2]);
    }

    if ($weekNum !== null) {
        $earlyWeek = $weekNum <= 3;
        $weekContext =
            "WEEKLY CONTEXT — THIS IS WEEK $weekNum MATERIAL:\n" .
            "- The module learning outcomes are the FULL SEMESTER destination, NOT a per-week checklist.\n" .
            "- A single week's lecture only needs to be a relevant stepping stone toward those outcomes.\n" .
            ($earlyWeek
                ? "- Week $weekNum is an early week. Foundational or introductory content is completely normal and expected here. Do NOT penalize it for not covering advanced outcomes yet.\n"
                : "- Week $weekNum should show progression, building on earlier content and moving toward the MLOs.\n"
            ) .
            "- Key question: 'Is this week's content a reasonable, relevant contribution to the module's learning journey?' NOT 'does it cover all MLOs?'";
    } else {
        $weekContext =
            "WEEKLY CONTEXT:\n" .
            "- The module learning outcomes are the FULL SEMESTER destination, NOT a per-week checklist.\n" .
            "- A single week's lecture only needs to be a relevant stepping stone toward those outcomes.\n" .
            "- Key question: 'Is this content a reasonable, relevant contribution to the module's learning journey?'";
    }

    $systemPrompt .= "\n\n" .
        "<LECTURE_MATERIAL_AUDIT_RULESET>\n" .
        "FOR LECTURE MATERIALS SPECIFICALLY:\n" .
        "- Format, layout, slide design, and document structure are completely irrelevant to scoring. Do NOT comment on them negatively.\n" .
        "- Focus on: (1) is this week's content a reasonable stepping stone toward the MLOs? (2) is the content reasonably current? (3) is the language academically appropriate?\n" .
        "- A lecture can take any creative approach as long as the underlying learning direction is served.\n" .
        "- Use web_audit to verify currency of any technologies, tools, data, or industry references cited.\n" .
        "- LANGUAGE CHECK: Flag if the language is unprofessional, inconsistent, or in an unexpected language without clear rationale.\n" .
        $weekContext . "\n" .
        "</LECTURE_MATERIAL_AUDIT_RULESET>";
}

$messages = [
    [
        'role' => 'system',
        'content' => $systemPrompt
    ]
];

foreach ($_SESSION["history_$auditId"] as $msg) {

    $messages[] = [
        'role' => $msg['role'],
        'content' => $msg['content']
    ];
}

// ========================================
// AGENT LOOP
// ========================================

$reply = callFireworks($messages);

// ========================================
// TOOL PARSER
// ========================================

if (preg_match(
    '/<call name="(.*?)">(.*?)<\/call>/s',
    $reply,
    $matches
)) {

    $tool = trim($matches[1]);
    $args = trim($matches[2]);

    $toolFunction = 'tool_' . $tool;
    $result = 'Tool failed';

    if (function_exists($toolFunction)) {
        $result = $toolFunction($args);
    }

    $_SESSION["history_$auditId"][] = [
        'role' => 'assistant',
        'content' => $reply
    ];

    $_SESSION["history_$auditId"][] = [
        'role' => 'tool',
        'content' => $result
    ];

    echo json_encode([
        'status' => 'thinking',
        'log' => "Executing: $tool"
    ]);

    exit();
}

// ========================================
// COMPLETE
// ========================================

unset($_SESSION["history_$auditId"]);

echo json_encode([
    'status' => 'complete',
    'log' => 'Audit Finished'
]);

exit();