<?php
require_once 'handshake.php';

if (isset($_GET['logout'])) { 
    session_destroy(); 
    header("Location: ./"); 
    exit(); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$_POST['username']]);
    $user = $stmt->fetch();

    // Swapped password_verify for a straight string match
    if ($user && $_POST['password'] === $user['password_hash']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        header("Location: ./");
        exit();
    }
    $error = "Bogus credentials, brah. Try again.";
}

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    $dashboard_path = "dashboards/{$role}.php";
    if (file_exists($dashboard_path)) { 
        include $dashboard_path; 
        exit(); 
    } else { 
        die("Dashboard missing for role: $role"); 
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S.A.G.E. | Login</title>
    <link rel="stylesheet" href="css/output.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50 text-gray-900 flex items-center justify-center min-h-screen p-6 font-sans">
    <div class="w-full max-w-lg">
        <h1 class="text-7xl font-bold tracking-tighter mb-2 text-sage-900">S.A.G.E.</h1>
        <p class="text-xl text-sage-500 font-light mb-12">System Academic Governance Engine</p>
        
        <?php if(isset($error)): ?><div class="bg-red-50 text-red-600 p-4 rounded-lg mb-6 text-sm font-bold"><?= $error ?></div><?php endif; ?>

        <form method="POST" class="space-y-6">
            <div><label class="block text-sm font-bold uppercase tracking-widest mb-2 text-gray-400">Username</label><input type="text" name="username" class="w-full bg-white border-2 border-gray-200 p-4 rounded-xl text-xl focus:outline-none focus:border-sage-500" required></div>
            <div><label class="block text-sm font-bold uppercase tracking-widest mb-2 text-gray-400">Password</label><input type="password" name="password" class="w-full bg-white border-2 border-gray-200 p-4 rounded-xl text-xl focus:outline-none focus:border-sage-500" required></div>
            <button type="submit" name="login" class="w-full bg-sage-900 hover:bg-sage-500 text-white font-bold text-xl py-4 rounded-xl transition-all shadow-xl shadow-sage-900/20 mt-4">Initiate Connection</button>
        </form>
    </div>
</body>
</html>