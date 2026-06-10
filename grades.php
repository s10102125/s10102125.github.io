<?php
require_once 'includes/functions.php';
require_login();
require_once 'includes/db.php';

$page_title = '成績管理';
$current_page = 'grades.php';
$filter_course = (int)($_GET['course_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pdo->prepare("INSERT INTO grades (course_id,title,score,max_score,type,graded_at) VALUES (?,?,?,?,?,?)")
            ->execute([$_POST['course_id'],trim($_POST['title']),$_POST['score'],$_POST['max_score'],$_POST['type'],$_POST['graded_at']?:date('Y-m-d')]);
        flash('成績新增成功！');
    } elseif ($action === 'edit') {
        $pdo->prepare("UPDATE grades SET course_id=?,title=?,score=?,max_score=?,type=?,graded_at=? WHERE id=?")
            ->execute([$_POST['course_id'],trim($_POST['title']),$_POST['score'],$_POST['max_score'],$_POST['type'],$_POST['graded_at'],(int)$_POST['id']]);
        flash('成績已更新！');
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM grades WHERE id=?")->execute([(int)$_POST['id']]);
        flash('成績已刪除');
    }
    redirect('grades.php?course_id='.$filter_course);
}

$courses = $pdo->query("SELECT id,name,color FROM courses ORDER BY name")->fetchAll();

