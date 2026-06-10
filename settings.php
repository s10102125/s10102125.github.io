<?php
require_once 'includes/functions.php';
require_login();
require_once 'includes/db.php';

$page_title = '個人設定';
$current_page = 'settings.php';

function get_setting(PDO $pdo, string $key, $default = null) {
    $stmt = $pdo->prepare("SELECT value FROM app_settings WHERE key=?");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : $value;
}
function set_setting(PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare("INSERT INTO app_settings (key, value) VALUES (?, ?)
        ON CONFLICT(key) DO UPDATE SET value=excluded.value");
    $stmt->execute([$key, $value]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';

    if ($section === 'profile') {
        $display_name = trim($_POST['display_name'] ?? '');
        $new_username = trim($_POST['new_username'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        $user_id = $_SESSION['user_id'];

        if (!$display_name) {
            flash('顯示名稱不能為空', 'error');
            redirect('settings.php#profile');
        }

        // Check username availability
        if ($new_username && $new_username !== $_SESSION['username']) {
            if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $new_username) || strlen($new_username) < 3) {
                flash('帳號格式不正確（至少3字元，英數/底線）', 'error');
                redirect('settings.php#profile');
            }
            $exists = $pdo->prepare("SELECT id FROM users WHERE username=? AND id!=?");
            $exists->execute([$new_username, $user_id]);
            if ($exists->fetchColumn()) {
                flash('此帳號已被使用', 'error');
                redirect('settings.php#profile');
            }
        }

        // Password change
        if ($new_password !== '') {
            if (strlen($new_password) < 4) {
                flash('密碼至少需要 4 個字元', 'error');
                redirect('settings.php#profile');
            }
            if ($new_password !== $confirm_password) {
                flash('兩次密碼不一致', 'error');
                redirect('settings.php#profile');
            }
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $user_id]);
        }

        $final_username = ($new_username && strlen($new_username) >= 3) ? $new_username : $_SESSION['username'];
        $pdo->prepare("UPDATE users SET display_name=?, username=? WHERE id=?")->execute([$display_name, $final_username, $user_id]);
        $_SESSION['display_name'] = $display_name;
        $_SESSION['username'] = $final_username;

        flash('帳號資料已更新！');
        redirect('settings.php#profile');
    } elseif ($section === 'countdowns') {
        $selected = [];
        foreach (['countdown_1', 'countdown_2', 'countdown_3'] as $field) {
            $value = trim($_POST[$field] ?? '');
            if ($value !== '' && !in_array($value, $selected, true)) $selected[] = $value;
        }
        set_setting($pdo, 'dashboard_countdowns', json_encode(array_slice($selected, 0, 3)));
        flash('倒數事件已更新！');
        redirect('settings.php#countdowns');
    } elseif ($section === 'polaroid') {
        $caption = trim($_POST['polaroid_caption'] ?? '');
        if (mb_strlen($caption) > 40) $caption = mb_substr($caption, 0, 40);
        set_setting($pdo, 'polaroid_caption', $caption ?: '你已經很厲害了，繼續加油');

        // Handle image upload
        if (!empty($_FILES['polaroid_image']['tmp_name'])) {
            $file = $_FILES['polaroid_image'];
            $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
            if (in_array($file['type'], $allowed) && $file['size'] < 3 * 1024 * 1024) {
                $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'][$file['type']];
                $dir = __DIR__ . '/data/uploads/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $filename = 'polaroid.' . $ext;
                move_uploaded_file($file['tmp_name'], $dir . $filename);
                set_setting($pdo, 'polaroid_image', 'data/uploads/' . $filename . '?v=' . time());
            } else {
                flash('圖片格式不支援或超過 3MB', 'error');
                redirect('settings.php#polaroid');
            }
        }
        flash('激勵小卡已更新！');
        redirect('settings.php#polaroid');
    } else {
        flash('設定已儲存！');
        redirect('settings.php');
    }
}

// Load current user data from DB
$current_user = $pdo->prepare("SELECT username, display_name, student_id FROM users WHERE id=?");
$current_user->execute([$_SESSION['user_id']]);
$current_user = $current_user->fetch();

