<?php
require_once __DIR__ . '/app.php';

start_session();

if (isset($_GET['locale'])) {
    set_locale($_GET['locale']);
    $q = isset($_GET['next']) ? '?next=' . rawurlencode((string) $_GET['next']) : '';
    header('Location: ' . rtrim(APP_URL, '/') . '/auth.php' . $q);
    exit;
}

$next = safe_next($_POST['next'] ?? $_GET['next'] ?? '');

// Already logged in (session or remember-me cookie)
if (session_user()) {
    header('Location: ' . $next);
    exit;
}

$error   = '';
$tab     = 'login';
$reg_open = registration_open();
$locale   = current_locale();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $error = t('auth.session_expired');
        $tab   = ($action === 'register' && $reg_open) ? 'register' : 'login';
    } elseif ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $stmt  = db()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password_hash'])) {
            login_user($user);
            header('Location: ' . $next);
            exit;
        }
        $error = t('auth.invalid_credentials');
        $tab   = 'login';

    } elseif ($action === 'register') {
        $tab = 'register';
        if (!$reg_open) {
            $error = t('auth.registration_closed');
            $tab   = 'login';
        } else {
            $email = trim($_POST['email'] ?? '');
            $pass  = $_POST['password'] ?? '';
            $pass2 = $_POST['password2'] ?? '';

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = t('auth.invalid_email');
            } elseif (strlen($pass) < 8) {
                $error = t('auth.password_short');
            } elseif ($pass !== $pass2) {
                $error = t('auth.password_mismatch');
            } else {
                try {
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $stmt = db()->prepare('INSERT INTO users (email, password_hash, locale) VALUES (?, ?, ?)');
                    $stmt->execute([$email, $hash, current_locale()]);
                    login_user(['id' => db()->lastInsertId(), 'email' => $email, 'notify_minutes' => 5, 'locale' => current_locale()]);
                    header('Location: ' . $next);
                    exit;
                } catch (PDOException $e) {
                    $error = t('auth.email_taken');
                }
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="<?= h($locale) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'] }
                }
            }
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/app.css?v=8">
</head>
<body class="auth-shell min-h-screen flex items-center justify-center p-6 font-sans">
    <div class="auth-card">
        <div class="text-center mb-7">
            <div class="brand justify-center mb-3">
                <span class="brand-mark" aria-hidden="true">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.6" d="M5 12.5l5 5L20 7"/></svg>
                </span>
            </div>
            <div class="brand-name text-2xl"><?= h(APP_NAME) ?></div>
            <p class="text-sm text-slate-400 mt-1.5"><?= h(t('auth.subtitle')) ?></p>
        </div>

        <?php if ($reg_open): ?>
        <div class="auth-tabs">
            <button type="button" onclick="switchTab('login')" id="tab-login" class="auth-tab <?= $tab==='login' ? 'is-on' : '' ?>"><?= h(t('auth.sign_in')) ?></button>
            <button type="button" onclick="switchTab('register')" id="tab-register" class="auth-tab <?= $tab==='register' ? 'is-on' : '' ?>"><?= h(t('auth.create_account')) ?></button>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="auth-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="form-login" class="<?= $tab==='register' ? 'hidden' : '' ?> space-y-4">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="next" value="<?= h($next) ?>">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <div>
                <label class="block text-sm font-medium mb-1.5"><?= h(t('auth.email')) ?></label>
                <input type="email" name="email" required autocomplete="email"
                    class="w-full"
                    value="<?= h($_POST['email'] ?? '') ?>">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1.5"><?= h(t('auth.password')) ?></label>
                <input type="password" name="password" required autocomplete="current-password"
                    class="w-full">
            </div>
            <button type="submit" class="btn-primary w-full py-2.5 mt-1"><?= h(t('auth.sign_in')) ?></button>
        </form>

        <?php if ($reg_open): ?>
        <form method="POST" id="form-register" class="<?= $tab==='login' ? 'hidden' : '' ?> space-y-4">
            <input type="hidden" name="action" value="register">
            <input type="hidden" name="next" value="<?= h($next) ?>">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <div>
                <label class="block text-sm font-medium mb-1.5"><?= h(t('auth.email')) ?></label>
                <input type="email" name="email" required autocomplete="email"
                    class="w-full"
                    value="<?= h($_POST['email'] ?? '') ?>">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1.5"><?= h(t('auth.password')) ?> <span class="text-slate-400 font-normal"><?= h(t('auth.password_min')) ?></span></label>
                <input type="password" name="password" required autocomplete="new-password"
                    class="w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1.5"><?= h(t('auth.confirm_password')) ?></label>
                <input type="password" name="password2" required autocomplete="new-password"
                    class="w-full">
            </div>
            <button type="submit" class="btn-primary w-full py-2.5 mt-1"><?= h(t('auth.create_account')) ?></button>
        </form>
        <?php endif; ?>

        <div class="lang-switch">
            <a href="?locale=en&amp;next=<?= h(rawurlencode($next)) ?>" class="<?= $locale==='en' ? 'is-on' : '' ?>">EN</a>
            <span aria-hidden="true">·</span>
            <a href="?locale=de&amp;next=<?= h(rawurlencode($next)) ?>" class="<?= $locale==='de' ? 'is-on' : '' ?>">DE</a>
        </div>
    </div>
    <script>
    function switchTab(tab) {
        const login = document.getElementById('form-login');
        const reg   = document.getElementById('form-register');
        if (login) login.classList.toggle('hidden', tab !== 'login');
        if (reg)   reg.classList.toggle('hidden', tab !== 'register');
        const tabLogin = document.getElementById('tab-login');
        const tabReg   = document.getElementById('tab-register');
        if (tabLogin) tabLogin.className = 'auth-tab' + (tab === 'login' ? ' is-on' : '');
        if (tabReg)   tabReg.className   = 'auth-tab' + (tab === 'register' ? ' is-on' : '');
    }
    </script>
</body>
</html>
