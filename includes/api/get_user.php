<?php
// File: includes/api/get_user.php
require_once '../config.php';
require_once '../functions.php';

// Kiểm tra đăng nhập
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để sử dụng tính năng này']);
    exit;
}

// Lấy thông tin người dùng
$user = getUserInfo($_SESSION['user_id']);

if ($user) {
    // Loại bỏ thông tin nhạy cảm (nếu có)
    unset($user['password']);
    
    $response = [
        'success' => true,
        'user' => $user
    ];
} else {
    $response = ['success' => false, 'message' => 'Không thể lấy thông tin người dùng'];
}

// Trả về kết quả dạng JSON
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>