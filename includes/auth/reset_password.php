<?php
// File: includes/auth/reset_password.php
require_once '../config.php';
require_once '../functions.php';

// Kiểm tra nếu đã submit form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Thu thập dữ liệu từ form
    $email = trim($_POST['email']);
    
    // Validate input
    if (empty($email)) {
        $response = ['success' => false, 'message' => 'Vui lòng nhập email'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response = ['success' => false, 'message' => 'Email không hợp lệ'];
    } else {
        // Kiểm tra email có tồn tại trong database không
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            $response = ['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu'];
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Email tồn tại, tạo token đặt lại mật khẩu
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token hết hạn sau 1 giờ
                
                // Lưu token vào database (giả định có bảng password_resets)
                $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $email, $token, $expires);
                if ($stmt->execute()) {
                    // Giả lập gửi email (thay thế bằng hàm gửi email thực tế nếu có)
                    $resetLink = "http://hocbai.work.gd/index.php?page=reset_password&token=$token";
                    // mail($email, "Đặt lại mật khẩu", "Nhấn vào đây để đặt lại mật khẩu: $resetLink");
                    
                    $response = ['success' => true, 'message' => 'Link đặt lại mật khẩu đã được gửi đến email của bạn'];
                } else {
                    $response = ['success' => false, 'message' => 'Có lỗi xảy ra, vui lòng thử lại sau'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Email không tồn tại trong hệ thống'];
            }
            
            $stmt->close();
            $conn->close();
        }
    }
    
    // Trả về kết quả dạng JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>