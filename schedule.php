<?php
require_once 'includes/functions.php';
require_login();
require_once 'includes/db.php';

$page_title = '週課表';
$current_page = 'schedule.php';

// Period definitions based on provided timetable
// Morning sessions: 01-07, Afternoon sessions: 08-09, 40,50,60,70
$periods = [
    '01' => ['start'=>'08:10','end'=>'09:00','label'=>'第01節'],
    '02' => ['start'=>'09:10','end'=>'10:00','label'=>'第02節'],
    '03' => ['start'=>'10:10','end'=>'11:00','label'=>'第03節'],
    '04' => ['start'=>'11:10','end'=>'12:00','label'=>'第04節'],
    '20' => ['start'=>'12:00','end'=>'12:50','label'=>'第20節'],
    '05' => ['start'=>'12:50','end'=>'13:40','label'=>'第05節'],
    '06' => ['start'=>'13:50','end'=>'14:40','label'=>'第06節'],
    '07' => ['start'=>'14:50','end'=>'15:40','label'=>'第07節'],
    '08' => ['start'=>'15:50','end'=>'16:40','label'=>'第08節'],
    '09' => ['start'=>'16:50','end'=>'17:40','label'=>'第09節'],
    '40' => ['start'=>'18:00','end'=>'18:50','label'=>'第40節'],
    '50' => ['start'=>'18:55','end'=>'19:45','label'=>'第50節'],
    '60' => ['start'=>'19:50','end'=>'20:40','label'=>'第60節'],
    '70' => ['start'=>'20:45','end'=>'21:35','label'=>'第70節'],
];

// Group labels: 上午 01-04, 中午 20, 下午 05-09, 夜間 40-70

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $period_key = $_POST['period_key'] ?? '';
        if (!isset($periods[$period_key])) {
            flash('請選擇有效的節次', 'error');
            redirect('schedule.php');
        }
        $start_time = $periods[$period_key]['start'];
        $end_time   = $periods[$period_key]['end'];

        // Check conflict
        $conflict = $pdo->prepare("
            SELECT s.*, c.name FROM schedule s JOIN courses c ON s.course_id=c.id
            WHERE s.day_of_week=? AND NOT (s.end_time<=? OR s.start_time>=?)
        ");
        $conflict->execute([$_POST['day_of_week'], $start_time, $end_time]);
        $conflict = $conflict->fetch();
        if ($conflict) {
            flash("衝堂警告！與「{$conflict['name']}」（{$conflict['start_time']}–{$conflict['end_time']}）時間重疊", 'error');
        } else {
            $pdo->prepare("INSERT INTO schedule (course_id,day_of_week,start_time,end_time) VALUES (?,?,?,?)")
                ->execute([$_POST['course_id'],$_POST['day_of_week'],$start_time,$end_time]);
            flash('課程已加入課表！');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM schedule WHERE id=?")->execute([(int)$_POST['id']]);
        flash('已從課表移除');
    }
    redirect('schedule.php');
}

