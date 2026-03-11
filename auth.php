<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in
if (!empty($_SESSION['user'])) {
    header('Location: ' . APP_URL . '/');
    exit;
}

$error = '';
$tab   = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $stmt  = db()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password_hash'])) {
            $_SESSION['user'] = ['id' => $user['id'], 'email' => $user['email'], 'notify_minutes' => $user['notify_minutes']];
            header('Location: ' . APP_URL . '/');
            exit;
        }
        $error = 'Invalid email or password.';
        $tab   = 'login';

    } elseif ($action === 'register') {
        $tab   = 'register';
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $pass2 = $_POST['password2'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($pass) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($pass !== $pass2) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = db()->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
                $stmt->execute([$email, $hash]);
                $id = db()->lastInsertId();
                $_SESSION['user'] = ['id' => $id, 'email' => $email, 'notify_minutes' => 5];
                header('Location: ' . APP_URL . '/');
                exit;
            } catch (PDOException $e) {
                $error = 'That email is already registered.';
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #f8fafc; }
        input:focus { outline: none; box-shadow: 0 0 0 2px #6366f1; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <span class="text-3xl font-bold text-indigo-600"><?= h(APP_NAME) ?></span>
        </div>

        <!-- Tab switcher -->
        <div class="flex bg-white rounded-xl shadow-sm border border-gray-200 mb-6 p-1">
            <button onclick="switchTab('login')"    id="tab-login"    class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors <?= $tab==='login'    ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-gray-700' ?>">Sign in</button>
            <button onclick="switchTab('register')" id="tab-register" class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors <?= $tab==='register' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-gray-700' ?>">Create account</button>
        </div>

        <?php if ($error): ?>
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"><?= h($error) ?></div>
        <?php endif; ?>

        <!-- Login form -->
        <form method="POST" id="form-login" class="<?= $tab==='register' ? 'hidden' : '' ?> bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
            <input type="hidden" name="action" value="login">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" required autocomplete="email"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                    value="<?= h($_POST['email'] ?? '') ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="password" required autocomplete="current-password"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors">
                Sign in
            </button>
        </form>

        <!-- Register form -->
        <form method="POST" id="form-register" class="<?= $tab==='login' ? 'hidden' : '' ?> bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
            <input type="hidden" name="action" value="register">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" required autocomplete="email"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                    value="<?= h($_POST['email'] ?? '') ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-gray-400 font-normal">(min 8 chars)</span></label>
                <input type="password" name="password" required autocomplete="new-password"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm password</label>
                <input type="password" name="password2" required autocomplete="new-password"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors">
                Create account
            </button>
        </form>
    </div>
    <script>
    function switchTab(tab) {
        document.getElementById('form-login').classList.toggle('hidden', tab !== 'login');
        document.getElementById('form-register').classList.toggle('hidden', tab !== 'register');
        document.getElementById('tab-login').className    = 'flex-1 py-2 rounded-lg text-sm font-medium transition-colors ' + (tab === 'login'    ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-gray-700');
        document.getElementById('tab-register').className = 'flex-1 py-2 rounded-lg text-sm font-medium transition-colors ' + (tab === 'register' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-gray-700');
    }
    </script>
</body>
</html>
