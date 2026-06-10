<?php
require_once 'includes/functions.php';
require_login();
require_once 'includes/db.php';

$page_title = '番茄鐘';
$current_page = 'pomodoro.php';

// Save session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_session'])) {
    $pdo->prepare("INSERT INTO pomodoro_sessions (course_id,duration_minutes,completed) VALUES (?,?,1)")
        ->execute([$_POST['course_id']?:null, (int)$_POST['duration']]);
    flash('專注時間已記錄！');
    redirect('pomodoro.php');
}

$courses = $pdo->query("SELECT id,name,color FROM courses ORDER BY name")->fetchAll();

// Stats
$today_sessions = $pdo->query("
    SELECT COUNT(*) as cnt, SUM(duration_minutes) as total
    FROM pomodoro_sessions
    WHERE DATE(started_at)=DATE('now') AND completed=1
")->fetch();

$week_sessions = $pdo->query("
    SELECT COUNT(*) as cnt, SUM(duration_minutes) as total
    FROM pomodoro_sessions
    WHERE started_at >= DATE('now','-7 days') AND completed=1
")->fetch();

$by_course = $pdo->query("
    SELECT c.name, c.color, COUNT(p.id) as cnt, SUM(p.duration_minutes) as total
    FROM pomodoro_sessions p JOIN courses c ON p.course_id=c.id
    WHERE p.completed=1
    GROUP BY p.course_id ORDER BY total DESC LIMIT 5
")->fetchAll();

$recent = $pdo->query("
    SELECT p.*, c.name as course_name, c.color
    FROM pomodoro_sessions p LEFT JOIN courses c ON p.course_id=c.id
    WHERE p.completed=1
    ORDER BY p.started_at DESC LIMIT 10
")->fetchAll();

require_once 'includes/header.php';
?>
<style>
.pomo-display {
    font-family: 'Space Mono', monospace;
    font-size: 72px;
    font-weight: 700;
    color: var(--text);
    text-align: center;
    letter-spacing: 4px;
    line-height: 1;
    margin: 24px 0;
}
.pomo-status {
    text-align: center;
    font-size: 13px;
    color: var(--text3);
    margin-bottom: 20px;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.pomo-ring {
    position: relative;
    width: 220px;
    height: 220px;
    margin: 0 auto;
}
.pomo-ring svg { transform: rotate(-90deg); }
.pomo-ring-text {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.pomo-controls { display: flex; gap: 10px; justify-content: center; margin-top: 20px; }
.pomo-btn {
    width: 48px; height: 48px;
    border-radius: 50%;
    border: none;
    font-size: 20px;
    cursor: pointer;
    transition: all .15s;
}
.pomo-btn-main { background: var(--accent); color: white; width: 60px; height: 60px; font-size: 24px; }
.pomo-btn-main:hover { background: var(--accent2); transform: scale(1.05); }
.pomo-btn-stop { background: var(--bg3); color: var(--text2); }
.pomo-btn-stop:hover { background: rgba(239,68,68,.2); color: var(--red); }
.mode-tabs { display: flex; gap: 6px; justify-content: center; margin-bottom: 20px; }
.mode-tab {
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 12px;
    cursor: pointer;
    border: 1px solid var(--border);
    color: var(--text3);
    background: transparent;
    transition: all .15s;
}
.mode-tab.active { background: var(--accent); color: white; border-color: var(--accent); }
</style>

<div class="grid" style="grid-template-columns:360px 1fr;gap:20px">
    <!-- Timer -->
    <div>
        <div class="card" style="text-align:center">
            <div class="mode-tabs">
                <button class="mode-tab active" onclick="setMode('focus',this)">專注</button>
                <button class="mode-tab" onclick="setMode('short',this)">短休息</button>
                <button class="mode-tab" onclick="setMode('long',this)">長休息</button>
            </div>

            <div class="pomo-ring">
                <svg width="220" height="220" viewBox="0 0 220 220">
                    <circle cx="110" cy="110" r="96" fill="none" stroke="var(--border)" stroke-width="8"/>
                    <circle id="ring" cx="110" cy="110" r="96" fill="none"
                        stroke="var(--accent)" stroke-width="8"
                        stroke-dasharray="603" stroke-dashoffset="0"
                        stroke-linecap="round" style="transition:stroke-dashoffset .5s"/>
                </svg>
                <div class="pomo-ring-text">
                    <div id="timer-display" style="font-family:'Space Mono',monospace;font-size:42px;font-weight:700;color:var(--text)">25:00</div>
                    <div id="timer-status" style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:1px;margin-top:4px">準備開始</div>
                </div>
            </div>

            <div class="pomo-controls">
                <button class="pomo-btn pomo-btn-stop" id="resetBtn" onclick="resetTimer()" title="重置">↺</button>
                <button class="pomo-btn pomo-btn-main" id="startBtn" onclick="toggleTimer()">▶</button>
                <button class="pomo-btn pomo-btn-stop" id="skipBtn" onclick="skipPhase()" title="跳過">⏭</button>
            </div>

            <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
                <label class="form-label" style="text-align:left;display:block;margin-bottom:6px">關聯課程</label>
                <select class="form-input" id="pomo_course">
                    <option value="">無課程</option>
                    <?php foreach($courses as $c): ?>
                    <option value="<?=$c['id']?>"><?= h($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-top:12px">
                <div style="display:flex;gap:8px;margin-bottom:8px">
                    <div style="flex:1;text-align:center">
                        <div style="font-size:11px;color:var(--text3)">專注時長</div>
                        <input type="number" id="focus_dur" value="25" min="1" max="60" class="form-input" style="text-align:center;font-family:'Space Mono',monospace;margin-top:4px">
                    </div>
                    <div style="flex:1;text-align:center">
                        <div style="font-size:11px;color:var(--text3)">短休息</div>
                        <input type="number" id="short_dur" value="5" min="1" max="30" class="form-input" style="text-align:center;font-family:'Space Mono',monospace;margin-top:4px">
                    </div>
                    <div style="flex:1;text-align:center">
                        <div style="font-size:11px;color:var(--text3)">長休息</div>
                        <input type="number" id="long_dur" value="15" min="5" max="60" class="form-input" style="text-align:center;font-family:'Space Mono',monospace;margin-top:4px">
                    </div>
                </div>
            </div>

            <!-- Pomodoro count -->
            <div style="display:flex;gap:6px;justify-content:center;margin-top:12px" id="pomo-dots"></div>
        </div>

        <!-- Today stats -->
        <div class="card" style="margin-top:16px">
            <div class="card-title"><?=svg_icon('statistics')?> 今日統計</div>
            <div class="grid grid-2">
                <div style="text-align:center;padding:10px">
                    <div style="font-size:28px;font-weight:700;font-family:'Space Mono',monospace;color:var(--accent)"><?= $today_sessions['cnt'] ?? 0 ?></div>
                    <div style="font-size:11px;color:var(--text3)">番茄數</div>
                </div>
                <div style="text-align:center;padding:10px">
                    <div style="font-size:28px;font-weight:700;font-family:'Space Mono',monospace;color:var(--green)"><?= $today_sessions['total'] ?? 0 ?></div>
                    <div style="font-size:11px;color:var(--text3)">分鐘</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats & History -->
    <div style="display:flex;flex-direction:column;gap:16px">
        <div class="grid grid-2" style="gap:16px">
            <div class="stat-card">
                <div class="stat-label"><?=svg_icon('calendar')?> 本週番茄數</div>
                <div class="stat-value stat-accent"><?= $week_sessions['cnt'] ?? 0 ?></div>
                <div class="stat-sub">共 <?= $week_sessions['total'] ?? 0 ?> 分鐘</div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="vertical-align:-2px;margin-right:3px"><circle cx="8" cy="9" r="5.5" stroke="currentColor" stroke-width="1.5"/><path d="M8 6.5V9L9.5 10.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 3.5L1.5 5M13 3.5L14.5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> 本週專注時間</div>
                <div class="stat-value stat-green"><?= round(($week_sessions['total'] ?? 0) / 60, 1) ?></div>
                <div class="stat-sub">小時</div>
            </div>
        </div>

        <?php if(!empty($by_course)): ?>
        <div class="card">
            <div class="card-title">各課程專注時間</div>
            <?php foreach($by_course as $b): ?>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                <?= course_color_dot($b['color']) ?>
                <span style="flex:1;color:var(--text)"><?= h($b['name']) ?></span>
                <span style="font-family:'Space Mono',monospace;font-size:12px;color:var(--text3)"><?=$b['total']?>min</span>
                <span class="badge badge-blue"><?=$b['cnt']?><?=svg_icon('pomodoro')?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-title">最近紀錄</div>
            <?php if(empty($recent)): ?>
            <div class="empty-state"><div class="icon"><?=svg_icon('pomodoro')?></div><p>還沒有番茄鐘紀錄</p></div>
            <?php else: ?>
            <div class="table-wrap"><table>
                <tr><th>時間</th><th>課程</th><th>時長</th></tr>
                <?php foreach($recent as $r): ?>
                <tr>
                    <td style="font-size:12px;color:var(--text3)"><?= substr($r['started_at'],0,16) ?></td>
                    <td><?php if($r['course_name']): ?><?= course_color_dot($r['color']) ?><?= h($r['course_name']) ?><?php else: ?>—<?php endif; ?></td>
                    <td><span class="badge badge-blue"><?=$r['duration_minutes']?> 分</span></td>
                </tr>
                <?php endforeach; ?>
            </table></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Hidden save form -->
<form method="post" id="saveForm" style="display:none">
    <input type="hidden" name="save_session" value="1">
    <input type="hidden" name="course_id" id="sf_course">
    <input type="hidden" name="duration" id="sf_duration">
</form>

<script>
const CIRCUMFERENCE = 2 * Math.PI * 96; // ≈603.19
const ring    = document.getElementById('ring');
const display = document.getElementById('timer-display');
const statusEl = document.getElementById('timer-status');
const startBtn = document.getElementById('startBtn');

// Initialise ring dash
ring.setAttribute('stroke-dasharray', CIRCUMFERENCE);
ring.setAttribute('stroke-dashoffset', 0);

let modes        = { focus: 25, short: 5, long: 15 };
let current_mode = 'focus';
let total_seconds = 25 * 60;
let remaining    = total_seconds;
let running      = false;
let timerInterval = null;
let pomo_count   = 0;
const colors = { focus: 'var(--accent)', short: 'var(--green)', long: 'var(--purple)' };
const labels = { focus: '專注中', short: '短休息', long: '長休息' };

function updateDots() {
    const d = document.getElementById('pomo-dots');
    d.innerHTML = '';
    for (let i = 0; i < 4; i++) {
        const s = document.createElement('div');
        s.style.cssText = `width:12px;height:12px;border-radius:50%;transition:background .3s;background:${i < pomo_count ? 'var(--accent)' : 'var(--border)'}`;
        d.appendChild(s);
    }
}
updateDots();

function readDurations() {
    modes.focus = Math.max(1, parseInt(document.getElementById('focus_dur').value) || 25);
    modes.short = Math.max(1, parseInt(document.getElementById('short_dur').value) || 5);
    modes.long  = Math.max(1, parseInt(document.getElementById('long_dur').value)  || 15);
}

function activateTab(mode) {
    document.querySelectorAll('.mode-tab').forEach(t => t.classList.remove('active'));
    const tabMap = { focus: 0, short: 1, long: 2 };
    const tabs = document.querySelectorAll('.mode-tab');
    if (tabs[tabMap[mode]]) tabs[tabMap[mode]].classList.add('active');
}

function setMode(mode, el) {
    clearInterval(timerInterval);
    running = false;
    startBtn.textContent = '▶';
    current_mode = mode;

    // Update tabs
    if (el) {
        document.querySelectorAll('.mode-tab').forEach(t => t.classList.remove('active'));
        el.classList.add('active');
    } else {
        activateTab(mode);
    }

    readDurations();
    total_seconds = modes[mode] * 60;
    remaining     = total_seconds;
    ring.style.stroke = colors[mode];
    statusEl.textContent = mode === 'focus' ? '準備開始' : labels[mode];
    updateDisplay();
}

function updateDisplay() {
    const m = String(Math.floor(remaining / 60)).padStart(2, '0');
    const s = String(remaining % 60).padStart(2, '0');
    display.textContent = m + ':' + s;

    // Ring: full circle = 0 offset, empty = CIRCUMFERENCE offset
    const pct = total_seconds > 0 ? remaining / total_seconds : 0;
    ring.style.strokeDashoffset = CIRCUMFERENCE * (1 - pct);

    document.title = m + ':' + s + ' — 番茄鐘';
}

function toggleTimer() {
    if (running) {
        clearInterval(timerInterval);
        running = false;
        startBtn.textContent = '▶';
        statusEl.textContent = '已暫停';
    } else {
        running = true;
        startBtn.textContent = '⏸';
        statusEl.textContent = labels[current_mode] || '專注中';

        timerInterval = setInterval(() => {
            if (remaining <= 0) {
                clearInterval(timerInterval);
                running = false;
                startBtn.textContent = '▶';
                onPhaseEnd();
                return;
            }
            remaining--;
            updateDisplay();
        }, 1000);

        if (Notification.permission === 'default') Notification.requestPermission();
    }
}

function onPhaseEnd() {
    if (current_mode === 'focus') {
        pomo_count = (pomo_count % 4) + 1;
        updateDots();
        saveSession();
        statusEl.textContent = '完成！';
        const nextMode = pomo_count === 4 ? 'long' : 'short';
        setTimeout(() => setMode(nextMode, null), 1200);
    } else {
        statusEl.textContent = '休息結束！';
        setTimeout(() => setMode('focus', null), 1200);
    }
    if (Notification.permission === 'granted') {
        new Notification(current_mode === 'focus' ? '專注完成！休息一下' : '休息結束，繼續專注');
    }
}

function resetTimer() {
    clearInterval(timerInterval);
    running = false;
    startBtn.textContent = '▶';
    remaining = total_seconds;
    statusEl.textContent = '準備開始';
    updateDisplay();
}

function skipPhase() {
    clearInterval(timerInterval);
    running = false;
    startBtn.textContent = '▶';
    if (current_mode === 'focus') {
        setMode('short', null);
    } else {
        setMode('focus', null);
    }
}

function saveSession() {
    document.getElementById('sf_course').value   = document.getElementById('pomo_course').value;
    document.getElementById('sf_duration').value = modes.focus;
    document.getElementById('saveForm').submit();
}

// Live update duration inputs
['focus_dur', 'short_dur', 'long_dur'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => {
        if (!running) setMode(current_mode, null);
    });
});

// Init
updateDisplay();
</script>

<?php require_once 'includes/footer.php'; ?>
