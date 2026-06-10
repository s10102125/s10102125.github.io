<?php
require_once 'includes/functions.php';
require_login();
require_once 'includes/db.php';

$page_title = '考試成績';
$current_page = 'exam_scores.php';

$sem_id = active_semester_id($pdo);

// Ensure semester_rankings table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS semester_rankings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    semester_id INTEGER NOT NULL UNIQUE,
    class_rank INTEGER,
    class_total INTEGER,
    dept_rank INTEGER,
    dept_total INTEGER,
    note TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $course_id = (int)$_POST['course_id'];
        $existing = $pdo->prepare("SELECT id FROM exam_scores WHERE course_id=? AND semester_id=?");
        $existing->execute([$course_id, $sem_id]);
        $eid = $existing->fetchColumn();

        $fields = [
            'midterm_score' => $_POST['midterm_score'] !== '' ? (float)$_POST['midterm_score'] : null,
            'midterm_max'   => (float)($_POST['midterm_max']   ?: 100),
            'final_score'   => $_POST['final_score'] !== '' ? (float)$_POST['final_score'] : null,
            'final_max'     => (float)($_POST['final_max']     ?: 100),
            'regular_score' => $_POST['regular_score'] !== '' ? (float)$_POST['regular_score'] : null,
            'regular_max'   => (float)($_POST['regular_max']   ?: 100),
            'regular_pct'   => (float)($_POST['regular_pct']   ?: 30),
            'midterm_pct'   => (float)($_POST['midterm_pct']   ?: 30),
            'final_pct'     => (float)($_POST['final_pct']     ?: 40),
            'pass_score'    => (float)($_POST['pass_score'] ?: 60),
            'note'          => trim($_POST['note'] ?? ''),
        ];

        if ($eid) {
            $set = implode(',', array_map(fn($k) => "$k=?", array_keys($fields)));
            $vals = array_values($fields);
            $vals[] = $eid;
            $pdo->prepare("UPDATE exam_scores SET $set, updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute($vals);
        } else {
            $keys = implode(',', array_keys($fields));
            $phs  = implode(',', array_fill(0, count($fields), '?'));
            $vals = array_values($fields);
            $pdo->prepare("INSERT INTO exam_scores (course_id,semester_id,$keys) VALUES (?,?,$phs)")
                ->execute(array_merge([$course_id, $sem_id], $vals));
        }
        flash('成績已儲存！');
        redirect('exam_scores.php');

    } elseif ($action === 'save_ranking') {
        $fields = [
            'class_rank'  => $_POST['class_rank']  !== '' ? (int)$_POST['class_rank']  : null,
            'class_total' => $_POST['class_total'] !== '' ? (int)$_POST['class_total'] : null,
            'dept_rank'   => $_POST['dept_rank']   !== '' ? (int)$_POST['dept_rank']   : null,
            'dept_total'  => $_POST['dept_total']  !== '' ? (int)$_POST['dept_total']  : null,
            'note'        => trim($_POST['rank_note'] ?? ''),
        ];
        $existing = $pdo->prepare("SELECT id FROM semester_rankings WHERE semester_id=?");
        $existing->execute([$sem_id]);
        $rid = $existing->fetchColumn();
        if ($rid) {
            $set = implode(',', array_map(fn($k) => "$k=?", array_keys($fields)));
            $vals = array_values($fields);
            $vals[] = $rid;
            $pdo->prepare("UPDATE semester_rankings SET $set, updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute($vals);
        } else {
            $keys = implode(',', array_keys($fields));
            $phs  = implode(',', array_fill(0, count($fields), '?'));
            $pdo->prepare("INSERT INTO semester_rankings (semester_id,$keys) VALUES (?,$phs)")
                ->execute(array_merge([$sem_id], array_values($fields)));
        }
        flash('排名已儲存！');
        redirect('exam_scores.php#ranking');
    }
}

// Get courses for this semester
$courses = $pdo->prepare("SELECT * FROM courses WHERE semester_id=? ORDER BY name");
$courses->execute([$sem_id]);
$courses = $courses->fetchAll();

// Get all exam score records
$scores_raw = $pdo->prepare("SELECT es.*, c.name, c.color FROM exam_scores es JOIN courses c ON es.course_id=c.id WHERE es.semester_id=?");
$scores_raw->execute([$sem_id]);
$scores_by_course = [];
foreach ($scores_raw->fetchAll() as $r) {
    $scores_by_course[$r['course_id']] = $r;
}

// Get semester ranking
$sem_ranking = $pdo->prepare("SELECT * FROM semester_rankings WHERE semester_id=?");
$sem_ranking->execute([$sem_id]);
$sem_ranking = $sem_ranking->fetch();

// Check if all courses have complete scores (for ranking unlock)
$all_complete = !empty($courses);
foreach ($courses as $c) {
    $sc = $scores_by_course[$c['id']] ?? null;
    if (!$sc || $sc['regular_score'] === null || $sc['midterm_score'] === null || $sc['final_score'] === null) {
        $all_complete = false;
        break;
    }
}
$complete_count = 0;
foreach ($courses as $c) {
    $sc = $scores_by_course[$c['id']] ?? null;
    if ($sc && $sc['regular_score'] !== null && $sc['midterm_score'] !== null && $sc['final_score'] !== null) {
        $complete_count++;
    }
}

