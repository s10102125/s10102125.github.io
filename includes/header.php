<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ?? '知序' ?> · ZENO</title>
<link rel="icon" type="image/png" href="data/zeno-logo.png">
<link rel="apple-touch-icon" href="data/zeno-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root {
    --bg: #0d0f14;
    --bg2: #13161e;
    --bg3: #1a1e28;
    --border: #242836;
    --text: #e8ecf4;
    --text2: #8892aa;
    --text3: #555e74;
    --accent: #5b7fff;
    --accent2: #7c9fff;
    --green: #22c55e;
    --red: #ef4444;
    --yellow: #f59e0b;
    --purple: #a78bfa;
    --cyan: #06b6d4;
    --card-radius: 12px;
    --sidebar-w: 240px;
}
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: 'Noto Sans TC', sans-serif;
    background: var(--bg);
    color: var(--text);
    display: flex;
    min-height: 100vh;
    font-size: 14px;
}
/* Sidebar */
#sidebar {
    width: var(--sidebar-w);
    background: var(--bg2);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 100;
    overflow-y: auto;
}
.sidebar-logo {
    padding: 20px 20px 16px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 12px;
}
.sidebar-logo .logo-img {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    object-fit: cover;
    flex-shrink: 0;
    background: #000;
}
.sidebar-logo .logo-text-wrap {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.sidebar-logo .logo-icon {
    font-family: 'Space Mono', monospace;
    font-size: 18px;
    font-weight: 700;
    color: var(--text);
    letter-spacing: 1px;
    line-height: 1;
}
.sidebar-logo .logo-icon span {
    color: var(--accent);
}
.sidebar-logo .logo-sub {
    font-size: 10px;
    color: var(--text3);
    margin-top: 3px;
    letter-spacing: 2px;
    text-transform: uppercase;
}
.nav-section {
    padding: 12px 12px 4px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.5px;
    color: var(--text3);
    text-transform: uppercase;
}
.nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 12px;
    margin: 1px 8px;
    border-radius: 8px;
    color: var(--text2);
    text-decoration: none;
    font-size: 13.5px;
    transition: all .15s;
    cursor: pointer;
}
.nav-item:hover { background: var(--bg3); color: var(--text); }
.nav-item.active { background: rgba(91,127,255,.15); color: var(--accent2); }
.nav-item .icon { font-size: 16px; width: 20px; text-align: center; }
.nav-badge {
    margin-left: auto;
    background: var(--red);
    color: white;
    font-size: 10px;
    font-family: 'Space Mono', monospace;
    padding: 1px 6px;
    border-radius: 10px;
    font-weight: 700;
}
/* Main */
#main {
    margin-left: var(--sidebar-w);
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}
#topbar {
    height: 56px;
    background: var(--bg2);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    padding: 0 28px;
    gap: 12px;
    position: sticky;
    top: 0;
    z-index: 50;
}
.topbar-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text);
}
.topbar-bread {
    font-size: 12px;
    color: var(--text3);
    margin-left: 4px;
}
.topbar-actions { margin-left: auto; display: flex; gap: 8px; }
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-family: 'Noto Sans TC', sans-serif;
    font-weight: 500;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all .15s;
}
.btn-primary { background: var(--accent); color: white; }
.btn-primary:hover { background: var(--accent2); }
.btn-ghost { background: transparent; color: var(--text2); border: 1px solid var(--border); }
.btn-ghost:hover { background: var(--bg3); color: var(--text); }
.btn-danger { background: rgba(239,68,68,.15); color: var(--red); border: 1px solid rgba(239,68,68,.3); }
.btn-danger:hover { background: rgba(239,68,68,.25); }
.btn-sm { padding: 4px 10px; font-size: 12px; }
/* Content */
#content { padding: 28px; flex: 1; }
/* Cards */
.card {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    padding: 20px;
}
.card-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--text2);
    text-transform: uppercase;
    letter-spacing: .8px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
