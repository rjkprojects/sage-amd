<?php
require_once 'handshake.php';

// --- JS TOOL PROXY ---
if (isset($_GET['action']) && $_GET['action'] === 'call') {
    $toolName = $_POST['tool'] ?? '';
    $toolArgs = $_POST['args'] ?? '';
    $toolFunction = 'tool_' . $toolName;
    
    if (function_exists($toolFunction)) {
        echo $toolFunction($toolArgs);
    } else {
        echo "Error: Tool $toolName not found.";
    }
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'get_file') {
    $path = $_POST['path'] ?? '';
    if (file_exists($path)) {
        echo file_get_contents($path);
    } else {
        echo "Error: File not found at $path";
    }
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'init_audit') {
    $filePath = $_POST['path'] ?? '';
    $moduleCode = $_POST['module_code'] ?? '';
    $docType = $_POST['doc_type'] ?? '';
    
    if (!$filePath || !$moduleCode) {
        echo json_encode(['error' => 'Missing path or module code']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM audit_results WHERE file_path = ?");
        $stmt->execute([$filePath]);
        $audit = $stmt->fetch();
        
        if (!$audit) {
            $stmt = $pdo->prepare("INSERT INTO audit_results (module_code, doc_type, file_path, status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([$moduleCode, $docType, $filePath]);
            $auditId = $pdo->lastInsertId();
        } else {
            $auditId = $audit['id'];
        }
        
        echo json_encode(['id' => $auditId]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

function tool_get_module_baseline($args) {
    $code = is_array($args) ? ($args['module_code'] ?? null) : $args;
    global $pdo;

    // Fetch module core info
    $stmt = $pdo->prepare("SELECT module_title, module_aim FROM modules WHERE module_code = ?");
    $stmt->execute([$code]);
    $res = $stmt->fetch();

    if (!$res) return "No baseline found for $code.";

    // Fetch module learning outcomes (primary anchor for audit)
    $stmt2 = $pdo->prepare("SELECT outcome_number, outcome_text FROM module_learning_outcomes WHERE module_code = ? ORDER BY outcome_number ASC");
    $stmt2->execute([$code]);
    $outcomes = $stmt2->fetchAll();

    $res['learning_outcomes'] = $outcomes;

    return json_encode($res);
}

function tool_web_audit($args) {
    $query = is_array($args) ? ($args['query'] ?? null) : $args;
    $debug = [];
    $debug[] = "🔍 [web_audit] Query: " . $query;
    
    // Try Brave API first
    if (!empty(BRAVE_API_KEY)) {
        $debug[] = "🔍 [web_audit] Attempting Brave API...";
        $braveResult = searchWithBrave($query, $debug);
        if ($braveResult !== null) {
            $debug[] = "✅ [web_audit] Brave API SUCCESS";
            $result = json_decode($braveResult, true);
            $result['_debug'] = $debug;
            return json_encode($result);
        }
        $debug[] = "⚠️  [web_audit] Brave API returned null, fallback to DuckDuckGo";
    } else {
        $debug[] = "⚠️  [web_audit] No BRAVE_API_KEY configured";
    }
    
    // Fall back to DuckDuckGo scraping
    $debug[] = "🔍 [web_audit] Attempting DuckDuckGo fallback...";
    return searchWithDuckDuckGo($query, $debug);
}

function searchWithBrave($query, &$debug) {
    $url = "https://api.search.brave.com/res/v1/web/search?q=" . urlencode($query) . "&count=3";
    $debug[] = "🔍 [Brave] URL: " . $url;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: application/json",
        "X-Subscription-Token: " . BRAVE_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_USERAGENT, 'SAGE-Agent-0.1');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $debug[] = "🔍 [Brave] HTTP Code: " . $httpCode . " | Error: " . ($curlError ?: "none");
    
    if ($curlError) {
        $debug[] = "❌ [Brave] cURL Error: " . $curlError;
        return null;
    }
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['web']['results']) && !empty($data['web']['results'])) {
            $debug[] = "✅ [Brave] Found " . count($data['web']['results']) . " results";
            $results = [];
            foreach (array_slice($data['web']['results'], 0, 3) as $result) {
                $results[] = [
                    'title' => $result['title'] ?? '',
                    'description' => $result['description'] ?? '',
                    'url' => $result['url'] ?? ''
                ];
            }
            return json_encode([
                'source' => 'Brave API',
                'query' => $query,
                'results' => $results
            ]);
        } else {
            $debug[] = "⚠️  [Brave] No results in response";
        }
    } else {
        $debug[] = "❌ [Brave] HTTP $httpCode: " . substr($response, 0, 100);
    }
    
    return null; // Signal to try fallback
}

function searchWithDuckDuckGo($query, &$debug) {
    $url = "https://duckduckgo.com/html/?q=" . urlencode($query);
    $debug[] = "🔍 [DuckDuckGo] URL: " . $url;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $debug[] = "🔍 [DuckDuckGo] HTTP Code: " . $httpCode . " | Error: " . ($curlError ?: "none");
    
    if ($curlError) {
        $debug[] = "❌ [DuckDuckGo] cURL Error: " . $curlError;
    }
    
    if ($httpCode === 200 && $response) {
        // Parse HTML with regex to extract results
        $results = [];
        
        // Improved DuckDuckGo result pattern for modern HTML structure
        if (preg_match_all('/<h2 class="result-title">.*?<a class="result-link" href="([^"]+)">([^<]+)<\/a>.*?<\/h2>.*?<p class="result-snippet">([^<]+)<\/p>/s', $response, $matches, PREG_SET_ORDER)) {
            foreach (array_slice($matches, 0, 3) as $match) {
                $url = htmlspecialchars_decode($match[1]);
                if (strpos($url, 'duckduckgo.com') === false && !empty($url)) {
                    $results[] = [
                        'title' => trim(strip_tags($match[2])),
                        'description' => trim(strip_tags($match[3])),
                        'url' => $url
                    ];
                }
            }
        }
        
        // Fallback for different HTML variant
        if (empty($results) && preg_match_all('/<a class="result-link" href="([^"]+)">([^<]+)<\/a>.*?<div class="result-snippet">([^<]+)<\/div>/s', $response, $matches, PREG_SET_ORDER)) {
            foreach (array_slice($matches, 0, 3) as $match) {
                $url = htmlspecialchars_decode($match[1]);
                if (strpos($url, 'duckduckgo.com') === false && !empty($url)) {
                    $results[] = [
                        'title' => trim(strip_tags($match[2])),
                        'description' => trim(strip_tags($match[3])),
                        'url' => $url
                    ];
                }
            }
        }
        
        $debug[] = "✅ [DuckDuckGo] Found " . count($results) . " results";
        
        if (!empty($results)) {
            return json_encode([
                'source' => 'DuckDuckGo',
                'query' => $query,
                'results' => $results,
                '_debug' => $debug
            ]);
        }
    }
    
    $debug[] = "⚠️  [DuckDuckGo] Fallback - no results found";
    
    // Minimal fallback
    return json_encode([
        'source' => 'Cache',
        'query' => $query,
        'results' => [
            ['title' => 'Search performed', 'description' => "Web search for '$query' completed. Please try again.", 'url' => 'https://duckduckgo.com/?q=' . urlencode($query)]
        ],
        '_debug' => $debug
    ]);
}

function clean_json_payload($payload) {
    $payload = trim($payload);
    
    // Strip markdown code block wrappers if present (e.g. ```json ... ```)
    if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $payload, $matches)) {
        $payload = trim($matches[1]);
    }
    
    // Remove trailing commas before closing braces/brackets
    $payload = preg_replace('/,\s*([\]}])/s', '$1', $payload);
    
    return $payload;
}

