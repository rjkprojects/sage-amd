<?php
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'coordinator') die("Access Denied.");

$stmt = $pdo->query("SELECT id, file_path, status, score FROM audit_results");
$db_audits = [];
while ($row = $stmt->fetch()) {
    $db_audits[$row['file_path']] = $row;
}

$stmt_m = $pdo->query("SELECT module_code, module_title FROM modules");
$module_names = [];
while ($m_row = $stmt_m->fetch()) {
    $module_names[$m_row['module_code']] = $m_row['module_title'];
}

$categories = [];
if (is_dir('categories')) {
    foreach (scandir('categories') as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $categories[$name] = json_decode(file_get_contents("categories/$file"), true);
        }
    }
}

$files = [];
if (is_dir('repository')) {
    $iti = new RecursiveDirectoryIterator('repository');
    foreach(new RecursiveIteratorIterator($iti) as $file){
        if($file->isFile() && in_array(strtolower($file->getExtension()), ['pdf', 'txt', 'md'])){
            $path = str_replace('\\', '/', $file->getPathname());
            $parts = explode('/', $path);
            
            if(count($parts) >= 7) {
                $filePath = $path;
                $dbRecord = $db_audits[$filePath] ?? null;
                
                $files[] = [
                    'path' => $filePath,
                    'term' => $parts[1],
                    'faculty' => $parts[2],
                    'lecturer' => $parts[3],
                    'module_code' => $parts[4],
                    'module_title' => $module_names[$parts[4]] ?? 'Unknown Module',
                    'doc_type' => $parts[5],
                    'filename' => $parts[6],
                    'db_id' => $dbRecord['id'] ?? null,
                    'status' => $dbRecord['status'] ?? 'untracked',
                    'score' => $dbRecord['score'] ?? null
                ];
            }
        }
    }
}

// Sort: lesson_plan (1), assignment (2), lecture_material (3)
usort($files, function($a, $b) {
    $order = [
        'lesson_plan' => 1,
        'lesson plan' => 1,
        'assignment' => 2,
        'assignment_brief' => 2,
        'assignment brief' => 2,
        'lecture_material' => 3,
        'lecture material' => 3
    ];
    $ta = strtolower($a['doc_type']);
    $tb = strtolower($b['doc_type']);
    $wa = $order[$ta] ?? 99;
    $wb = $order[$tb] ?? 99;
    if ($wa !== $wb) {
        return $wa - $wb;
    }
    return strcmp($a['module_code'] . $a['filename'], $b['module_code'] . $b['filename']);
});

$faculties = array_unique(array_column($files, 'faculty'));
sort($faculties);
$terms = array_unique(array_column($files, 'term'));
sort($terms);
$lecturers = array_unique(array_column($files, 'lecturer'));
sort($lecturers);
$modules = [];
foreach ($files as $f) {
    $modules[$f['module_code']] = $f['module_title'];
}
ksort($modules);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>S.A.G.E. | Coordinator</title>
    <link rel="stylesheet" href="css/output.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        const FIREWORKS_API_URL = '<?= FIREWORKS_API_URL; ?>';
        const FIREWORKS_API_KEY = '<?= FIREWORKS_API_KEY; ?>';
        const FIREWORKS_MODEL   = '<?= FIREWORKS_MODEL; ?>';
        const SAGE_SYSTEM_PROMPT = <?= json_encode($sage_system_prompt) ?>;
        const CATEGORY_RULES = <?= json_encode($categories) ?>;
    </script>
