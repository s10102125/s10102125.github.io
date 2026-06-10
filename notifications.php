<?php
require_once 'includes/functions.php';
require_login();
require_once 'includes/db.php';

$page_title = '通知中心';
$current_page = 'notifications.php';

$notifications = [];

// Overdue assignments
$overdue = $pdo->query("
    SELECT a.*, c.name as course_name, c.color
    FROM assignments a LEFT JOIN courses c ON a.course_id=c.id
    WHERE a.status='pending' AND a.due_date < DATE('now')
    ORDER BY a.due_date
")->fetchAll();
foreach($overdue as $a) {
    $days = abs(ceil((strtotime($a['due_date'])-time())/86400));
    $notifications[] = [
        'type'=>'error', 'icon'=>svg_icon('assignments'),
        'title'=>"作業逾期：{$a['title']}",
        'body'=>($a['course_name']??"無課程")." · 已逾期 {$days} 天",
        'link'=>'assignments.php'
    ];
}

// Due soon (within 3 days)
$soon = $pdo->query("
    SELECT a.*, c.name as course_name, c.color
    FROM assignments a LEFT JOIN courses c ON a.course_id=c.id
    WHERE a.status='pending' AND a.due_date BETWEEN DATE('now') AND DATE('now','+3 days')
    ORDER BY a.due_date
")->fetchAll();
foreach($soon as $a) {
    $days = ceil((strtotime($a['due_date'])-time())/86400);
    $notifications[] = [
        'type'=>'warn', 'icon'=>svg_icon('alarm'),
        'title'=>"即將截止：{$a['title']}",
        'body'=>($a['course_name']??"無課程")." · ".($days===0?'今天截止':"還有 {$days} 天"),
        'link'=>'assignments.php'
    ];
}

// Attendance warnings
$att_warn = $pdo->query("
    SELECT c.name, c.color, c.credits,
        SUM(a.status='absent') as absent
    FROM courses c LEFT JOIN attendance a ON a.course_id=c.id
    GROUP BY c.id
")->fetchAll();
foreach($att_warn as $w) {
    $w['absent'] = (int)($w['absent'] ?? 0);
    $w['semester_total'] = semester_class_count($w);
    $w['rate'] = attendance_rate_from_absences($w, $w['absent']);
    if ($w['rate'] >= 75) continue;
    $level = $w['rate'] < 67 ? 'error' : 'warn';
    $notifications[] = [
        'type'=>$level, 'icon'=>svg_icon('warning'),
        'title'=>"出席率警告：{$w['name']}",
        'body'=>"出席率 {$w['rate']}%（缺席 {$w['absent']} / {$w['semester_total']} 節），請注意！",
        'link'=>'attendance.php'
    ];
}

// Low grades
$low_grades = $pdo->query("
    SELECT c.name, c.color,
        ROUND(AVG(g.score/g.max_score*100),1) as avg
    FROM grades g JOIN courses c ON g.course_id=c.id
    GROUP BY g.course_id HAVING avg < 60
")->fetchAll();
foreach($low_grades as $g) {
    $notifications[] = [
        'type'=>'warn', 'icon'=>svg_icon('statistics'),
        'title'=>"成績偏低：{$g['name']}",
        'body'=>"平均成績 {$g['avg']} 分，建議加強複習",
        'link'=>'grades.php'
    ];
}

// Pending todos due today
$today_todos = $pdo->query("
    SELECT * FROM todos WHERE status='pending' AND due_date=DATE('now')
")->fetchAll();
foreach($today_todos as $t) {
    $notifications[] = [
        'type'=>'info', 'icon'=>svg_icon('todos'),
        'title'=>"待辦事項到期：{$t['title']}",
        'body'=>"今天截止",
        'link'=>'todos.php'
    ];
}

if(empty($notifications)) {
    $notifications[] = ['type'=>'success','icon'=>svg_icon('check'),'title'=>'一切正常！','body'=>'目前沒有任何警告或提醒。','link'=>''];
}

require_once 'includes/header.php';
?>
<style>
.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 10px;
    border-left: 4px solid;
    transition: opacity .2s;
}
.notif-item.error { background:rgba(239,68,68,.08); border-color:var(--red); }
.notif-item.warn  { background:rgba(245,158,11,.08); border-color:var(--yellow); }
.notif-item.info  { background:rgba(91,127,255,.08); border-color:var(--accent); }
.notif-item.success { background:rgba(34,197,94,.08); border-color:var(--green); }
.notif-icon { font-size:22px; flex-shrink:0; }
.notif-title { font-weight:700; color:var(--text); font-size:14px; }
.notif-body { font-size:12px; color:var(--text3); margin-top:2px; }
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
    <div style="font-size:13px;color:var(--text3)"><?= count($notifications) ?> 則通知</div>
</div>

<div>
<?php foreach($notifications as $n): ?>
<div class="notif-item <?= $n['type'] ?>">
    <div class="notif-icon"><?= $n['icon'] ?></div>
    <div style="flex:1">
        <div class="notif-title"><?= h($n['title']) ?></div>
        <div class="notif-body"><?= h($n['body']) ?></div>
    </div>
    <?php if($n['link']): ?>
    <a href="<?= $n['link'] ?>" class="btn btn-ghost btn-sm" style="flex-shrink:0">查看</a>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
