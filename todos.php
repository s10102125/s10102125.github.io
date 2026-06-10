<?php
require_once 'includes/functions.php';
require_login();
require_once 'includes/db.php';

$page_title = '待辦事項';
$current_page = 'todos.php';
$filter = $_GET['status'] ?? 'pending';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pdo->prepare("INSERT INTO todos (title,description,due_date,priority) VALUES (?,?,?,?)")
            ->execute([trim($_POST['title']),trim($_POST['description']),
                $_POST['due_date']?:null,$_POST['priority']]);
        flash('待辦事項已新增！');
    } elseif ($action === 'toggle') {
        $stmt = $pdo->prepare("SELECT status FROM todos WHERE id=?");
        $stmt->execute([(int)$_POST['id']]);
        $cur = $stmt->fetchColumn();
        $new = $cur === 'done' ? 'pending' : 'done';
        $pdo->prepare("UPDATE todos SET status=? WHERE id=?")->execute([$new,(int)$_POST['id']]);
        flash($new==='done' ? '已完成！' : '已標記為未完成');
    } elseif ($action === 'edit') {
        $pdo->prepare("UPDATE todos SET title=?,description=?,due_date=?,priority=? WHERE id=?")
            ->execute([trim($_POST['title']),trim($_POST['description']),
                $_POST['due_date']?:null,$_POST['priority'],(int)$_POST['id']]);
        flash('已更新！');
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM todos WHERE id=?")->execute([(int)$_POST['id']]);
        flash('已刪除');
    }
    redirect('todos.php?status='.$filter);
}

