<?php
require_once 'includes/functions.php';
require_login();
require_once 'includes/db.php';

$id = (int)($_GET['id'] ?? 0);
$course = $pdo->prepare("SELECT * FROM courses WHERE id=?");
$course->execute([$id]);
$course = $course->fetch();
if (!$course) { flash('找不到課程','error'); redirect('courses.php'); }

$page_title = $course['name'];
$page_bread = '課程詳情';
$current_page = 'courses.php';

// Handle tabs
$tab = $_GET['tab'] ?? 'info';

// Handle note add
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'add_note') {
        $pdo->prepare("INSERT INTO notes (course_id,title,content) VALUES (?,?,?)")
            ->execute([$id, trim($_POST['title']), trim($_POST['content'])]);
        flash('筆記新增成功！');
    } elseif ($act === 'upload_note') {
        // File upload note
        $note_title = trim($_POST['note_title'] ?? '');
        if (!empty($_FILES['note_file']['tmp_name'])) {
            $file = $_FILES['note_file'];
            $allowed_exts = ['pdf','doc','docx','txt','md','ppt','pptx','xls','xlsx','png','jpg','jpeg'];
            $orig_name = $file['name'];
            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_exts)) {
                flash('不支援的檔案格式', 'error');
            } elseif ($file['size'] > 20 * 1024 * 1024) {
                flash('檔案不可超過 20MB', 'error');
            } else {
                $dir = __DIR__ . '/data/notes/' . $id . '/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $save_name = ($note_title ?: pathinfo($orig_name, PATHINFO_FILENAME)) . '.' . $ext;
                $save_name = preg_replace('/[^\w\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}.\-_ ]/u', '_', $save_name);
                move_uploaded_file($file['tmp_name'], $dir . $save_name);
                $pdo->prepare("INSERT INTO notes (course_id,title,content) VALUES (?,?,?)")
                    ->execute([$id, $save_name, 'file:' . 'data/notes/' . $id . '/' . $save_name]);
                flash('筆記檔案上傳成功！');
            }
        } else {
            flash('請選擇檔案', 'error');
        }
    } elseif ($act === 'rename_note') {
        $nid = (int)$_POST['nid'];
        $new_name = trim($_POST['new_name'] ?? '');
        if ($new_name) {
            $note = $pdo->prepare("SELECT * FROM notes WHERE id=? AND course_id=?");
            $note->execute([$nid, $id]);
            $note = $note->fetch();
            if ($note && strpos($note['content'], 'file:') === 0) {
                $old_path = __DIR__ . '/' . substr($note['content'], 5);
                $ext = strtolower(pathinfo($old_path, PATHINFO_EXTENSION));
                $new_name_full = $new_name . '.' . $ext;
                $new_name_full = preg_replace('/[^\w\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}.\-_ ]/u', '_', $new_name_full);
                $new_path = dirname($old_path) . '/' . $new_name_full;
                if (file_exists($old_path)) rename($old_path, $new_path);
                $new_rel = 'data/notes/' . $id . '/' . $new_name_full;
                $pdo->prepare("UPDATE notes SET title=?,content=? WHERE id=? AND course_id=?")
                    ->execute([$new_name_full, 'file:' . $new_rel, $nid, $id]);
                flash('已重新命名！');
            }
        }
    } elseif ($act === 'del_note') {
        $nid = (int)$_POST['nid'];
        $note = $pdo->prepare("SELECT * FROM notes WHERE id=? AND course_id=?");
        $note->execute([$nid, $id]);
        $note = $note->fetch();
        if ($note && strpos($note['content'], 'file:') === 0) {
            $fpath = __DIR__ . '/' . substr($note['content'], 5);
            if (file_exists($fpath)) unlink($fpath);
        }
        $pdo->prepare("DELETE FROM notes WHERE id=? AND course_id=?")->execute([$nid,$id]);
        flash('筆記已刪除');
    }
    redirect("course_detail.php?id=$id&tab=$tab");
}

$assignments = $pdo->prepare("SELECT * FROM assignments WHERE course_id=? ORDER BY due_date")->execute([$id]) ? [] : [];
$assignments = $pdo->prepare("SELECT * FROM assignments WHERE course_id=? ORDER BY due_date");
$assignments->execute([$id]); $assignments = $assignments->fetchAll();

