<?php
require_once 'includes/functions.php';
require_login();
require_once 'includes/db.php';

$page_title = '統計分析';
$current_page = 'statistics.php';

// Attendance per course
$att_stats = $pdo->query("
    SELECT c.name, c.color, c.credits,
        COUNT(a.id) as recorded_total,
        SUM(a.status IN ('present','late')) as present,
        SUM(a.status='absent') as absent,
        SUM(a.status='excused') as excused
    FROM courses c LEFT JOIN attendance a ON a.course_id=c.id
    GROUP BY c.id
")->fetchAll();
$att_stats = array_map(function($s) {
    $s['absent'] = (int)($s['absent'] ?? 0);
    $s['semester_total'] = semester_class_count($s);
    $s['rate'] = attendance_rate_from_absences($s, $s['absent']);
    return $s;
}, $att_stats);
usort($att_stats, fn($a, $b) => $b['rate'] <=> $a['rate']);

// Grade per course
$grade_stats = $pdo->query("
    SELECT c.name, c.color,
        COUNT(g.id) as cnt,
        ROUND(AVG(g.score/g.max_score*100),1) as avg,
        ROUND(MAX(g.score/g.max_score*100),1) as max,
        ROUND(MIN(g.score/g.max_score*100),1) as min
    FROM courses c LEFT JOIN grades g ON g.course_id=c.id
    GROUP BY c.id HAVING cnt>0 ORDER BY avg DESC
")->fetchAll();

// Grade trend (last 20)
$grade_trend = $pdo->query("
    SELECT g.graded_at, ROUND(g.score/g.max_score*100,1) as pct, c.name, c.color
    FROM grades g JOIN courses c ON g.course_id=c.id
    ORDER BY g.graded_at DESC LIMIT 20
")->fetchAll();
$grade_trend = array_reverse($grade_trend);

// Pomodoro by day (last 14 days)
$pomo_daily = $pdo->query("
    SELECT DATE(started_at) as day, COUNT(*) as cnt, SUM(duration_minutes) as mins
    FROM pomodoro_sessions WHERE completed=1 AND started_at>=DATE('now','-14 days')
    GROUP BY day ORDER BY day
")->fetchAll();

// Assignments completion rate
$assign_stats = $pdo->query("
    SELECT c.name, c.color,
        SUM(a.status='done') as done,
        SUM(a.status='pending') as pending,
        COUNT(*) as total
    FROM courses c LEFT JOIN assignments a ON a.course_id=c.id
    GROUP BY c.id HAVING total>0
")->fetchAll();

require_once 'includes/header.php';
?>
<style>
.chart-bar-wrap { display:flex; flex-direction:column; gap:12px; }
.chart-bar-row { display:flex; align-items:center; gap:10px; }
.chart-bar-label { width:100px; font-size:12px; color:var(--text2); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex-shrink:0; }
.chart-bar-track { flex:1; height:20px; background:var(--bg3); border-radius:4px; overflow:hidden; position:relative; }
.chart-bar-fill { height:100%; border-radius:4px; display:flex; align-items:center; padding-left:8px; font-size:11px; font-family:'Space Mono',monospace; color:white; font-weight:700; transition:width .5s; }
.chart-bar-val { width:50px; text-align:right; font-family:'Space Mono',monospace; font-size:12px; color:var(--text3); flex-shrink:0; }

/* Mini line chart */
.sparkline { overflow:visible; }
</style>

<div class="grid grid-2" style="gap:20px">

    <!-- Attendance chart -->
    <div class="card">
        <div class="card-title"><?=svg_icon('attendance')?> 各課出席率</div>
        <div class="chart-bar-wrap">
        <?php foreach($att_stats as $s): ?>
        <div class="chart-bar-row">
            <div class="chart-bar-label"><?= h($s['name']) ?></div>
            <div class="chart-bar-track">
                <div class="chart-bar-fill" style="width:<?=$s['rate']?>%;background:<?=$s['rate']>=75?'var(--green)':($s['rate']>=60?'var(--yellow)':'var(--red)')?>">
                    <?php if($s['rate']>20): ?><?=$s['rate']?>%<?php endif; ?>
                </div>
            </div>
            <div class="chart-bar-val"><?=$s['rate']?>%</div>
        </div>
        <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:12px;margin-top:16px;font-size:12px;color:var(--text3)">
            <span style="color:var(--green)">■ ≥75% 達標</span>
            <span style="color:var(--yellow)">■ 60-75% 留意</span>
            <span style="color:var(--red)">■ &lt;60% 危險</span>
        </div>
    </div>

    <!-- Grade chart -->
    <div class="card">
        <div class="card-title"><?=svg_icon('grades')?> 各課平均成績</div>
        <div class="chart-bar-wrap">
        <?php foreach($grade_stats as $s): ?>
        <div class="chart-bar-row">
            <div class="chart-bar-label"><?= h($s['name']) ?></div>
            <div class="chart-bar-track">
                <div class="chart-bar-fill" style="width:<?=$s['avg']?>%;background:<?=$s['avg']>=80?'var(--green)':($s['avg']>=60?'var(--accent)':'var(--red)')?>">
                    <?php if($s['avg']>20): ?><?=$s['avg']?><?php endif; ?>
                </div>
            </div>
            <div class="chart-bar-val"><?=$s['avg']?></div>
        </div>
        <?php endforeach; ?>
        </div>
        <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border);display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;text-align:center">
            <?php
            $all_avgs = array_column($grade_stats,'avg');
            $overall = $all_avgs ? round(array_sum($all_avgs)/count($all_avgs),1) : 0;
            $best = $grade_stats ? $grade_stats[0] : null;
            $grade_stats_tmp = $grade_stats;
            $worst = $grade_stats_tmp ? end($grade_stats_tmp) : null;
            ?>
            <div><div style="font-size:18px;font-weight:700;color:var(--accent);font-family:'Space Mono',monospace"><?=$overall?></div><div style="font-size:11px;color:var(--text3)">總平均</div></div>
            <div><div style="font-size:18px;font-weight:700;color:var(--green);font-family:'Space Mono',monospace"><?=$best?$best['avg']:'-'?></div><div style="font-size:11px;color:var(--text3)">最高科均</div></div>
            <div><div style="font-size:18px;font-weight:700;color:var(--red);font-family:'Space Mono',monospace"><?=$worst?$worst['avg']:'-'?></div><div style="font-size:11px;color:var(--text3)">最低科均</div></div>
        </div>
    </div>

    <!-- Grade trend -->
    <div class="card">
        <div class="card-title"><?=svg_icon('trend')?> 成績趨勢</div>
        <?php if(empty($grade_trend)): ?>
        <div class="empty-state"><p>尚無成績資料</p></div>
        <?php else: ?>
        <svg class="sparkline" width="100%" height="120" viewBox="0 0 400 120" preserveAspectRatio="none">
            <defs>
                <linearGradient id="grad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#5b7fff" stop-opacity=".3"/>
                    <stop offset="100%" stop-color="#5b7fff" stop-opacity="0"/>
                </linearGradient>
            </defs>
            <?php
            $n = count($grade_trend);
            $pts = [];
            foreach($grade_trend as $i=>$g) {
                $x = $n>1 ? $i/($n-1)*380+10 : 200;
                $y = 110 - ($g['pct']/100)*100;
                $pts[] = "$x,$y";
            }
            $path = implode(' L ',$pts);
            // simpler area
            $first_x = explode(',',$pts[0])[0];
            $pts_last = end($pts);
            $last_x = explode(',', $pts_last)[0];
            echo "<path d='M $path' fill='none' stroke='#5b7fff' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'/>";
            echo "<path d='M ".$pts[0]." L ".implode(" L ",array_slice($pts,1))." L {$last_x},110 L {$first_x},110 Z' fill='url(#grad)'/>";
            foreach($pts as $i=>$pt) {
                list($px,$py) = explode(',',$pt);
                echo "<circle cx='$px' cy='$py' r='3' fill='#5b7fff'/>";
            }
            // Y-axis labels
            foreach([0,25,50,75,100] as $v) {
                $y = 110 - ($v/100)*100;
                echo "<text x='0' y='$y' font-size='9' fill='#555e74' dominant-baseline='middle'>$v</text>";
            }
            ?>
        </svg>
        <div style="font-size:11px;color:var(--text3);text-align:center;margin-top:6px">最近 <?=count($grade_trend)?> 筆成績趨勢</div>
        <?php endif; ?>
    </div>

    <!-- Pomodoro stats -->
    <div class="card">
        <div class="card-title"><?=svg_icon('pomodoro')?> 近14天番茄鐘</div>
        <?php if(empty($pomo_daily)): ?>
        <div class="empty-state"><p>尚無番茄鐘紀錄</p></div>
        <?php else:
            $max_mins = max(array_column($pomo_daily,'mins')) ?: 1;
        ?>
        <div class="chart-bar-wrap">
        <?php foreach($pomo_daily as $p): ?>
        <div class="chart-bar-row">
            <div class="chart-bar-label" style="font-family:'Space Mono',monospace;font-size:11px"><?= substr($p['day'],5) ?></div>
            <div class="chart-bar-track">
                <div class="chart-bar-fill" style="width:<?= round($p['mins']/$max_mins*100) ?>%;background:var(--red)">
                    <?php if($p['mins']>30): ?><?=$p['mins']?>min<?php endif; ?>
                </div>
            </div>
            <div class="chart-bar-val"><?=$p['cnt']?><?=svg_icon('pomodoro')?></div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Assignment completion -->
    <div class="card" style="grid-column:1/-1">
        <div class="card-title"><?=svg_icon('assignments')?> 作業完成率</div>
        <div class="chart-bar-wrap">
        <?php foreach($assign_stats as $s):
            if(!$s['total']) continue;
            $rate = round($s['done']/$s['total']*100);
        ?>
        <div class="chart-bar-row">
            <div class="chart-bar-label"><?= course_color_dot($s['color']) ?><?= h($s['name']) ?></div>
            <div class="chart-bar-track">
                <div class="chart-bar-fill" style="width:<?=$rate?>%;background:<?=$rate>=80?'var(--green)':($rate>=50?'var(--accent)':'var(--yellow)')?>">
                    <?php if($rate>15): ?><?=$s['done']?>/<?=$s['total']?><?php endif; ?>
                </div>
            </div>
            <div class="chart-bar-val"><?=$rate?>%</div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
