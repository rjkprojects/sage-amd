<?php
require_once 'handshake.php';
if (!isset($_SESSION['role'])) die("Access Denied.");

$stmt = $pdo->prepare("
    SELECT a.*, m.module_title, m.module_code
    FROM audit_results a
    JOIN modules m ON a.module_code = m.module_code
    WHERE a.id = ?
");
$stmt->execute([$_GET['id'] ?? 0]);
$report = $stmt->fetch();

if (!$report) die("<div style='padding:50px; font-family:sans-serif;'>Report not found, mate.</div>");

$data = json_decode($report['suggestions_json'], true);

// Determine ok / need-review from score
$score   = intval($report['score'] ?? 0);
$isOk    = $score >= 60;
$verdict = $isOk ? 'ok' : 'need-review';
$verdictLabel  = $isOk ? 'OK' : 'Need Review';
$verdictColor  = $isOk ? 'green' : 'red';
$verdictBg     = $isOk ? '#f0fdf4' : '#fef2f2';
$verdictBorder = $isOk ? '#bbf7d0' : '#fecaca';
$verdictText   = $isOk ? '#15803d' : '#dc2626';

// Fetch module learning outcomes from DB
$stmt2 = $pdo->prepare("
    SELECT outcome_number, outcome_text
    FROM module_learning_outcomes
    WHERE module_code = ?
    ORDER BY outcome_number ASC
");
$stmt2->execute([$report['module_code']]);
$learningOutcomes = $stmt2->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S.A.G.E. | Report — <?= htmlspecialchars($report['module_title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Space Grotesk"', 'sans-serif'] },
                    colors: {
                        sage: { 50: '#f4f7f5', 100: '#e1eae4', 200: '#c3d4c9', 300: '#9ab8a3', 400: '#6f9479', 500: '#8FA396', 600: '#5a7d65', 700: '#476151', 800: '#384e41', 900: '#2A362F' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-900 font-sans min-h-screen p-6 md:p-14">
<div class="max-w-5xl mx-auto space-y-8">

    <!-- BACK BUTTON -->
    <a href="./" class="inline-flex items-center gap-2 text-sage-600 font-semibold uppercase tracking-widest text-xs hover:text-sage-900 transition border-2 border-sage-200 px-4 py-2 rounded-xl bg-white shadow-sm">
        ← Back to Dashboard
    </a>

    <!-- HEADER CARD -->
    <div class="bg-white border-2 border-gray-100 rounded-3xl p-10 shadow-sm flex flex-col md:flex-row md:items-start md:justify-between gap-6">
        <div class="flex-1">
            <p class="text-xs font-bold uppercase tracking-widest text-sage-500 mb-2">
                <?= htmlspecialchars($report['module_code']) ?> &middot; <?= htmlspecialchars(basename($report['file_path'])) ?>
            </p>
            <h1 class="text-4xl md:text-5xl font-bold tracking-tighter text-sage-900 leading-tight mb-3">
                <?= htmlspecialchars($report['module_title']) ?>
            </h1>
            <p class="text-sm text-gray-400 font-light">
                Audited <?= date('d M Y, H:i', strtotime($report['created_at'])) ?>
            </p>
        </div>

        <!-- VERDICT BADGE -->
        <div class="flex-shrink-0">
            <div class="rounded-2xl px-8 py-5 text-center border-2"
                 style="background:<?= $verdictBg ?>; border-color:<?= $verdictBorder ?>">
                <p class="text-xs font-bold uppercase tracking-widest mb-1" style="color:<?= $verdictText ?>">
                    Assessment
                </p>
                <p class="text-3xl font-bold tracking-tight" style="color:<?= $verdictText ?>">
                    <?= $verdictLabel ?>
                </p>
            </div>
        </div>
    </div>

    <!-- MODULE LEARNING OUTCOMES -->
    <?php if (!empty($learningOutcomes)): ?>
    <div class="bg-white border-2 border-sage-100 rounded-3xl p-10 shadow-sm">
        <h2 class="text-xs font-bold uppercase tracking-widest text-sage-500 mb-5 pb-3 border-b-2 border-sage-100">
            Module Learning Outcomes
        </h2>
        <ol class="space-y-3">
            <?php foreach ($learningOutcomes as $lo): ?>
            <li class="flex items-start gap-3">
                <span class="flex-shrink-0 w-7 h-7 rounded-full bg-sage-100 text-sage-700 text-xs font-bold flex items-center justify-center mt-0.5">
                    <?= $lo['outcome_number'] ?>
                </span>
                <span class="text-gray-700 leading-relaxed text-sm">
                    <?= htmlspecialchars($lo['outcome_text']) ?>
                </span>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
    <?php endif; ?>

    <!-- ASSESSMENT SUMMARY -->
    <?php if (!empty($data['summary'])): ?>
    <div class="bg-sage-50 border-2 border-sage-200 rounded-3xl p-10 shadow-sm">
        <h2 class="text-xs font-bold uppercase tracking-widest text-sage-600 mb-4 pb-3 border-b-2 border-sage-200">
            Assessment Summary
        </h2>
        <p class="text-gray-700 leading-relaxed">
            <?= htmlspecialchars($data['summary']) ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- STRENGTHS + ROOM FOR IMPROVEMENT -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Strengths -->
        <div class="bg-white border-2 border-green-100 rounded-3xl p-8 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-widest text-green-600 mb-5 pb-2 border-b-2 border-green-100">
                Strengths
            </h3>
            <ul class="space-y-3">
                <?php foreach ($data['strengths'] ?? [] as $s): ?>
                <li class="flex items-start gap-3">
                    <span class="text-green-500 font-bold mt-0.5 flex-shrink-0">✓</span>
                    <span class="text-gray-700 leading-relaxed text-sm"><?= htmlspecialchars($s) ?></span>
                </li>
                <?php endforeach; ?>
                <?php if (empty($data['strengths'])): ?>
                <li class="text-gray-400 text-sm italic">No strengths recorded.</li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Room for Improvement -->
        <div class="bg-white border-2 border-amber-100 rounded-3xl p-8 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-widest text-amber-600 mb-5 pb-2 border-b-2 border-amber-100">
                Room for Improvement
            </h3>
            <ul class="space-y-3">
                <?php foreach ($data['weaknesses'] ?? [] as $w): ?>
                <li class="flex items-start gap-3">
                    <span class="text-amber-500 font-bold mt-0.5 flex-shrink-0">◦</span>
                    <span class="text-gray-700 leading-relaxed text-sm"><?= htmlspecialchars($w) ?></span>
                </li>
                <?php endforeach; ?>
                <?php if (empty($data['weaknesses'])): ?>
                <li class="text-gray-400 text-sm italic">No areas flagged.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- LIVE UPDATE SUGGESTIONS -->
    <?php if (!empty($data['suggestions'])): ?>
    <div>
        <h2 class="text-2xl font-bold mb-5 text-sage-900 tracking-tighter">Live Update Suggestions</h2>
        <div class="space-y-5">
            <?php foreach ($data['suggestions'] as $sug): ?>
            <div class="bg-white border-2 border-sage-100 rounded-3xl p-8 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-sage-50 rounded-full -translate-y-8 translate-x-8 pointer-events-none"></div>
                <h4 class="text-base font-bold mb-2 text-sage-900 relative z-10">
                    <?= htmlspecialchars($sug['issue'] ?? '') ?>
                </h4>
                <p class="text-gray-600 text-sm mb-5 leading-relaxed relative z-10">
                    <?= htmlspecialchars($sug['fix'] ?? '') ?>
                </p>
                <?php if (!empty($sug['url'])): ?>
                <a href="<?= htmlspecialchars($sug['url']) ?>" target="_blank"
                   class="inline-flex items-center gap-2 text-xs font-bold bg-sage-900 text-white px-5 py-2.5 rounded-xl hover:bg-sage-700 transition uppercase tracking-widest relative z-10">
                    🔗 View Reference
                </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
</body>
</html>