<?php
require_once 'includes/functions.php';
require_login();
require_once 'includes/db.php';

$page_title = '課程管理';
$current_page = 'courses.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name']);
        $code = trim($_POST['code']);
        $teacher = trim($_POST['teacher']);
        $location = trim($_POST['location']);
        $color = $_POST['color'] ?? '#6366f1';
        $credits = (int)($_POST['credits'] ?? 3);

        if ($action === 'add') {
            $sem_id_new = active_semester_id($pdo);
            $stmt = $pdo->prepare("INSERT INTO courses (name,code,teacher,location,color,credits,semester_id) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$name,$code,$teacher,$location,$color,$credits,$sem_id_new]);
            $course_id = $pdo->lastInsertId();
        } else {
            $course_id = (int)$_POST['id'];
            $stmt = $pdo->prepare("UPDATE courses SET name=?,code=?,teacher=?,location=?,color=?,credits=? WHERE id=?");
            $stmt->execute([$name,$code,$teacher,$location,$color,$credits,$course_id]);
            // Delete old schedule if editing
            if (!empty($_POST['update_schedule'])) {
                $pdo->prepare("DELETE FROM schedule WHERE course_id=?")->execute([$course_id]);
            }
        }

        // Handle schedule periods (multi-checkbox)
        if (!empty($_POST['schedule_entries'])) {
            $entries = json_decode($_POST['schedule_entries'], true);
            if (is_array($entries)) {
                $periods = [
                    '01'=>['08:10','09:00'],'02'=>['09:10','10:00'],'03'=>['10:10','11:00'],
                    '04'=>['11:10','12:00'],'20'=>['12:00','12:50'],'05'=>['12:50','13:40'],
                    '06'=>['13:50','14:40'],'07'=>['14:50','15:40'],'08'=>['15:50','16:40'],
                    '09'=>['16:50','17:40'],'40'=>['18:00','18:50'],'50'=>['18:55','19:45'],
                    '60'=>['19:50','20:40'],'70'=>['20:45','21:35'],
                ];
                foreach ($entries as $e) {
                    $dow = (int)($e['day'] ?? 0);
                    $pkey = $e['period'] ?? '';
                    if ($dow >= 1 && $dow <= 5 && isset($periods[$pkey])) {
                        $pdo->prepare("INSERT INTO schedule (course_id,day_of_week,start_time,end_time) VALUES (?,?,?,?)")
                            ->execute([$course_id, $dow, $periods[$pkey][0], $periods[$pkey][1]]);
                    }
                }
            }
        }

        flash($action === 'add' ? '課程新增成功！' : '課程更新成功！');
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM courses WHERE id=?")->execute([$id]);
        flash('課程已刪除');
    }
    redirect('courses.php');
}

$sem_id = active_semester_id($pdo);