$todos = $pdo->query("
    SELECT * FROM todos
    ".($filter!=='all'?"WHERE status='$filter'":'')."
    ORDER BY
        CASE priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END,
        CASE WHEN due_date IS NULL THEN 1 ELSE 0 END,
        due_date ASC
")->fetchAll();

$counts = $pdo->query("SELECT status,COUNT(*) FROM todos GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

ob_start(); ?>
<button class="btn btn-primary" onclick="openModal('addModal')">＋ 新增待辦</button>
<style>
.todo-check:hover { border-color: var(--green) !important; background: rgba(34,197,94,.08) !important; }
.todo-check:hover svg path { opacity: 0.5 !important; stroke: var(--green) !important; }
</style>
<?php $topbar_actions = ob_get_clean();
require_once 'includes/header.php'; ?>

<div style="display:flex;gap:6px;margin-bottom:20px">
    <?php foreach(['pending'=>'未完成','done'=>'已完成','all'=>'全部'] as $s=>$l): ?>
    <a href="?status=<?=$s?>" class="btn btn-ghost btn-sm"
       style="<?=$filter===$s?'background:var(--accent);color:white;border-color:var(--accent)':''?>">
        <?=$l?> <?php if(isset($counts[$s])): ?>(<?=$counts[$s]?>)<?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <?php if(empty($todos)): ?>
    <div class="empty-state">
        <div class="icon"><?=svg_icon('check')?></div>
        <p>沒有<?=$filter==='pending'?'待完成':'已完成'?>的事項</p>
    </div>
    <?php else: ?>
    <?php foreach($todos as $t):
        $overdue = $t['status']==='pending' && $t['due_date'] && $t['due_date'] < date('Y-m-d');
        $days = $t['due_date'] ? ceil((strtotime($t['due_date'])-time())/86400) : null;
    ?>
    <div style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border)">
        <form method="post" style="display:contents">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?=$t['id']?>">
            <button type="submit" title="<?=$t['status']==='done'?'標記為未完成':'標記完成'?>" class="todo-check"
                style="width:22px;height:22px;border-radius:6px;border:2px solid <?=$t['status']==='done'?'var(--green)':'var(--border)'?>;background:<?=$t['status']==='done'?'var(--green)':'transparent'?>;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;padding:0;transition:all .15s">
                <?php if($t['status']==='done'): ?>
                <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M2.5 6.5L5.5 9.5L10.5 3.5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <?php else: ?>
                <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M2.5 6.5L5.5 9.5L10.5 3.5" stroke="var(--border)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0"/></svg>
                <?php endif; ?>
            </button>
        </form>
        <div style="flex:1;min-width:0">
            <div style="font-weight:600;color:var(--text);<?=$t['status']==='done'?'text-decoration:line-through;opacity:.5':''?>"><?=h($t['title'])?></div>
            <?php if($t['description']): ?>
            <div style="font-size:12px;color:var(--text3);margin-top:2px"><?=h($t['description'])?></div>
            <?php endif; ?>
        </div>
        <?=badge_priority($t['priority'])?>
        <?php if($t['due_date']): ?>
        <span style="font-size:12px;font-family:'Space Mono',monospace;white-space:nowrap;color:<?=$overdue?'var(--red)':($days<=2?'var(--yellow)':'var(--text3)')?>">
            <?=$overdue?'逾期 '.abs($days).'天':($days===0?'今天':'還有'.$days.'天')?>
        </span>
        <?php endif; ?>
        <div style="display:flex;gap:6px;flex-shrink:0">
            <button class="btn btn-ghost btn-sm" onclick='editTodo(<?=htmlspecialchars(json_encode($t),ENT_QUOTES)?>)'>編輯</button>
            <form method="post" style="display:inline" onsubmit="return confirm('確定刪除？')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?=$t['id']?>">
                <button class="btn btn-danger btn-sm">刪除</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ── Modals（JS teleport 會把它們移到 body 最外層）── -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-title">新增待辦事項</div>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">標題 *</label>
                <input class="form-input" name="title" required placeholder="待辦事項標題" autocomplete="off">
            </div>

            <!-- Date mode toggle -->
            <div class="form-group">
                <label class="form-label">截止日期</label>
                <div style="display:flex;gap:0;margin-bottom:10px;border:1px solid var(--border);border-radius:8px;overflow:hidden;width:fit-content">
                    <button type="button" id="a_mode_none" onclick="setDateMode('add','none')"
                        style="padding:6px 14px;font-size:12px;border:none;cursor:pointer;transition:.15s;font-family:'Noto Sans TC',sans-serif;background:var(--accent);color:white">
                        無日期
                    </button>
                    <button type="button" id="a_mode_single" onclick="setDateMode('add','single')"
                        style="padding:6px 14px;font-size:12px;border:none;cursor:pointer;transition:.15s;font-family:'Noto Sans TC',sans-serif;background:var(--bg3);color:var(--text2)">
                        單日
                    </button>
                    <button type="button" id="a_mode_range" onclick="setDateMode('add','range')"
                        style="padding:6px 14px;font-size:12px;border:none;cursor:pointer;transition:.15s;font-family:'Noto Sans TC',sans-serif;background:var(--bg3);color:var(--text2)">
                        範圍
                    </button>
                </div>
                <div id="a_date_single" style="display:none">
                    <input class="form-input" name="due_date" id="a_due_single" type="date">
                </div>
                <div id="a_date_range" style="display:none;display:none;gap:8px;align-items:center">
                    <input class="form-input" name="due_date" id="a_due_start" type="date" style="flex:1">
                    <span style="color:var(--text3);font-size:13px">到</span>
                    <input class="form-input" name="end_date" id="a_due_end" type="date" style="flex:1">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">優先度</label>
                <div style="display:flex;gap:6px">
                    <?php foreach(['low'=>'低','normal'=>'中','high'=>'高'] as $v=>$l): ?>
                    <label style="flex:1;cursor:pointer">
                        <input type="radio" name="priority" value="<?=$v?>" <?=$v==='normal'?'checked':''?> style="display:none" class="prio-radio">
                        <div class="prio-btn" data-val="<?=$v?>" style="text-align:center;padding:8px;border-radius:8px;border:1px solid var(--border);font-size:13px;transition:.15s;background:var(--bg3);color:var(--text2)">
                            <?=$l?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">描述</label>
                <textarea class="form-input" name="description" placeholder="補充說明…"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">取消</button>
                <button type="submit" class="btn btn-primary">新增</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-title">編輯待辦事項</div>
        <form method="post">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="et_id">
            <div class="form-group">
                <label class="form-label">標題 *</label>
                <input class="form-input" name="title" id="et_title" required autocomplete="off">
            </div>
            <!-- Date mode toggle -->
            <div class="form-group">
                <label class="form-label">截止日期</label>
                <div style="display:flex;gap:0;margin-bottom:10px;border:1px solid var(--border);border-radius:8px;overflow:hidden;width:fit-content">
                    <button type="button" id="e_mode_none" onclick="setDateMode('edit','none')"
                        style="padding:6px 14px;font-size:12px;border:none;cursor:pointer;transition:.15s;font-family:'Noto Sans TC',sans-serif;background:var(--bg3);color:var(--text2)">
                        無日期
                    </button>
                    <button type="button" id="e_mode_single" onclick="setDateMode('edit','single')"
                        style="padding:6px 14px;font-size:12px;border:none;cursor:pointer;transition:.15s;font-family:'Noto Sans TC',sans-serif;background:var(--bg3);color:var(--text2)">
                        單日
                    </button>
                    <button type="button" id="e_mode_range" onclick="setDateMode('edit','range')"
                        style="padding:6px 14px;font-size:12px;border:none;cursor:pointer;transition:.15s;font-family:'Noto Sans TC',sans-serif;background:var(--bg3);color:var(--text2)">
                        範圍
                    </button>
                </div>
                <div id="e_date_single" style="display:none">
                    <input class="form-input" name="due_date" id="et_due" type="date">
                </div>
                <div id="e_date_range" style="display:none;gap:8px;align-items:center">
                    <input class="form-input" name="due_date" id="et_due_start" type="date" style="flex:1">
                    <span style="color:var(--text3);font-size:13px">到</span>
                    <input class="form-input" name="end_date" id="et_due_end" type="date" style="flex:1">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">優先度</label>
                <div style="display:flex;gap:6px">
                    <?php foreach(['low'=>'低','normal'=>'中','high'=>'高'] as $v=>$l): ?>
                    <label style="flex:1;cursor:pointer">
                        <input type="radio" name="priority" value="<?=$v?>" id="ep_<?=$v?>" style="display:none" class="prio-radio-e">
                        <div class="prio-btn-e" data-val="<?=$v?>" style="text-align:center;padding:8px;border-radius:8px;border:1px solid var(--border);font-size:13px;transition:.15s;background:var(--bg3);color:var(--text2)">
                            <?=$l?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">描述</label>
                <textarea class="form-input" name="description" id="et_desc" placeholder="補充說明…"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">取消</button>
                <button type="submit" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<script>
// Date mode toggle
var _dateMode = { add: 'none', edit: 'none' };

function setDateMode(form, mode) {
    _dateMode[form] = mode;
    var prefix = form === 'add' ? 'a' : 'e';
    var modes = ['none','single','range'];
    modes.forEach(function(m) {
        var btn = document.getElementById(prefix + '_mode_' + m);
        if (btn) {
            btn.style.background = m === mode ? 'var(--accent)' : 'var(--bg3)';
            btn.style.color      = m === mode ? 'white'         : 'var(--text2)';
        }
    });
    var singleEl = document.getElementById(prefix + '_date_single');
    var rangeEl  = document.getElementById(prefix + '_date_range');
    if (singleEl) singleEl.style.display = mode === 'single' ? 'block' : 'none';
    if (rangeEl)  rangeEl.style.display  = mode === 'range'  ? 'flex'  : 'none';

    // Clear hidden fields when switching away
    if (mode !== 'single') {
        var sd = document.getElementById(prefix === 'a' ? 'a_due_single' : 'et_due');
        if (sd) sd.value = '';
    }
    if (mode !== 'range') {
        var rd = document.getElementById(prefix === 'a' ? 'a_due_end' : 'et_due_end');
        if (rd) rd.value = '';
    }
}

// Priority button highlight
function initPrioBtns(cls, radioClass) {
    document.querySelectorAll('.' + cls).forEach(function(btn) {
        btn.addEventListener('click', function() {
            var val = this.getAttribute('data-val');
            document.querySelectorAll('.' + cls).forEach(function(b) {
                var isActive = b.getAttribute('data-val') === val;
                b.style.background   = isActive ? 'var(--accent)' : 'var(--bg3)';
                b.style.color        = isActive ? 'white'         : 'var(--text2)';
                b.style.borderColor  = isActive ? 'var(--accent)' : 'var(--border)';
            });
        });
    });
}
document.addEventListener('DOMContentLoaded', function() {
    initPrioBtns('prio-btn', 'prio-radio');
    initPrioBtns('prio-btn-e', 'prio-radio-e');
    // Highlight default (normal)
    document.querySelectorAll('.prio-btn[data-val="normal"],.prio-btn-e[data-val="normal"]').forEach(function(b){
        b.style.background = 'var(--accent)'; b.style.color = 'white'; b.style.borderColor = 'var(--accent)';
    });
    // Default mode
    setDateMode('add','none');
    setDateMode('edit','none');
});

function editTodo(t) {
    document.getElementById('et_id').value       = t.id;
    document.getElementById('et_title').value    = t.title    || '';
    document.getElementById('et_desc').value     = t.description || '';

    // Detect date mode
    var has_start = t.due_date  && t.due_date  !== '';
    var has_end   = t.end_date  && t.end_date  !== '' && t.end_date !== t.due_date;
    var mode = 'none';
    if (has_end)        mode = 'range';
    else if (has_start) mode = 'single';
    setDateMode('edit', mode);

    if (mode === 'single') {
        document.getElementById('et_due').value = t.due_date || '';
    } else if (mode === 'range') {
        document.getElementById('et_due_start').value = t.due_date  || '';
        document.getElementById('et_due_end').value   = t.end_date  || '';
    }

    // Priority
    var prio = t.priority || 'normal';
    document.querySelectorAll('.prio-btn-e').forEach(function(b) {
        var isActive = b.getAttribute('data-val') === prio;
        b.style.background   = isActive ? 'var(--accent)' : 'var(--bg3)';
        b.style.color        = isActive ? 'white'         : 'var(--text2)';
        b.style.borderColor  = isActive ? 'var(--accent)' : 'var(--border)';
    });
    document.querySelectorAll('.prio-radio-e').forEach(function(r) {
        r.checked = r.value === prio;
    });

    openModal('editModal');
}
</script>

<?php require_once 'includes/footer.php'; ?>