$grades = $pdo->prepare("SELECT * FROM grades WHERE course_id=? ORDER BY graded_at DESC");
$grades->execute([$id]); $grades = $grades->fetchAll();

$attendance = $pdo->prepare("SELECT * FROM attendance WHERE course_id=? ORDER BY date DESC");
$attendance->execute([$id]); $attendance = $attendance->fetchAll();

$notes = $pdo->prepare("SELECT * FROM notes WHERE course_id=? ORDER BY updated_at DESC");
$notes->execute([$id]); $notes = $notes->fetchAll();

// Attendance stats
$att_total = count($attendance);
$att_absent = count(array_filter($attendance, fn($a) => $a['status'] === 'absent'));
$att_semester_total = semester_class_count($course);
$att_rate = attendance_rate_from_absences($course, $att_absent);

require_once 'includes/header.php';
?>
<style>
.tabs { display:flex; gap:4px; margin-bottom:20px; }
.tab-btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text2);
    cursor: pointer;
    transition: all .15s;
    text-decoration: none;
    display: inline-block;
}
.tab-btn.active, .tab-btn:hover { background: var(--accent); color: white; border-color: var(--accent); }
</style>

<div class="card" style="border-top:4px solid <?= h($course['color']) ?>;margin-bottom:20px">
    <div style="display:flex;align-items:center;gap:16px">
        <div style="width:48px;height:48px;border-radius:12px;background:<?= h($course['color']) ?>;display:flex;align-items:center;justify-content:center;font-size:22px"><?=svg_icon('courses')?></div>
        <div>
            <div style="font-size:20px;font-weight:700;color:var(--text)"><?= h($course['name']) ?></div>
            <div style="font-size:13px;color:var(--text3)"><?= h($course['code']) ?> · <?= $course['credits'] ?> 學分 · <?= h($course['teacher'] ?? '') ?> · <?= h($course['location'] ?? '') ?></div>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px;align-items:flex-start">
            <a href="courses.php?edit_id=<?= $id ?>" class="btn btn-ghost btn-sm" style="margin-top:4px"><?=svg_icon('settings')?> 編輯課程</a>
            <div class="stat-card" style="padding:10px 16px;text-align:center">
                <div class="stat-label" style="margin-bottom:2px">出席率</div>
                <div style="font-size:20px;font-weight:700;font-family:'Space Mono',monospace;color:<?= $att_rate>=75?'var(--green)':'var(--red)' ?>"><?= $att_rate ?>%</div>
            </div>
            <?php $avg = $grades ? round(array_sum(array_map(fn($g)=>$g['score']/$g['max_score']*100,$grades))/count($grades),1) : null; ?>
            <?php if($avg): ?>
            <div class="stat-card" style="padding:10px 16px;text-align:center">
                <div class="stat-label" style="margin-bottom:2px">平均分數</div>
                <div style="font-size:20px;font-weight:700;font-family:'Space Mono',monospace;color:<?= $avg>=75?'var(--green)':'var(--yellow)' ?>"><?= $avg ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="tabs">
    <a href="?id=<?=$id?>&tab=info" class="tab-btn <?= $tab==='info'?'active':'' ?>"><?=svg_icon('todos')?> 課程資訊</a>
    <a href="?id=<?=$id?>&tab=attendance" class="tab-btn <?= $tab==='attendance'?'active':'' ?>"><?=svg_icon('attendance')?> 出缺席 (<?=$att_total?>)</a>
    <a href="?id=<?=$id?>&tab=assignments" class="tab-btn <?= $tab==='assignments'?'active':'' ?>"><?=svg_icon('assignments')?> 作業 (<?=count($assignments)?>)</a>
    <a href="?id=<?=$id?>&tab=grades" class="tab-btn <?= $tab==='grades'?'active':'' ?>"><?=svg_icon('grades')?> 成績 (<?=count($grades)?>)</a>
    <a href="?id=<?=$id?>&tab=notes" class="tab-btn <?= $tab==='notes'?'active':'' ?>"><?=svg_icon('notes')?> 筆記 (<?=count($notes)?>)</a>