$where = $filter_course ? "WHERE g.course_id=$filter_course" : '';
$grades = $pdo->query("
    SELECT g.*, c.name as course_name, c.color,
        ROUND(g.score/g.max_score*100,1) as pct
    FROM grades g JOIN courses c ON g.course_id=c.id
    $where ORDER BY g.graded_at DESC
")->fetchAll();

// Per-course stats
$course_stats = $pdo->query("
    SELECT c.id, c.name, c.color,
        COUNT(g.id) as cnt,
        ROUND(AVG(g.score/g.max_score*100),1) as avg,
        ROUND(MAX(g.score/g.max_score*100),1) as max,
        ROUND(MIN(g.score/g.max_score*100),1) as min
    FROM courses c LEFT JOIN grades g ON g.course_id=c.id
    GROUP BY c.id ORDER BY avg DESC
")->fetchAll();

ob_start(); ?>
<button class="btn btn-primary" onclick="openModal('addModal')">＋ 新增成績</button>
<?php $topbar_actions = ob_get_clean();
require_once 'includes/header.php'; ?>

<!-- Course summary -->
<div class="grid grid-4" style="margin-bottom:20px">
    <?php foreach($course_stats as $cs): if(!$cs['cnt']) continue; ?>
    <div class="stat-card" style="border-top:3px solid <?= h($cs['color']) ?>;cursor:pointer" onclick="location.href='?course_id=<?=$cs['id']?>'">
        <div class="stat-label" style="margin-bottom:4px"><?= course_color_dot($cs['color']) ?><?= h($cs['name']) ?></div>
        <div class="stat-value <?= $cs['avg']>=75?'stat-green':($cs['avg']>=60?'stat-yellow':'stat-red') ?>"><?= $cs['avg'] ?></div>
        <div class="stat-sub">最高 <?= $cs['max'] ?> · 最低 <?= $cs['min'] ?> · <?= $cs['cnt'] ?> 筆</div>
        <div class="progress-bar"><div class="progress-fill" style="width:<?= $cs['avg'] ?>%;background:<?= $cs['avg']>=75?'var(--green)':($cs['avg']>=60?'var(--yellow)':'var(--red)') ?>"></div></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-title">成績列表
        <?php if($filter_course): ?>
        <a href="grades.php" class="btn btn-ghost btn-sm" style="margin-left:8px">清除篩選</a>
        <?php endif; ?>
        <select class="form-input btn-sm" style="width:160px;margin-left:auto" onchange="location.href='?course_id='+this.value">
            <option value="0">全部課程</option>
            <?php foreach($courses as $c): ?>
            <option value="<?=$c['id']?>" <?=$filter_course==$c['id']?'selected':''?>><?= h($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if(empty($grades)): ?>
    <div class="empty-state"><div class="icon"><?=svg_icon('grades')?></div><p>尚無成績紀錄</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>課程</th><th>項目</th><th>類型</th><th>分數</th><th>百分比</th><th>日期</th><th>操作</th></tr>
        <?php
        $type_map=['exam'=>'考試','quiz'=>'小考','homework'=>'作業','project'=>'專題'];
        foreach($grades as $g): ?>
        <tr>
            <td><?= course_color_dot($g['color']) ?><?= h($g['course_name']) ?></td>
            <td style="color:var(--text);font-weight:600"><?= h($g['title']) ?></td>
            <td><span class="badge badge-purple"><?= $type_map[$g['type']] ?? $g['type'] ?></span></td>
            <td style="font-family:'Space Mono',monospace"><?= $g['score'] ?>/<?= $g['max_score'] ?></td>
            <td><?= grade_badge($g['pct']) ?></td>
            <td style="color:var(--text3)"><?= $g['graded_at'] ?></td>
            <td>
                <div style="display:flex;gap:6px">
                    <button class="btn btn-ghost btn-sm" onclick='editGrade(<?= htmlspecialchars(json_encode($g)) ?>)'>編輯</button>
                    <form method="post" style="display:inline" onsubmit="return confirm('確定刪除？')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $g['id'] ?>">
                        <button class="btn btn-danger btn-sm">刪除</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>

<?php foreach(['add'=>'新增','edit'=>'編輯'] as $mode=>$label): ?>
<div class="modal-overlay" id="<?=$mode?>Modal">
    <div class="modal">
        <div class="modal-title"><?=$label?>成績</div>
        <form method="post">
            <input type="hidden" name="action" value="<?=$mode?>">
            <?php if($mode==='edit'): ?><input type="hidden" name="id" id="eg_id"><?php endif; ?>
            <div class="form-group">
                <label class="form-label">課程 *</label>
                <select class="form-input" name="course_id" id="<?=$mode?>_g_course" required>
                    <?php foreach($courses as $c): ?>
                    <option value="<?=$c['id']?>" <?=$filter_course==$c['id']&&$mode==='add'?'selected':''?>><?= h($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">項目名稱 *</label>
                <input class="form-input" name="title" id="<?=$mode?>_g_title" required placeholder="例：期中考">
            </div>
            <div class="grid grid-3">
                <div class="form-group">
                    <label class="form-label">得分</label>
                    <input class="form-input" name="score" id="<?=$mode?>_g_score" type="number" step="0.1" min="0" required value="<?=$mode==='add'?'0':''?>">
                </div>
                <div class="form-group">
                    <label class="form-label">滿分</label>
                    <input class="form-input" name="max_score" id="<?=$mode?>_g_max" type="number" step="0.1" min="1" required value="100">
                </div>
                <div class="form-group">
                    <label class="form-label">日期</label>
                    <input class="form-input" name="graded_at" id="<?=$mode?>_g_date" type="date" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">類型</label>
                <select class="form-input" name="type" id="<?=$mode?>_g_type">
                    <option value="exam">考試</option>
                    <option value="quiz">小考</option>
                    <option value="homework">作業</option>
                    <option value="project">專題</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal('<?=$mode?>Modal')">取消</button>
                <button type="submit" class="btn btn-primary"><?=$label?></button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<script>
function editGrade(g) {
    document.getElementById('eg_id').value = g.id;
    document.getElementById('edit_g_course').value = g.course_id;
    document.getElementById('edit_g_title').value = g.title;
    document.getElementById('edit_g_score').value = g.score;
    document.getElementById('edit_g_max').value = g.max_score;
    document.getElementById('edit_g_type').value = g.type;
    document.getElementById('edit_g_date').value = g.graded_at;
    openModal('editModal');
}
</script>
<?php require_once 'includes/footer.php'; ?>