function tool_save_audit($args) {
    global $pdo;
    $parts = explode('|', $args, 2);
    if (count($parts) < 2) return "Save failed: Invalid format.";
    
    $auditId = intval(trim($parts[0]));
    if ($auditId <= 0) {
        if (preg_match('/\d+/', $parts[0], $idMatches)) {
            $auditId = intval($idMatches[0]);
        }
    }
    
    if ($auditId <= 0) {
        return "Save failed: Invalid Audit ID '" . htmlspecialchars($parts[0]) . "'";
    }
    
    $jsonPayload = clean_json_payload($parts[1]);
    $json = json_decode($jsonPayload, true);
    
    if ($json === null) {
        // 1. Fix unescaped backslashes (escape any backslash not followed by a valid JSON escape char)
        $repairedPayload = preg_replace('/\\\\(?![uU][0-9a-fA-F]{4}|["\\\\\/bfnrt])/s', '\\\\\\\\', $jsonPayload);
        
        // 2. Fix unescaped raw newlines in strings
        $repairedPayload = preg_replace_callback('/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/s', function($m) {
            return '"' . str_replace(array("\r", "\n"), array("", "\\n"), $m[1]) . '"';
        }, $repairedPayload);
        
        $json = json_decode($repairedPayload, true);
        if ($json !== null) {
            $jsonPayload = $repairedPayload;
        }
    }
    
    if ($json === null) {
        return "Save failed: Invalid JSON payload. Error: " . json_last_error_msg() . "\nPayload sample: " . substr($jsonPayload, 0, 200) . "...";
    }
    
    $score = intval($json['score'] ?? 0);
    
    try {
        $stmt = $pdo->prepare("UPDATE audit_results SET score = ?, suggestions_json = ?, status = 'checked' WHERE id = ?");
        $stmt->execute([$score, $jsonPayload, $auditId]);
        return "Success: Audit saved to DB.";
    } catch (Exception $e) {
        return "DB Error: " . $e->getMessage();
    }
}