/* Grid */
.grid { display: grid; gap: 16px; }
.grid-2 { grid-template-columns: repeat(2, 1fr); }
.grid-3 { grid-template-columns: repeat(3, 1fr); }
.grid-4 { grid-template-columns: repeat(4, 1fr); }
/* Stat cards */
.stat-card {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    padding: 18px 20px;
}
.stat-label { font-size: 12px; color: var(--text3); margin-bottom: 6px; }
.stat-value { font-size: 28px; font-weight: 700; font-family: 'Space Mono', monospace; color: var(--text); }
.stat-sub { font-size: 11px; color: var(--text3); margin-top: 4px; }
.stat-accent { color: var(--accent); }
.stat-green { color: var(--green); }
.stat-red { color: var(--red); }
.stat-yellow { color: var(--yellow); }
/* Tables */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th {
    text-align: left;
    padding: 10px 14px;
    font-size: 11px;
    font-weight: 600;
    color: var(--text3);
    text-transform: uppercase;
    letter-spacing: .8px;
    border-bottom: 1px solid var(--border);
}
td {
    padding: 11px 14px;
    border-bottom: 1px solid var(--border);
    color: var(--text2);
    font-size: 13.5px;
}
tr:last-child td { border-bottom: none; }
tr:hover td { background: var(--bg3); }
/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    font-family: 'Space Mono', monospace;
}
.badge-green { background: rgba(34,197,94,.15); color: var(--green); }
.badge-red { background: rgba(239,68,68,.15); color: var(--red); }
.badge-yellow { background: rgba(245,158,11,.15); color: var(--yellow); }
.badge-blue { background: rgba(91,127,255,.15); color: var(--accent2); }
.badge-purple { background: rgba(167,139,250,.15); color: var(--purple); }
.badge-gray { background: rgba(136,146,170,.12); color: var(--text3); }
/* Forms */
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 12px; color: var(--text2); margin-bottom: 6px; font-weight: 500; }
.form-input {
    width: 100%;
    background: var(--bg3);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 9px 12px;
    color: var(--text);
    font-family: 'Noto Sans TC', sans-serif;
    font-size: 13.5px;
    outline: none;
    transition: border-color .15s;
}
.form-input:focus { border-color: var(--accent); }
.form-input option { background: var(--bg3); }
textarea.form-input { resize: vertical; min-height: 80px; }
/* ── Modal ── */
.modal-overlay {
    /* 從 body appendChild 後才生效；預設隱藏 */
    display: none;
    position: fixed;
    inset: 0;                      /* top/right/bottom/left: 0 */
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.65);
    z-index: 9999;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    /* 不加 opacity transition，避免 display:none 切換時的衝突 */
}
.modal-overlay.open {
    /* display:flex 由 JS openModal() 的 style.display 設定 */
}
.modal {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 32px;
    width: min(680px, calc(100vw - 32px));
    max-width: 94vw;
    max-height: 88vh;
    overflow-y: auto;
    box-shadow: 0 24px 64px rgba(0, 0, 0, 0.6);
    position: relative;
    /* 不用 transform scale，避免觸發新的 containing block */
}
.modal-title {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 20px;
    color: var(--text);
}
.modal-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    margin-top: 20px;
}
@media (max-width: 560px) {
    .modal {
        padding: 24px;
        border-radius: 14px;
    }
}
/* Course color dot */
.course-dot {
    display: inline-block;
    width: 10px; height: 10px;
    border-radius: 50%;
    margin-right: 6px;
    flex-shrink: 0;
}
/* Alert */
.alert {
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.alert-warn { background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.3); color: var(--yellow); }
.alert-success { background: rgba(34,197,94,.1); border: 1px solid rgba(34,197,94,.3); color: var(--green); }
.alert-err { background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.3); color: var(--red); }
/* Progress bar */
.progress-bar {
    height: 6px;
    background: var(--bg3);
    border-radius: 3px;
    overflow: hidden;
    margin-top: 6px;
}
.progress-fill {
    height: 100%;
    border-radius: 3px;
    transition: width .4s;
}
/* Scrollbar */
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
/* Empty state */
.empty-state { text-align: center; padding: 48px 20px; color: var(--text3); }
.empty-state .icon { font-size: 40px; margin-bottom: 12px; }
.empty-state p { font-size: 14px; }
/* Flash message */
.flash {
    position: fixed; top: 20px; right: 20px;
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    z-index: 999;
    animation: slideIn .3s ease, fadeOut .3s ease 2.7s forwards;
}
.flash-success { background: var(--green); color: white; }
.flash-error { background: var(--red); color: white; }
@keyframes slideIn { from { transform: translateX(60px); opacity:0; } to { transform: none; opacity:1; } }
@keyframes fadeOut { to { opacity:0; transform: translateX(60px); } }
@keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
</style>
</head>
<body>

