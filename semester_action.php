<?php
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_login();

$action = $_POST['action'] ?? '';
$redirect = $_POST['redirect'] ?? 'index.php';

// Strip any existing switch_semester param from redirect to avoid loops
$redirect = preg_replace('/([?&])switch_semester=[^&]*(&|$)/', '$1', $redirect);
$redirect = rtrim($redirect, '?&');

if ($action === 'add') {
    $name  = trim($_POST['name']  ?? '');
    $label = trim($_POST['label'] ?? '');
    if ($name) {
        $pdo->prepare("INSERT INTO semesters (name, label, is_current) VALUES (?, ?, 0)")
            ->execute([$name, $label ?: $name]);
        $new_id = $pdo->lastInsertId();
        $_SESSION['active_semester'] = (int)$new_id;
    }
} elseif ($action === 'edit') {
    $id    = (int)($_POST['id'] ?? 0);
    $name  = trim($_POST['name']  ?? '');
    $label = trim($_POST['label'] ?? '');
    if ($id && $name) {
        $pdo->prepare("UPDATE semesters SET name=?, label=? WHERE id=?")
            ->execute([$name, $label ?: $name, $id]);
    }
} elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $pdo->prepare("DELETE FROM semesters WHERE id=?")->execute([$id]);
        // If deleted semester was active, switch to another
        if (($_SESSION['active_semester'] ?? 0) == $id) {
            $next = $pdo->query("SELECT id FROM semesters ORDER BY id DESC LIMIT 1")->fetchColumn();
            $_SESSION['active_semester'] = $next ? (int)$next : null;
        }
    }
}

header('Location: ' . $redirect);
exit;