ob_start(); ?>
<button class="btn btn-primary" onclick="openModal('addScoreModal')">＋ 輸入成績</button>
<?php $topbar_actions = ob_get_clean();
require_once 'includes/header.php'; ?>

<style>
.score-card {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    padding: 20px 24px;
    margin-bottom: 14px;
    transition: border-color .15s;
}
.score-card:hover { border-color: var(--accent); }
.score-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}
.score-course-name {
    font-size: 16px;
    font-weight: 700;
    color: var(--text);
    flex: 1;
}
.score-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 14px;
}
.score-item {
    background: var(--bg3);
    border-radius: 10px;
    padding: 14px 16px;
    text-align: center;
}
.score-item-label {
    font-size: 11px;
    color: var(--text3);
    text-transform: uppercase;
    letter-spacing: .8px;
    margin-bottom: 6px;
    font-weight: 600;
}
.score-item-value {
    font-size: 28px;
    font-weight: 700;
    font-family: 'Space Mono', monospace;
    line-height: 1;
}
.score-item-pct {
    font-size: 11px;
    color: var(--text3);
    margin-top: 4px;
}
.semester-score-big {
    font-size: 42px;
    font-weight: 700;
    font-family: 'Space Mono', monospace;
    line-height: 1;
}
.calc-box {
    background: linear-gradient(135deg, rgba(91,127,255,.08), rgba(167,139,250,.08));
    border: 1px solid rgba(91,127,255,.2);
    border-radius: 10px;
    padding: 14px 18px;
    margin-top: 12px;
}
.calc-box-title {
    font-size: 11px;
    font-weight: 600;
    color: var(--accent2);
    text-transform: uppercase;
    letter-spacing: .8px;
    margin-bottom: 10px;
}
.calc-result {
    font-size: 13px;
    color: var(--text2);
    line-height: 1.7;
}
.calc-result strong {
    color: var(--text);
    font-family: 'Space Mono', monospace;
}
.need-score-value {
    font-size: 24px;
    font-weight: 700;
    font-family: 'Space Mono', monospace;
}