<nav id="sidebar">
    <div class="sidebar-logo">
        <img class="logo-img" src="data/zeno-logo.png" alt="ZENO">
        <div class="logo-text-wrap">
            <div class="logo-icon">知序 <span>ZENO</span></div>
            <div class="logo-sub">學習管理系統</div>
        </div>
    </div>

    <?php
    // Semester switcher in sidebar
    global $pdo;
    if (!isset($pdo)) require_once __DIR__ . '/db.php';
    $active_sem_id = active_semester_id($pdo);
    $all_sems = $pdo->query("SELECT * FROM semesters ORDER BY id DESC")->fetchAll();
    $active_sem = null;
    foreach ($all_sems as $s) { if ($s['id'] == $active_sem_id) { $active_sem = $s; break; } }
    ?>
    <!-- Semester switcher -->
    <div style="padding:10px 12px 4px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
            <div style="font-size:10px;font-weight:700;letter-spacing:1.5px;color:var(--text3);text-transform:uppercase">學年學期</div>
            <div style="display:flex;gap:2px;align-items:center">
                <?php if($active_sem): ?>
                <button onclick="openEditSemesterModal(<?=$active_sem['id']?>, '<?=addslashes(h($active_sem['label']??$active_sem['name']))?>', '<?=addslashes(h($active_sem['name']))?>')" title="編輯學期"
                    style="background:transparent;border:none;color:var(--text3);cursor:pointer;padding:2px 4px;border-radius:4px;font-size:12px;line-height:1;transition:.15s"
                    onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--text3)'"><svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M11 2L14 5L5 14H2V11L11 2Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></button>
                <button onclick="confirmDeleteSemester(<?=$active_sem['id']?>, '<?=addslashes(h($active_sem['label']??$active_sem['name']))?>')" title="刪除學期"
                    style="background:transparent;border:none;color:var(--text3);cursor:pointer;padding:2px 4px;border-radius:4px;font-size:12px;line-height:1;transition:.15s"
                    onmouseover="this.style.color='var(--red,#f87171)'" onmouseout="this.style.color='var(--text3)'"><svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M3 5H13M6 5V3H10V5M5.5 5L6 13H10L10.5 5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                <?php endif; ?>
                <button onclick="openNewSemesterModal()" title="新增學期"
                    style="background:transparent;border:none;color:var(--text3);cursor:pointer;padding:2px 5px;border-radius:4px;font-size:15px;line-height:1;transition:.15s"
                    onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--text3)'">＋</button>
            </div>
        </div>
        <!-- Dropdown selector -->
        <select onchange="switchSemester(this.value)"
            style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:7px 10px;color:var(--text);font-family:'Noto Sans TC',sans-serif;font-size:12.5px;cursor:pointer;outline:none;appearance:auto">
            <?php foreach($all_sems as $s): ?>
            <option value="<?=$s['id']?>" <?=$s['id']==$active_sem_id?'selected':''?>>
                <?=h($s['label']??$s['name'])?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="nav-section">主要</div>
    <?php nav_item('home', '儀表板', 'index.php', $current_page ?? ''); ?>
    <?php nav_item('calendar', '月曆', 'calendar.php', $current_page ?? ''); ?>
    <?php nav_item('pomodoro', '番茄鐘', 'pomodoro.php', $current_page ?? ''); ?>

    <div class="nav-section">學業</div>
    <?php nav_item('courses', '課程管理', 'courses.php', $current_page ?? ''); ?>
    <?php nav_item('schedule', '週課表', 'schedule.php', $current_page ?? ''); ?>
    <?php nav_item('assignments', '作業管理', 'assignments.php', $current_page ?? '', $pending_assignments ?? null); ?>
    <?php nav_item('grades', '成績管理', 'grades.php', $current_page ?? ''); ?>
    <?php nav_item('attendance', '出缺席', 'attendance.php', $current_page ?? ''); ?>
    <?php nav_item('statistics', '考試成績', 'exam_scores.php', $current_page ?? ''); ?>

    <div class="nav-section">工具</div>
    <?php nav_item('todos', '待辦事項', 'todos.php', $current_page ?? ''); ?>
    <?php nav_item('statistics', '統計分析', 'statistics.php', $current_page ?? ''); ?>
    <?php nav_item('notifications', '通知中心', 'notifications.php', $current_page ?? ''); ?>
    <?php nav_item('notes', '學期運勢', 'tarot.php', $current_page ?? ''); ?>

    <div class="nav-section">系統</div>
    <?php nav_item('settings', '個人設定', 'settings.php', $current_page ?? ''); ?>

    <!-- User info + logout at bottom -->
    <div style="margin-top:auto;padding:12px;border-top:1px solid var(--border)">
        <div style="display:flex;align-items:center;gap:10px">
            <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--purple,#a78bfa));display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:700;font-size:13px;color:white">
                <?= mb_substr($_SESSION['display_name'] ?? '?', 0, 1) ?>
            </div>
            <div style="flex:1;min-width:0">
                <div style="font-size:13px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($_SESSION['display_name'] ?? '同學') ?></div>
                <div style="font-size:11px;color:var(--text3)"><?= h($_SESSION['username'] ?? '') ?></div>
            </div>
            <a href="logout.php" title="登出" style="color:var(--text3);transition:.15s;padding:4px" onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--text3)'">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M6 3H3a1 1 0 00-1 1v8a1 1 0 001 1h3M10 11l3-3-3-3M13 8H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
        </div>
    </div>