</head>
<body class="bg-gray-50 text-gray-900 font-sans min-h-screen p-8 md:p-16">
    <div class="max-w-7xl mx-auto">
        <header class="flex justify-between items-end mb-12 border-b-2 border-sage-500 pb-6">
            <div>
                <h1 class="text-6xl font-bold tracking-tighter text-sage-900">Coordinator</h1>
                <p class="text-sage-500 text-xl mt-2 font-light">Live Repository Feed</p>
            </div>
            <a href="?logout=true" class="bg-red-50 text-red-500 hover:bg-red-500 hover:text-white font-bold uppercase tracking-widest text-xs px-6 py-3 rounded-xl transition">Logout</a>
        </header>

        <!-- Filters -->
        <div class="bg-white border-2 border-gray-100 rounded-3xl p-6 shadow-sm mb-6 flex flex-wrap gap-4 items-center">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-bold uppercase tracking-widest text-gray-400 mb-2">Faculty</label>
                <select id="filter-faculty" onchange="applyFilters()" class="w-full bg-gray-50 border-2 border-gray-100 rounded-xl px-4 py-2 text-sm focus:border-sage-500 focus:outline-none">
                    <option value="">All Faculties</option>
                    <?php foreach($faculties as $fac): ?>
                        <option value="<?= htmlspecialchars($fac) ?>"><?= htmlspecialchars(str_replace('_', ' ', $fac)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-bold uppercase tracking-widest text-gray-400 mb-2">Term</label>
                <select id="filter-term" onchange="applyFilters()" class="w-full bg-gray-50 border-2 border-gray-100 rounded-xl px-4 py-2 text-sm focus:border-sage-500 focus:outline-none">
                    <option value="">All Terms</option>
                    <?php foreach($terms as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars(str_replace('_', ' ', $t)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-bold uppercase tracking-widest text-gray-400 mb-2">Lecturer</label>
                <select id="filter-lecturer" onchange="applyFilters()" class="w-full bg-gray-50 border-2 border-gray-100 rounded-xl px-4 py-2 text-sm focus:border-sage-500 focus:outline-none">
                    <option value="">All Lecturers</option>
                    <?php foreach($lecturers as $l): ?>
                        <option value="<?= htmlspecialchars($l) ?>"><?= htmlspecialchars(str_replace('_', ' ', $l)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-bold uppercase tracking-widest text-gray-400 mb-2">Module</label>
                <select id="filter-module" onchange="applyFilters()" class="w-full bg-gray-50 border-2 border-gray-100 rounded-xl px-4 py-2 text-sm focus:border-sage-500 focus:outline-none">
                    <option value="">All Modules</option>
                    <?php foreach($modules as $code => $title): ?>
                        <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($code) ?> - <?= htmlspecialchars($title) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="bg-white border-2 border-gray-100 rounded-3xl overflow-hidden shadow-sm">
            <table class="w-full text-left">
                <thead class="bg-gray-50 border-b-2 border-gray-100">
                    <tr class="text-xs uppercase tracking-widest text-gray-400">
                        <th class="p-6">Location (Term / Fac / Lect)</th>
                        <th class="p-6">Module & Type</th>
                        <th class="p-6">File</th>
                        <th class="p-6 text-center">Status</th>
                        <th class="p-6 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr id="empty-row" style="display: none;"><td colspan="5" class="p-12 text-center text-gray-400 font-light text-xl">No materials match selected filters.</td></tr>
                    <?php if(empty($files)): ?>
                        <tr><td colspan="5" class="p-12 text-center text-gray-400 font-light text-xl">Repository is empty. Drop some PDFs in the folders, mate.</td></tr>
                    <?php endif; ?>

                    <?php foreach($files as $f): ?>
                    <tr class="hover:bg-gray-50 transition" data-row data-faculty="<?= htmlspecialchars($f['faculty']) ?>" data-term="<?= htmlspecialchars($f['term']) ?>" data-lecturer="<?= htmlspecialchars($f['lecturer']) ?>" data-module="<?= htmlspecialchars($f['module_code']) ?>">
                        <td class="p-6">
                            <span class="block font-bold text-sage-900"><?= $f['term'] ?></span>
                            <span class="block text-xs text-gray-400 tracking-widest uppercase mt-1"><?= $f['faculty'] ?> > <?= str_replace('_', ' ', $f['lecturer']) ?></span>
                        </td>
                        <td class="p-6">
                            <span class="block font-bold text-lg"><?= $f['module_code'] ?> - <?= htmlspecialchars($f['module_title']) ?></span>
                            <span class="block text-sage-500 text-xs font-bold uppercase tracking-widest mt-1"><?= str_replace('_', ' ', $f['doc_type']) ?></span>
                        </td>
                        <td class="p-6 text-gray-500 text-sm font-mono truncate max-w-[200px]" title="<?= $f['filename'] ?>"><?= $f['filename'] ?></td>
                        <td class="p-6 text-center">
                            <?php if($f['status'] === 'checked'): ?>
                                <?php $isOk = intval($f['score'] ?? 0) >= 60; ?>
                                <?php if($isOk): ?>
                                    <span class="bg-green-50 text-green-700 px-3 py-1 rounded-full text-xs font-bold tracking-widest uppercase border border-green-200">OK</span>
                                <?php else: ?>
                                    <span class="bg-amber-50 text-amber-700 px-3 py-1 rounded-full text-xs font-bold tracking-widest uppercase border border-amber-200">Need Review</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="bg-gray-100 text-gray-500 px-3 py-1 rounded-full text-xs font-bold tracking-widest uppercase border border-gray-200">Untracked</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-6 text-right">
                            <?php if($f['status'] === 'checked'): ?>
                                <a href="report?id=<?= $f['db_id'] ?>" class="inline-block border-2 border-sage-500 text-sage-900 hover:bg-sage-500 hover:text-white px-6 py-3 rounded-xl font-bold text-xs uppercase tracking-widest transition w-40 text-center">View Report</a>
                            <?php else: ?>
                                <button onclick="runAudit('<?= addslashes($f['path']) ?>', '<?= $f['module_code'] ?>', '<?= $f['doc_type'] ?>', this)" class="bg-sage-900 hover:bg-sage-500 text-white px-6 py-3 rounded-xl font-bold text-xs uppercase tracking-widest shadow-md transition w-40">Audit</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        async function runAudit(filePath, moduleCode, docType, btn) {
            btn.innerText = "S.A.G.E. Initializing...";
            btn.classList.add('animate-pulse', 'bg-sage-500');
            
            try {
                // 1. Initialize Audit Record in DB
                btn.innerText = "Initializing Audit...";
                const initRes = await fetch('tools.php?action=init_audit', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ path: filePath, module_code: moduleCode, doc_type: docType })
                });
                const initData = await initRes.json();
                if (initData.error) throw new Error(initData.error);
                const auditId = initData.id;

                // 2. Get File Content via Proxy
                btn.innerText = "Reading Document...";
                const fileRes = await fetch('tools.php?action=get_file', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ path: filePath })
                });
                const fileData = await fileRes.text();
                
                if (fileData.startsWith('Error:')) throw new Error(fileData);

                const categoryInfo = CATEGORY_RULES[docType] || null;
                let userPrompt = `[TASK] Audit this document.\n\nModule: ${moduleCode}\nDoc Type: ${docType}\nAudit ID: ${auditId}\n`;
                if (categoryInfo) {
                    userPrompt += `\n[CATEGORY SPECIFIC INSTRUCTIONS]\n${categoryInfo.instruction}\n\nKPIs to Scrutinize:\n` + categoryInfo.kpi.map(k => `- ${k}`).join('\n') + `\n`;
                }
                userPrompt += `\nDOCUMENT CONTENT:\n${fileData.substring(0, 20000)}`;

                let systemPrompt = SAGE_SYSTEM_PROMPT;

                // Global audit philosophy for all doc types
                systemPrompt += "\n\n<SAGE_AUDIT_PHILOSOPHY>\n" +
                    "PRIMARY ANCHOR — MODULE LEARNING OUTCOMES (MLOs):\n" +
                    "- ALWAYS start by calling get_module_baseline to retrieve the module_learning_outcomes.\n" +
                    "- The MLOs are the PRIMARY and MOST IMPORTANT benchmark for all audit assessments.\n" +
                    "- Your core question is: does this document broadly address the learning outcomes? A thematic, loose match is sufficient.\n" +
                    "- module_assessments and module_weeks are SECONDARY REFERENCE CONTEXT ONLY. Do NOT penalize a document for not matching them precisely.\n\n" +
                    "LANGUAGE APPROPRIATENESS:\n" +
                    "- Check whether the language used is appropriate for an academic/professional setting.\n" +
                    "- Flag if the language is unprofessional, overly casual, ambiguous, or inconsistent in a way that could impact students.\n" +
                    "- If the document mixes languages without clear rationale, note it constructively.\n\n" +
                    "ASSESSMENT VERDICT:\n" +
                    "- The final verdict is EITHER 'ok' OR 'need-review'. No other rating.\n" +
                    "- 'ok' = content broadly covers learning outcomes and is reasonably current. Score >= 60.\n" +
                    "- 'need-review' = one or more learning outcomes significantly unaddressed, or severely outdated. Score < 60.\n" +
                    "- Still include a numeric score in save_audit JSON for DB record-keeping.\n\n" +
                    "TONE: Do NOT roast or embarrass the lecturer. Supportive, constructive review only.\n" +
                    "Lecturers have creative freedom week-to-week. Respect pedagogical choices.\n" +
                    "</SAGE_AUDIT_PHILOSOPHY>";

                if (docType.toLowerCase().includes('lecture')) {
                    // Detect week number from filename (e.g. W1_, Week2_, w3_, etc.)
                    const weekMatch = filePath.split(/[\/\\]/).pop().match(/(?:week[-_]?(\d+)|[Ww](\d+))[_\-\s\.]/i);
                    const weekNum = weekMatch ? parseInt(weekMatch[1] || weekMatch[2]) : null;

                    let weekContext = '';
                    if (weekNum !== null) {
                        const earlyWeek = weekNum <= 3;
                        weekContext =
                            `WEEKLY CONTEXT \u2014 THIS IS WEEK ${weekNum} MATERIAL:\n` +
                            `- The module learning outcomes are the FULL SEMESTER destination, NOT a per-week checklist.\n` +
                            `- A single week's lecture only needs to be a relevant stepping stone toward those outcomes.\n` +
                            (earlyWeek
                                ? `- Week ${weekNum} is an early week. Foundational or introductory content is completely normal and expected here. Do NOT penalize it for not covering advanced outcomes yet.\n`
                                : `- Week ${weekNum} should show progression, building on earlier content and moving toward the MLOs.\n`
                            ) +
                            `- Key question: 'Is this week's content a reasonable, relevant contribution to the module's learning journey?' NOT 'does it cover all MLOs?'`;
                    } else {
                        weekContext =
                            'WEEKLY CONTEXT:\n' +
                            '- The module learning outcomes are the FULL SEMESTER destination, NOT a per-week checklist.\n' +
                            '- A single week lecture only needs to be a relevant stepping stone toward those outcomes.\n' +
                            "- Key question: 'Is this content a reasonable, relevant contribution to the module's learning journey?'";
                    }

                    systemPrompt += "\n\n<LECTURE_MATERIAL_AUDIT_RULESET>\n" +
                        "FOR LECTURE MATERIALS SPECIFICALLY:\n" +
                        "- Format, layout, slide design, and document structure are completely irrelevant to scoring. Do NOT comment on them negatively.\n" +
                        "- Focus on: (1) is this week's content a reasonable stepping stone toward the MLOs? (2) is the content reasonably current (use web_audit to check)? (3) is the language academically appropriate?\n" +
                        "- A lecture can take any creative approach as long as the underlying learning direction is served.\n" +
                        "- Use web_audit to verify currency of any technologies, tools, data, or industry references cited.\n" +
                        "- LANGUAGE CHECK: Flag if the language is unprofessional, inconsistent, or in an unexpected language without clear rationale.\n" +
                        weekContext + "\n" +
                        "</LECTURE_MATERIAL_AUDIT_RULESET>";
                }

                // 3. Build Messages using unstripped SAGE_SYSTEM_PROMPT for correct XML tools
                let messages = [
                    { role: 'system', content: systemPrompt },
                    { 
                        role: 'user', 
                        content: userPrompt
                    }
                ];

                let maxTurns = 10;
                let turns = 0;
                let isComplete = false;
                let saved = false;

                while (turns < maxTurns && !isComplete) {
                    btn.innerText = turns === 0 ? "Analyzing..." : `Thinking (Step ${turns})...`;
                    
                    const response = await fetch(FIREWORKS_API_URL, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Authorization': 'Bearer ' + FIREWORKS_API_KEY,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            model: FIREWORKS_MODEL,
                            max_tokens: 131072,
                            top_k: 40,
                            presence_penalty: 0,
                            frequency_penalty: 0,
                            temperature: 0.3,
                            messages: messages
                        })
                    });

                    if (!response.ok) {
                        const errorBody = await response.text();
                        throw new Error(`AI API error ${response.status}: ${errorBody || response.statusText}`);
                    }
                    const data = await response.json();
                    const assistantText = data.choices[0].message.content;

                    // 4. Tool Check
                    const toolMatch = assistantText.match(/<call name="([^"]+)">(.*?)<\/call>/s);
                    if (toolMatch) {
                        const toolName = toolMatch[1];
                        const toolArgs = toolMatch[2].replace(/<[^>]*>/g, '').trim();

                        btn.innerText = `Executing: ${toolName}...`;
                        
                        // Call tool via PHP proxy
                        const toolRes = await fetch('tools.php?action=call', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ tool: toolName, args: toolArgs })
                        });
                        const resultText = await toolRes.text();

                        messages.push({ role: 'assistant', content: assistantText });
                        messages.push({ 
                            role: 'user', 
                            content: `[TOOL RESULT: ${toolName}]\n${resultText}\n[END RESULT]` 
                        });
                        
                        turns++;
                        if (toolName === 'save_audit') {
                            if (resultText.trim().startsWith('Success')) {
                                saved = true;
                                isComplete = true;
                            } else {
                                console.warn('save_audit returned failure:', resultText);
                            }
                        }
                    } else {
                        if (!saved && turns < maxTurns - 1) {
                            // If the AI outputted the report but forgot to call the save_audit tool, prompt it to save
                            messages.push({ role: 'assistant', content: assistantText });
                            messages.push({
                                role: 'user',
                                content: `[SYSTEM NOTICE: Your audit report is received. You MUST now call the save_audit tool to persist these results to the database! Please make the save_audit tool call immediately using this exact format:
<call name="save_audit">
${auditId}|{"score":YOUR_SCORE,"summary":"...","strengths":["..."],"weaknesses":["..."],"suggestions":[{"issue":"...","fix":"...","url":"..."}]}
</call>
Do not write any other text, just make the tool call.]`
                            });
                            turns++;
                        } else {
                            isComplete = true;
                        }
                    }
                }

                if (saved) {
                    btn.innerText = "Success!";
                    setTimeout(() => location.reload(), 1000);
                } else {
                    btn.innerText = "Audit Incomplete";
                    alert("Audit finished but results were not saved to database. The AI might not have called the save_audit tool.");
                    btn.classList.remove('animate-pulse', 'bg-sage-500');
                }

            } catch (err) {
                console.error(err);
                btn.innerText = "Error!";
                alert("Audit failed: " + err.message);
                btn.classList.remove('animate-pulse', 'bg-sage-500');
            }
        }

        function applyFilters() {
            const faculty = document.getElementById('filter-faculty').value;
            const term = document.getElementById('filter-term').value;
            const lecturer = document.getElementById('filter-lecturer').value;
            const module = document.getElementById('filter-module').value;

            const rows = document.querySelectorAll('tbody tr[data-row]');
            let count = 0;
            rows.forEach(row => {
                const matchesFaculty = !faculty || row.getAttribute('data-faculty') === faculty;
                const matchesTerm = !term || row.getAttribute('data-term') === term;
                const matchesLecturer = !lecturer || row.getAttribute('data-lecturer') === lecturer;
                const matchesModule = !module || row.getAttribute('data-module') === module;

                if (matchesFaculty && matchesTerm && matchesLecturer && matchesModule) {
                    row.style.display = '';
                    count++;
                } else {
                    row.style.display = 'none';
                }
            });

            const emptyRow = document.getElementById('empty-row');
            if (emptyRow) {
                emptyRow.style.display = count === 0 ? '' : 'none';
            }
        }
    </script>
</body>
</html>