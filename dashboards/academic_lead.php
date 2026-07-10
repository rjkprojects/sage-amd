<?php
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'academic_lead') die("Access Denied.");
global $pdo;
$stats = $pdo->query("SELECT status, COUNT(*) as count FROM audit_results GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$rawAudits = $pdo->query("SELECT a.*, m.module_code, m.module_title FROM audit_results a JOIN modules m ON a.module_code = m.module_code ORDER BY a.created_at DESC")->fetchAll();

$recentAudits = [];
foreach ($rawAudits as $r) {
    $parts = explode('/', str_replace('\\', '/', $r['file_path']));
    if (count($parts) >= 7) {
        $recentAudits[] = [
            'id' => $r['id'],
            'path' => $r['file_path'],
            'term' => $parts[1],
            'faculty' => $parts[2],
            'lecturer' => $parts[3],
            'module_code' => $r['module_code'],
            'module_title' => $r['module_title'],
            'doc_type' => $r['doc_type'],
            'filename' => $parts[6],
            'score' => $r['score'],
            'status' => $r['status']
        ];
    } else {
        $recentAudits[] = [
            'id' => $r['id'],
            'path' => $r['file_path'],
            'term' => 'Unknown Term',
            'faculty' => 'Unknown Faculty',
            'lecturer' => 'Unknown Lecturer',
            'module_code' => $r['module_code'],
            'module_title' => $r['module_title'],
            'doc_type' => $r['doc_type'],
            'filename' => basename($r['file_path']),
            'score' => $r['score'],
            'status' => $r['status']
        ];
    }
}

// Sort: lesson_plan (1), assignment (2), lecture_material (3)
usort($recentAudits, function($a, $b) {
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

$faculties = array_unique(array_column($recentAudits, 'faculty'));
sort($faculties);
$terms = array_unique(array_column($recentAudits, 'term'));
sort($terms);
$lecturers = array_unique(array_column($recentAudits, 'lecturer'));
sort($lecturers);
$modules = [];
foreach ($recentAudits as $f) {
    $modules[$f['module_code']] = $f['module_title'];
}
ksort($modules);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>S.A.G.E. | Lead</title>
    <link rel="stylesheet" href="css/output.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

</head>
<body class="bg-gray-50 text-gray-900 font-sans min-h-screen p-8 md:p-16">
    <div class="flex gap-8 max-w-full mx-auto h-screen" style="width:60%; margin-left: 0">
        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <div class="max-w-4xl">
                <header class="flex justify-between items-end mb-12 border-b-2 border-sage-500 pb-6">
                    <div><h1 class="text-6xl font-bold tracking-tighter text-sage-900">Academic Lead</h1><p class="text-sage-500 text-xl mt-2 font-light">Global Governance Overview</p></div>
                    <a href="./?logout=true" class="bg-red-50 text-red-500 hover:bg-red-500 hover:text-white font-bold uppercase tracking-widest text-xs px-6 py-3 rounded-xl transition">Logout</a>
                </header>
                <main class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
                    <div class="bg-white border-2 border-gray-100 p-8 rounded-3xl flex justify-between"><div class="text-gray-400 text-xs font-bold uppercase tracking-widest mb-2">Untracked / Pending</div><p class="text-6xl font-bold text-gray-300"><?= $stats['pending'] ?? 0 ?></p></div>
                    <div class="bg-white border-2 border-sage-200 p-8 rounded-3xl flex justify-between"><div class="text-sage-500 text-xs font-bold uppercase tracking-widest mb-2">Checked & Cleared</div><p class="text-6xl font-bold text-sage-900"><?= $stats['checked'] ?? 0 ?></p></div>
                </main>
                <!-- Filters -->
                <div class="bg-white border-2 border-gray-100 rounded-3xl p-6 shadow-sm mb-6 flex flex-wrap gap-4 items-center">
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-400 mb-2">Faculty</label>
                        <select id="filter-faculty" onchange="applyFilters()" class="w-full bg-gray-50 border-2 border-gray-100 rounded-xl px-4 py-2 text-sm focus:border-sage-500 focus:outline-none">
                            <option value="">All Faculties</option>
                            <?php foreach($faculties as $fac): ?>
                                <option value="<?= htmlspecialchars($fac) ?>"><?= htmlspecialchars(str_replace('_', ' ', $fac)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-400 mb-2">Term</label>
                        <select id="filter-term" onchange="applyFilters()" class="w-full bg-gray-50 border-2 border-gray-100 rounded-xl px-4 py-2 text-sm focus:border-sage-500 focus:outline-none">
                            <option value="">All Terms</option>
                            <?php foreach($terms as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars(str_replace('_', ' ', $t)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-400 mb-2">Lecturer</label>
                        <select id="filter-lecturer" onchange="applyFilters()" class="w-full bg-gray-50 border-2 border-gray-100 rounded-xl px-4 py-2 text-sm focus:border-sage-500 focus:outline-none">
                            <option value="">All Lecturers</option>
                            <?php foreach($lecturers as $l): ?>
                                <option value="<?= htmlspecialchars($l) ?>"><?= htmlspecialchars(str_replace('_', ' ', $l)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[180px]">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-400 mb-2">Module</label>
                        <select id="filter-module" onchange="applyFilters()" class="w-full bg-gray-50 border-2 border-gray-100 rounded-xl px-4 py-2 text-sm focus:border-sage-500 focus:outline-none">
                            <option value="">All Modules</option>
                            <?php foreach($modules as $code => $title): ?>
                                <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($code) ?> - <?= htmlspecialchars($title) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <section class="bg-white border-2 border-gray-100 rounded-3xl p-8 shadow-sm">
                    <h2 class="text-2xl font-bold mb-6 text-sage-900">S.A.G.E. Curricular Status</h2>
                    <table class="w-full text-left">
                        <thead class="border-b-2 border-gray-100">
                            <tr class="text-xs text-gray-400 uppercase tracking-widest">
                                <th class="pb-4">Module</th>
                                <th class="pb-4">Document</th>
                                <th class="pb-4">Status</th>
                                <th class="pb-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr id="empty-row" style="display: none;"><td colspan="4" class="p-12 text-center text-gray-400 font-light text-xl">No activities match selected filters.</td></tr>
                            <?php if(empty($recentAudits)): ?>
                                <tr><td colspan="4" class="p-12 text-center text-gray-400 font-light text-xl">No audits have been executed yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach($recentAudits as $a): ?>
                            <tr class="hover:bg-gray-50 transition" data-row data-faculty="<?= htmlspecialchars($a['faculty']) ?>" data-term="<?= htmlspecialchars($a['term']) ?>" data-lecturer="<?= htmlspecialchars($a['lecturer']) ?>" data-module="<?= htmlspecialchars($a['module_code']) ?>">
                                <td class="py-4">
                                    <span class="block font-bold text-sage-900"><?= htmlspecialchars($a['module_code']) ?></span>
                                    <span class="block text-xs text-gray-400 mt-1"><?= htmlspecialchars($a['module_title']) ?></span>
                                </td>
                                <td class="py-4">
                                    <span class="block text-sage-500 font-bold uppercase text-xs tracking-widest"><?= str_replace('_', ' ', $a['doc_type']) ?></span>
                                    <span class="block text-xs text-gray-400 font-mono mt-1 max-w-[180px] truncate" title="<?= htmlspecialchars($a['filename']) ?>"><?= htmlspecialchars($a['filename']) ?></span>
                                </td>
                                <td class="py-4">
                                    <?php if($a['status'] === 'checked'): ?>
                                        <?php $isOk = intval($a['score'] ?? 0) >= 60; ?>
                                        <?php if($isOk): ?>
                                            <span class="bg-green-50 text-green-700 px-3 py-1 rounded-full text-xs font-bold tracking-widest uppercase border border-green-200">OK</span>
                                        <?php else: ?>
                                            <span class="bg-amber-50 text-amber-700 px-3 py-1 rounded-full text-xs font-bold tracking-widest uppercase border border-amber-200">Need Review</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="bg-gray-100 text-gray-400 px-3 py-1 rounded-full text-xs font-bold tracking-widest uppercase border border-gray-200">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 text-right">
                                    <?php if($a['status'] === 'checked'): ?>
                                        <a href="report.php?id=<?= $a['id'] ?>" class="text-xs font-bold text-sage-500 hover:text-sage-900 uppercase tracking-widest transition">View Report →</a>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-300 uppercase tracking-widest">Awaiting Ghost</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>

        <!-- Terminal Chat Panel -->
        <div style="height:85vh; position: fixed; right: 30px; max-width: 800px; min-width: 400px; width:40%" class="w-96 flex flex-col bg-gray-900 border-2 border-sage-500 rounded-lg overflow-hidden shadow-2xl">
            <!-- Header -->
            <div class="bg-sage-500 px-4 py-3 border-b border-sage-900 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <span class="text-sage-900 font-bold text-sm tracking-widest uppercase">sage_chat</span>
                    <span id="providerBadge" class="text-xs font-bold bg-sage-900 text-sage-500 px-2 py-0.5 rounded border border-sage-500">FIREWORKS</span>
                </div>
                <button id="clearChatBtn" class="text-xs text-sage-900 opacity-70 hover:opacity-100 font-bold transition" style="background-color: transparent">CLEAR</button>
            </div>

            <!-- Chat Output -->
            <div id="chatOutput" class="flex-1 overflow-y-auto p-4 space-y-4 font-mono text-sm text-green-400 bg-gray-900 scroll-smooth">
                <div class="text-green-600 text-xs">> sage initialized @ <?= date('H:i:s') ?></div>
                <div class="text-gray-500 text-xs">type your query below...</div>
            </div>

            <!-- Status Indicator -->
            <div id="chatStatus" class="hidden px-4 py-1 bg-gray-900 text-[10px] font-mono text-cyan-500 animate-pulse">
                > SAGE is active...
            </div>

            <!-- Input Area -->
            <div class="bg-gray-800 border-t border-sage-500 p-4">
                <div class="flex items-start gap-2 text-green-400 font-mono text-sm">
                    <span class="text-green-600 mt-1 font-bold">></span>
                    <textarea 
                        id="chatInput" 
                        rows="1"
                        placeholder="_ " 
                        class="flex-1 bg-transparent outline-none text-green-400 placeholder-green-800 font-mono text-sm resize-none overflow-hidden"
                        autocomplete="off"
                    ></textarea>
                </div>
            </div>
        </div>
    </div>

    <script>
        const chatOutput = document.getElementById('chatOutput');
        const chatInput = document.getElementById('chatInput');
        const clearChatBtn = document.getElementById('clearChatBtn');
        const chatStatus = document.getElementById('chatStatus');
        let isWaiting = false;
        let chatHistory = [];
        const STORAGE_KEY = 'sage_chat_history';
        const CONTEXT_WINDOW = <?= CHAT_CONTEXT_WINDOW; ?>;
        const SAGE_SYSTEM_PROMPT = <?= json_encode($sage_system_prompt) ?>;

        // Auto-expand textarea
        chatInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // Load chat history from localStorage
        function loadHistory() {
            const stored = localStorage.getItem(STORAGE_KEY);
            chatHistory = stored ? JSON.parse(stored) : [];
            renderChat();
        }

        function saveHistory() {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(chatHistory));
        }

        function renderChat() {
            chatOutput.innerHTML = '<div class="text-green-600 text-xs">> sage online</div>';
            chatHistory.forEach(msg => {
                appendMessageToUI(msg.role, msg.content);
            });
            scrollToBottom();
        }

        function scrollToBottom() {
            chatOutput.scrollTop = chatOutput.scrollHeight;
        }

        function addMessage(role, text) {
            chatHistory.push({ role, content: text });
            saveHistory();
            appendMessageToUI(role, text);
            scrollToBottom();
        }

        function appendMessageToUI(role, text) {
            const div = document.createElement('div');
            // Ensure text is always a string (Puter might return objects or arrays)
            let textContent = '';
            if (typeof text === 'string') {
                textContent = text;
            } else if (Array.isArray(text)) {
                // If it's an array, join it or extract first element text
                textContent = text.map(item => typeof item === 'string' ? item : (item?.content || item?.text || JSON.stringify(item))).join('\n');
            } else if (typeof text === 'object' && text !== null) {
                textContent = text?.content || text?.text || JSON.stringify(text);
            } else {
                textContent = String(text);
            }
            
            if (role === 'user') {
                div.className = 'text-cyan-300';
                div.innerHTML = `<span class="text-cyan-600 font-bold">> </span>${escapeHtml(textContent)}`;
            } else if (role === 'error') {
                div.className = 'text-red-400 text-xs border-l-2 border-red-500 pl-2 py-1 bg-red-900/10';
                div.innerHTML = `<span class="font-bold">SYSTEM ERROR: </span>${escapeHtml(textContent)}`;
            } else if (role === 'info') {
                div.className = 'text-yellow-400 text-xs border-l-2 border-yellow-500 pl-2 py-1 bg-yellow-900/10';
                div.innerHTML = `<span class="font-bold">INFO: </span>${escapeHtml(textContent)}`;
            } else {
                div.className = 'prose-sage';
                // Render Markdown for assistant - ensure it's a string first
                try {
                    div.innerHTML = marked.parse(String(textContent));
                } catch (e) {
                    console.error('Marked parsing error:', e, 'Input:', textContent);
                    div.textContent = textContent;
                }
            }
            chatOutput.appendChild(div);
        }

        function escapeHtml(text) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        clearChatBtn.addEventListener('click', () => {
            if (confirm('Clear all chat history?')) {
                chatHistory = [];
                localStorage.removeItem(STORAGE_KEY);
                renderChat();
                chatInput.focus();
            }
        });


        chatInput.addEventListener('keydown', async (e) => {
            // Send on Enter ONLY if Shift and Ctrl are NOT pressed
            if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey) {
                e.preventDefault();
                if (isWaiting) return;
                
                const message = chatInput.value.trim();
                if (!message) return;

                isWaiting = true;
                chatInput.value = '';
                chatInput.style.height = 'auto'; // Reset height
                chatInput.disabled = true;
                chatStatus.classList.remove('hidden');

                addMessage('user', message);

                try {
                    const contextMessages = chatHistory.slice(0, -1).slice(-CONTEXT_WINDOW);

                    chatStatus.textContent = '> SAGE is thinking...';

                    const res = await fetch('nexus.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ 
                            message: btoa(unescape(encodeURIComponent(message))), 
                            context: btoa(unescape(encodeURIComponent(JSON.stringify(contextMessages)))),
                            encoded: 'true'
                        })
                    });

                    const text = await res.text();
                    
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error("Server output was not JSON:", text);
                        addMessage('error', `Server returned garbage. Check console. Snippet: ${text.substring(0, 100)}`);
                        isWaiting = false;
                        chatInput.disabled = false;
                        return;
                    }
                    
                    if (data.error) {
                        addMessage('error', data.error);
                    } else {
                        addMessage('assistant', data.response);
                    }
                } catch (err) {
                    chatStatus.classList.add('hidden');
                    console.error("Inference error:", err);
                    addMessage('error', `Fireworks inference failed: ${err.message}. Check browser console for details.`);
                }

                chatStatus.classList.add('hidden');
                isWaiting = false;
                chatInput.disabled = false;
                chatInput.focus();
            }
        });

        loadHistory();
        chatInput.focus();

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