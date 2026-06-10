<?php
session_start();
date_default_timezone_set('Asia/Taipei');

// ── Auth guard ──────────────────────────────────────────────────────────────
function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// ── Semester helper ──────────────────────────────────────────────────────────
// Returns the active semester_id stored in session (or the current semester from DB)
function active_semester_id(PDO $pdo): int {
    // Ensure semesters table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS semesters (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        label TEXT,
        start_date DATE,
        end_date DATE,
        is_current INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    // Ensure courses has semester_id column
    $cols = $pdo->query("PRAGMA table_info(courses)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('semester_id', $cols)) {
        $pdo->exec("ALTER TABLE courses ADD COLUMN semester_id INTEGER DEFAULT 1");
    }

    // Create default semester if none exist
    $count = $pdo->query("SELECT COUNT(*) FROM semesters")->fetchColumn();
    if ($count == 0) {
        $year = date('Y');
        $month = (int)date('m');
        $sem = $month >= 8 ? '上學期' : '下學期';
        $sem_year = $month >= 8 ? ($year - 1911) : ($year - 1911 - 1);
        $pdo->exec("INSERT INTO semesters (name, label, is_current) VALUES ('{$sem_year}{$sem}', '{$sem_year}學年{$sem}', 1)");
        // Assign all existing courses to semester 1
        $pdo->exec("UPDATE courses SET semester_id = 1 WHERE semester_id IS NULL OR semester_id = 0");
    }

    // Switch semester if requested
    if (!empty($_GET['switch_semester'])) {
        $sid = (int)$_GET['switch_semester'];
        $exists = $pdo->prepare("SELECT id FROM semesters WHERE id = ?");
        $exists->execute([$sid]);
        if ($exists->fetchColumn()) {
            $_SESSION['active_semester'] = $sid;
        }
    }

    if (!empty($_SESSION['active_semester'])) {
        // Verify it still exists
        $check = $pdo->prepare("SELECT id FROM semesters WHERE id = ?");
        $check->execute([$_SESSION['active_semester']]);
        if ($check->fetchColumn()) {
            return (int)$_SESSION['active_semester'];
        }
    }
    // Default: current semester
    $sid = $pdo->query("SELECT id FROM semesters WHERE is_current = 1 ORDER BY id DESC LIMIT 1")->fetchColumn();
    if (!$sid) $sid = $pdo->query("SELECT id FROM semesters ORDER BY id DESC LIMIT 1")->fetchColumn();
    $_SESSION['active_semester'] = (int)$sid;
    return (int)$sid;
}

