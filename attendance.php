<?php
require_once 'includes/functions.php';
require_login();
require_once 'includes/db.php';

$page_title = '出缺席管理';
$current_page = 'attendance.php';
$filter_course = (int)($_GET['course_id'] ?? 0);

// Period definitions (same as schedule.php)
$periods = [
    '01'=>['start'=>'08:10','end'=>'09:00'],
    '02'=>['start'=>'09:10','end'=>'10:00'],
    '03'=>['start'=>'10:10','end'=>'11:00'],
    '04'=>['start'=>'11:10','end'=>'12:00'],
    '20'=>['start'=>'12:00','end'=>'12:50'],
    '05'=>['start'=>'12:50','end'=>'13:40'],
    '06'=>['start'=>'13:50','end'=>'14:40'],
    '07'=>['start'=>'14:50','end'=>'15:40'],
    '08'=>['start'=>'15:50','end'=>'16:40'],
    '09'=>['start'=>'16:50','end'=>'17:40'],
    '40'=>['start'=>'18:00','end'=>'18:50'],
    '50'=>['start'=>'18:55','end'=>'19:45'],
    '60'=>['start'=>'19:50','end'=>'20:40'],
    '70'=>['start'=>'20:45','end'=>'21:35'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_bulk') {
        // Batch add: multiple periods on same date
        $course_id = (int)$_POST['course_id'];
        $date      = $_POST['date'];
        $status    = $_POST['status'];
        $note      = trim($_POST['note'] ?? '');
        $mode      = $_POST['bulk_mode'] ?? 'periods'; // 'all_day' or 'periods'

        if ($mode === 'all_day') {
            // record one entry for whole day
            $pdo->prepare("INSERT INTO attendance (course_id,date,status,note) VALUES (?,?,?,?)")
                ->execute([$course_id,$date,$status,$note.' (整天)']);
        } else {
            $selected_periods = $_POST['periods'] ?? [];
            if (empty($selected_periods)) {
                flash('請至少選擇一節', 'error');
                redirect('attendance.php?course_id='.$filter_course);
            }
            foreach ($selected_periods as $pkey) {
                $period_note = $note ? $note.' (第'.$pkey.'節)' : '第'.$pkey.'節';
                $pdo->prepare("INSERT INTO attendance (course_id,date,status,note) VALUES (?,?,?,?)")
                    ->execute([$course_id,$date,$status,$period_note]);
            }
        }
        flash('出缺席紀錄已新增！');
    } elseif ($action === 'add') {
        $pdo->prepare("INSERT INTO attendance (course_id,date,status,note) VALUES (?,?,?,?)")
            ->execute([$_POST['course_id'],$_POST['date'],$_POST['status'],trim($_POST['note'] ?? '')]);
        flash('出缺席記錄已新增！');
    } elseif ($action === 'edit') {
        $pdo->prepare("UPDATE attendance SET course_id=?,date=?,status=?,note=? WHERE id=?")
            ->execute([$_POST['course_id'],$_POST['date'],$_POST['status'],trim($_POST['note'] ?? ''),(int)$_POST['id']]);
        flash('記錄已更新！');
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM attendance WHERE id=?")->execute([(int)$_POST['id']]);
        flash('記錄已刪除');
    }
    redirect('attendance.php?course_id='.$filter_course);
}

$courses = $pdo->query("SELECT id,name,color FROM courses ORDER BY name")->fetchAll();

