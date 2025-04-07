<?php
// File: includes/auth/login.php
require_once '../config.php';
require_once '../functions.php';

// Kiểm tra nếu đã submit form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Thu thập dữ liệu từ form
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Validate input
    if (empty($username) || empty($password)) {
        $response = ['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin'];
    } else {
        // Xử lý đăng nhập
        $result = loginUser($username, $password);
        
        if ($result['success']) {
            // Lưu thông tin vào session
            $_SESSION['user_id'] = $result['user_id']; // Giả định loginUser trả về user_id
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