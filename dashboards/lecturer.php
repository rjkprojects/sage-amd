<?php
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'lecturer') die("Access Denied.");
global $pdo;
$reports = $pdo->query("SELECT a.*, m.module_code, m.module_title FROM audit_results a JOIN modules m ON a.module_code = m.module_code WHERE a.status = 'checked' ORDER BY a.created_at DESC")->fetchAll();

$files = [];
foreach ($reports as $r) {
    $parts = explode('/', str_replace('\\', '/', $r['file_path']));
    if (count($parts) >= 7) {
        $files[] = [
            'id' => $r['id'],
            'path' => $r['file_path'],
            'term' => $parts[1],
            'faculty' => $parts[2],
            'lecturer' => $parts[3],
            'module_code' => $r['module_code'],
            'module_title' => $r['module_title'],
            'doc_type' => $r['doc_type'],
            'filename' => $parts[6],
            'score' => $r['score']
        ];
    } else {
        $files[] = [
            'id' => $r['id'],
            'path' => $r['file_path'],
            'term' => 'Unknown Term',
            'faculty' => 'Unknown Faculty',
            'lecturer' => 'Unknown Lecturer',
            'module_code' => $r['module_code'],
            'module_title' => $r['module_title'],
            'doc_type' => $r['doc_type'],
            'filename' => basename($r['file_path']),
            'score' => $r['score']
        ];
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
    <title>S.A.G.E. | Lecturer</title>
    <link rel="stylesheet" href="css/output.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;600;700&display=swap" rel="stylesheet">

</head>
<body class="bg-gray-50 text-gray-900 font-sans min-h-screen p-8 md:p-16">
    <div class="max-w-6xl mx-auto">
        <header class="flex justify-between items-end mb-12 border-b-2 border-sage-500 pb-6">
            <div><h1 class="text-6xl font-bold tracking-tighter text-sage-900">Lecturer</h1><p class="text-sage-500 text-xl mt-2 font-light">Feedback & Audit Reports</p></div>
            <a href="./?logout=true" class="bg-red-50 text-red-500 hover:bg-red-500 hover:text-white font-bold uppercase tracking-widest text-xs px-6 py-3 rounded-xl transition">Logout</a>
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

        <div id="no-cards" class="hidden bg-white border-2 border-gray-100 rounded-3xl p-12 text-center text-gray-400 font-light text-xl mb-12">
            No reports match selected filters.
        </div>

        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach($files as $res): ?>
                <div class="bg-white border-2 border-sage-100 rounded-3xl p-8 flex flex-col justify-between" data-card data-faculty="<?= htmlspecialchars($res['faculty']) ?>" data-term="<?= htmlspecialchars($res['term']) ?>" data-lecturer="<?= htmlspecialchars($res['lecturer']) ?>" data-module="<?= htmlspecialchars($res['module_code']) ?>">
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-2xl font-bold text-sage-900"><?= $res['module_code'] ?></h3>
                                <p class="text-xs text-gray-400 mt-1 font-bold"><?= htmlspecialchars($res['module_title']) ?></p>
                            </div>
                            <?php $isOk = intval($res['score'] ?? 0) >= 60; ?>
                            <?php if($isOk): ?>
                                <span class="bg-green-50 text-green-700 px-3 py-1 rounded-full font-bold text-xs uppercase tracking-widest border border-green-200 flex-shrink-0">OK</span>
                            <?php else: ?>
                                <span class="bg-amber-50 text-amber-700 px-3 py-1 rounded-full font-bold text-xs uppercase tracking-widest border border-amber-200 flex-shrink-0">Need Review</span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100 space-y-1">
                            <span class="block text-sage-500 text-xs font-bold uppercase tracking-widest"><?= str_replace('_', ' ', $res['doc_type']) ?></span>
                            <span class="block text-gray-500 text-sm font-mono truncate" title="<?= htmlspecialchars($res['filename']) ?>"><?= htmlspecialchars($res['filename']) ?></span>
                        </div>
                    </div>
                    <a href="report?id=<?= $res['id'] ?>" class="block mt-6 w-full bg-sage-50 hover:bg-sage-500 text-sage-900 hover:text-white text-center font-bold uppercase tracking-widest text-xs py-3 rounded-xl transition border border-sage-200">View Report</a>
                </div>
            <?php endforeach; ?>
        </section>
    </div>

    <script>
        function applyFilters() {
            const faculty = document.getElementById('filter-faculty').value;
            const term = document.getElementById('filter-term').value;
            const lecturer = document.getElementById('filter-lecturer').value;
            const module = document.getElementById('filter-module').value;

            const cards = document.querySelectorAll('[data-card]');
            let count = 0;
            cards.forEach(card => {
                const matchesFaculty = !faculty || card.getAttribute('data-faculty') === faculty;
                const matchesTerm = !term || card.getAttribute('data-term') === term;
                const matchesLecturer = !lecturer || card.getAttribute('data-lecturer') === lecturer;
                const matchesModule = !module || card.getAttribute('data-module') === module;

                if (matchesFaculty && matchesTerm && matchesLecturer && matchesModule) {
                    card.style.display = '';
                    count++;
                } else {
                    card.style.display = 'none';
                }
            });

            const noCards = document.getElementById('no-cards');
            if (noCards) {
                if (count === 0) {
                    noCards.classList.remove('hidden');
                } else {
                    noCards.classList.add('hidden');
                }
            }
        }
    </script>
</body>
</html>