// Per-course stats — excused/official_leave/bereavement do NOT count as absent
$course_stats = $pdo->query("
    SELECT c.id, c.name, c.color, c.credits,
        COUNT(a.id) as recorded_total,
        SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN a.status='absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN a.status IN ('excused','official_leave','bereavement') THEN 1 ELSE 0 END) as excused
    FROM courses c LEFT JOIN attendance a ON a.course_id=c.id
    GROUP BY c.id
")->fetchAll();

$where = $filter_course ? "WHERE a.course_id=$filter_course" : '';
$records = $pdo->query("
    SELECT a.*, c.name as course_name, c.color
    FROM attendance a JOIN courses c ON a.course_id=c.id
    $where ORDER BY a.date DESC, a.id DESC
")->fetchAll();

// Status config — only 'absent' counts against attendance
$status_config = [
    'present'        => ['badge-green',  '出席'],
    'absent'         => ['badge-red',    '缺席'],
    'excused'        => ['badge-blue',   '請假'],
    'official_leave' => ['badge-purple', '公假'],
    'bereavement'    => ['badge-gray',   '喪假'],
];

function render_status_badge(string $s, array $cfg): string {
    $info = $cfg[$s] ?? ['badge-gray', $s];
    return '<span class="badge '.$info[0].'">'.$info[1].'</span>';
}

ob_start(); ?>
<button class="btn btn-primary" onclick="openModal('bulkModal')">＋ 新增紀錄</button>
<?php $topbar_actions = ob_get_clean();
require_once 'includes/header.php'; ?>

<style>
.att-stat-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:14px; margin-bottom:20px; }
.att-stat-card {
    background:var(--bg2); border:1px solid var(--border);
    border-radius:var(--card-radius); padding:16px 18px;
    border-top:3px solid var(--border);
    transition: box-shadow .15s;
}
.att-stat-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.2); }
.att-stat-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px; }
.att-stat-name { font-size:13px; font-weight:600; color:var(--text); }
.att-rate { font-size:26px; font-weight:700; font-family:'Space Mono',monospace; }
.att-rate-ok { color:var(--green); }
.att-rate-warn { color:var(--yellow); }
.att-rate-crit { color:var(--red); }
.att-detail { font-size:11px; color:var(--text3); margin-top:4px; }
.period-grid {
    display:grid; grid-template-columns:repeat(7,1fr);
    gap:6px; margin:12px 0;
}
.period-btn {
    border:1.5px solid var(--border);
    background:var(--bg3);
    color:var(--text2);
    border-radius:8px;
    padding:8px 4px;
    font-size:11px;
    font-family:'Space Mono',monospace;
    cursor:pointer;
    text-align:center;
    transition:all .12s;
    line-height:1.3;
}
.period-btn:hover { border-color:var(--accent); color:var(--accent); }
.period-btn.selected { background:var(--accent); border-color:var(--accent); color:white; }
.bulk-mode-tabs { display:flex; gap:8px; margin-bottom:14px; }
.tab-btn {
    padding:6px 14px; border-radius:7px; font-size:12px; font-weight:600;
    border:1.5px solid var(--border); background:var(--bg3); color:var(--text2);
    cursor:pointer; transition:.12s;
}
.tab-btn.active { background:var(--accent); border-color:var(--accent); color:white; }
</style>