$courses = $pdo->prepare("
    SELECT c.*,
        (SELECT COUNT(*) FROM assignments WHERE course_id=c.id AND status='pending') as pending_hw,
        (SELECT ROUND(AVG(score/max_score*100),1) FROM grades WHERE course_id=c.id) as avg_grade,
        (SELECT COUNT(*) FROM schedule WHERE course_id=c.id) as schedule_count
    FROM courses c WHERE c.semester_id=? ORDER BY c.id
");
$courses->execute([$sem_id]);
$courses = $courses->fetchAll();

$pending_assignments = $pdo->query("SELECT COUNT(*) FROM assignments WHERE status='pending'")->fetchColumn() ?: null;

// Auto-open edit modal if coming from course_detail
$auto_edit_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;

ob_start();
?>
<a href="#" onclick="openModal('addModal')" class="btn btn-primary">＋ 新增課程</a>
<?php
$topbar_actions = ob_get_clean();
require_once 'includes/header.php';
?>

<div class="grid grid-3" style="margin-bottom:20px">
    <?php foreach($courses as $c): ?>
    <div class="card" style="border-top: 3px solid <?= h($c['color']) ?>">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px">
            <div>
                <div style="font-size:16px;font-weight:700;color:var(--text)"><?= h($c['name']) ?></div>
                <div style="font-size:12px;color:var(--text3);margin-top:2px"><?= h($c['code']) ?> · <?= $c['credits'] ?> 學分</div>
            </div>
            <div style="display:flex;gap:6px">
                <button class="btn btn-ghost btn-sm" onclick="editCourse(<?= htmlspecialchars(json_encode($c)) ?>)">編輯</button>
                <form method="post" style="display:inline" onsubmit="return confirm('確定刪除「<?= h($c['name']) ?>」？')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <button class="btn btn-danger btn-sm">刪除</button>
                </form>
            </div>
        </div>
        <div style="display:flex;gap:16px;margin-bottom:14px;font-size:13px;color:var(--text2)">
            <span><?=svg_icon('teacher')?> <?= h($c['teacher'] ?? '—') ?></span>
            <span><?=svg_icon('location')?> <?= h($c['location'] ?? '—') ?></span>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <span class="badge badge-blue"><?=svg_icon('calendar')?> <?= $c['schedule_count'] ?> 節/週</span>
            <?php if($c['pending_hw'] > 0): ?>
            <span class="badge badge-yellow"><?=svg_icon('assignments')?> <?= $c['pending_hw'] ?> 作業</span>
            <?php endif; ?>
            <?php if($c['avg_grade']): ?>
            <?= grade_badge($c['avg_grade']) ?>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:6px;margin-top:12px">
            <a href="course_detail.php?id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm" style="flex:1;justify-content:center">查看詳情</a>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Add new card -->
    <div class="card" style="border:2px dashed var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;min-height:160px"
         onclick="openModal('addModal')">
        <div style="text-align:center;color:var(--text3)">
            <div style="font-size:32px;margin-bottom:8px">＋</div>
            <div>新增課程</div>
        </div>
    </div>
</div>

<style>
.course-modal { width: min(760px, calc(100vw - 32px)); max-width: 96vw; padding: 34px; }
.course-modal .modal-title {
    font-size: 18px; font-weight: 700; margin-bottom: 24px;
    padding-bottom: 16px; border-bottom: 1px solid var(--border);
    color: var(--text); white-space: nowrap;
}
.course-modal .form-group { margin-bottom: 18px; }
.course-modal .form-label {
    display: block; font-size: 12px; font-weight: 600;
    color: var(--text2); margin-bottom: 7px;
    text-transform: uppercase; letter-spacing: 0.6px;
}
.course-modal .form-input { width: 100%; height: 40px; padding: 0 12px; font-size: 14px; }
.course-modal textarea.form-input { height: auto; padding: 10px 12px; }
.course-modal .credits-row { display: flex; gap: 12px; align-items: center; }
.course-modal .credits-row input { width: 100px; text-align: center; }
.course-modal .credits-hint { font-size: 12px; color: var(--text3); }

/* Color picker */
.color-picker-row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.color-swatch { width: 32px; height: 32px; border-radius: 8px; border: 2px solid transparent; cursor: pointer; transition: transform .1s, border-color .1s; flex-shrink: 0; }
.color-swatch:hover { transform: scale(1.15); }
.color-swatch.selected { border-color: white; box-shadow: 0 0 0 2px var(--accent); }
.color-swatch-custom { width: 32px; height: 32px; border-radius: 8px; border: 2px solid var(--border); cursor: pointer; overflow: hidden; flex-shrink: 0; position: relative; background: linear-gradient(135deg, #f97316, #ec4899, #6366f1); }
.color-swatch-custom input[type="color"] { position: absolute; inset: 0; width: 100%; height: 100%; border: none; padding: 0; cursor: pointer; opacity: 0; }
.color-swatch-custom::after {
    content: '';
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    pointer-events: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M17 3a2.827 2.827 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: center;
}

/* Schedule period selector */
.period-section { margin-bottom: 8px; }
.period-section-title { font-size: 10px; font-weight: 700; color: var(--text3); letter-spacing: 1px; text-transform: uppercase; margin-bottom: 6px; }
.period-day-row { display: flex; gap: 6px; align-items: center; margin-bottom: 6px; flex-wrap: wrap; }
.period-day-label { font-size: 12px; color: var(--text2); font-weight: 600; width: 32px; flex-shrink: 0; }
.period-btn {
    padding: 4px 8px; border-radius: 6px; font-size: 11px; cursor: pointer;
    border: 1.5px solid var(--border); background: var(--bg3); color: var(--text2);
    transition: all .12s; user-select: none; white-space: nowrap;
}
.period-btn.selected {
    background: var(--accent); color: white; border-color: var(--accent);
}
.period-btn:hover:not(.selected) { border-color: var(--accent); color: var(--accent); }
</style>

<?php
$period_defs = [
    '01'=>['08:10','09:00','上午'],'02'=>['09:10','10:00','上午'],
    '03'=>['10:10','11:00','上午'],'04'=>['11:10','12:00','上午'],
    '20'=>['12:00','12:50','中午'],
    '05'=>['12:50','13:40','下午'],'06'=>['13:50','14:40','下午'],
    '07'=>['14:50','15:40','下午'],'08'=>['15:50','16:40','下午'],'09'=>['16:50','17:40','下午'],
    '40'=>['18:00','18:50','夜間'],'50'=>['18:55','19:45','夜間'],
    '60'=>['19:50','20:40','夜間'],'70'=>['20:45','21:35','夜間'],
];
$period_json = json_encode($period_defs);
?>

<!-- Schedule picker template (shared by add/edit) -->
<template id="scheduleTpl">
<div class="form-group" id="PFXSCHEDULE_WRAP">
    <label class="form-label">上課時間（可多選節次）</label>
    <div id="PFX_period_ui">
        <?php
        $groups = ['上午'=>[],'中午'=>[],'下午'=>[],'夜間'=>[]];
        foreach($period_defs as $key=>$p) $groups[$p[2]][] = $key;
        foreach($groups as $gname => $keys): ?>
        <div class="period-section">
            <div class="period-section-title"><?= $gname ?></div>
            <?php foreach(['週一','週二','週三','週四','週五'] as $di => $dn): $dow = $di+1; ?>
            <div class="period-day-row">
                <span class="period-day-label"><?= $dn ?></span>
                <?php foreach($keys as $key): ?>
                <div class="period-btn" data-day="<?= $dow ?>" data-period="<?= $key ?>"
                     onclick="togglePeriod(this,'PFX')"
                     title="第<?= $key ?>節 <?= $period_defs[$key][0] ?>–<?= $period_defs[$key][1] ?>">
                    <?= $key ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <input type="hidden" name="schedule_entries" id="PFX_schedule_entries" value="[]">
    <input type="hidden" name="update_schedule" value="1">
    <div style="font-size:11px;color:var(--text3);margin-top:4px" id="PFX_period_hint">尚未選擇任何節次</div>
</div>
</template>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal course-modal">
        <div class="modal-title">＋ 新增課程</div>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="color" id="add_color_val" value="#6366f1">

            <div class="form-group">
                <label class="form-label">課程名稱 *</label>
                <input class="form-input" name="name" required placeholder="例：微積分、線性代數" autocomplete="off">
            </div>
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">課程代碼</label>
                    <input class="form-input" name="code" placeholder="例：MATH101">
                </div>
                <div class="form-group">
                    <label class="form-label">學分數</label>
                    <div class="credits-row">
                        <input class="form-input" name="credits" type="number" min="1" max="6" value="3">
                        <span class="credits-hint">1–6 學分</span>
                    </div>
                </div>
            </div>
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">授課教師</label>
                    <input class="form-input" name="teacher" placeholder="教授姓名">
                </div>
                <div class="form-group">
                    <label class="form-label">教室位置</label>
                    <input class="form-input" name="location" placeholder="例：理學院 302">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">課程顏色</label>
                <div class="color-picker-row" id="add_swatches">
                    <?php foreach(['#6366f1','#3b82f6','#06b6d4','#22c55e','#f59e0b','#ef4444','#a78bfa','#ec4899','#14b8a6','#f97316'] as $clr): ?>
                    <div class="color-swatch <?= $clr==='#6366f1'?'selected':'' ?>"
                         style="background:<?=$clr?>" data-color="<?=$clr?>"
                         onclick="pickColor('add','<?=$clr?>', this)"></div>
                    <?php endforeach; ?>
                    <div class="color-swatch-custom" title="自訂顏色">
                        <input type="color" id="add_custom_color" value="#6366f1"
                               oninput="pickColor('add', this.value, null)">
                    </div>
                </div>
            </div>

            <!-- Period selector (add) -->
            <div id="add_period_mount"></div>

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">取消</button>
                <button type="submit" class="btn btn-primary" style="min-width:80px">新增課程</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal course-modal">
        <div class="modal-title">編輯課程</div>
        <form method="post" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <input type="hidden" name="color" id="edit_color_val" value="#6366f1">

            <div class="form-group">
                <label class="form-label">課程名稱 *</label>
                <input class="form-input" name="name" id="edit_name" required autocomplete="off">
            </div>
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">課程代碼</label>
                    <input class="form-input" name="code" id="edit_code">
                </div>
                <div class="form-group">
                    <label class="form-label">學分數</label>
                    <div class="credits-row">
                        <input class="form-input" name="credits" id="edit_credits" type="number" min="1" max="6">
                        <span class="credits-hint">1–6 學分</span>
                    </div>
                </div>
            </div>
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">授課教師</label>
                    <input class="form-input" name="teacher" id="edit_teacher">
                </div>
                <div class="form-group">
                    <label class="form-label">教室位置</label>
                    <input class="form-input" name="location" id="edit_location">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">課程顏色</label>
                <div class="color-picker-row" id="edit_swatches">
                    <?php foreach(['#6366f1','#3b82f6','#06b6d4','#22c55e','#f59e0b','#ef4444','#a78bfa','#ec4899','#14b8a6','#f97316'] as $clr): ?>
                    <div class="color-swatch"
                         style="background:<?=$clr?>" data-color="<?=$clr?>"
                         onclick="pickColor('edit','<?=$clr?>', this)"></div>
                    <?php endforeach; ?>
                    <div class="color-swatch-custom" title="自訂顏色">
                        <input type="color" id="edit_custom_color" value="#6366f1"
                               oninput="pickColor('edit', this.value, null)">
                    </div>
                </div>
            </div>

            <!-- Period selector (edit) -->
            <div id="edit_period_mount"></div>

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">取消</button>
                <button type="submit" class="btn btn-primary" style="min-width:80px">儲存變更</button>
            </div>
        </form>
    </div>
</div>

<script>
var _periodDefs = <?= $period_json ?>;

// Inject period UI into add/edit mount points
function buildPeriodUI(prefix) {
    var tpl = document.getElementById('scheduleTpl').innerHTML
        .replace(/PFX/g, prefix)
        .replace(/id="PFX/g, 'id="' + prefix)
        .replace(/id="PFXSCHEDULE_WRAP"/g, 'id="' + prefix + 'SCHEDULE_WRAP"');
    // Actually just use innerHTML replacement
    var div = document.createElement('div');
    div.innerHTML = document.getElementById('scheduleTpl').innerHTML
        .split('PFX').join(prefix);
    return div.firstElementChild;
}

// Build and mount on page load
(function() {
    ['add','edit'].forEach(function(prefix) {
        var mount = document.getElementById(prefix + '_period_mount');
        if (!mount) return;
        var tpl = document.getElementById('scheduleTpl').innerHTML.split('PFX').join(prefix);
        mount.innerHTML = tpl;
    });
})();

function togglePeriod(btn, prefix) {
    btn.classList.toggle('selected');
    updatePeriodEntries(prefix);
}

function updatePeriodEntries(prefix) {
    var btns = document.querySelectorAll('#' + prefix + '_period_ui .period-btn.selected');
    var entries = [];
    btns.forEach(function(b) {
        entries.push({day: parseInt(b.dataset.day), period: b.dataset.period});
    });
    document.getElementById(prefix + '_schedule_entries').value = JSON.stringify(entries);
    var hint = document.getElementById(prefix + '_period_hint');
    if (hint) {
        if (entries.length === 0) {
            hint.textContent = '尚未選擇任何節次';
        } else {
            var dayNames = ['','週一','週二','週三','週四','週五'];
            var parts = entries.map(function(e){ return dayNames[e.day]+'第'+e.period+'節'; });
            hint.textContent = '已選：' + parts.join('、');
        }
    }
}

function clearPeriodSelection(prefix) {
    document.querySelectorAll('#' + prefix + '_period_ui .period-btn').forEach(function(b){
        b.classList.remove('selected');
    });
    updatePeriodEntries(prefix);
}

function setSelectedPeriods(prefix, existing) {
    // existing: [{day_of_week, start_time}]
    clearPeriodSelection(prefix);
    existing.forEach(function(s) {
        // find period key by start_time
        var pkey = null;
        for (var k in _periodDefs) {
            if (_periodDefs[k][0] === s.start_time) { pkey = k; break; }
        }
        if (!pkey) return;
        var btn = document.querySelector('#' + prefix + '_period_ui .period-btn[data-day="'+s.day_of_week+'"][data-period="'+pkey+'"]');
        if (btn) btn.classList.add('selected');
    });
    updatePeriodEntries(prefix);
}

function pickColor(prefix, color, swatchEl) {
    document.getElementById(prefix + '_color_val').value = color;
    document.getElementById(prefix + '_custom_color').value = color;
    document.querySelectorAll('#' + prefix + '_swatches .color-swatch').forEach(function(s) {
        s.classList.toggle('selected', s === swatchEl);
    });
}

function editCourse(c) {
    document.getElementById('edit_id').value       = c.id;
    document.getElementById('edit_name').value     = c.name;
    document.getElementById('edit_code').value     = c.code     || '';
    document.getElementById('edit_teacher').value  = c.teacher  || '';
    document.getElementById('edit_location').value = c.location || '';
    document.getElementById('edit_credits').value  = c.credits  || 3;

    var color = c.color || '#6366f1';
    var matched = null;
    document.querySelectorAll('#edit_swatches .color-swatch').forEach(function(s) {
        var match = s.dataset.color === color;
        s.classList.toggle('selected', match);
        if (match) matched = s;
    });
    document.getElementById('edit_color_val').value    = color;
    document.getElementById('edit_custom_color').value = color;

    // Load existing schedule via AJAX fetch
    fetch('?get_schedule=1&course_id=' + c.id)
        .then(function(r){ return r.json(); })
        .then(function(data){ setSelectedPeriods('edit', data); })
        .catch(function(){ clearPeriodSelection('edit'); });

    openModal('editModal');
}
</script>

<?php
// AJAX schedule fetch
if (isset($_GET['get_schedule']) && isset($_GET['course_id'])) {
    header('Content-Type: application/json');
    $cid = (int)$_GET['course_id'];
    $rows = $pdo->prepare("SELECT day_of_week, start_time FROM schedule WHERE course_id=?");
    $rows->execute([$cid]);
    echo json_encode($rows->fetchAll(PDO::FETCH_ASSOC));
    exit;
}
?>

<?php if ($auto_edit_id > 0):
    $auto_course = null;
    foreach ($courses as $c) { if ($c['id'] == $auto_edit_id) { $auto_course = $c; break; } }
    if ($auto_course): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    editCourse(<?= json_encode($auto_course) ?>);
});
</script>
<?php endif; endif; ?>

<?php require_once 'includes/footer.php'; ?>