// SVG icon library — 16x16 CSS-drawn icons, no emoji
function svg_icon($key, $size = 16) {
    $s = $size;
    $h = $size / 2;
    $icons = [
    // home
        'home' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <path d='M2 7L8 2L14 7V14H10V10H6V14H2V7Z' stroke='currentColor' stroke-width='1.5' stroke-linejoin='round'/>
        </svg>",
    // calendar
        'calendar' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <rect x='2' y='3' width='12' height='11' rx='2' stroke='currentColor' stroke-width='1.5'/>
            <path d='M5 2V4M11 2V4' stroke='currentColor' stroke-width='1.5' stroke-linecap='round'/>
            <path d='M2 7H14' stroke='currentColor' stroke-width='1.5'/>
            <circle cx='5.5' cy='10.5' r='1' fill='currentColor'/>
            <circle cx='8' cy='10.5' r='1' fill='currentColor'/>
            <circle cx='10.5' cy='10.5' r='1' fill='currentColor'/>
        </svg>",
    // tomato / pomodoro
        'pomodoro' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <circle cx='8' cy='9' r='5.5' stroke='currentColor' stroke-width='1.5'/>
            <path d='M8 3.5V2M8 2C8 2 9.5 1 11 2' stroke='currentColor' stroke-width='1.5' stroke-linecap='round'/>
            <path d='M6 9.5L7.5 11L10 8' stroke='currentColor' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/>
        </svg>",
    // courses
        'courses' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <path d='M3 3H10C10.6 3 11 3.4 11 4V13L7 11L3 13V3Z' stroke='currentColor' stroke-width='1.5' stroke-linejoin='round'/>
            <path d='M11 4H13V14L7 11' stroke='currentColor' stroke-width='1.5' stroke-linejoin='round'/>
        </svg>",
    // schedule
        'schedule' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <rect x='2' y='3' width='12' height='11' rx='2' stroke='currentColor' stroke-width='1.5'/>
            <path d='M5 2V4M11 2V4' stroke='currentColor' stroke-width='1.5' stroke-linecap='round'/>
            <path d='M2 7H14' stroke='currentColor' stroke-width='1.5'/>
            <path d='M5 10H11M5 12.5H8.5' stroke='currentColor' stroke-width='1.5' stroke-linecap='round'/>
        </svg>",
    // assignments
        'assignments' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <rect x='3' y='2' width='10' height='12' rx='1.5' stroke='currentColor' stroke-width='1.5'/>
            <path d='M6 6H10M6 9H10M6 12H8' stroke='currentColor' stroke-width='1.5' stroke-linecap='round'/>
        </svg>",
    // grades
        'grades' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <path d='M8 2L14 5L8 8L2 5L8 2Z' stroke='currentColor' stroke-width='1.5' stroke-linejoin='round'/>
            <path d='M5 6.5V10.5C5 10.5 6 12 8 12C10 12 11 10.5 11 10.5V6.5' stroke='currentColor' stroke-width='1.5' stroke-linecap='round'/>
            <path d='M14 5V9' stroke='currentColor' stroke-width='1.5' stroke-linecap='round'/>
        </svg>",
    // attendance
        'attendance' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <circle cx='6' cy='5' r='2.5' stroke='currentColor' stroke-width='1.5'/>
            <path d='M1 13C1 10.8 3.2 9 6 9C8.8 9 11 10.8 11 13' stroke='currentColor' stroke-width='1.5' stroke-linecap='round'/>
            <circle cx='12' cy='5' r='2' stroke='currentColor' stroke-width='1.3'/>
            <path d='M11.5 9.2C11.8 9.1 12.1 9 12.5 9C14.3 9 15.5 10.3 15.5 12' stroke='currentColor' stroke-width='1.3' stroke-linecap='round'/>
        </svg>",
    // todos
        'todos' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <rect x='3' y='2' width='10' height='12' rx='1.5' stroke='currentColor' stroke-width='1.5'/>
            <path d='M6 6L7.5 7.5L10 5' stroke='currentColor' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/>
            <path d='M6 10L7.5 11.5L10 9' stroke='currentColor' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/>
        </svg>",
    // statistics
        'statistics' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <path d='M2 13V9H5V13H2Z' stroke='currentColor' stroke-width='1.5' stroke-linejoin='round'/>
            <path d='M6.5 13V6H9.5V13H6.5Z' stroke='currentColor' stroke-width='1.5' stroke-linejoin='round'/>
            <path d='M11 13V3H14V13H11Z' stroke='currentColor' stroke-width='1.5' stroke-linejoin='round'/>
        </svg>",
    // notifications
        'notifications' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <path d='M8 2C8 2 5 3.5 5 7V11H11V7C11 3.5 8 2 8 2Z' stroke='currentColor' stroke-width='1.5' stroke-linejoin='round'/>
            <path d='M4 11H12' stroke='currentColor' stroke-width='1.5' stroke-linecap='round'/>
            <path d='M6.5 11C6.5 11 6.5 13 8 13C9.5 13 9.5 11 9.5 11' stroke='currentColor' stroke-width='1.5' stroke-linecap='round'/>
        </svg>",
    // settings
        'settings' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <circle cx='8' cy='8' r='2.5' stroke='currentColor' stroke-width='1.5'/>
            <path d='M8 1.5V3M8 13V14.5M14.5 8H13M3 8H1.5M12.7 3.3L11.6 4.4M4.4 11.6L3.3 12.7M12.7 12.7L11.6 11.6M4.4 4.4L3.3 3.3' stroke='currentColor' stroke-width='1.5' stroke-linecap='round'/>
        </svg>",
    // trend
        'trend' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <path d='M2 12L6 8L9 10L14 4' stroke='currentColor' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/>
            <path d='M11 4H14V7' stroke='currentColor' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/>
        </svg>",
    // ℹ info
        'info' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <circle cx='8' cy='8' r='6' stroke='currentColor' stroke-width='1.5'/>
            <path d='M8 7V11' stroke='currentColor' stroke-width='1.5' stroke-linecap='round'/>
            <circle cx='8' cy='5' r='0.8' fill='currentColor'/>
        </svg>",
        // ⏰ alarm / timer
        'alarm' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <circle cx='8' cy='9' r='5.5' stroke='currentColor' stroke-width='1.5'/>
            <path d='M8 6.5V9L9.5 10.5' stroke='currentColor' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/>
            <path d='M3 3.5L1.5 5M13 3.5L14.5 5' stroke='currentColor' stroke-width='1.5' stroke-linecap='round'/>
        </svg>",
    // warning
        'warning' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <path d='M8 2L14.5 13H1.5L8 2Z' stroke='currentColor' stroke-width='1.5' stroke-linejoin='round'/>
            <path d='M8 6.5V9.5' stroke='currentColor' stroke-width='1.5' stroke-linecap='round'/>
            <circle cx='8' cy='11.5' r='0.75' fill='currentColor'/>
        </svg>",
    // check / done
        'check' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <circle cx='8' cy='8' r='6' stroke='currentColor' stroke-width='1.5'/>
            <path d='M5 8L7 10L11 6' stroke='currentColor' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/>
        </svg>",
    // location
        'location' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <path d='M8 2C5.8 2 4 3.8 4 6C4 9 8 14 8 14C8 14 12 9 12 6C12 3.8 10.2 2 8 2Z' stroke='currentColor' stroke-width='1.5'/>
            <circle cx='8' cy='6' r='1.5' stroke='currentColor' stroke-width='1.3'/>
        </svg>",
    // ‍ teacher
        'teacher' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <circle cx='8' cy='5' r='3' stroke='currentColor' stroke-width='1.5'/>
            <path d='M2 14C2 11.2 4.7 9 8 9C11.3 9 14 11.2 14 14' stroke='currentColor' stroke-width='1.5' stroke-linecap='round'/>
        </svg>",
    // notes
        'notes' => "<svg width='{$s}' height='{$s}' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'>
            <rect x='3' y='2' width='10' height='12' rx='1.5' stroke='currentColor' stroke-width='1.5'/>
            <path d='M6 6H10M6 9H10M6 12H9' stroke='currentColor' stroke-width='1.5' stroke-linecap='round'/>
        </svg>",
    ];
    $svg = $icons[$key] ?? $icons['notes'];
    return "<span class='nav-svg-icon' style='display:inline-flex;align-items:center;justify-content:center;width:{$s}px;height:{$s}px;flex-shrink:0'>{$svg}</span>";
}

