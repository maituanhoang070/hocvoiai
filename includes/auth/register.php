<?php
// File: includes/auth/register.php
require_once '../config.php';
require_once '../functions.php';

// Kiểm tra nếu đã submit form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Thu thập dữ liệu từ form
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    
    // Validate input
    if (empty($username) || empty($email) || empty($password)) {
        $response = ['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin'];
    } elseif ($password !== $confirm_password) {
        $response = ['success' => false, 'message' => 'Mật khẩu xác nhận không khớp'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response = ['success' => false, 'message' => 'Email không hợp lệ'];
    } elseif (strlen($password) < 6) {
        $response = ['success' => false, 'message' => 'Mật khẩu phải có ít nhất 6 ký tự'];
    } else {
        // Xử lý đăng ký
        $result = registerUser($username, $email, $password, $full_name);
        
        if ($result['success']) {
            // Đăng nhập người dùng sau khi đăng ký
            $_SESSION['user_id'] = $result['user_id'];
            $_SESSION['username'] = $username;
            
            $response = ['success' => true, 'redirect' => 'index.php'];
        } else {
            $response = ['success' => false, 'message' => $result['message']];
        }
    }
    
    // Trả về kết quả dạng JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>