/**
 * Fetch all modules from the database.
 * Returns a JSON array of modules.
 */
function tool_get_all_modules() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM modules");
    $modules = $stmt->fetchAll();
    return json_encode($modules);
}

/**
 * Fetch a specific module by ID.
 * @param int $module_id
 * Returns a JSON object representing the module.
 */
function tool_get_module_by_code($args) {
    $module_code = is_array($args) ? ($args['module_code'] ?? null) : $args;
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM modules WHERE module_code = ?");
    $stmt->execute([$module_code]);
    $module = $stmt->fetch();
    return $module ? json_encode($module) : json_encode(['error' => 'Module not found']);
}

/**
 * Fetch learning outcomes for a specific module.
 * @param int $module_id
 * Returns a JSON array of learning outcomes.
 */
function tool_get_learning_outcomes($args) {
    $module_code = is_array($args) ? ($args['module_code'] ?? null) : $args;
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM module_learning_outcomes WHERE module_code = ?");
    $stmt->execute([$module_code]);
    $outcomes = $stmt->fetchAll();
    return json_encode($outcomes);
}

/**
 * Fetch weekly breakdown for a specific module.
 * @param int $module_id
 * Returns a JSON array of weekly breakdowns.
 */
function tool_get_module_weeks($args) {
    $module_code = is_array($args) ? ($args['module_code'] ?? null) : $args;
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM module_weeks WHERE module_code = ?");
    $stmt->execute([$module_code]);
    $weeks = $stmt->fetchAll();
    return json_encode($weeks);
}

/**
 * Fetch assessments for a specific module.
 * @param int $module_id
 * Returns a JSON array of assessments.
 */
function tool_get_module_assessments($args) {
    $module_code = is_array($args) ? ($args['module_code'] ?? null) : $args;
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM module_assessments WHERE module_code = ?");
    $stmt->execute([$module_code]);
    $assessments = $stmt->fetchAll();
    return json_encode($assessments);
}

/**
 * Fetch audit results for a specific module.
 * @param int $module_id
 * Returns a JSON array of audit results.
 */
function tool_get_audit_results($args) {
    $module_code = is_array($args) ? ($args['module_code'] ?? null) : $args;
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM audit_results WHERE module_code = ?");
    $stmt->execute([$module_code]);
    $audit_results = $stmt->fetchAll();
    return json_encode($audit_results);
}
?>