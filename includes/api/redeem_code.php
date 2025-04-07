<?php
// File: includes/api/redeem_code.php
require_once '../config.php';
require_once '../functions.php';

// Kiểm tra đăng nhập
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để sử dụng tính năng này']);
    exit;
}

// Kiểm tra nếu đã submit form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Thu thập dữ liệu từ form
    $code = trim($_POST['code']);
    
    // Validate input
    if (empty($code)) {
        $response = ['success' => false, 'message' => 'Vui lòng nhập mã code'];
    } else {
        // Xử lý nhập mã
        $result = redeemCode($_SESSION['user_id'], $code);
        
        if ($result['success']) {
            $response = [
                'success' => true, 
                'message' => 'Bạn đã nhận được ' . $result['points'] . ' điểm từ mã code này!',
                'points' => $result['points']
            ];
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