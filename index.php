<?php
require_once 'includes/functions.php';
require_login();
require_once 'includes/db.php';

$page_title = '儀表板';
$current_page = 'index.php';

// Active semester
$sem_id = active_semester_id($pdo);

// Handle memo save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_memo') {
    $content = trim($_POST['memo_content'] ?? '');
    $existing = $pdo->query("SELECT id FROM dashboard_memos ORDER BY id LIMIT 1")->fetchColumn();
    if ($content) {
        if ($existing) {
            $pdo->prepare("UPDATE dashboard_memos SET content=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$content, $existing]);
        } else {
            $pdo->prepare("INSERT INTO dashboard_memos (content) VALUES (?)")->execute([$content]);
        }
    } else {
        $pdo->exec("DELETE FROM dashboard_memos");
    }
    redirect('index.php');
}

function get_setting(PDO $pdo, string $key, $default = null) {
    $stmt = $pdo->prepare("SELECT value FROM app_settings WHERE key=?");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : $value;
}

// Today's schedule
$today_dow = (int)date('N');
$today_schedule = $pdo->prepare("
    SELECT s.*, c.name, c.color, c.teacher, c.location
    FROM schedule s JOIN courses c ON s.course_id = c.id
    WHERE s.day_of_week = ? AND c.semester_id = ? ORDER BY s.start_time
");
$today_schedule->execute([$today_dow, $sem_id]);
$today_classes = $today_schedule->fetchAll();

$today_date      = date('Y-m-d');
$today_formatted = date('m月d日');
$today_day_name  = ['','星期一','星期二','星期三','星期四','星期五','星期六','星期日'][date('N')];
$today_day_en    = ['','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'][date('N')];
$today_month_num = date('Y · m');

// Upcoming assignments
$upcoming_assignments = $pdo->prepare("
    SELECT a.*, c.name as course_name, c.color
    FROM assignments a LEFT JOIN courses c ON a.course_id = c.id
    WHERE a.status = 'pending' AND a.due_date >= date('now') AND c.semester_id = ?
    ORDER BY a.due_date LIMIT 5
");
$upcoming_assignments->execute([$sem_id]);
$upcoming_assignments = $upcoming_assignments->fetchAll();

// Countdown candidates
// Countdown candidates
$countdown_candidates = $pdo->prepare("
    SELECT 'assignment:' || a.id as event_key, a.title, a.due_date,
        c.name as course_name, c.color,
        CASE
            WHEN a.title LIKE '%期中%' OR a.title LIKE '%中考%' THEN '期中考倒數'
            WHEN a.title LIKE '%期末%' OR a.title LIKE '%末考%' THEN '期末考倒數'
            ELSE '作業截止'
        END as exam_type,
        CAST((julianday(a.due_date) - julianday('now')) as INTEGER) as days_until
    FROM assignments a LEFT JOIN courses c ON a.course_id = c.id
    WHERE a.status = 'pending' AND a.due_date >= date('now') AND c.semester_id = ?
    UNION ALL
    SELECT 'todo:' || t.id as event_key, t.title, t.due_date,
        NULL as course_name, '#8b5cf6' as color,
        CASE
            WHEN t.title LIKE '%期中%' OR t.title LIKE '%中考%' THEN '期中考倒數'
            WHEN t.title LIKE '%期末%' OR t.title LIKE '%末考%' THEN '期末考倒數'
            ELSE '倒數'
        END as exam_type,
        CAST((julianday(t.due_date) - julianday('now')) as INTEGER) as days_until
    FROM todos t
    WHERE t.status = 'pending' AND t.due_date >= date('now')
    ORDER BY due_date LIMIT 30
");
$countdown_candidates->execute([$sem_id]);
$countdown_candidates = $countdown_candidates->fetchAll();

$saved_countdowns = json_decode(get_setting($pdo, 'dashboard_countdowns', '[]'), true);
if (!is_array($saved_countdowns)) $saved_countdowns = [];

$candidate_by_key = [];
foreach ($countdown_candidates as $event) {
    $candidate_by_key[$event['event_key']] = $event;
}

$important_dates = [];
foreach ($saved_countdowns as $key) {
    if (isset($candidate_by_key[$key])) $important_dates[] = $candidate_by_key[$key];
}
if (!$important_dates) {
    foreach ($countdown_candidates as $event) {
        if (strpos($event['title'], '期中') !== false || strpos($event['title'], '期末') !== false) {
            $important_dates[] = $event;
        }
        if (count($important_dates) >= 2) break;
    }
}

$overdue = $pdo->prepare("SELECT COUNT(*) FROM assignments a JOIN courses c ON a.course_id=c.id WHERE a.status='pending' AND a.due_date < date('now') AND c.semester_id=?");
$overdue->execute([$sem_id]);
$overdue = $overdue->fetchColumn();

$pending_count = $pdo->prepare("SELECT COUNT(*) FROM assignments a JOIN courses c ON a.course_id=c.id WHERE a.status='pending' AND c.semester_id=?");
$pending_count->execute([$sem_id]);
$pending_count = $pending_count->fetchColumn();
$pending_assignments = $pending_count ?: null;

$att_courses = $pdo->prepare("
    SELECT c.id, c.name, c.credits,
        SUM(CASE WHEN a.status='absent' THEN 1 ELSE 0 END) as absent_count
    FROM courses c LEFT JOIN attendance a ON a.course_id = c.id
    WHERE c.semester_id = ?
    GROUP BY c.id
");
$att_courses->execute([$sem_id]);
$att_courses = $att_courses->fetchAll();
$att_total_classes = 0; $att_absent_count = 0;
foreach ($att_courses as $ca) {
    $att_total_classes += semester_class_count($ca);
    $att_absent_count += (int)($ca['absent_count'] ?? 0);
}
$att_rate = $att_total_classes > 0
    ? round(max(0, $att_total_classes - $att_absent_count) / $att_total_classes * 100, 1) : 100;

$avg_grade_stmt = $pdo->prepare("SELECT AVG(g.score/g.max_score*100) FROM grades g JOIN courses c ON g.course_id=c.id WHERE c.semester_id=?");
$avg_grade_stmt->execute([$sem_id]);
$avg_grade = $avg_grade_stmt->fetchColumn();
$avg_grade = $avg_grade ? round($avg_grade, 1) : 0;

$course_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE semester_id=?");
$course_count_stmt->execute([$sem_id]);
$course_count = $course_count_stmt->fetchColumn();

$warnings_stmt = $pdo->prepare("
    SELECT c.id, c.name, c.color, c.credits,
        SUM(CASE WHEN a.status='absent' THEN 1 ELSE 0 END) as absent_count
    FROM courses c LEFT JOIN attendance a ON a.course_id = c.id
    WHERE c.semester_id = ?
    GROUP BY c.id
");
$warnings_stmt->execute([$sem_id]);
$warnings = $warnings_stmt->fetchAll();
$warnings = array_values(array_filter(array_map(function($w) {
    $w['absent_count'] = (int)($w['absent_count'] ?? 0);
    $w['semester_total'] = semester_class_count($w);
    $w['rate'] = attendance_rate_from_absences($w, $w['absent_count']);
    return $w;
}, $warnings), fn($w) => $w['rate'] < 75 || ($w['semester_total'] > 0 && $w['absent_count'] * 3 >= $w['semester_total'])));

$recent_grades_stmt = $pdo->prepare("
    SELECT g.*, c.name as course_name, c.color, ROUND(g.score/g.max_score*100,1) as pct
    FROM grades g JOIN courses c ON g.course_id = c.id
    WHERE c.semester_id = ?
    ORDER BY g.graded_at DESC LIMIT 4
");
$recent_grades_stmt->execute([$sem_id]);
$recent_grades = $recent_grades_stmt->fetchAll();

// Polaroid settings
$polaroid_caption = get_setting($pdo, 'polaroid_caption', '你已經很厲害了，繼續加油');
$polaroid_image   = get_setting($pdo, 'polaroid_image', '');

// Dashboard memo
$dashboard_memo = $pdo->query("SELECT * FROM dashboard_memos ORDER BY id LIMIT 1")->fetch();

require_once 'includes/header.php';
?>

<style>
/* ── Hero bar ── */
.hero-bar {
    display: grid;
    grid-template-columns: 200px 1fr 172px;
    gap: 12px;
    align-items: stretch;
    margin-bottom: 20px;
}

/* Date card */
.date-card {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    padding: 22px 24px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;
}
.date-weekday {
    font-size: 10px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--text3);
    font-weight: 600;
}
.date-day-num {
    font-size: 90px;
    font-weight: 700;
    line-height: .88;
    font-family: 'Space Mono', monospace;
    color: var(--text);
    letter-spacing: -6px;
    margin-top: 6px;
}
.date-month-year {
    font-size: 12px;
    color: var(--text3);
    font-family: 'Space Mono', monospace;
    margin-top: 8px;
    letter-spacing: 1px;
}
.date-accent {
    width: 28px; height: 2px;
    background: var(--accent);
    border-radius: 1px;
    margin: 14px 0 10px;
    opacity: .5;
}
.date-day-zh {
    font-size: 11px;
    color: var(--text3);
    letter-spacing: .5px;
}

/* Countdown cards */
.cd-col { display: flex; flex-direction: row; gap: 10px; flex-wrap: wrap; }
.cd-card {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 7px;
    flex: 1;
}
.cd-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
.cd-label {
    font-size: 10px;
    color: var(--text3);
    text-transform: uppercase;
    letter-spacing: .8px;
    font-weight: 600;
}
.cd-target {
    font-size: 11px;
    font-family: 'Space Mono', monospace;
    color: var(--text3);
    white-space: nowrap;
    flex-shrink: 0;
}
.cd-event {
    font-size: 13px;
    color: var(--text);
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cd-bottom { display: flex; align-items: baseline; gap: 6px; }
.cd-days-big {
    font-size: 40px;
    font-weight: 700;
    line-height: 1;
    font-family: 'Space Mono', monospace;
}
.cd-unit { font-size: 12px; color: var(--text3); padding-bottom: 4px; }
.cd-bar-wrap { height: 2px; background: var(--bg3); border-radius: 2px; overflow: hidden; }
.cd-bar-fill  { height: 100%; border-radius: 2px; }
.cd-ok   { color: var(--green); } .b-ok   { background: var(--green); }
.cd-warn { color: var(--yellow); } .b-warn { background: var(--yellow); }
.cd-crit { color: var(--red); }   .b-crit { background: var(--red); }

/* Polaroid */
.polaroid-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.polaroid {
    background: #f8f6f0;
    padding: 8px 8px 0 8px;
    border-radius: 2px;
    box-shadow: 0 2px 10px rgba(0,0,0,.25), 0 0 0 0.5px rgba(0,0,0,.1);
    transform: rotate(-1.8deg);
    width: 152px;
    transition: transform .2s;
    cursor: pointer;
    text-decoration: none;
}
.polaroid:hover { transform: rotate(0deg) scale(1.04); }
.polaroid-img {
    width: 100%;
    aspect-ratio: 1/1;
    background: var(--bg3);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.polaroid-img img { width: 100%; height: 100%; object-fit: cover; }
.polaroid-placeholder { display: flex; flex-direction: column; align-items: center; gap: 6px; color: var(--text3); }
.polaroid-placeholder svg { opacity: .5; }
.polaroid-placeholder span { font-size: 10px; }
.polaroid-caption-wrap {
    padding: 10px 6px 14px;
    min-height: 46px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
}
.polaroid-caption {
    font-size: 11.5px;
    color: #7a7060;
    text-align: center;
    line-height: 1.5;
    font-family: 'Noto Sans TC', sans-serif;
}
.polaroid-hint {
    font-size: 10px;
    color: var(--text3);
    text-align: center;
    opacity: .6;
}

/* rest of dashboard */
.today-class-item { display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border); }
.today-class-item:last-child { border-bottom:none; }
.class-time { font-family:'Space Mono',monospace;font-size:12px;color:var(--text3);width:80px;flex-shrink:0; }
.class-name { font-weight:600;color:var(--text);font-size:14px; }
.class-meta { font-size:12px;color:var(--text3);margin-top:2px; }
.assign-row { display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border); }
.assign-row:last-child { border-bottom:none; }
.warn-item { display:flex;align-items:center;gap:10px;padding:10px 14px;background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.2);border-radius:8px;margin-bottom:8px; }
.grade-mini { display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border); }
.grade-mini:last-child { border-bottom:none; }

@media (max-width: 860px) {
    .hero-bar { grid-template-columns: 1fr 1fr; }
    .polaroid-col { display: none; }
}
@media (max-width: 600px) {
    .hero-bar { grid-template-columns: 1fr; }
}
</style>

<!-- Hero bar -->
<div class="hero-bar">

    <!-- Date card -->
    <div class="date-card">
        <div>
            <div class="date-weekday"><?= $today_day_en ?></div>
            <div class="date-day-num"><?= date('d') ?></div>
            <div class="date-month-year"><?= date('Y') ?> · <?= date('m') ?></div>
        </div>
        <div>
            <div class="date-accent"></div>
            <div class="date-day-zh"><?= $today_day_name ?></div>
        </div>
    </div>

    <!-- Countdown column -->
    <?php $cd_count = count($important_dates); ?>
    <div class="cd-col" style="flex-direction:<?= $cd_count <= 1 ? 'column' : ($cd_count === 3 ? 'row' : 'row') ?>;flex-wrap:wrap">
        <?php if (!empty($important_dates)):
            foreach (array_slice($important_dates, 0, 3) as $d):
                $days = max(0, (int)$d['days_until']);
                $max_days = 90;
                $pct = min(100, round(($max_days - $days) / $max_days * 100));
                if ($days <= 3)      { $cls = 'cd-crit'; $bcls = 'b-crit'; }
                elseif ($days <= 7)  { $cls = 'cd-warn'; $bcls = 'b-warn'; }
                else                 { $cls = 'cd-ok';   $bcls = 'b-ok'; }
        ?>
        <div class="cd-card" style="<?= $cd_count === 1 ? 'flex:1' : 'flex:1;min-width:0' ?>">
            <div class="cd-top">
                <div class="cd-label"><?= h($d['exam_type']) ?></div>
                <div class="cd-target"><?= h($d['due_date']) ?></div>
            </div>
            <div class="cd-event"><?= h($d['title']) ?></div>
            <div class="cd-bottom">
                <div class="cd-days-big <?= $cls ?>"><?= $days ?></div>
                <div class="cd-unit">天後</div>
            </div>
            <div class="cd-bar-wrap"><div class="cd-bar-fill <?= $bcls ?>" style="width:<?= $pct ?>%"></div></div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="cd-card" style="align-items:center;justify-content:center;border-style:dashed;flex:1">
            <div style="text-align:center;color:var(--text3);font-size:13px">
                尚無倒數事件<br>
                <a href="settings.php#countdowns" style="color:var(--accent);font-size:12px;text-decoration:none">前往設定 →</a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Polaroid -->
    <div class="polaroid-col">
        <a class="polaroid" href="settings.php#polaroid" title="前往設定更換照片">
            <div class="polaroid-img">
                <?php if ($polaroid_image): ?>
                <img src="<?= h($polaroid_image) ?>" alt="激勵照片">
                <?php else: ?>
                <div class="polaroid-placeholder">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="5" width="18" height="14" rx="2"/>
                        <circle cx="12" cy="12" r="3.5"/>
                        <path d="M3 9h2M16 5l1.5-2h3"/>
                    </svg>
                    <span>放一張照片</span>
                </div>
                <?php endif; ?>
            </div>
            <div class="polaroid-caption-wrap">
                <div class="polaroid-caption"><?= h($polaroid_caption) ?></div>
            </div>
        </a>
        <div class="polaroid-hint"></div>
    </div>
</div>

<!-- Stats -->
<?php if ($dashboard_memo): ?>
<div style="margin-bottom:14px;display:flex;align-items:center;gap:12px;background:var(--bg2);border:1px solid var(--border);border-left:3px solid var(--accent);border-radius:var(--card-radius);padding:14px 18px">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="color:var(--accent);flex-shrink:0"><rect x="3" y="2" width="10" height="12" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M6 5h4M6 8h4M6 11h2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
    <div style="flex:1;font-size:13px;color:var(--text2);white-space:pre-wrap;line-height:1.6"><?= h($dashboard_memo['content']) ?></div>
    <button onclick="openMemoModal()" title="編輯備忘錄" style="background:transparent;border:none;color:var(--text3);cursor:pointer;padding:4px;transition:.15s;flex-shrink:0" onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text3)'">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M9.5 2.5L11.5 4.5M2 12l.7-3.5L9.5 2.5l2 2L4.2 11.3 2 12z" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
</div>
<?php endif; ?>
<div class="grid grid-4" style="margin-bottom:20px">
    <div class="stat-card">
        <div class="stat-label"><?=svg_icon('courses')?> 課程數</div>
        <div class="stat-value stat-accent"><?= $course_count ?></div>
        <div class="stat-sub">本學期</div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?=svg_icon('statistics')?> 平均成績</div>
        <div class="stat-value <?= $avg_grade >= 75 ? 'stat-green' : 'stat-yellow' ?>"><?= $avg_grade ?: '—' ?></div>
        <div class="stat-sub">所有科目平均</div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?=svg_icon('attendance')?> 出席率</div>
        <div class="stat-value <?= $att_rate >= 75 ? 'stat-green' : 'stat-red' ?>"><?= $att_rate ?>%</div>
        <div class="stat-sub">缺席 <?= $att_absent_count ?> / <?= $att_total_classes ?> 節</div>
        <div class="progress-bar"><div class="progress-fill" style="width:<?= $att_rate ?>%;background:var(--<?= $att_rate >= 75 ? 'green' : 'red' ?>)"></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?=svg_icon('assignments')?> 待辦作業</div>
        <div class="stat-value <?= $pending_count > 0 ? 'stat-yellow' : 'stat-green' ?>"><?= $pending_count ?></div>
        <div class="stat-sub"><?= $overdue > 0 ? "{$overdue} 筆已逾期" : '目前無逾期' ?></div>
    </div>
</div>

<?php if (!empty($warnings)): ?>
<div style="margin-bottom:20px">
<?php foreach($warnings as $w): ?>
    <div class="warn-item">
        <?=svg_icon('warning')?>
        <?= course_color_dot($w['color']) ?>
        <div><strong><?= h($w['name']) ?></strong> 出席率僅 <strong style="color:var(--red)"><?= $w['rate'] ?>%</strong>（缺席 <?= $w['absent_count'] ?> / <?= $w['semester_total'] ?> 節）</div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid grid-2" style="margin-bottom:20px">
    <div class="card">
        <div class="card-title"><?=svg_icon('home')?> 今日課程
            <span style="font-size:12px;color:var(--text3);font-weight:400;text-transform:none;letter-spacing:0">
                <?= ['','週一','週二','週三','週四','週五','週六','週日'][$today_dow] ?>
            </span>
        </div>
        <?php if (empty($today_classes)): ?>
            <div class="empty-state"><div class="icon"><?=svg_icon('check')?></div><p>今天沒有課程！</p></div>
        <?php else: ?>
            <?php foreach($today_classes as $c): ?>
            <div class="today-class-item">
                <div style="width:3px;height:40px;border-radius:2px;background:<?= h($c['color']) ?>;flex-shrink:0"></div>
                <div class="class-time"><?= h($c['start_time']) ?><br><?= h($c['end_time']) ?></div>
                <div>
                    <div class="class-name"><?= h($c['name']) ?></div>
                    <div class="class-meta"><?=svg_icon('location')?> <?= h($c['location'] ?? '—') ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><?=svg_icon('assignments')?> 即將到期作業
            <a href="assignments.php" class="btn btn-ghost btn-sm" style="margin-left:auto">查看全部</a>
        </div>
        <?php if (empty($upcoming_assignments)): ?>
            <div class="empty-state"><div class="icon"><?=svg_icon('check')?></div><p>沒有待辦作業</p></div>
        <?php else: ?>
            <?php foreach($upcoming_assignments as $a):
                $days_left = (int)((strtotime($a['due_date']) - time()) / 86400);
                $urgency = $days_left <= 2 ? 'stat-red' : ($days_left <= 5 ? 'stat-yellow' : 'stat-accent');
            ?>
            <div class="assign-row">
                <?= course_color_dot($a['color'] ?? '#555') ?>
                <div style="flex:1">
                    <div style="font-weight:600;color:var(--text)"><?= h($a['title']) ?></div>
                    <div style="font-size:12px;color:var(--text3)"><?= h($a['course_name'] ?? '無課程') ?></div>
                </div>
                <div class="<?= $urgency ?>" style="font-size:12px;font-family:'Space Mono',monospace"><?= $days_left <= 0 ? '今天' : "{$days_left}天後" ?></div>
                <?= badge_priority($a['priority']) ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-title"><?=svg_icon('grades')?> 最近成績
            <a href="grades.php" class="btn btn-ghost btn-sm" style="margin-left:auto">查看全部</a>
        </div>
        <?php if (empty($recent_grades)): ?>
            <div class="empty-state"><div class="icon"><?=svg_icon('statistics')?></div><p>尚無成績紀錄</p></div>
        <?php else: ?>
            <?php foreach($recent_grades as $g): ?>
            <div class="grade-mini">
                <?= course_color_dot($g['color']) ?>
                <div style="flex:1">
                    <div style="color:var(--text);font-weight:600"><?= h($g['title']) ?></div>
                    <div style="font-size:12px;color:var(--text3)"><?= h($g['course_name']) ?></div>
                </div>
                <?= grade_badge($g['pct']) ?>
                <span style="font-size:12px;color:var(--text3);margin-left:4px"><?= $g['score'] ?>/<?= $g['max_score'] ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><?=svg_icon('todos')?> 待辦事項
            <a href="todos.php" class="btn btn-ghost btn-sm" style="margin-left:auto">查看全部</a>
        </div>
        <?php
        $todos = $pdo->query("SELECT * FROM todos WHERE status='pending' ORDER BY due_date ASC LIMIT 5")->fetchAll();
        if (empty($todos)): ?>
            <div class="empty-state"><div class="icon"><?=svg_icon('check')?></div><p>待辦事項已全部完成！</p></div>
        <?php else: ?>
            <?php foreach($todos as $t): ?>
            <div class="assign-row">
                <?= badge_priority($t['priority']) ?>
                <div style="flex:1;color:var(--text);font-weight:500"><?= h($t['title']) ?></div>
                <?php if ($t['due_date']): ?>
                <span style="font-size:11px;color:var(--text3);font-family:'Space Mono',monospace"><?= $t['due_date'] ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<!-- Memo Modal -->
<div class="modal-overlay" id="memoModal">
    <div class="modal" style="max-width:480px">
        <div class="modal-title"><svg width="15" height="15" viewBox="0 0 16 16" fill="none" style="vertical-align:-2px;margin-right:5px"><rect x="3" y="2" width="10" height="12" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M6 6H10M6 9H10M6 12H8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> 備忘錄</div>
        <form method="post">
            <input type="hidden" name="action" value="save_memo">
            <div class="form-group">
                <label class="form-label">內容（留空則不顯示）</label>
                <textarea class="form-input" name="memo_content" id="memoContent" rows="5" placeholder="寫下重要提醒、待記事項…"><?= h($dashboard_memo['content'] ?? '') ?></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal('memoModal')">取消</button>
                <button type="submit" class="btn btn-primary">儲存</button>
            </div>
        </form>
    </div>
</div>
<script>
function openMemoModal() { openModal('memoModal'); }
</script>
<?php if (!$dashboard_memo): ?>
<div style="position:fixed;bottom:24px;right:24px;z-index:90">
    <button onclick="openMemoModal()" style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:10px 16px;color:var(--text2);font-size:13px;cursor:pointer;display:flex;align-items:center;gap:8px;box-shadow:0 4px 20px rgba(0,0,0,.4);transition:.2s;font-family:'Noto Sans TC',sans-serif" onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text2)'">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="2" y="1.5" width="10" height="11" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M5 5h4M5 7.5h4M5 10h2" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
        新增備忘錄
    </button>
</div>
<?php endif; ?>
