<?php
require_once 'includes/functions.php';
require_once 'includes/db.php';

if (!empty($_SESSION['user_id'])) {
    redirect('index.php');
}

$mode  = $_GET['mode'] ?? 'login'; // login | register
$error = '';
$success = '';

// Ensure users table
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    display_name TEXT,
    student_id TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Auto-create default account if none exists
$user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$default_hint = '';
if ($user_count == 0) {
    $hash = password_hash('1234', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)")
        ->execute(['admin', $hash, '同學']);
    $default_hint = '首次使用，已建立預設帳號：admin / 1234';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']      = $user['id'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['display_name'] = $user['display_name'] ?? $user['username'];
            redirect('index.php');
        } else {
            $error = '帳號或密碼錯誤';
        }

    } elseif ($action === 'register') {
        $username     = trim($_POST['username'] ?? '');
        $display_name = trim($_POST['display_name'] ?? '');
        $student_id   = trim($_POST['student_id'] ?? '');
        $password     = $_POST['password'] ?? '';
        $password2    = $_POST['password2'] ?? '';

        if (strlen($username) < 3) {
            $error = '帳號至少需要 3 個字元';
        } elseif (!preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
            $error = '帳號只能使用英文、數字、底線或連字號';
        } elseif (strlen($password) < 4) {
            $error = '密碼至少需要 4 個字元';
        } elseif ($password !== $password2) {
            $error = '兩次密碼不一致';
        } else {
            $exists = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $exists->execute([$username]);
            if ($exists->fetchColumn()) {
                $error = '此帳號已被使用，請換一個';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (username, password_hash, display_name, student_id) VALUES (?,?,?,?)")
                    ->execute([$username, $hash, $display_name ?: $username, $student_id]);
                $new_id = $pdo->lastInsertId();
                $_SESSION['user_id']      = $new_id;
                $_SESSION['username']     = $username;
                $_SESSION['display_name'] = $display_name ?: $username;
                redirect('index.php');
            }
        }
        $mode = 'register';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $mode === 'register' ? '註冊' : '登入' ?> · ZENO 知序</title>
<link rel="icon" type="image/png" href="data/zeno-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root {
    --bg:#0d0f14;--bg2:#13161e;--bg3:#1a1e28;
    --border:#242836;--text:#e8ecf4;--text2:#8892aa;--text3:#555e74;
    --accent:#5b7fff;--accent2:#7c9fff;--green:#22c55e;--red:#ef4444;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Noto Sans TC',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;font-size:14px;}
.wrap{width:min(420px,94vw);}
.logo{text-align:center;margin-bottom:28px;}
.logo img{width:52px;height:52px;border-radius:14px;margin-bottom:14px;}
.logo h1{font-family:'Space Mono',monospace;font-size:22px;font-weight:700;letter-spacing:2px;}
.logo h1 span{color:var(--accent);}
.logo p{font-size:11px;color:var(--text3);letter-spacing:2.5px;text-transform:uppercase;margin-top:4px;}
/* Tab */
.tabs{display:flex;border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:24px;}
.tab{flex:1;padding:10px;text-align:center;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;transition:.15s;color:var(--text2);background:var(--bg3);}
.tab.active{background:var(--accent);color:white;}
/* Card */
.card{background:var(--bg2);border:1px solid var(--border);border-radius:16px;padding:28px;}
.form-group{margin-bottom:16px;}
.form-label{display:block;font-size:12px;color:var(--text2);margin-bottom:7px;font-weight:500;letter-spacing:.3px;}
.form-input{width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:9px;padding:11px 14px;color:var(--text);font-family:'Noto Sans TC',sans-serif;font-size:14px;outline:none;transition:border-color .15s;}
.form-input:focus{border-color:var(--accent);}
.form-hint{font-size:11px;color:var(--text3);margin-top:5px;}
.btn{width:100%;background:var(--accent);color:white;border:none;border-radius:9px;padding:12px;font-size:14px;font-weight:600;font-family:'Noto Sans TC',sans-serif;cursor:pointer;transition:.15s;margin-top:4px;}
.btn:hover{background:var(--accent2);}
.error-msg{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:var(--red);border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:18px;}
.hint-msg{background:rgba(91,127,255,.08);border:1px solid rgba(91,127,255,.25);color:var(--accent2);border-radius:8px;padding:10px 14px;font-size:12px;margin-bottom:18px;text-align:center;}
.footer{text-align:center;margin-top:18px;font-size:11px;color:var(--text3);}
.divider{display:flex;align-items:center;gap:12px;margin:14px 0;}
.divider-line{flex:1;height:1px;background:var(--border);}
.divider-txt{font-size:11px;color:var(--text3);}
</style>
</head>
<body>
<div class="wrap">
    <div class="logo">
        <img src="data/zeno-logo.png" alt="ZENO">
        <h1>知序 <span>ZENO</span></h1>
        <p>學習管理系統</p>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <a href="?mode=login"    class="tab <?= $mode==='login'    ?'active':'' ?>">登入</a>
        <a href="?mode=register" class="tab <?= $mode==='register' ?'active':'' ?>">註冊</a>
    </div>

    <div class="card">
        <?php if ($error): ?>
        <div class="error-msg"><?= h($error) ?></div>
        <?php endif; ?>
        <?php if ($default_hint && $mode === 'login'): ?>
        <div class="hint-msg"><?= h($default_hint) ?></div>
        <?php endif; ?>

        <?php if ($mode === 'login'): ?>
        <!-- LOGIN FORM -->
        <form method="post" autocomplete="off">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
                <label class="form-label">帳號</label>
                <input class="form-input" name="username" type="text" required autofocus
                    value="<?= h($_POST['username'] ?? '') ?>" placeholder="輸入帳號">
            </div>
            <div class="form-group">
                <label class="form-label">密碼</label>
                <input class="form-input" name="password" type="password" required placeholder="輸入密碼">
            </div>
            <button type="submit" class="btn">登入</button>
        </form>

        <div class="divider"><div class="divider-line"></div><div class="divider-txt">還沒有帳號？</div><div class="divider-line"></div></div>
        <a href="?mode=register" style="display:block;text-align:center;color:var(--accent);font-size:13px;text-decoration:none">前往註冊 →</a>

        <?php else: ?>
        <!-- REGISTER FORM -->
        <form method="post" autocomplete="off">
            <input type="hidden" name="action" value="register">
            <div class="form-group">
                <label class="form-label">帳號 *</label>
                <input class="form-input" name="username" type="text" required autofocus
                    value="<?= h($_POST['username'] ?? '') ?>" placeholder="3~20 字，英文 / 數字 / _">
                <div class="form-hint">登入時使用，建立後無法更改</div>
            </div>
            <div class="form-group">
                <label class="form-label">顯示名稱</label>
                <input class="form-input" name="display_name" type="text"
                    value="<?= h($_POST['display_name'] ?? '') ?>" placeholder="顯示在 sidebar 的名字（可留空）">
            </div>
            <div class="form-group">
                <label class="form-label">學號</label>
                <input class="form-input" name="student_id" type="text"
                    value="<?= h($_POST['student_id'] ?? '') ?>" placeholder="選填">
            </div>
            <div class="form-group">
                <label class="form-label">密碼 *</label>
                <input class="form-input" name="password" type="password" required placeholder="至少 4 個字元">
            </div>
            <div class="form-group">
                <label class="form-label">確認密碼 *</label>
                <input class="form-input" name="password2" type="password" required placeholder="再輸入一次密碼">
            </div>
            <button type="submit" class="btn">建立帳號</button>
        </form>

        <div class="divider"><div class="divider-line"></div><div class="divider-txt">已有帳號？</div><div class="divider-line"></div></div>
        <a href="?mode=login" style="display:block;text-align:center;color:var(--accent);font-size:13px;text-decoration:none">← 返回登入</a>
        <?php endif; ?>
    </div>
    <div class="footer">ZENO · 知序學習管理系統</div>
</div>
</body>
</html>