/* Ranking section */
.ranking-section {
    scroll-margin-top: 80px;
    margin-top: 28px;
}
.ranking-card {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: var(--card-radius);
    overflow: hidden;
}
.ranking-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 24px;
    border-bottom: 1px solid var(--border);
}
.ranking-header-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 700;
    color: var(--text);
}
.ranking-body { padding: 20px 24px; }
.rank-display-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-bottom: 16px;
}
.rank-display-item {
    background: var(--bg3);
    border-radius: 10px;
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.rank-display-label {
    font-size: 11px;
    color: var(--text3);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .8px;
}
.rank-display-value {
    font-size: 32px;
    font-weight: 700;
    font-family: 'Space Mono', monospace;
    color: var(--accent2);
    line-height: 1;
}
.rank-display-total {
    font-size: 12px;
    color: var(--text3);
}
.rank-locked {
    padding: 32px 24px;
    text-align: center;
    color: var(--text3);
}
.rank-locked-icon {
    font-size: 32px;
    margin-bottom: 10px;
}
.rank-progress {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 14px;
    padding: 10px 14px;
    background: var(--bg3);
    border-radius: 8px;
}
@media (max-width: 600px) {
    .score-grid { grid-template-columns: 1fr 1fr; }
    .rank-display-grid { grid-template-columns: 1fr; }
}
</style>

<?php if (empty($courses)): ?>
<div class="empty-state">
    <div class="icon"><?= svg_icon('grades', 36) ?></div>
    <p>本學期尚無課程，請先在「課程管理」新增課程</p>
</div>
<?php else: ?>

<?php foreach ($courses as $c):
    $sc = $scores_by_course[$c['id']] ?? null;

    $sem_score = null;
    $can_calc  = false;
    $reg = $mid = $fin = null;
    $rp = $mp = $fp = null;

    if ($sc) {
        $rp = (float)($sc['regular_pct'] ?? 30) / 100;
        $mp = (float)($sc['midterm_pct'] ?? 30) / 100;
        $fp = (float)($sc['final_pct']   ?? 40) / 100;

        $reg = $sc['regular_score'] !== null ? ((float)$sc['regular_score'] / (float)($sc['regular_max'] ?: 100) * 100) : null;
        $mid = $sc['midterm_score'] !== null ? ((float)$sc['midterm_score'] / (float)($sc['midterm_max'] ?: 100) * 100) : null;
        $fin = $sc['final_score']   !== null ? ((float)$sc['final_score']   / (float)($sc['final_max']   ?: 100) * 100) : null;

        if ($reg !== null && $mid !== null && $fin !== null) {
            $sem_score = round($reg * $rp + $mid * $mp + $fin * $fp, 1);
        }
        if ($reg !== null && $mid !== null && $fin === null && $fp > 0) {
            $pass = (float)($sc['pass_score'] ?? 60);
            $need_final = round(($pass - ($reg * $rp + $mid * $mp)) / $fp, 1);
            $can_calc = true;
        }
    }

    $color = $sc
        ? ($sem_score !== null
            ? ($sem_score >= 80 ? 'var(--green)' : ($sem_score >= 60 ? 'var(--yellow)' : 'var(--red)'))
            : 'var(--text3)')
        : 'var(--text3)';
?>
<div class="score-card">
    <div class="score-card-header">
        <div style="width:4px;height:36px;border-radius:2px;background:<?= h($c['color']) ?>;flex-shrink:0"></div>
        <div class="score-course-name"><?= h($c['name']) ?></div>
        <?php if ($sc): ?>
        <div style="text-align:right">
            <div style="font-size:11px;color:var(--text3)">學期總成績</div>
            <div class="semester-score-big" style="color:<?= $color ?>"><?= $sem_score !== null ? $sem_score : '—' ?></div>
        </div>
        <?php endif; ?>
        <button class="btn btn-ghost btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode([
            'id'   => $c['id'],
            'name' => $c['name'],
            'sc'   => $sc,
        ]), ENT_QUOTES) ?>)">
            <?= $sc ? '編輯' : '＋ 輸入' ?>
        </button>
    </div>

    <?php if ($sc): ?>
    <div class="score-grid">
        <?php
        $items = [
            ['平時成績', $sc['regular_score'], $sc['regular_max'], $sc['regular_pct'] ?? 30],
            ['期中考',   $sc['midterm_score'], $sc['midterm_max'], $sc['midterm_pct'] ?? 30],
            ['期末考',   $sc['final_score'],   $sc['final_max'],   $sc['final_pct']   ?? 40],
        ];
        foreach ($items as [$lbl, $score, $max, $pct]):
            $p = $score !== null ? round((float)$score / (float)($max ?: 100) * 100, 1) : null;
            $c2 = $p !== null ? ($p >= 80 ? 'var(--green)' : ($p >= 60 ? 'var(--yellow)' : 'var(--red)')) : 'var(--text3)';
        ?>
        <div class="score-item">
            <div class="score-item-label"><?= $lbl ?></div>
            <div class="score-item-value" style="color:<?= $c2 ?>"><?= $p !== null ? $p : '—' ?></div>
            <div class="score-item-pct">佔 <?= (int)$pct ?>%</div>
            <?php if ($score !== null): ?>
            <div style="font-size:10px;color:var(--text3);margin-top:2px"><?= $score ?>/<?= $max ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($sem_score !== null): ?>
    <div style="display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;font-size:12px;background:<?= $sem_score >= 60 ? 'rgba(34,197,94,.1)' : 'rgba(239,68,68,.1)' ?>;color:<?= $sem_score >= 60 ? 'var(--green)' : 'var(--red)' ?>">
        <?php if ($sem_score >= 60): ?>
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6L4.5 8.5L10 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> 及格
        <?php else: ?>
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M3 3L9 9M9 3L3 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> 不及格
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($can_calc && isset($need_final)): ?>
    <div class="calc-box">
        <div class="calc-box-title"><svg width="13" height="13" viewBox="0 0 16 16" fill="none" style="vertical-align:-2px;margin-right:4px"><path d="M2 13V9H5V13H2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M6.5 13V6H9.5V13H6.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M11 13V3H14V13H11Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg> 期末考目標計算</div>
        <div class="calc-result">
            目前加權：平時 <?= round($reg * $rp, 1) ?> + 期中 <?= round($mid * $mp, 1) ?> = <strong><?= round($reg * $rp + $mid * $mp, 1) ?></strong> 分<br>
            <?php if ($need_final <= 0): ?>
                <span style="color:var(--green)"><svg width="13" height="13" viewBox="0 0 12 12" fill="none" style="vertical-align:-1px"><path d="M2 6L4.5 8.5L10 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg> 不管期末考幾分都能過！</span>
            <?php elseif ($need_final > 100): ?>
                <span style="color:var(--red)"><svg width="13" height="13" viewBox="0 0 12 12" fill="none" style="vertical-align:-1px"><path d="M3 3L9 9M9 3L3 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> 即使期末考滿分仍無法及格（差 <strong><?= round($need_final - 100, 1) ?></strong> 分）</span>
            <?php else: ?>
                期末考需達到：
                <span class="need-score-value" style="color:var(--<?= $need_final > 80 ? 'red' : ($need_final > 60 ? 'yellow' : 'green') ?>"><?= $need_final ?></span>
                <span style="color:var(--text3)"> 分以上（原始分 / <?= $sc['final_max'] ?: 100 ?>）</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($sc['note']): ?>
    <div style="margin-top:10px;font-size:12px;color:var(--text3);border-left:2px solid var(--border);padding-left:10px"><?= h($sc['note']) ?></div>
    <?php endif; ?>

    <?php else: ?>
    <div style="color:var(--text3);font-size:13px;padding:8px 0">尚未輸入成績 — 點擊「＋ 輸入」開始記錄</div>
    <?php endif; ?>