</div>

<?php if ($tab === 'info'): ?>
<div class="card">
    <div class="card-title">課程詳細資訊</div>
    <table>
        <tr><td style="color:var(--text3);width:120px">課程名稱</td><td style="color:var(--text);font-weight:600"><?= h($course['name']) ?></td></tr>
        <tr><td style="color:var(--text3)">課程代碼</td><td><?= h($course['code'] ?? '—') ?></td></tr>
        <tr><td style="color:var(--text3)">授課教師</td><td><?= h($course['teacher'] ?? '—') ?></td></tr>
        <tr><td style="color:var(--text3)">上課地點</td><td><?= h($course['location'] ?? '—') ?></td></tr>
        <tr><td style="color:var(--text3)">學分數</td><td><?= $course['credits'] ?></td></tr>
        <tr><td style="color:var(--text3)">建立時間</td><td><?= $course['created_at'] ?></td></tr>
    </table>
</div>

<?php elseif ($tab === 'attendance'): ?>
<div class="card">
    <div class="card-title">出缺席紀錄
        <a href="attendance.php?course_id=<?=$id?>" class="btn btn-primary btn-sm" style="margin-left:auto">新增紀錄</a>
    </div>
    <?php if (empty($attendance)): ?>
    <div class="empty-state"><div class="icon"><?=svg_icon('todos')?></div><p>尚無出缺席紀錄</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>日期</th><th>狀態</th><th>備注</th></tr>
        <?php foreach($attendance as $a): ?>
        <tr>
            <td style="font-family:'Space Mono',monospace"><?= $a['date'] ?></td>
            <td><?= badge_status($a['status']) ?></td>
            <td><?= h($a['note'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'assignments'): ?>
<div class="card">
    <div class="card-title">作業列表
        <a href="assignments.php?course_id=<?=$id?>" class="btn btn-primary btn-sm" style="margin-left:auto">新增作業</a>
    </div>
    <?php if (empty($assignments)): ?>
    <div class="empty-state"><div class="icon"><?=svg_icon('assignments')?></div><p>尚無作業</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>作業名稱</th><th>截止日期</th><th>優先度</th><th>狀態</th></tr>
        <?php foreach($assignments as $a): ?>
        <tr>
            <td style="color:var(--text);font-weight:500"><?= h($a['title']) ?></td>
            <td style="font-family:'Space Mono',monospace"><?= $a['due_date'] ?? '—' ?></td>
            <td><?= badge_priority($a['priority']) ?></td>
            <td><?= badge_status($a['status']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'grades'): ?>
<div class="card">
    <div class="card-title">成績紀錄
        <a href="grades.php?course_id=<?=$id?>" class="btn btn-primary btn-sm" style="margin-left:auto">新增成績</a>
    </div>
    <?php if (empty($grades)): ?>
    <div class="empty-state"><div class="icon"><?=svg_icon('grades')?></div><p>尚無成績紀錄</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>項目</th><th>類型</th><th>分數</th><th>百分比</th><th>日期</th></tr>
        <?php foreach($grades as $g):
            $pct = round($g['score']/$g['max_score']*100,1);
            $type_map=['exam'=>'考試','quiz'=>'小考','homework'=>'作業','project'=>'專題'];
        ?>
        <tr>
            <td style="color:var(--text);font-weight:500"><?= h($g['title']) ?></td>
            <td><span class="badge badge-purple"><?= $type_map[$g['type']] ?? $g['type'] ?></span></td>
            <td style="font-family:'Space Mono',monospace"><?= $g['score'] ?>/<?= $g['max_score'] ?></td>
            <td><?= grade_badge($pct) ?></td>
            <td style="color:var(--text3)"><?= $g['graded_at'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'notes'): ?>
<style>
.note-file-card {
    display:flex;align-items:center;gap:12px;padding:14px;
    border-radius:10px;border:1px solid var(--border);margin-bottom:10px;
    background:var(--bg2);transition:border-color .15s;
}
.note-file-card:hover { border-color:var(--accent); }
.note-file-icon {
    width:42px;height:42px;border-radius:8px;
    display:flex;align-items:center;justify-content:center;
    font-size:20px;flex-shrink:0;
}
.note-pdf    { background:rgba(239,68,68,.12);color:#ef4444; }
.note-doc    { background:rgba(59,130,246,.12);color:#3b82f6; }
.note-img    { background:rgba(34,197,94,.12);color:#22c55e; }
.note-other  { background:rgba(100,116,139,.12);color:#64748b; }
.note-pdf-preview {
    width:100%;max-height:500px;border:none;border-radius:8px;
    margin-top:8px;background:var(--bg3);
}
</style>
<?php
function note_file_icon(string $ext): string {
    if ($ext === 'pdf') return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="12" y2="17"/></svg>';
    if (in_array($ext, ['doc','docx'])) return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
    if (in_array($ext, ['png','jpg','jpeg','gif','webp'])) return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
    return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
}
function note_icon_class(string $ext): string {
    if ($ext === 'pdf') return 'note-pdf';
    if (in_array($ext, ['doc','docx'])) return 'note-doc';
    if (in_array($ext, ['png','jpg','jpeg','gif','webp'])) return 'note-img';
    return 'note-other';
}
?>
<div style="display:flex;gap:16px;align-items:flex-start">
    <div style="flex:1;min-width:0">
        <div class="card">
            <div class="card-title"><?=svg_icon('notes')?> 筆記檔案
                <span style="font-size:12px;color:var(--text3);font-weight:400;margin-left:6px"><?= count($notes) ?> 個檔案</span>
            </div>
            <?php if (empty($notes)): ?>
            <div class="empty-state"><div class="icon"><?=svg_icon('notes')?></div><p>尚無筆記檔案，請從右側上傳</p></div>
            <?php else: ?>
            <?php foreach($notes as $n):
                $is_file = strpos($n['content'], 'file:') === 0;
                $file_path = $is_file ? substr($n['content'], 5) : '';
                $ext = strtolower(pathinfo($n['title'], PATHINFO_EXTENSION));
                $base_name = pathinfo($n['title'], PATHINFO_FILENAME);
                $file_exists = $is_file && file_exists(__DIR__ . '/' . $file_path);
            ?>
            <div class="note-file-card" id="note_card_<?= $n['id'] ?>">
                <?php if ($is_file): ?>
                <div class="note-file-icon <?= note_icon_class($ext) ?>"><?= note_file_icon($ext) ?></div>
                <div style="flex:1;min-width:0">
                    <div style="font-weight:600;color:var(--text);display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <span id="note_name_<?= $n['id'] ?>"><?= h($n['title']) ?></span>
                        <button class="btn btn-ghost btn-sm" style="padding:2px 8px;font-size:11px"
                            onclick="startRename(<?= $n['id'] ?>, '<?= addslashes($base_name) ?>', '<?= $ext ?>')">重新命名</button>
                    </div>
                    <div style="font-size:11px;color:var(--text3);margin-top:2px"><?= strtoupper($ext) ?> · <?= $n['updated_at'] ?></div>
                    <?php if ($ext === 'pdf' && $file_exists): ?>
                    <div style="margin-top:8px">
                        <button class="btn btn-ghost btn-sm" style="font-size:11px"
                            onclick="togglePdfPreview(<?= $n['id'] ?>, '<?= addslashes(h($file_path)) ?>')">
                            <?=svg_icon('info')?> 網頁預覽
                        </button>
                    </div>
                    <div id="pdf_preview_<?= $n['id'] ?>" style="display:none;margin-top:8px">
                        <iframe src="<?= h($file_path) ?>" class="note-pdf-preview" height="480"></iframe>
                    </div>
                    <?php endif; ?>
                </div>
                <div style="display:flex;gap:6px;flex-shrink:0">
                    <?php if ($file_exists): ?>
                    <a href="<?= h($file_path) ?>" download class="btn btn-ghost btn-sm"><?=svg_icon('grades')?> 下載</a>
                    <?php endif; ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('刪除此筆記檔案？')">
                        <input type="hidden" name="action" value="del_note">
                        <input type="hidden" name="nid" value="<?= $n['id'] ?>">
                        <button class="btn btn-danger btn-sm">刪除</button>
                    </form>
                </div>
                <?php else: ?>
                <!-- Text note fallback -->
                <div style="flex:1">
                    <div style="font-weight:600;color:var(--text);margin-bottom:4px"><?= h($n['title']) ?></div>
                    <div style="font-size:12px;color:var(--text2);white-space:pre-wrap"><?= h($n['content']) ?></div>
                </div>
                <form method="post" style="display:inline;flex-shrink:0">
                    <input type="hidden" name="action" value="del_note">
                    <input type="hidden" name="nid" value="<?= $n['id'] ?>">
                    <button class="btn btn-danger btn-sm" onclick="return confirm('刪除？')">刪除</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div style="width:280px;flex-shrink:0">
        <div class="card">
            <div class="card-title"><?=svg_icon('notes')?> 上傳筆記檔案</div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_note">
                <div class="form-group">
                    <label class="form-label">選擇檔案</label>
                    <div id="drop_zone" style="border:2px dashed var(--border);border-radius:10px;padding:20px 12px;text-align:center;cursor:pointer;transition:.15s;background:var(--bg3)"
                         onclick="document.getElementById('note_file_input').click()"
                         ondragover="event.preventDefault();this.style.borderColor='var(--accent)'"
                         ondragleave="this.style.borderColor='var(--border)'"
                         ondrop="handleDrop(event)">
                        <div style="margin-bottom:6px"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg></div>
                        <div style="font-size:12px;color:var(--text3)">點擊或拖曳檔案到這裡</div>
                        <div style="font-size:10px;color:var(--text3);margin-top:4px">PDF、Word、圖片等，最大 20MB</div>
                        <div id="drop_filename" style="font-size:12px;color:var(--accent);margin-top:6px;font-weight:600"></div>
                    </div>
                    <input type="file" name="note_file" id="note_file_input" accept=".pdf,.doc,.docx,.txt,.md,.ppt,.pptx,.xls,.xlsx,.png,.jpg,.jpeg"
                           style="display:none" onchange="showFileName(this)">
                </div>
                <div class="form-group">
                    <label class="form-label">自訂檔名 <span style="color:var(--text3);font-weight:400">（可選，不需加副檔名）</span></label>
                    <input class="form-input" name="note_title" id="note_title_input" placeholder="留空則使用原始檔名">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%"><?=svg_icon('notes')?> 上傳</button>
            </form>
        </div>
    </div>
</div>

<!-- Rename Modal -->
<div class="modal-overlay" id="renameModal">
    <div class="modal" style="width:min(420px,calc(100vw - 32px));padding:28px">
        <div class="modal-title">重新命名筆記</div>
        <form method="post">
            <input type="hidden" name="action" value="rename_note">
            <input type="hidden" name="nid" id="rename_nid">
            <div class="form-group">
                <label class="form-label">新檔名 <span style="color:var(--text3);font-weight:400" id="rename_ext_hint"></span></label>
                <input class="form-input" name="new_name" id="rename_input" required placeholder="新的檔案名稱（不需副檔名）">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal('renameModal')">取消</button>
                <button type="submit" class="btn btn-primary">確認重新命名</button>
            </div>
        </form>
    </div>
</div>

<script>
function showFileName(input) {
    var name = input.files[0] ? input.files[0].name : '';
    document.getElementById('drop_filename').textContent = name ? '已選擇：' + name : '';
    // Auto-fill title field with filename without extension
    var titleInput = document.getElementById('note_title_input');
    if (name && !titleInput.value) {
        titleInput.value = name.replace(/\.[^.]+$/, '');
    }
}
function handleDrop(e) {
    e.preventDefault();
    var dt = e.dataTransfer;
    if (dt.files.length) {
        document.getElementById('note_file_input').files = dt.files;
        showFileName(document.getElementById('note_file_input'));
    }
    e.currentTarget.style.borderColor = 'var(--border)';
}
function togglePdfPreview(id, path) {
    var el = document.getElementById('pdf_preview_' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
function startRename(id, currentBase, ext) {
    document.getElementById('rename_nid').value = id;
    document.getElementById('rename_input').value = currentBase;
    document.getElementById('rename_ext_hint').textContent = '（副檔名：.' + ext + '）';
    openModal('renameModal');
}
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
