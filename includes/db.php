<?php
// SQLite database connection
$db_path = __DIR__ . '/../data/cms.db';
if (!is_dir(dirname($db_path))) {
    mkdir(dirname($db_path), 0755, true);
}

try {
    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Initialize tables
$pdo->exec("
CREATE TABLE IF NOT EXISTS courses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    code TEXT,
    teacher TEXT,
    location TEXT,
    color TEXT DEFAULT '#6366f1',
    credits INTEGER DEFAULT 3,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS schedule (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INTEGER,
    day_of_week INTEGER NOT NULL, -- 1=Mon, 5=Fri
    start_time TEXT NOT NULL,
    end_time TEXT NOT NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INTEGER,
    title TEXT NOT NULL,
    description TEXT,
    due_date DATE,
    status TEXT DEFAULT 'pending', -- pending, done
    priority TEXT DEFAULT 'normal', -- low, normal, high
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS grades (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    score REAL NOT NULL,
    max_score REAL DEFAULT 100,
    type TEXT DEFAULT 'exam', -- exam, quiz, homework, project
    graded_at DATE DEFAULT CURRENT_DATE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS attendance (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INTEGER NOT NULL,
    date DATE NOT NULL,
    status TEXT DEFAULT 'present', -- present, late, absent, excused
    note TEXT,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS todos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    due_date DATE,
    status TEXT DEFAULT 'pending', -- pending, done
    priority TEXT DEFAULT 'normal',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pomodoro_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INTEGER,
    duration_minutes INTEGER DEFAULT 25,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed INTEGER DEFAULT 0,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    content TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS app_settings (
    key TEXT PRIMARY KEY,
    value TEXT
);
");

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void {
    $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll();
    foreach ($cols as $col) {
        if ($col['name'] === $column) return;
    }
    $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
}

ensure_column($pdo, 'assignments', 'end_date', 'DATE');
ensure_column($pdo, 'todos', 'end_date', 'DATE');
$pdo->exec("UPDATE assignments SET end_date = due_date WHERE end_date IS NULL AND due_date IS NOT NULL");
$pdo->exec("UPDATE todos SET end_date = due_date WHERE end_date IS NULL AND due_date IS NOT NULL");

// Seed demo data if empty
$count = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
if ($count == 0) {
    $pdo->exec("
    INSERT INTO courses (name, code, teacher, location, color, credits) VALUES
    ('微積分', 'MATH101', '陳教授', '理學院201', '#ef4444', 3),
    ('英文寫作', 'ENG201', '林老師', '人文大樓305', '#f59e0b', 2),
    ('資料結構', 'CS301', '王教授', '資訊館501', '#10b981', 3),
    ('經濟學概論', 'ECON101', '張老師', '社科院102', '#3b82f6', 3),
    ('物理實驗', 'PHY201', '吳教授', '物理館Lab3', '#8b5cf6', 1);

    INSERT INTO schedule (course_id, day_of_week, start_time, end_time) VALUES
    (1, 1, '08:00', '09:30'), (1, 3, '08:00', '09:30'),
    (2, 2, '10:00', '11:30'),
    (3, 1, '13:00', '14:30'), (3, 4, '13:00', '14:30'),
    (4, 2, '13:00', '14:30'), (4, 5, '10:00', '11:30'),
    (5, 3, '15:00', '17:00');

    INSERT INTO assignments (course_id, title, description, due_date, status, priority) VALUES
    (1, '微積分第三章習題', '完成 P.120-135 所有題目', date('now', '+3 days'), 'pending', 'high'),
    (3, '鏈結串列實作', '用 C++ 實作單向鏈結串列', date('now', '+5 days'), 'pending', 'high'),
    (2, '英文作文：My Future', '500字以上', date('now', '+7 days'), 'pending', 'normal'),
    (4, '個體經濟學報告', '分析台灣房市', date('now', '+10 days'), 'pending', 'normal'),
    (1, '微積分第二章習題', '期中考前複習', date('now', '-2 days'), 'done', 'normal'),
    (3, '陣列排序演算法', '實作三種排序', date('now', '-5 days'), 'done', 'high');

    INSERT INTO grades (course_id, title, score, max_score, type, graded_at) VALUES
    (1, '第一次期中考', 82, 100, 'exam', date('now', '-30 days')),
    (1, '第一次小考', 90, 100, 'quiz', date('now', '-45 days')),
    (2, '作文一', 88, 100, 'homework', date('now', '-20 days')),
    (3, '期中考', 75, 100, 'exam', date('now', '-28 days')),
    (3, '程式作業一', 95, 100, 'homework', date('now', '-15 days')),
    (4, '期中考', 78, 100, 'exam', date('now', '-25 days')),
    (5, '實驗報告一', 92, 100, 'homework', date('now', '-10 days'));

    INSERT INTO attendance (course_id, date, status) VALUES
    (1, date('now', '-7 days'), 'present'),
    (1, date('now', '-14 days'), 'present'),
    (1, date('now', '-21 days'), 'late'),
    (2, date('now', '-6 days'), 'present'),
    (2, date('now', '-13 days'), 'absent'),
    (3, date('now', '-7 days'), 'present'),
    (3, date('now', '-14 days'), 'present'),
    (4, date('now', '-6 days'), 'present'),
    (5, date('now', '-5 days'), 'present');

    INSERT INTO todos (title, description, due_date, priority, status) VALUES
    ('準備期末考複習計畫', '列出所有科目複習重點', date('now', '+2 days'), 'high', 'pending'),
    ('申請圖書館借閱證', NULL, date('now', '+5 days'), 'low', 'pending'),
    ('繳交下學期選課費', NULL, date('now', '+1 days'), 'high', 'pending'),
    ('購買物理課教科書', NULL, NULL, 'normal', 'done');
    ");
}

// Extra tables
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    display_name TEXT,
    student_id TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS semesters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    label TEXT,
    start_date DATE,
    end_date DATE,
    is_current INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS exam_scores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INTEGER NOT NULL,
    semester_id INTEGER DEFAULT 1,
    midterm_score REAL,
    midterm_max REAL DEFAULT 100,
    final_score REAL,
    final_max REAL DEFAULT 100,
    regular_score REAL,
    regular_max REAL DEFAULT 100,
    regular_pct REAL DEFAULT 30,
    midterm_pct REAL DEFAULT 30,
    final_pct REAL DEFAULT 40,
    class_rank INTEGER,
    school_rank INTEGER,
    class_total INTEGER,
    school_total INTEGER,
    pass_score REAL DEFAULT 60,
    note TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS dashboard_memos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    content TEXT NOT NULL,
    color TEXT DEFAULT '#5b7fff',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

// Ensure default user exists
$u = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($u == 0) {
    $hash = password_hash('1234', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)")
        ->execute(['admin', $hash, '同學']);
}

// Ensure semester_id column in courses
$cols = $pdo->query("PRAGMA table_info(courses)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (!in_array('semester_id', $cols)) {
    $pdo->exec("ALTER TABLE courses ADD COLUMN semester_id INTEGER DEFAULT 1");
}