</div>
<?php endforeach; ?>


<!-- ═══════════════════════════════════════════════
     學期整體排名（班排 / 系排）
     等所有課程成績填完才解鎖
════════════════════════════════════════════════ -->
<div class="ranking-section" id="ranking">
    <div class="ranking-card">
        <div class="ranking-header">
            <div class="ranking-header-title">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M8 1L9.5 5.5H14.5L10.5 8.5L12 13L8 10.5L4 13L5.5 8.5L1.5 5.5H6.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                </svg>
                學期整體排名
                <span style="font-size:11px;font-weight:400;color:var(--text3)">班排 · 系排</span>
            </div>
            <?php if ($all_complete): ?>
            <button class="btn btn-ghost btn-sm" onclick="openModal('rankingModal')">
                <?= $sem_ranking ? '編輯排名' : '＋ 輸入排名' ?>
            </button>
            <?php endif; ?>
        </div>

        <?php if (!$all_complete): ?>
        <!-- Locked state -->
        <div class="rank-locked">
            <div class="rank-locked-icon">
                <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
                    <rect x="8" y="16" width="20" height="15" rx="3" stroke="currentColor" stroke-width="1.8"/>
                    <path d="M12 16V11C12 8.24 14.24 6 17 6H19C21.76 6 24 8.24 24 11V16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <circle cx="18" cy="23" r="2" fill="currentColor" opacity=".5"/>
                </svg>
            </div>
            <div style="font-size:14px;font-weight:600;color:var(--text2);margin-bottom:6px">排名尚未解鎖</div>
            <div style="font-size:13px;color:var(--text3)">填完所有課程的期中、期末、平時成績後即可輸入學期排名</div>
            <div class="rank-progress">
                <div style="flex:1">
                    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text3);margin-bottom:6px">
                        <span>成績填寫進度</span>
                        <span style="font-family:'Space Mono',monospace"><?= $complete_count ?> / <?= count($courses) ?> 科</span>
                    </div>
                    <div style="height:4px;background:var(--bg);border-radius:2px;overflow:hidden">
                        <div style="height:100%;border-radius:2px;background:var(--accent);width:<?= count($courses) > 0 ? round($complete_count / count($courses) * 100) : 0 ?>%;transition:width .4s"></div>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($sem_ranking && ($sem_ranking['class_rank'] || $sem_ranking['dept_rank'])): ?>
        <!-- Display ranking -->
        <div class="ranking-body">
            <div class="rank-display-grid">
                <div class="rank-display-item">
                    <div class="rank-display-label">班級排名</div>
                    <div class="rank-display-value">
                        <?= $sem_ranking['class_rank'] ?? '—' ?>
                    </div>
                    <div class="rank-display-total">
                        共 <?= $sem_ranking['class_total'] ? $sem_ranking['class_total'] . ' 人' : '— 人' ?>
                        <?php if ($sem_ranking['class_rank'] && $sem_ranking['class_total']): ?>
                        · 前 <?= round($sem_ranking['class_rank'] / $sem_ranking['class_total'] * 100) ?>%
                        <?php endif; ?>
                    </div>
                </div>
                <div class="rank-display-item">
                    <div class="rank-display-label">系所排名</div>
                    <div class="rank-display-value">
                        <?= $sem_ranking['dept_rank'] ?? '—' ?>
                    </div>
                    <div class="rank-display-total">
                        共 <?= $sem_ranking['dept_total'] ? $sem_ranking['dept_total'] . ' 人' : '— 人' ?>
                        <?php if ($sem_ranking['dept_rank'] && $sem_ranking['dept_total']): ?>
                        · 前 <?= round($sem_ranking['dept_rank'] / $sem_ranking['dept_total'] * 100) ?>%
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if ($sem_ranking['note']): ?>
            <div style="font-size:12px;color:var(--text3);border-left:2px solid var(--border);padding-left:10px"><?= h($sem_ranking['note']) ?></div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- Unlocked but no data yet -->
        <div style="padding:28px 24px;text-align:center">
            <div style="font-size:13px;color:var(--text2);margin-bottom:12px">所有課程成績已填完，可以輸入本學期排名了！</div>
            <button class="btn btn-primary" onclick="openModal('rankingModal')">＋ 輸入排名</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; // end if courses not empty ?>


<!-- ═══════ Modals ═══════ -->