$schedule = $pdo->query("
    SELECT s.*, c.name, c.color, c.location, c.teacher
    FROM schedule s JOIN courses c ON s.course_id=c.id
    ORDER BY s.start_time
")->fetchAll();

$courses = $pdo->query("SELECT id,name,color FROM courses ORDER BY name")->fetchAll();

// Group by day
$by_day = [];
for ($d = 1; $d <= 5; $d++) $by_day[$d] = [];
foreach ($schedule as $s) {
    $by_day[$s['day_of_week']][] = $s;
}

// Map start_time => period_key for lookup
$time_to_period = [];
foreach ($periods as $key => $p) {
    $time_to_period[$p['start']] = $key;
}

ob_start();
?>
<button class="btn btn-primary" onclick="openModal('addModal')">＋ 新增課程</button>
<?php
$topbar_actions = ob_get_clean();
require_once 'includes/header.php';
?>
<style>
.timetable {
    display: grid;
    grid-template-columns: 88px repeat(5, 1fr);
    border-radius: var(--card-radius);
    overflow: hidden;
    border: 1px solid var(--border);
}
.tt-header {
    background: var(--bg3);
    padding: 12px 8px;
    text-align: center;
    font-size: 11px;
    font-weight: 700;
    color: var(--text2);
    border-right: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    letter-spacing: .5px;
    text-transform: uppercase;
}
.tt-header:last-child { border-right: none; }
.tt-period-cell {
    background: var(--bg3);
    border-right: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    padding: 8px 6px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
}
.tt-period-num {
    font-family: 'Space Mono', monospace;
    font-size: 11px;
    font-weight: 700;
    color: var(--accent);
    line-height: 1;
}
.tt-period-time {
    font-size: 9px;
    color: var(--text3);
    text-align: center;
    line-height: 1.5;
}
.tt-cell {
    border-right: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    min-height: 62px;
    padding: 4px;
    position: relative;
}
.tt-cell:last-child { border-right: none; }
.tt-block {
    border-radius: 8px;
    padding: 7px 9px;
    font-size: 11px;
    line-height: 1.4;
    position: relative;
    height: 100%;
    cursor: default;
    transition: transform .1s;
}
.tt-block:hover { transform: scale(1.02); }
.tt-block strong { display: block; font-size: 12px; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tt-remove {
    position: absolute; top: 5px; right: 5px;
    background: rgba(0,0,0,.4); border: none; color: white;
    border-radius: 4px; cursor: pointer; font-size: 10px;
    padding: 2px 5px; opacity: 0; transition: .15s;
    line-height: 1;
}
.tt-block:hover .tt-remove { opacity: 1; }
.divider-row .tt-period-cell {
    background: rgba(91,127,255,.04);
}
.divider-row .tt-cell {
    background: rgba(91,127,255,.04);
    min-height: 10px;
}
.section-label {
    grid-column: 1 / -1;
    background: var(--bg3);
    border-bottom: 1px solid var(--border);
    padding: 5px 14px;
    font-size: 10px;
    font-weight: 700;
    color: var(--text3);
    letter-spacing: 1px;
    text-transform: uppercase;
}
</style>

<div style="overflow-x:auto">
<div class="timetable">
    <div class="tt-header">節次</div>
    <?php foreach(['一','二','三','四','五'] as $d): ?>
    <div class="tt-header">週<?= $d ?></div>
    <?php endforeach; ?>

    <?php
    $group_labels = [
        '01' => '上午',
        '20' => '中午',
        '05' => '下午',
        '40' => '夜間',
    ];
    foreach($periods as $pkey => $period):
        if (isset($group_labels[$pkey])): ?>
    <div class="section-label"><?= $group_labels[$pkey] ?></div>
    <?php endif; ?>

    <!-- Period label cell -->
    <div class="tt-period-cell">
        <div class="tt-period-num"><?= $pkey ?></div>
        <div class="tt-period-time"><?= $period['start'] ?><br><?= $period['end'] ?></div>
    </div>

    <?php for ($d=1; $d<=5; $d++):
        $cell_class = array_filter($by_day[$d], function($c) use ($period) {
            return $c['start_time'] === $period['start'];
        });
    ?>
    <div class="tt-cell">
        <?php foreach($cell_class as $c): ?>
        <div class="tt-block" style="background:<?= h($c['color']) ?>22;border:1.5px solid <?= h($c['color']) ?>44;color:var(--text)">
            <strong style="color:<?= h($c['color']) ?>"><?= h($c['name']) ?></strong>
            <?php if($c['location']): ?>
            <span style="font-size:10px;color:var(--text3)"><?= h($c['location']) ?></span>
            <?php endif; ?>
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button class="tt-remove" onclick="return confirm('移除此課程？')"><svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M1.5 1.5L8.5 8.5M8.5 1.5L1.5 8.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endfor; ?>
    <?php endforeach; ?>
</div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal" style="width:min(480px,calc(100vw - 32px));padding:30px">
        <div class="modal-title">新增課程到課表</div>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">課程</label>
                <select class="form-input" name="course_id" required>
                    <option value="">選擇課程...</option>
                    <?php foreach($courses as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">星期</label>
                <select class="form-input" name="day_of_week" required>
                    <?php foreach(['週一'=>1,'週二'=>2,'週三'=>3,'週四'=>4,'週五'=>5] as $l=>$v): ?>
                    <option value="<?= $v ?>"><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">節次</label>
                <select class="form-input" name="period_key" required>
                    <option value="">選擇節次...</option>
                    <?php foreach($periods as $key => $p): ?>
                    <option value="<?= $key ?>">第<?= $key ?>節 &nbsp; <?= $p['start'] ?> – <?= $p['end'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">取消</button>
                <button type="submit" class="btn btn-primary">新增</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
