<?php
require_once 'includes/functions.php';
require_login();
require_once 'includes/db.php';

$page_title = '作業管理';
$current_page = 'assignments.php';
$filter_course = (int)($_GET['course_id'] ?? 0);
$filter_status = $_GET['status'] ?? 'pending';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pdo->prepare("INSERT INTO assignments (course_id,title,description,due_date,priority,status) VALUES (?,?,?,?,?,?)")
            ->execute([
                $_POST['course_id'] ?: null,
                trim($_POST['title']),
                trim($_POST['description']),
                $_POST['due_date'] ?: null,
                $_POST['priority'],
                'pending'
            ]);
        flash('作業已新增！');
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $cur = $pdo->prepare("SELECT status FROM assignments WHERE id=?")->execute([$id]) ? '' : '';
        $stmt = $pdo->prepare("SELECT status FROM assignments WHERE id=?");
        $stmt->execute([$id]);
        $cur = $stmt->fetchColumn();
        $new = $cur === 'done' ? 'pending' : 'done';
        $pdo->prepare("UPDATE assignments SET status=? WHERE id=?")->execute([$new,$id]);
        $msg = $new === 'done' ? '已標記完成 ' . svg_icon('check') : '已標記為未完成';
        flash($msg);
    } elseif ($action === 'edit') {
        $pdo->prepare("UPDATE assignments SET course_id=?,title=?,description=?,due_date=?,priority=? WHERE id=?")
            ->execute([
                $_POST['course_id'] ?: null,
                trim($_POST['title']),
                trim($_POST['description']),
                $_POST['due_date'] ?: null,
                $_POST['priority'],
                (int)$_POST['id']
            ]);
        flash('作業已更新！');
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM assignments WHERE id=?")->execute([(int)$_POST['id']]);
        flash('作業已刪除');
    }
    $qs = http_build_query(['course_id'=>$filter_course,'status'=>$filter_status]);
    redirect("assignments.php?$qs");
}

$courses = $pdo->query("SELECT id,name,color FROM courses ORDER BY name")->fetchAll();

$where = [];
$params = [];
if ($filter_course) { $where[] = 'a.course_id=?'; $params[] = $filter_course; }
if ($filter_status !== 'all') { $where[] = 'a.status=?'; $params[] = $filter_status; }
$whereStr = $where ? 'WHERE '.implode(' AND ',$where) : '';