<!-- Course score edit modal -->
<div class="modal-overlay" id="editScoreModal">
    <div class="modal" style="max-width:580px">
        <div class="modal-title" id="editScoreTitle">輸入考試成績</div>
        <form method="post" id="editScoreForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="course_id" id="sc_course_id">

            <!-- Weight row -->
            <div style="background:var(--bg3);border-radius:10px;padding:14px 16px;margin-bottom:16px">
                <div style="font-size:11px;color:var(--text2);font-weight:600;margin-bottom:10px;text-transform:uppercase;letter-spacing:.8px">成績比例（合計應為 100%）</div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
                    <?php foreach(['regular_pct'=>'平時','midterm_pct'=>'期中','final_pct'=>'期末'] as $k=>$l): ?>
                    <div>
                        <label style="font-size:11px;color:var(--text3);margin-bottom:4px;display:block"><?=$l?> %</label>
                        <input class="form-input" name="<?=$k?>" id="sc_<?=$k?>" type="number" min="0" max="100" step="1"
                            style="text-align:center;font-family:'Space Mono',monospace;font-size:16px;font-weight:700"
                            oninput="updatePctTotal()">
                    </div>
                    <?php endforeach; ?>
                </div>
                <div id="pctWarning" style="font-size:11px;color:var(--yellow);margin-top:8px;display:none"><svg width="13" height="13" viewBox="0 0 12 12" fill="none" style="vertical-align:-1px"><path d="M6 1.5L11 10H1L6 1.5Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M6 5V7.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><circle cx="6" cy="9" r="0.6" fill="currentColor"/></svg> 合計不等於 100%，請調整</div>
            </div>

            <!-- Score inputs -->
            <?php foreach(['regular'=>['平時成績','regular_score','regular_max'],'midterm'=>['期中考','midterm_score','midterm_max'],'final'=>['期末考','final_score','final_max']] as $type=>[$label,$sc_key,$max_key]): ?>
            <div style="display:grid;grid-template-columns:1fr 80px;gap:10px;margin-bottom:14px;align-items:end">
                <div>
                    <label class="form-label"><?= $label ?> <span style="color:var(--text3);font-weight:400">（留空表示尚未考）</span></label>
                    <input class="form-input" name="<?=$sc_key?>" id="sc_<?=$sc_key?>" type="number" step="0.1" min="0" placeholder="未考" oninput="liveCalc()">
                </div>
                <div>
                    <label class="form-label" style="color:var(--text3)">滿分</label>
                    <input class="form-input" name="<?=$max_key?>" id="sc_<?=$max_key?>" type="number" step="1" min="1" value="100" style="text-align:center" oninput="liveCalc()">
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Live calc preview -->
            <div id="liveCalcBox" style="display:none;background:linear-gradient(135deg,rgba(91,127,255,.1),rgba(167,139,250,.08));border:1px solid rgba(91,127,255,.25);border-radius:10px;padding:14px 16px;margin-bottom:14px">
                <div style="font-size:10px;font-weight:700;color:var(--accent2);text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px"><svg width="11" height="11" viewBox="0 0 16 16" fill="none" style="vertical-align:-1px;margin-right:3px"><path d="M2 13V9H5V13H2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M6.5 13V6H9.5V13H6.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M11 13V3H14V13H11Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg> 期末考目標計算</div>
                <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:8px">
                    <div id="liveNeedVal" style="font-size:36px;font-weight:700;font-family:'Space Mono',monospace;line-height:1"></div>
                    <div id="liveNeedUnit" style="font-size:13px;color:var(--text3)"></div>
                </div>
                <div id="liveNeedDesc" style="font-size:12px;color:var(--text2);line-height:1.6;margin-bottom:8px"></div>
                <div style="height:4px;background:rgba(255,255,255,.08);border-radius:2px;overflow:hidden">
                    <div id="liveNeedBar" style="height:100%;border-radius:2px;transition:width .3s,background .3s"></div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:80px 1fr;gap:10px;margin-bottom:16px;align-items:end">
                <div>
                    <label class="form-label">及格分數</label>
                    <input class="form-input" name="pass_score" id="sc_pass_score" type="number" step="1" min="0" max="100" value="60" style="text-align:center" oninput="liveCalc()">
                </div>
                <div>
                    <label class="form-label">備注</label>
                    <input class="form-input" name="note" id="sc_note" type="text" placeholder="老師說的話、特殊說明…">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal('editScoreModal')">取消</button>
                <button type="submit" class="btn btn-primary">儲存成績</button>
            </div>
        </form>
    </div>
</div>

<!-- Select course modal -->
<div class="modal-overlay" id="addScoreModal">
    <div class="modal" style="max-width:360px">
        <div class="modal-title">選擇課程</div>
        <div style="display:flex;flex-direction:column;gap:8px">
            <?php foreach($courses as $c): ?>
            <button class="btn btn-ghost" style="justify-content:flex-start;gap:10px"
                onclick="openEditModal({id:<?=$c['id']?>,name:<?=json_encode($c['name'])?>,sc:<?=json_encode($scores_by_course[$c['id']] ?? null)?>});closeModal('addScoreModal')">
                <span style="width:10px;height:10px;border-radius:50%;background:<?=h($c['color'])?>;flex-shrink:0;display:inline-block"></span>
                <?= h($c['name']) ?>
                <?php $sc_tmp = $scores_by_course[$c['id']] ?? null; ?>
                <?php if ($sc_tmp && $sc_tmp['regular_score'] !== null && $sc_tmp['midterm_score'] !== null && $sc_tmp['final_score'] !== null): ?>
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" style="margin-left:auto;color:var(--green)"><path d="M2 6L5 9L10 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" onclick="closeModal('addScoreModal')">取消</button>
        </div>
    </div>