<!-- Course attendance summary -->
<div class="att-stat-grid">
    <?php foreach($course_stats as $cs):
        $absent = (int)($cs['absent'] ?? 0);
        $semester_total = semester_class_count($cs);
        $rate = attendance_rate_from_absences($cs, $absent);
        $warn = $rate < 75;
        $critical = $semester_total > 0 && $absent * 3 >= $semester_total;
        $rate_class = $critical ? 'att-rate-crit' : ($warn ? 'att-rate-warn' : 'att-rate-ok');
        $border_color = $critical ? 'var(--red)' : ($warn ? 'var(--yellow)' : 'var(--green)');
    ?>
    <div class="att-stat-card" style="border-top-color:<?= $border_color ?>">
        <div class="att-stat-top">
            <div class="att-stat-name"><?= course_color_dot($cs['color']) ?><?= h($cs['name']) ?></div>
            <?php if($critical): ?>
            <span class="badge badge-red"><svg width="12" height="12" viewBox="0 0 12 12" fill="none" style="vertical-align:-1px"><path d="M6 1.5L11 10H1L6 1.5Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M6 5V7.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><circle cx="6" cy="9" r="0.6" fill="currentColor"/></svg> 預警</span>
            <?php elseif($warn): ?>
            <span class="badge badge-yellow">留意</span>
            <?php endif; ?>
        </div>
        <div class="att-rate <?= $rate_class ?>"><?= $rate ?>%</div>
        <div class="att-detail">總<?= $semester_total ?>節 · 缺席 <?= $absent ?> · 請/公/喪假 <?= (int)($cs['excused']??0) ?></div>
        <div class="progress-bar" style="margin-top:8px">
            <div class="progress-fill" style="width:<?= $rate ?>%;background:<?= $border_color ?>"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-title">出缺席紀錄
        <select class="form-input btn-sm" style="width:160px;margin-left:auto" onchange="location.href='?course_id='+this.value">
            <option value="0">全部課程</option>
            <?php foreach($courses as $c): ?>
            <option value="<?=$c['id']?>" <?=$filter_course==$c['id']?'selected':''?>><?= h($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if(empty($records)): ?>
    <div class="empty-state"><div class="icon"><?=svg_icon('attendance')?></div><p>尚無出缺席紀錄</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>課程</th><th>日期</th><th>狀態</th><th>備注</th><th>操作</th></tr>
        <?php foreach($records as $r): ?>
        <tr>
            <td><?= course_color_dot($r['color']) ?><?= h($r['course_name']) ?></td>
            <td style="font-family:'Space Mono',monospace"><?= $r['date'] ?></td>
            <td><?= render_status_badge($r['status'], $status_config) ?></td>
            <td style="color:var(--text3)"><?= h($r['note'] ?? '') ?></td>
            <td>
                <div style="display:flex;gap:6px">
                    <button class="btn btn-ghost btn-sm" onclick='editAtt(<?= htmlspecialchars(json_encode($r)) ?>)'>編輯</button>
                    <form method="post" style="display:inline" onsubmit="return confirm('確定刪除？')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button class="btn btn-danger btn-sm">刪除</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>

<!-- Bulk Add Modal -->
<div class="modal-overlay" id="bulkModal">
    <div class="modal" style="width:min(560px,calc(100vw - 32px));padding:32px">
        <div class="modal-title">新增出缺席紀錄</div>
        <form method="post" id="bulkForm">
            <input type="hidden" name="action" value="add_bulk">
            <input type="hidden" name="bulk_mode" id="bulk_mode_val" value="periods">

            <div class="form-group">
                <label class="form-label">課程 *</label>
                <select class="form-input" name="course_id" required>
                    <?php foreach($courses as $c): ?>
                    <option value="<?=$c['id']?>" <?=$filter_course==$c['id']?'selected':''?>><?= h($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div class="form-group">
                    <label class="form-label">日期 *</label>
                    <input class="form-input" name="date" type="date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">狀態 *</label>
                    <select class="form-input" name="status" required>
                        <option value="present">出席</option>
                        <option value="absent">缺席</option>
                        <option value="excused">請假</option>
                        <option value="official_leave">公假</option>
                        <option value="bereavement">喪假</option>
                    </select>
                </div>
            </div>

            <!-- Mode tabs -->
            <div class="form-group">
                <label class="form-label">選擇範圍</label>
                <div class="bulk-mode-tabs">
                    <button type="button" class="tab-btn active" onclick="setMode('periods', this)">選擇節次</button>
                    <button type="button" class="tab-btn" onclick="setMode('all_day', this)">整天</button>
                </div>

                <div id="periods_panel">
                    <div class="period-grid">
                        <?php foreach($periods as $pkey => $p): ?>
                        <div class="period-btn" data-key="<?= $pkey ?>" onclick="togglePeriod(this)">
                            <div><?= $pkey ?></div>
                            <div style="font-size:9px;color:inherit;opacity:.7"><?= substr($p['start'],0,5) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="period_hidden_inputs"></div>
                    <div style="font-size:11px;color:var(--text3);margin-top:4px" id="selected_count">未選擇節次</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">備注</label>
                <input class="form-input" name="note" placeholder="備注（選填）">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal('bulkModal')">取消</button>
                <button type="submit" class="btn btn-primary">新增</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal" style="width:min(480px,calc(100vw - 32px));padding:28px">
        <div class="modal-title">編輯出缺席紀錄</div>
        <form method="post">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="ea2_id">
            <div class="form-group">
                <label class="form-label">課程 *</label>
                <select class="form-input" name="course_id" id="edit_at_course" required>
                    <?php foreach($courses as $c): ?>
                    <option value="<?=$c['id']?>"><?= h($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div class="form-group">
                    <label class="form-label">日期 *</label>
                    <input class="form-input" name="date" id="edit_at_date" type="date" required>
                </div>
                <div class="form-group">
                    <label class="form-label">狀態 *</label>
                    <select class="form-input" name="status" id="edit_at_status" required>
                        <option value="present">出席</option>
                        <option value="absent">缺席</option>
                        <option value="excused">請假</option>
                        <option value="official_leave">公假</option>
                        <option value="bereavement">喪假</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">備注</label>
                <input class="form-input" name="note" id="edit_at_note" placeholder="備注（選填）">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">取消</button>
                <button type="submit" class="btn btn-primary">儲存</button>
            </div>
        </form>
    </div>
</div>

<script>
// Period selection
var selectedPeriods = new Set();

function togglePeriod(el) {
    var key = el.dataset.key;
    if (selectedPeriods.has(key)) {
        selectedPeriods.delete(key);
        el.classList.remove('selected');
    } else {
        selectedPeriods.add(key);
        el.classList.add('selected');
    }
    updateHiddenInputs();
}

function updateHiddenInputs() {
    var container = document.getElementById('period_hidden_inputs');
    container.innerHTML = '';
    selectedPeriods.forEach(function(k) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'periods[]'; inp.value = k;
        container.appendChild(inp);
    });
    var count = document.getElementById('selected_count');
    count.textContent = selectedPeriods.size > 0
        ? '已選 ' + selectedPeriods.size + ' 節：' + Array.from(selectedPeriods).sort().join('、')
        : '未選擇節次';
}

function setMode(mode, btn) {
    document.getElementById('bulk_mode_val').value = mode;
    document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    document.getElementById('periods_panel').style.display = mode === 'periods' ? '' : 'none';
}

function editAtt(r) {
    document.getElementById('ea2_id').value = r.id;
    document.getElementById('edit_at_course').value = r.course_id;
    document.getElementById('edit_at_date').value = r.date;
    var s = r.status === 'late' ? 'present' : r.status;
    document.getElementById('edit_at_status').value = s;
    document.getElementById('edit_at_note').value = r.note || '';
    openModal('editModal');
}
</script>

<?php require_once 'includes/footer.php'; ?>
