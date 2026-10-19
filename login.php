<?php
require_once 'config.php';
require_once 'auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Username atau kata sandi tidak valid.';
        }
    } else {
        $error = 'Harap isi username dan kata sandi.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Todo App</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .login-wrapper {
            min-height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            width: 100%;
            max-width: 440px;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            padding: 36px 32px;
            transition: var(--transition);
        }
        .login-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .login-badge {
            background: var(--color-primary-light);
            color: var(--color-primary);
            padding: 4px 10px;
            border-radius: var(--radius-full);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .login-card h1 {
            margin: 0 0 6px;
            font-size: 24px;
            font-weight: 700;
            color: var(--color-text);
            letter-spacing: -0.02em;
        }
        .login-card .subtitle {
            margin: 0 0 24px;
            color: var(--color-muted);
            font-size: 13.5px;
            line-height: 1.5;
        }
        .login-error {
            margin-bottom: 20px;
            padding: 12px 14px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--color-danger-border);
            background: var(--color-danger-bg);
            color: var(--color-danger-text);
            font-size: 13px;
            font-weight: 500;
        }
        .login-card button[type="submit"] {
            width: 100%;
            min-height: 44px;
            border: 1px solid var(--color-primary);
            background: var(--color-primary);
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 600;
            border-radius: var(--radius-sm);
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            margin-top: 10px;
        }
        .login-card button[type="submit"]:hover {
            background: var(--color-primary-hover);
            border-color: var(--color-primary-hover);
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }
        .login-card button[type="submit"]:active {
            transform: translateY(0);
        }
        .login-demo-box {
            margin-top: 24px;
            padding: 14px;
            background: var(--color-surface-muted);
            border: 1px solid var(--color-border);
            border-radius: var(--radius);
            font-size: 12px;
            color: var(--color-muted);
            line-height: 1.6;
        }
        .login-demo-box strong {
            color: var(--color-text);
            display: block;
            margin-bottom: 4px;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.05em;
        }
        .login-demo-box code {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 11.5px;
            color: var(--color-text);
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-brand">
                <span class="login-badge">ITS PWEB 2026</span>
            </div>
            <h1>Masuk ke Akun</h1>
            <p class="subtitle">Todo App Enterprise - Dafa Dega Wijaya (5025251195)</p>

            <?php if ($error): ?>
                <div class="login-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Masukkan username Anda..." required autocomplete="username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="password">Kata Sandi</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                </div>
                <button type="submit">Masuk ke Dashboard</button>
            </form>

            <div class="login-demo-box">
                <strong>Akun Pengujian Demo:</strong>
                <div>Akun 1: <code>dafa</code> &nbsp;|&nbsp; Sandi: <code>password</code></div>
                <div>Akun 2: <code>teman</code> &nbsp;|&nbsp; Sandi: <code>password</code></div>
            </div>
        </div>
    </div>
    <script>
    try {
        if (localStorage.getItem('todoMode') === 'dark') {
            document.body.classList.add('dark-mode');
        }
    } catch (e) {}
    </script>
</body>
</html>