</div>

<!-- Ranking modal -->
<div class="modal-overlay" id="rankingModal">
    <div class="modal" style="max-width:460px">
        <div class="modal-title">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="display:inline;vertical-align:-2px;margin-right:6px">
                <path d="M8 1L9.5 5.5H14.5L10.5 8.5L12 13L8 10.5L4 13L5.5 8.5L1.5 5.5H6.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
            </svg>
            學期整體排名
        </div>
        <form method="post">
            <input type="hidden" name="action" value="save_ranking">
            <p style="font-size:13px;color:var(--text3);margin-bottom:18px">從學校系統（ePortfolio）查到的學期成績排名，填名次和總人數。</p>

            <div style="background:var(--bg3);border-radius:10px;padding:16px;margin-bottom:14px">
                <div style="font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.8px;margin-bottom:12px">班級排名</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div>
                        <label class="form-label">名次</label>
                        <input class="form-input" name="class_rank" id="rk_class_rank" type="number" min="1" placeholder="例：20"
                            value="<?= $sem_ranking['class_rank'] ?? '' ?>">
                    </div>
                    <div>
                        <label class="form-label">班級總人數</label>
                        <input class="form-input" name="class_total" id="rk_class_total" type="number" min="1" placeholder="例：62"
                            value="<?= $sem_ranking['class_total'] ?? '' ?>">
                    </div>
                </div>
            </div>

            <div style="background:var(--bg3);border-radius:10px;padding:16px;margin-bottom:14px">
                <div style="font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.8px;margin-bottom:12px">系所排名</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div>
                        <label class="form-label">名次</label>
                        <input class="form-input" name="dept_rank" id="rk_dept_rank" type="number" min="1" placeholder="例：27"
                            value="<?= $sem_ranking['dept_rank'] ?? '' ?>">
                    </div>
                    <div>
                        <label class="form-label">系所總人數</label>
                        <input class="form-input" name="dept_total" id="rk_dept_total" type="number" min="1" placeholder="例：116"
                            value="<?= $sem_ranking['dept_total'] ?? '' ?>">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">備注</label>
                <input class="form-input" name="rank_note" type="text" placeholder="例：113 學年上學期"
                    value="<?= h($sem_ranking['note'] ?? '') ?>">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal('rankingModal')">取消</button>
                <button type="submit" class="btn btn-primary">儲存排名</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(data) {
    var sc = data.sc || {};
    document.getElementById('editScoreTitle').textContent = data.name + ' — 考試成績';
    document.getElementById('sc_course_id').value = data.id;

    document.getElementById('sc_regular_pct').value  = sc.regular_pct  ?? 30;
    document.getElementById('sc_midterm_pct').value  = sc.midterm_pct  ?? 30;
    document.getElementById('sc_final_pct').value    = sc.final_pct    ?? 40;

    document.getElementById('sc_regular_score').value = sc.regular_score ?? '';
    document.getElementById('sc_regular_max').value   = sc.regular_max   ?? 100;
    document.getElementById('sc_midterm_score').value = sc.midterm_score ?? '';
    document.getElementById('sc_midterm_max').value   = sc.midterm_max   ?? 100;
    document.getElementById('sc_final_score').value   = sc.final_score   ?? '';
    document.getElementById('sc_final_max').value     = sc.final_max     ?? 100;

    document.getElementById('sc_pass_score').value = sc.pass_score ?? 60;
    document.getElementById('sc_note').value        = sc.note       ?? '';

    updatePctTotal();
    liveCalc();
    openModal('editScoreModal');
}

function updatePctTotal() {
    var total = (parseFloat(document.getElementById('sc_regular_pct').value)||0)
              + (parseFloat(document.getElementById('sc_midterm_pct').value)||0)
              + (parseFloat(document.getElementById('sc_final_pct').value)||0);
    document.getElementById('pctWarning').style.display = Math.round(total) !== 100 ? 'block' : 'none';
    liveCalc();
}