// Countdown candidates
$countdown_candidates = $pdo->query("
    SELECT 'assignment:' || a.id as event_key, a.title, a.due_date
    FROM assignments a WHERE a.status = 'pending' AND a.due_date >= date('now')
    UNION ALL
    SELECT 'todo:' || t.id as event_key, t.title, t.due_date
    FROM todos t WHERE t.status = 'pending' AND t.due_date >= date('now')
    ORDER BY due_date LIMIT 30
")->fetchAll();

$saved_countdowns = json_decode(get_setting($pdo, 'dashboard_countdowns', '[]'), true);
if (!is_array($saved_countdowns)) $saved_countdowns = [];

$polaroid_caption = get_setting($pdo, 'polaroid_caption', '你已經很厲害了，繼續加油');
$polaroid_image   = get_setting($pdo, 'polaroid_image', '');

require_once 'includes/header.php';
?>
<style>
.settings-section { scroll-margin-top: 80px; }
.settings-label-row {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 0; border-bottom: 1px solid var(--border);
}
.settings-label-row:last-child { border-bottom: none; }
.settings-label-info { flex: 1; }
.settings-label-title { font-weight: 600; color: var(--text); font-size: 13.5px; }
.settings-label-desc  { font-size: 12px; color: var(--text3); margin-top: 2px; }
.toggle-wrap { position:relative;display:inline-block;width:40px;height:22px;cursor:pointer;flex-shrink:0; }
.toggle-wrap input { opacity:0;width:0;height:0; }
.toggle-track { position:absolute;inset:0;background:var(--bg3);border:1px solid var(--border);border-radius:11px;transition:.2s; }
.toggle-thumb { position:absolute;left:2px;top:2px;width:16px;height:16px;background:var(--text3);border-radius:50%;transition:.2s; }

/* Polaroid preview in settings */
.polaroid-preview-wrap {
    display: flex; gap: 28px; align-items: flex-start;
    padding: 16px 0 8px;
}
.polaroid-settings-frame {
    background: #f8f6f0;
    padding: 8px 8px 0 8px;
    border-radius: 2px;
    box-shadow: 0 2px 10px rgba(0,0,0,.25), 0 0 0 0.5px rgba(0,0,0,.1);
    transform: rotate(-1.8deg);
    width: 140px;
    flex-shrink: 0;
    transition: transform .2s;
}
.polaroid-settings-frame:hover { transform: rotate(0deg); }
.polaroid-settings-img {
    width: 100%; aspect-ratio: 1/1;
    background: var(--bg3);
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
}
.polaroid-settings-img img { width:100%;height:100%;object-fit:cover; }
.polaroid-settings-caption-wrap {
    padding: 10px 6px 14px;
    min-height: 46px;
    display: flex; align-items: flex-end; justify-content: center;
}
.polaroid-settings-caption {
    font-size: 11px; color: #7a7060;
    text-align: center; line-height: 1.5;
    font-family: 'Noto Sans TC', sans-serif;
}
.polaroid-fields { flex: 1; display: flex; flex-direction: column; gap: 14px; }
</style>

<div id="top" class="grid grid-2" style="gap:20px;align-items:start">
  <div>
    <!-- Profile -->
    <div id="profile" class="card settings-section" style="margin-bottom:16px">
        <div class="card-title"><?=svg_icon('teacher')?> 帳號與個人資料</div>
        <form method="post">
            <input type="hidden" name="section" value="profile">
            <div class="form-group">
                <label class="form-label">顯示名稱</label>
                <input class="form-input" name="display_name" value="<?=h($current_user['display_name'] ?? $_SESSION['display_name'] ?? '')?>" required placeholder="顯示在側欄的名字">
            </div>
            <div style="background:var(--bg3);border-radius:10px;padding:14px 16px;margin-bottom:16px">
                <div style="font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px">修改帳號</div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">帳號名稱</label>
                    <input class="form-input" name="new_username" value="<?=h($current_user['username'] ?? $_SESSION['username'] ?? '')?>" placeholder="英數字 / 底線，至少 3 字元">
                    <div style="font-size:11px;color:var(--text3);margin-top:4px">登入時使用的帳號</div>
                </div>
            </div>
            <div style="background:var(--bg3);border-radius:10px;padding:14px 16px;margin-bottom:16px">
                <div style="font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px">修改密碼 <span style="font-weight:400;color:var(--text3)">（留空則不變更）</span></div>
                <div class="form-group">
                    <label class="form-label">新密碼</label>
                    <input class="form-input" name="new_password" type="password" placeholder="至少 4 個字元" autocomplete="new-password">
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">確認密碼</label>
                    <input class="form-input" name="confirm_password" type="password" placeholder="再輸入一次" autocomplete="new-password">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">儲存帳號資料</button>
        </form>
    </div>

    <!-- Polaroid -->
    <div id="polaroid" class="card settings-section" style="margin-bottom:16px">
        <div class="card-title"><?=svg_icon('notes')?> 激勵小卡</div>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="section" value="polaroid">
            <div class="polaroid-preview-wrap">
                <div class="polaroid-settings-frame">
                    <div class="polaroid-settings-img" id="preview_wrap">
                        <?php if ($polaroid_image): ?>
                        <img src="<?= h($polaroid_image) ?>" id="preview_img" alt="">
                        <?php else: ?>
                        <img id="preview_img" style="display:none" alt="">
                        <svg id="preview_placeholder" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.4;color:var(--text3)">
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <circle cx="12" cy="12" r="3.5"/>
                        </svg>
                        <?php endif; ?>
                    </div>
                    <div class="polaroid-settings-caption-wrap">
                        <div class="polaroid-settings-caption" id="preview_caption"><?= h($polaroid_caption) ?></div>
                    </div>
                </div>
                <div class="polaroid-fields">
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">激勵文字 <span style="color:var(--text3);font-weight:400">（最多 40 字）</span></label>
                        <textarea class="form-input" name="polaroid_caption" id="caption_input"
                            rows="3" maxlength="40"
                            oninput="document.getElementById('preview_caption').textContent=this.value"
                            placeholder="寫下激勵自己的話…"><?= h($polaroid_caption) ?></textarea>
                        <div style="font-size:11px;color:var(--text3);margin-top:4px" id="char_count"></div>
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">上傳照片 <span style="color:var(--text3);font-weight:400">（JPG / PNG，最大 3 MB）</span></label>
                        <label style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:8px;border:1px solid var(--border);background:var(--bg3);cursor:pointer;font-size:13px;color:var(--text2);transition:border-color .15s;width:100%;box-sizing:border-box" id="polaroid_file_label">
                            <?=svg_icon('notes')?>
                            <span id="polaroid_file_name">選擇照片檔案…</span>
                            <input type="file" name="polaroid_image" accept="image/*"
                                   style="display:none"
                                   onchange="previewImage(this);document.getElementById('polaroid_file_name').textContent=this.files[0]?this.files[0].name:'選擇照片檔案…'">
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">儲存小卡</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Pomodoro -->
    <div id="pomodoro" class="card settings-section">
        <div class="card-title"><?=svg_icon('pomodoro')?> 番茄鐘設定</div>
        <form method="post">
            <input type="hidden" name="section" value="pomodoro">
            <div class="form-group"><label class="form-label">專注時間（分鐘）</label><input class="form-input" name="pomo_focus" type="number" min="1" max="60" value="25"></div>
            <div class="form-group"><label class="form-label">短休息（分鐘）</label><input class="form-input" name="pomo_short" type="number" min="1" max="30" value="5"></div>
            <div class="form-group"><label class="form-label">長休息（分鐘）</label><input class="form-input" name="pomo_long" type="number" min="5" max="60" value="15"></div>
            <button type="submit" class="btn btn-primary">儲存</button>
        </form>
    </div>
  </div>

  <div>
    <!-- Countdowns -->
    <div id="countdowns" class="card settings-section" style="margin-bottom:16px">
        <div class="card-title"><?=svg_icon('alarm')?> 儀表板倒數事件</div>
        <p style="font-size:13px;color:var(--text3);margin-bottom:16px">選擇最多 3 個事件顯示在儀表板的倒數計時區塊。</p>
        <form method="post">
            <input type="hidden" name="section" value="countdowns">
            <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="form-group">
                <label class="form-label">倒數事件 <?= $i+1 ?></label>
                <select class="form-input" name="countdown_<?= $i+1 ?>">
                    <option value="">不顯示</option>
                    <?php foreach($countdown_candidates as $event): ?>
                    <option value="<?= h($event['event_key']) ?>"
                        <?= ($saved_countdowns[$i] ?? '') === $event['event_key'] ? 'selected' : '' ?>>
                        <?= h($event['title']) ?> · <?= h($event['due_date']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endfor; ?>
            <button type="submit" class="btn btn-primary">套用</button>
        </form>
    </div>

    <!-- Notifications -->
    <div id="notifications" class="card settings-section" style="margin-bottom:16px">
        <div class="card-title"><?=svg_icon('notifications')?> 通知設定</div>
        <?php $toggles = [
            ['作業截止提醒','notify_hw','提前 3 天提醒作業截止'],
            ['缺席預警通知','notify_att','出席率低於 75% 時通知'],
            ['成績低分提醒','notify_grade','平均成績低於 60 時通知'],
            ['番茄鐘結束通知','notify_pomo','每次番茄鐘完成時通知'],
        ];
        foreach($toggles as [$label,$name,$desc]): ?>
        <div class="settings-label-row">
            <div class="settings-label-info">
                <div class="settings-label-title"><?= $label ?></div>
                <div class="settings-label-desc"><?= $desc ?></div>
            </div>
            <label class="toggle-wrap">
                <input type="checkbox" checked name="<?=$name?>">
                <span class="toggle-track"></span>
                <span class="toggle-thumb"></span>
            </label>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Data overview -->
    <div id="data" class="card settings-section" style="margin-bottom:16px">
        <div class="card-title"><?=svg_icon('statistics')?> 資料概覽</div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;padding:4px 0">
            <?php $counts = [
                ['課程',   $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn(),    'courses'],
                ['作業',   $pdo->query("SELECT COUNT(*) FROM assignments")->fetchColumn(),'assignments'],
                ['成績',   $pdo->query("SELECT COUNT(*) FROM grades")->fetchColumn(),     'grades'],
                ['出缺席', $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn(), 'attendance'],
                ['待辦',   $pdo->query("SELECT COUNT(*) FROM todos")->fetchColumn(),      'todos'],
                ['番茄鐘', $pdo->query("SELECT COUNT(*) FROM pomodoro_sessions")->fetchColumn(),'pomodoro'],
            ];
            foreach($counts as [$l,$v,$icon]): ?>
            <div style="text-align:center;padding:12px;background:var(--bg3);border-radius:8px">
                <div style="font-size:22px;font-weight:700;font-family:'Space Mono',monospace;color:var(--accent)"><?=$v?></div>
                <div style="font-size:11px;color:var(--text3);margin-top:4px"><?=svg_icon($icon,12)?> <?=$l?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- About -->
    <div id="about" class="card settings-section">
        <div class="card-title"><?=svg_icon('info')?> 關於</div>
        <table>
            <tr><td style="color:var(--text3);width:90px">系統名稱</td><td>知序 ZENO</td></tr>
            <tr><td style="color:var(--text3)">版本</td><td><span class="badge badge-green">v1.0.0</span></td></tr>
            <tr><td style="color:var(--text3)">技術</td><td>PHP + SQLite</td></tr>
        </table>
    </div>
  </div>
</div>

<script>
function previewImage(input) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        var img = document.getElementById('preview_img');
        var ph  = document.getElementById('preview_placeholder');
        img.src = e.target.result;
        img.style.display = 'block';
        if (ph) ph.style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}

// Char counter
var captionInput = document.getElementById('caption_input');
var charCount    = document.getElementById('char_count');
function updateCount() {
    var len = captionInput.value.length;
    charCount.textContent = len + ' / 40 字';
    charCount.style.color = len > 35 ? 'var(--yellow)' : 'var(--text3)';
}
if (captionInput) { captionInput.addEventListener('input', updateCount); updateCount(); }

// Toggle switches
document.querySelectorAll('.toggle-wrap input').forEach(function(inp) {
    function sync() {
        var thumb = inp.parentElement.querySelector('.toggle-thumb');
        var track = inp.parentElement.querySelector('.toggle-track');
        if (thumb) thumb.style.transform = inp.checked ? 'translateX(18px)' : '';
        if (track) {
            track.style.background   = inp.checked ? 'var(--accent)' : '';
            track.style.borderColor  = inp.checked ? 'var(--accent)' : '';
            if (thumb) thumb.style.background = inp.checked ? 'white' : '';
        }
    }
    inp.addEventListener('change', sync);
    sync();
});

// Anchor scroll
if (location.hash) {
    var el = document.querySelector(location.hash);
    if (el) setTimeout(function(){ el.scrollIntoView({behavior:'smooth',block:'start'}); }, 150);
}
</script>

<?php require_once 'includes/footer.php'; ?>