function nav_item($icon, $label, $page, $current, $badge = null) {
    $active = ($current === $page) ? ' active' : '';
    $b = $badge ? "<span class='nav-badge'>{$badge}</span>" : '';
    $icon_html = svg_icon($icon);
    echo "<a href='{$page}' class='nav-item{$active}'><span class='icon'>{$icon_html}</span>{$label}{$b}</a>";
}

function flash($msg, $type = 'success') {
    $_SESSION['flash_msg'] = $msg;
    $_SESSION['flash_type'] = $type;
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES); }

function weekly_class_count(array $course): int {
    $name = $course['name'] ?? '';
    if (mb_strpos($name, '體育') !== false) return 2;
    if (mb_strpos($name, '英文') !== false) return 1;
    return max(0, (int)($course['credits'] ?? 0));
}

function semester_class_count(array $course): int {
    return weekly_class_count($course) * 18;
}

function attendance_rate_from_absences(array $course, int $absent): float {
    $total = semester_class_count($course);
    if ($total <= 0) return 100.0;
    return round(max(0, $total - $absent) / $total * 100, 1);
}

function badge_status($status) {
    $map = [
        'present'  => ['badge-green', '出席'],
        'late'     => ['badge-green', '出席'],
        'absent'   => ['badge-red', '缺席'],
        'excused'        => ['badge-blue',   '請假'],
        'official_leave' => ['badge-purple', '公假'],
        'bereavement'    => ['badge-gray',   '喪假'],
        'pending'  => ['badge-yellow', '未完成'],
        'done'     => ['badge-green', '完成'],
    ];
    $v = $map[$status] ?? ['badge-gray', $status];
    return "<span class='badge {$v[0]}'>{$v[1]}</span>";
}

function badge_priority($priority) {
    $map = [
        'high'   => ['badge-red', '高'],
        'normal' => ['badge-blue', '中'],
        'low'    => ['badge-gray', '低'],
    ];
    $v = $map[$priority] ?? ['badge-gray', $priority];
    return "<span class='badge {$v[0]}'>{$v[1]}</span>";
}

function days_zh($d) {
    return ['', '週一', '週二', '週三', '週四', '週五', '週六', '週日'][$d] ?? '';
}

function grade_badge($pct) {
    if ($pct >= 90) return "<span class='badge badge-green'>" . round($pct) . "</span>";
    if ($pct >= 75) return "<span class='badge badge-blue'>"  . round($pct) . "</span>";
    if ($pct >= 60) return "<span class='badge badge-yellow'>". round($pct) . "</span>";
    return "<span class='badge badge-red'>" . round($pct) . "</span>";
}

function course_color_dot($color) {
    return "<span class='course-dot' style='background:{$color}'></span>";
}