function liveCalc() {
    var reg   = parseFloat(document.getElementById('sc_regular_score').value);
    var regM  = parseFloat(document.getElementById('sc_regular_max').value)  || 100;
    var mid   = parseFloat(document.getElementById('sc_midterm_score').value);
    var midM  = parseFloat(document.getElementById('sc_midterm_max').value)  || 100;
    var fin   = parseFloat(document.getElementById('sc_final_score').value);
    var rp    = (parseFloat(document.getElementById('sc_regular_pct').value) || 30) / 100;
    var mp    = (parseFloat(document.getElementById('sc_midterm_pct').value) || 30) / 100;
    var fp    = (parseFloat(document.getElementById('sc_final_pct').value)   || 40) / 100;
    var pass  = parseFloat(document.getElementById('sc_pass_score').value)   || 60;

    var box   = document.getElementById('liveCalcBox');
    var valEl = document.getElementById('liveNeedVal');
    var unitEl= document.getElementById('liveNeedUnit');
    var desc  = document.getElementById('liveNeedDesc');
    var bar   = document.getElementById('liveNeedBar');

    var hasReg = !isNaN(reg);
    var hasMid = !isNaN(mid);
    var hasFin = !isNaN(fin);

    // Show box only when we have something useful to say
    if (!hasReg && !hasMid) { box.style.display = 'none'; return; }

    box.style.display = 'block';

    if (hasFin) {
        // All three filled: show final weighted score
        var regPct = hasReg ? reg / regM * 100 : 0;
        var midPct = hasMid ? mid / midM * 100 : 0;
        var finPct = fin / (parseFloat(document.getElementById('sc_final_max').value) || 100) * 100;
        var total  = Math.round((regPct * rp + midPct * mp + finPct * fp) * 10) / 10;
        var color  = total >= 80 ? '#22c55e' : total >= 60 ? '#f59e0b' : '#ef4444';
        valEl.textContent  = total;
        valEl.style.color  = color;
        unitEl.textContent = '分（學期總成績）';
        desc.textContent   = total >= 60 ? '通過' : '未過';
        desc.style.color   = color;
        bar.style.width    = Math.min(total, 100) + '%';
        bar.style.background = color;
        return;
    }

    if (!hasReg || !hasMid || fp <= 0) { box.style.display = 'none'; return; }

    // Have reg + mid, no final → calculate needed final
    var regPct = reg / regM * 100;
    var midPct = mid / midM * 100;
    var currentW = regPct * rp + midPct * mp;
    var finMax   = parseFloat(document.getElementById('sc_final_max').value) || 100;
    var needPct  = (pass - currentW) / fp;
    var needRaw  = Math.round(needPct / 100 * finMax * 10) / 10;

    if (needPct <= 0) {
        valEl.textContent  = '✓';
        valEl.style.color  = '#22c55e';
        unitEl.textContent = '不需要擔心期末了';
        desc.innerHTML     = '目前加權已 <strong style="color:#e8ecf4">' + Math.round(currentW * 10)/10 + '</strong> 分，不管期末考幾分都能過！';
        desc.style.color   = '#8892aa';
        bar.style.width    = '100%';
        bar.style.background = '#22c55e';
    } else if (needPct > 100) {
        valEl.textContent  = '✗';
        valEl.style.color  = '#ef4444';
        unitEl.textContent = '即使期末滿分也過不了';
        desc.innerHTML     = '目前加權 <strong style="color:#e8ecf4">' + Math.round(currentW * 10)/10 + '</strong> 分，差 <strong style="color:#ef4444">' + Math.round((needPct - 100) * 10)/10 + '</strong> 分，建議找老師討論';
        desc.style.color   = '#8892aa';
        bar.style.width    = '100%';
        bar.style.background = '#ef4444';
    } else {
        var color = needPct > 80 ? '#ef4444' : needPct > 60 ? '#f59e0b' : '#22c55e';
        valEl.textContent  = needRaw;
        valEl.style.color  = color;
        unitEl.textContent = '分（/ ' + finMax + '）';
        desc.innerHTML     = '目前加權 <strong style="color:#e8ecf4">' + Math.round(currentW * 10)/10 + '</strong> 分，期末考需達到這個分數才能過';
        desc.style.color   = '#8892aa';
        bar.style.width    = needPct + '%';
        bar.style.background = color;
    }
}