$stmt = $pdo->prepare("
    SELECT a.*, c.name as course_name, c.color
    FROM assignments a LEFT JOIN courses c ON a.course_id=c.id
    $whereStr ORDER BY a.due_date ASC, a.priority DESC
");
$stmt->execute($params);
$assignments = $stmt->fetchAll();

$counts = $pdo->query("
    SELECT status, COUNT(*) as n FROM assignments GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$pending_assignments = ($counts['pending'] ?? 0) ?: null;

ob_start(); ?>
<button class="btn btn-primary" onclick="openModal('addModal')">＋ 新增作業</button>
<?php $topbar_actions = ob_get_clean();
require_once 'includes/header.php'; ?>

<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
    <div style="display:flex;gap:6px">
        <?php foreach(['pending'=>'未完成','done'=>'已完成','all'=>'全部'] as $s=>$l): ?>
        <a href="?status=<?=$s?>&course_id=<?=$filter_course?>" class="btn btn-ghost btn-sm <?= $filter_status===$s?'active':'' ?>"
           style="<?= $filter_status===$s?'background:var(--accent);color:white;border-color:var(--accent)':'' ?>">
            <?= $l ?>
            <?php if(isset($counts[$s])): ?><span style="font-family:'Space Mono',monospace;margin-left:4px">(<?= $counts[$s] ?>)</span><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <select class="form-input" style="width:180px;padding:6px 10px" onchange="location.href='?status=<?=$filter_status?>&course_id='+this.value">
        <option value="0">全部課程</option>
        <?php foreach($courses as $c): ?>
        <option value="<?=$c['id']?>" <?= $filter_course==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="card">
    <?php if (empty($assignments)): ?>
    <div class="empty-state"><div class="icon"><?=svg_icon('check')?></div><p>沒有<?= $filter_status==='pending'?'待完成的':'已完成的' ?>作業</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>作業名稱</th><th>課程</th><th>截止日期</th><th>優先度</th><th>狀態</th><th>操作</th></tr>
        <?php foreach($assignments as $a):
            $overdue = $a['status']==='pending' && $a['due_date'] && $a['due_date'] < date('Y-m-d');
            $days_left = $a['due_date'] ? ceil((strtotime($a['due_date'])-time())/86400) : null;
        ?>
        <tr style="<?= $overdue?'opacity:.8':'' ?>">
            <td>
                <div style="font-weight:600;color:var(--text);<?= $a['status']==='done'?'text-decoration:line-through;opacity:.6':'' ?>"><?= h($a['title']) ?></div>
                <?php if($a['description']): ?><div style="font-size:12px;color:var(--text3)"><?= h(mb_substr($a['description'],0,50)) ?>…</div><?php endif; ?>
                <?php if($overdue): ?><span class="badge badge-red" style="margin-top:4px">已逾期</span><?php endif; ?>
            </td>
            <td><?php if($a['course_name']): ?><?= course_color_dot($a['color']) ?><?= h($a['course_name']) ?><?php else: ?><span style="color:var(--text3)">—</span><?php endif; ?></td>
            <td>
                <?php if($a['due_date']): ?>
                <span style="font-family:'Space Mono',monospace;font-size:12px"><?= $a['due_date'] ?></span>
                <?php if($days_left !== null && $a['status']==='pending'): ?>
                <br><span style="font-size:11px;color:<?= $days_left<=2?'var(--red)':($days_left<=5?'var(--yellow)':'var(--text3)') ?>">
                    <?= $days_left < 0 ? abs($days_left).'天前' : ($days_left===0?'今天':'還有'.$days_left.'天') ?>
                </span>
                <?php endif; ?>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td><?= badge_priority($a['priority']) ?></td>
            <td><?= badge_status($a['status']) ?></td>
            <td>
                <div style="display:flex;gap:6px">
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                        <button class="btn btn-ghost btn-sm"><?= $a['status']==='done'?'← 未完成':'<svg width=\"12\" height=\"12\" viewBox=\"0 0 12 12\" fill=\"none\"><path d=\"M2 6L5 9L10 3\" stroke=\"currentColor\" stroke-width=\"1.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/></svg> 完成' ?></button>
                    </form>
                    <button class="btn btn-ghost btn-sm" onclick='editAssign(<?= htmlspecialchars(json_encode($a)) ?>)'>編輯</button>
                    <form method="post" style="display:inline" onsubmit="return confirm('確定刪除？')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                        <button class="btn btn-danger btn-sm">刪除</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-title">新增作業</div>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">作業名稱 *</label>
                <input class="form-input" name="title" required placeholder="作業標題">
            </div>
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">課程</label>
                    <select class="form-input" name="course_id">
                        <option value="">無課程</option>
                        <?php foreach($courses as $c): ?>
                        <option value="<?=$c['id']?>" <?=$filter_course==$c['id']?'selected':''?>><?= h($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">截止日期</label>
                    <input class="form-input" name="due_date" type="date">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">優先度</label>
                <select class="form-input" name="priority">
                    <option value="low">低</option>
                    <option value="normal" selected>中</option>
                    <option value="high">高</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">描述</label>
                <textarea class="form-input" name="description" placeholder="作業說明…"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">取消</button>
                <button type="submit" class="btn btn-primary">新增</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-title">編輯作業</div>
        <form method="post">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="ea_id">
            <div class="form-group">
                <label class="form-label">作業名稱 *</label>
                <input class="form-input" name="title" id="ea_title" required>
            </div>
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">課程</label>
                    <select class="form-input" name="course_id" id="ea_course">
                        <option value="">無課程</option>
                        <?php foreach($courses as $c): ?>
                        <option value="<?=$c['id']?>"><?= h($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">截止日期</label>
                    <input class="form-input" name="due_date" id="ea_due" type="date">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">優先度</label>
                <select class="form-input" name="priority" id="ea_priority">
                    <option value="low">低</option>
                    <option value="normal">中</option>
                    <option value="high">高</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">描述</label>
                <textarea class="form-input" name="description" id="ea_desc"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">取消</button>
                <button type="submit" class="btn btn-primary">儲存</button>
            </div>
        </form>
    </div>
</div>
<script>
function editAssign(a) {
    document.getElementById('ea_id').value = a.id;
    document.getElementById('ea_title').value = a.title;
    document.getElementById('ea_course').value = a.course_id || '';
    document.getElementById('ea_due').value = a.due_date || '';
    document.getElementById('ea_priority').value = a.priority;
    document.getElementById('ea_desc').value = a.description || '';
    openModal('editModal');
}
</script>
<?php require_once 'includes/footer.php'; ?>