</nav>

<!-- New Semester Modal -->
<div id="newSemModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9999;align-items:center;justify-content:center">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:16px;padding:32px;width:min(400px,94vw);box-shadow:0 24px 64px rgba(0,0,0,.6)">
        <div style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:20px">新增學期</div>
        <form method="post" action="semester_action.php">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="redirect" value="<?= h($_SERVER['REQUEST_URI']) ?>">
            <div style="margin-bottom:16px">
                <label style="display:block;font-size:12px;color:var(--text2);margin-bottom:6px;font-weight:500">學年度</label>
                <select id="sem_year_select" onchange="updateSemLabel()" style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:var(--text);font-family:'Noto Sans TC',sans-serif;font-size:13.5px;outline:none;margin-bottom:8px">
                    <?php
                    $cur_roc = (int)date('Y') - 1911;
                    for ($y = $cur_roc + 1; $y >= $cur_roc - 5; $y--) {
                        $sel = ($y == $cur_roc) ? 'selected' : '';
                        echo "<option value=\"$y\" $sel>{$y} 學年度</option>";
                    }
                    ?>
                </select>
                <select id="sem_term_select" onchange="updateSemLabel()" style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:var(--text);font-family:'Noto Sans TC',sans-serif;font-size:13.5px;outline:none">
                    <option value="上">上學期</option>
                    <option value="下">下學期</option>
                </select>
            </div>
            <div style="margin-bottom:16px">
                <label style="display:block;font-size:12px;color:var(--text2);margin-bottom:6px;font-weight:500">學期名稱（可自訂）</label>
                <input id="sem_label_input" name="label" class="form-input" required placeholder="例：113學年下學期" style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:var(--text);font-family:'Noto Sans TC',sans-serif;font-size:13.5px;outline:none">
            </div>
            <div style="margin-bottom:16px">
                <label style="display:block;font-size:12px;color:var(--text2);margin-bottom:6px;font-weight:500">簡短代碼</label>
                <input id="sem_name_input" name="name" class="form-input" required placeholder="例：113下" style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:var(--text);font-family:'Noto Sans TC',sans-serif;font-size:13.5px;outline:none">
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px">
                <button type="button" onclick="closeNewSemesterModal()" style="background:transparent;border:1px solid var(--border);border-radius:8px;padding:8px 16px;color:var(--text2);cursor:pointer;font-family:'Noto Sans TC',sans-serif">取消</button>
                <button type="submit" style="background:var(--accent);color:white;border:none;border-radius:8px;padding:8px 16px;font-weight:600;cursor:pointer;font-family:'Noto Sans TC',sans-serif">建立學期</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Semester Modal -->
<div id="editSemModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9999;align-items:center;justify-content:center">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:16px;padding:32px;width:min(400px,94vw);box-shadow:0 24px 64px rgba(0,0,0,.6)">
        <div style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:20px"><svg width="15" height="15" viewBox="0 0 16 16" fill="none" style="vertical-align:-2px;margin-right:6px"><path d="M11 2L14 5L5 14H2V11L11 2Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg> 編輯學期</div>
        <form method="post" action="semester_action.php">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="redirect" value="<?= h($_SERVER['REQUEST_URI']) ?>">
            <input type="hidden" name="id" id="edit_sem_id">
            <div style="margin-bottom:16px">
                <label style="display:block;font-size:12px;color:var(--text2);margin-bottom:6px;font-weight:500">學期名稱（顯示用）</label>
                <input name="label" id="edit_sem_label" class="form-input" required style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:var(--text);font-family:'Noto Sans TC',sans-serif;font-size:13.5px;outline:none">
            </div>
            <div style="margin-bottom:16px">
                <label style="display:block;font-size:12px;color:var(--text2);margin-bottom:6px;font-weight:500">簡短代碼</label>
                <input name="name" id="edit_sem_name" class="form-input" required style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:9px 12px;color:var(--text);font-family:'Noto Sans TC',sans-serif;font-size:13.5px;outline:none">
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px">
                <button type="button" onclick="closeEditSemesterModal()" style="background:transparent;border:1px solid var(--border);border-radius:8px;padding:8px 16px;color:var(--text2);cursor:pointer;font-family:'Noto Sans TC',sans-serif">取消</button>
                <button type="submit" style="background:var(--accent);color:white;border:none;border-radius:8px;padding:8px 16px;font-weight:600;cursor:pointer;font-family:'Noto Sans TC',sans-serif">儲存</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Semester Form (hidden) -->