// Auto-scroll to #ranking if redirected there
if (location.hash === '#ranking') {
    setTimeout(function() {
        var el = document.getElementById('ranking');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 200);
}

// ── 佔卜彩蛋 ───────────────────────────────────────────────────────────
// 入口：在 footer 的版本號上連按 3 下
(function() {
    var _clicks = 0, _timer;
    var _trigger = document.getElementById('easterEggTrigger');
    if (!_trigger) return;
    _trigger.addEventListener('click', function(e) {
        _clicks++;
        clearTimeout(_timer);
        if (_clicks >= 3) {
            _clicks = 0;
            openFortuneModal();
        } else {
            _timer = setTimeout(function() { _clicks = 0; }, 600);
        }
    });

    var fortunes = [
        { icon: '★', verdict: '大吉', color: '#f59e0b',
          lines: ['天時地利人和，成績自然亮眼', '考場如戰場，你已準備好了', '期末考會比你預想的簡單'] },
        { icon: '●', verdict: '吉', color: '#22c55e',
          lines: ['穩紮穩打，及格沒問題', '老師今天心情應該不錯', '臨時抱佛腳也有效，快去讀'] },
        { icon: '◑', verdict: '小吉', color: '#5b7fff',
          lines: ['有點危險但過得了', '記得交作業，平時分救你', '睡前再看一次重點'] },
        { icon: '○', verdict: '末吉', color: '#8892aa',
          lines: ['盡人事聽天命', '老師改卷心情影響你的命運', '考卷寫滿可能有同情分'] },
        { icon: '↯', verdict: '凶', color: '#ef4444',
          lines: ['你現在應該在讀書不是在這裡', '可能需要找老師談談', '別放棄，補考還有機會'] },
        { icon: '✘', verdict: '大凶', color: '#a78bfa',
          lines: ['連這個系統都知道你沒讀書', '但大凶過後必有大吉（也許）', '至少你還在按這個彩蛋，代表你沒放棄'] },
    ];

    // Weighted random: 大吉少、大凶更少
    var weights = [5, 30, 35, 20, 8, 2];
    function weightedRandom() {
        var total = weights.reduce(function(a, b) { return a + b; }, 0);
        var r = Math.random() * total, acc = 0;
        for (var i = 0; i < weights.length; i++) {
            acc += weights[i];
            if (r < acc) return fortunes[i];
        }
        return fortunes[2];
    }

    window.openFortuneModal = function() {
        document.getElementById('fortuneModal').style.display = 'flex';
        document.getElementById('fortuneResult').style.display = 'none';
        document.getElementById('fortuneBtn').style.display = 'inline-flex';
        document.getElementById('fortuneOrb').style.animation = 'none';
        document.getElementById('fortuneOrb').style.transform = '';
    };

    window.drawFortune = function() {
        var f = weightedRandom();
        var btn = document.getElementById('fortuneBtn');
        var orb = document.getElementById('fortuneOrb');
        var result = document.getElementById('fortuneResult');

        btn.style.display = 'none';
        orb.style.animation = 'orbPulse .4s ease';
        setTimeout(function() {
            orb.style.animation = '';
            result.style.display = 'block';
            document.getElementById('fortuneIcon').textContent   = f.icon;
            document.getElementById('fortuneVerdict').textContent = f.verdict;
            document.getElementById('fortuneVerdict').style.color = f.color;
            var line = f.lines[Math.floor(Math.random() * f.lines.length)];
            document.getElementById('fortuneLine').textContent = line;
            document.getElementById('fortuneVerdictRing').style.borderColor = f.color + '55';
        }, 400);
    };

    document.getElementById('fortuneModal').addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
})();
</script>

<!-- Fortune Modal（彩蛋） -->
<div id="fortuneModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;align-items:center;justify-content:center">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:20px;padding:36px 32px;width:min(360px,92vw);text-align:center;box-shadow:0 32px 80px rgba(0,0,0,.7);position:relative">
        <!-- close -->
        <button onclick="document.getElementById('fortuneModal').style.display='none'"
            style="position:absolute;top:14px;right:14px;background:transparent;border:none;color:var(--text3);cursor:pointer;font-size:18px;padding:4px;transition:.15s;line-height:1"
            onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text3)'"><svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 2L10 10M10 2L2 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></button>

        <div style="font-size:11px;letter-spacing:2px;color:var(--text3);text-transform:uppercase;margin-bottom:16px"><svg width="13" height="13" viewBox="0 0 16 16" fill="none" style="vertical-align:-2px;margin-right:4px"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M8 5C8 5 6 6.5 6 8C6 9.1 6.9 10 8 10C9.1 10 10 9.1 10 8C10 6.5 8 5 8 5Z" stroke="currentColor" stroke-width="1.3"/><circle cx="8" cy="8" r="1" fill="currentColor"/></svg> 學期占卜</div>

        <!-- Orb -->
        <div id="fortuneOrb" style="width:100px;height:100px;margin:0 auto 20px;border-radius:50%;background:radial-gradient(circle at 35% 35%, #7c9fff, #1a1e28 70%);box-shadow:0 0 30px rgba(91,127,255,.4),inset 0 0 20px rgba(0,0,0,.5);cursor:pointer;transition:.15s"
            onclick="drawFortune()" id="fortuneVerdictRing" style="border:2px solid transparent">
        </div>

        <button id="fortuneBtn" onclick="drawFortune()"
            class="btn btn-primary" style="margin-bottom:0">
            <svg width="13" height="13" viewBox="0 0 16 16" fill="none" style="vertical-align:-2px;margin-right:4px"><path d="M8 2L9.2 5.8H13.2L10 8.2L11.2 12L8 9.6L4.8 12L6 8.2L2.8 5.8H6.8L8 2Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg> 點擊占卜
        </button>

        <div id="fortuneResult" style="display:none">
            <div style="display:flex;flex-direction:column;align-items:center;gap:8px">
                <div id="fortuneIcon" style="font-size:40px;margin-bottom:4px"></div>
                <div id="fortuneVerdict" style="font-size:28px;font-weight:700;font-family:'Space Mono',monospace"></div>
                <div id="fortuneLine" style="font-size:13px;color:var(--text2);line-height:1.6;margin-top:4px;max-width:260px"></div>
                <button onclick="document.getElementById('fortuneResult').style.display='none';document.getElementById('fortuneBtn').style.display='inline-flex';"
                    class="btn btn-ghost btn-sm" style="margin-top:14px">再占一次</button>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes orbPulse {
    0%   { transform: scale(1); box-shadow: 0 0 30px rgba(91,127,255,.4); }
    50%  { transform: scale(1.2); box-shadow: 0 0 60px rgba(91,127,255,.8); }
    100% { transform: scale(1); }
}
</style>

<?php require_once 'includes/footer.php'; ?>
