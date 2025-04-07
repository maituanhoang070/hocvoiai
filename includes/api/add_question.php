<?php
// File: includes/api/add_question.php
header('Content-Type: application/json');
session_start();
require_once '../config.php';
require_once '../functions.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để thực hiện hành động này']);
    exit;
}

$user_id = $_SESSION['user_id'];
$subject = isset($_POST['subject']) ? $_POST['subject'] : '';
$skill = isset($_POST['skill']) ? $_POST['skill'] : '';
$topic = isset($_POST['topic']) ? $_POST['topic'] : '';
$content = isset($_POST['content']) ? $_POST['content'] : '';
$bot_used = isset($_POST['bot_used']) ? $_POST['bot_used'] : '';



// Trừ điểm người dùng
$sql = "UPDATE users SET points = points - 1 WHERE id = ? AND points >= 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Bạn không đủ điểm để đặt câu hỏi']);
    exit;
}

// Lưu câu hỏi vào database
$sql = "INSERT INTO questions (user_id, subject, skill, topic, content, bot_used, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param('isssss', $user_id, $subject, $skill, $topic, $content, $bot_used);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Không thể lưu câu hỏi']);
}

$stmt->close();
$conn->close();
?>