<form id="deleteSemForm" method="post" action="semester_action.php" style="display:none">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="redirect" value="<?= h($_SERVER['REQUEST_URI']) ?>">
    <input type="hidden" name="id" id="delete_sem_id">
</form>

<script>
function switchSemester(id) {
    var url = new URL(window.location.href);
    url.searchParams.set('switch_semester', id);
    window.location.href = url.toString();
}
function updateSemLabel() {
    var y = document.getElementById('sem_year_select').value;
    var t = document.getElementById('sem_term_select').value;
    document.getElementById('sem_label_input').value = y + '學年' + t + '學期';
    document.getElementById('sem_name_input').value = y + t;
}
function openNewSemesterModal() {
    document.getElementById('newSemModal').style.display = 'flex';
    updateSemLabel();
}
function closeNewSemesterModal() {
    document.getElementById('newSemModal').style.display = 'none';
}
function openEditSemesterModal(id, label, name) {
    document.getElementById('edit_sem_id').value = id;
    document.getElementById('edit_sem_label').value = label;
    document.getElementById('edit_sem_name').value = name;
    document.getElementById('editSemModal').style.display = 'flex';
}
function closeEditSemesterModal() {
    document.getElementById('editSemModal').style.display = 'none';
}
function confirmDeleteSemester(id, label) {
    if (confirm('確定要刪除「' + label + '」嗎？\n相關課程資料不會被刪除。')) {
        document.getElementById('delete_sem_id').value = id;
        document.getElementById('deleteSemForm').submit();
    }
}
document.getElementById('newSemModal').addEventListener('click', function(e){ if(e.target===this) closeNewSemesterModal(); });
document.getElementById('editSemModal').addEventListener('click', function(e){ if(e.target===this) closeEditSemesterModal(); });
</script>

<div id="main">
    <div id="topbar">
        <span class="topbar-title"><?= $page_title ?? '課程管理系統' ?></span>
        <?php
        // Show upcoming exams/important dates
        global $pdo;
        if (!isset($pdo)) {
            require_once __DIR__ . '/db.php';
        }
        $important_exams = $pdo->query("
            SELECT title, due_date, CAST((julianday(due_date) - julianday('now')) as INTEGER) as days_until
            FROM assignments
            WHERE status='pending' AND due_date >= date('now')
                AND (title LIKE '%期中%' OR title LIKE '%期末%' OR title LIKE '%中考%' OR title LIKE '%末考%')
            ORDER BY due_date LIMIT 1
        ")->fetch();
        
        if ($important_exams && $important_exams['days_until'] <= 14):
        ?>
        <span class="topbar-bread" style="color: var(--yellow); font-weight: 600; animation: blink 1.5s infinite;">
            <svg width="13" height="13" viewBox="0 0 16 16" fill="none" style="vertical-align:-2px;margin-right:4px"><path d='M8 2C8 2 5 3.5 5 7V11H11V7C11 3.5 8 2 8 2Z' stroke='currentColor' stroke-width='1.5' stroke-linejoin='round'/><path d='M4 11H12' stroke='currentColor' stroke-width='1.5' stroke-linecap='round'/><path d='M6.5 11C6.5 11 6.5 13 8 13C9.5 13 9.5 11 9.5 11' stroke='currentColor' stroke-width='1.5' stroke-linecap='round'/></svg> <?= htmlspecialchars($important_exams['title']) ?> · 還有 <?= $important_exams['days_until'] ?> 天
        </span>
        <?php elseif (!empty($page_bread)): ?>
        <span class="topbar-bread">/ <?= $page_bread ?></span>
        <?php endif; ?>
        <div class="topbar-actions">
            <?php if (!empty($topbar_actions)) echo $topbar_actions; ?>
        </div>
    </div>
    <div id="content">

<?php if (!empty($_SESSION['flash_msg'])): ?>
<div class="flash flash-<?= $_SESSION['flash_type'] ?? 'success' ?>"><?= htmlspecialchars($_SESSION['flash_msg']) ?></div>
<?php unset($_SESSION['flash_msg'], $_SESSION['flash_type']); ?>
<?php endif; ?>
