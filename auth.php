<?php
require_once __DIR__ . '/app.php';

start_session();

if (isset($_GET['locale'])) {
    set_locale($_GET['locale']);
    $q = [];
    if (isset($_GET['next'])) $q['next'] = (string) $_GET['next'];
    if (isset($_GET['reset'])) $q['reset'] = (string) $_GET['reset'];
    if (isset($_GET['forgot'])) $q['forgot'] = '1';
    $qs = $q ? '?' . http_build_query($q) : '';
    header('Location: ' . rtrim(APP_URL, '/') . '/auth.php' . $qs);
    exit;
}

$next = safe_next($_POST['next'] ?? $_GET['next'] ?? '');

// Already logged in (session or remember-me cookie)
if (session_user()) {
    header('Location: ' . $next);
    exit;
}

$error   = '';
$notice  = '';
$tab     = 'login';
$reg_open = registration_open();
$locale   = current_locale();
$reset_token = strtolower(trim((string) ($_POST['reset_token'] ?? $_GET['reset'] ?? '')));

if (isset($_GET['forgot'])) {
    $tab = 'forgot';
}
if ($reset_token !== '') {
    $tab = 'reset';
    if (!password_reset_user($reset_token)) {
        $error = t('auth.reset_invalid');
        $tab = 'forgot';
        $reset_token = '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $error = t('auth.session_expired');
        $tab   = ($action === 'register' && $reg_open) ? 'register' : (($action === 'reset_request' || $action === 'reset_password') ? $tab : 'login');
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
    } elseif ($action === 'reset_request') {
        $tab = 'forgot';
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = t('auth.invalid_email');
        } else {
            password_reset_request($email);
            $notice = t('auth.reset_sent');
        }
    } elseif ($action === 'reset_password') {
        $tab = 'reset';
        $pass  = $_POST['password'] ?? '';
        $pass2 = $_POST['password2'] ?? '';
        $user  = $reset_token !== '' ? password_reset_user($reset_token) : null;
        if (!$user) {
            $error = t('auth.reset_invalid');
            $tab = 'forgot';
            $reset_token = '';
        } elseif (strlen($pass) < 8) {
            $error = t('auth.password_short');
        } elseif ($pass !== $pass2) {
            $error = t('auth.password_mismatch');
        } elseif (!password_reset_complete((int) $user['id'], $reset_token, $pass)) {
            $error = t('auth.reset_invalid');
            $tab = 'forgot';
            $reset_token = '';
        } else {
            header('Location: ' . rtrim(APP_URL, '/') . '/auth.php?updated=1');
            exit;
        }
    }
}

if (isset($_GET['updated'])) {
    $notice = t('auth.reset_updated');
    $tab = 'login';
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

        <?php if ($reg_open && ($tab === 'login' || $tab === 'register')): ?>
        <div class="auth-tabs">
            <button type="button" onclick="switchTab('login')" id="tab-login" class="auth-tab <?= $tab==='login' ? 'is-on' : '' ?>"><?= h(t('auth.sign_in')) ?></button>
            <button type="button" onclick="switchTab('register')" id="tab-register" class="auth-tab <?= $tab==='register' ? 'is-on' : '' ?>"><?= h(t('auth.create_account')) ?></button>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="auth-error"><?= h($error) ?></div>
        <?php endif; ?>
        <?php if ($notice): ?>
        <div class="auth-error" style="background:#ecfdf5;border-color:#a7f3d0;color:#047857"><?= h($notice) ?></div>
        <?php endif; ?>

        <form method="POST" id="form-login" class="<?= $tab==='login' ? '' : 'hidden' ?> space-y-4">
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
            <p class="text-center text-sm pt-1">
                <a href="?forgot=1&amp;next=<?= h(rawurlencode($next)) ?>" class="text-slate-500 hover:text-slate-800"><?= h(t('auth.forgot_password')) ?></a>
            </p>
        </form>

        <form method="POST" id="form-forgot" class="<?= $tab==='forgot' ? '' : 'hidden' ?> space-y-4">
            <input type="hidden" name="action" value="reset_request">
            <input type="hidden" name="next" value="<?= h($next) ?>">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <p class="text-sm text-slate-500"><?= h(t('auth.reset_hint')) ?></p>
            <div>
                <label class="block text-sm font-medium mb-1.5"><?= h(t('auth.email')) ?></label>
                <input type="email" name="email" required autocomplete="email"
                    class="w-full"
                    value="<?= h($_POST['email'] ?? '') ?>">
            </div>
            <button type="submit" class="btn-primary w-full py-2.5 mt-1"><?= h(t('auth.reset_send')) ?></button>
            <p class="text-center text-sm pt-1">
                <a href="auth.php?next=<?= h(rawurlencode($next)) ?>" class="text-slate-500 hover:text-slate-800"><?= h(t('auth.back_to_sign_in')) ?></a>
            </p>
        </form>

        <form method="POST" id="form-reset" class="<?= $tab==='reset' ? '' : 'hidden' ?> space-y-4">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="next" value="<?= h($next) ?>">
            <input type="hidden" name="reset_token" value="<?= h($reset_token) ?>">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <div>
                <label class="block text-sm font-medium mb-1.5"><?= h(t('auth.new_password')) ?> <span class="text-slate-400 font-normal"><?= h(t('auth.password_min')) ?></span></label>
                <input type="password" name="password" required autocomplete="new-password" class="w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1.5"><?= h(t('auth.confirm_password')) ?></label>
                <input type="password" name="password2" required autocomplete="new-password" class="w-full">
            </div>
            <button type="submit" class="btn-primary w-full py-2.5 mt-1"><?= h(t('auth.reset_password')) ?></button>
        </form>

        <?php if ($reg_open): ?>
        <form method="POST" id="form-register" class="<?= $tab==='register' ? '' : 'hidden' ?> space-y-4">
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
            <a href="?locale=en&amp;next=<?= h(rawurlencode($next)) ?><?= $tab==='forgot' ? '&amp;forgot=1' : '' ?><?= $reset_token !== '' ? '&amp;reset=' . h(rawurlencode($reset_token)) : '' ?>" class="<?= $locale==='en' ? 'is-on' : '' ?>">EN</a>
            <span aria-hidden="true">·</span>
            <a href="?locale=de&amp;next=<?= h(rawurlencode($next)) ?><?= $tab==='forgot' ? '&amp;forgot=1' : '' ?><?= $reset_token !== '' ? '&amp;reset=' . h(rawurlencode($reset_token)) : '' ?>" class="<?= $locale==='de' ? 'is-on' : '' ?>">DE</a>
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
