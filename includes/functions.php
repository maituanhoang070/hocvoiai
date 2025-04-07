<?php
// File: includes/functions.php
// Hàm kiểm tra người dùng đã đăng nhập chưa
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Hàm chuyển hướng nếu chưa đăng nhập
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: index.php?page=login");
        exit;
    }
}

// Hàm lấy thông tin người dùng
function getUserInfo($user_id) {
    global $conn;
    $sql = "SELECT id, username, email, full_name, points, role 
            FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if(mysqli_num_rows($result) == 1) {
        return mysqli_fetch_assoc($result);
    }
    return false;
}

// Hàm kiểm tra và cập nhật điểm thưởng hàng ngày
function checkDailyBonus($user_id) {
    global $conn;
    
    // Kiểm tra xem hôm nay đã nhận điểm thưởng chưa
    $today = date('Y-m-d');
    $sql = "SELECT id FROM point_history 
            WHERE user_id = ? AND action = 'daily_bonus' 
            AND DATE(created_at) = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $user_id, $today);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if(mysqli_num_rows($result) == 0) {
        // Chưa nhận thưởng hôm nay, thêm điểm
        addPoints($user_id, POINTS_DAILY_BONUS, 'daily_bonus', 'Điểm thưởng đăng nhập hàng ngày');
        return true;
    }
    
    return false;
}

// Hàm thêm điểm cho người dùng
function addPoints($user_id, $points, $action, $details = '') {
    global $conn;
    
    // Cập nhật điểm trong bảng users
    $sql = "UPDATE users SET points = points + ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $points, $user_id);
    $update_result = mysqli_stmt_execute($stmt);
    
    // Thêm vào lịch sử điểm
    $sql = "INSERT INTO point_history (user_id, points, action, details) 
            VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iiss", $user_id, $points, $action, $details);
    $history_result = mysqli_stmt_execute($stmt);
    
    return $update_result && $history_result;
}

// Hàm kiểm tra và sử dụng điểm cho câu hỏi
function usePointsForQuestion($user_id, $points_required = POINTS_PER_QUESTION) {
    global $conn;
    
    // Kiểm tra số điểm hiện tại
    $user = getUserInfo($user_id);
    if($user && $user['points'] >= $points_required) {
        // Trừ điểm
        $points = -$points_required; // Số âm vì là trừ điểm
        addPoints($user_id, $points, 'question', 'Sử dụng điểm để đặt câu hỏi');
        return true;
    }
    
    return false;
}

// Hàm xử lý nhập mã code
function redeemCode($user_id, $code) {
    global $conn;
    
    // Kiểm tra mã code tồn tại và còn hiệu lực
    $sql = "SELECT * FROM redeem_codes 
            WHERE code = ? 
            AND (expiry_date IS NULL OR expiry_date > NOW()) 
            AND (max_uses = 0 OR current_uses < max_uses)";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $code);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if(mysqli_num_rows($result) == 1) {
        $code_data = mysqli_fetch_assoc($result);
        
        // Kiểm tra xem người dùng đã dùng mã này chưa
        $sql = "SELECT id FROM code_redemptions 
                WHERE user_id = ? AND code_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $code_data['id']);
        mysqli_stmt_execute($stmt);
        $check_result = mysqli_stmt_get_result($stmt);
        
        if(mysqli_num_rows($check_result) > 0) {
            return ['success' => false, 'message' => 'Bạn đã sử dụng mã này rồi'];
        }
        
        // Cập nhật số lần sử dụng
        $sql = "UPDATE redeem_codes SET current_uses = current_uses + 1 WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $code_data['id']);
        mysqli_stmt_execute($stmt);
        
        // Thêm vào bảng lịch sử sử dụng mã
        $sql = "INSERT INTO code_redemptions (user_id, code_id, points_awarded) 
                VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iii", $user_id, $code_data['id'], $code_data['points']);
        mysqli_stmt_execute($stmt);
        
        // Thêm điểm cho người dùng
        addPoints($user_id, $code_data['points'], 'redeem_code', 'Nhập mã code: ' . $code);
        
        return ['success' => true, 'points' => $code_data['points']];
    }
    
    return ['success' => false, 'message' => 'Mã không hợp lệ hoặc đã hết hạn'];
}

// Hàm lấy lịch sử câu hỏi của người dùng
function getUserQuestions($user_id, $limit = 10) {
    global $conn;
    
    $sql = "SELECT * FROM questions 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $questions = [];
    while($row = mysqli_fetch_assoc($result)) {
        $questions[] = $row;
    }
    
    return $questions;
}

// Hàm lấy lịch sử điểm của người dùng
function getPointHistory($user_id, $limit = 10) {
    global $conn;
    
    $sql = "SELECT * FROM point_history 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $history = [];
    while($row = mysqli_fetch_assoc($result)) {
        $history[] = $row;
    }
    
    return $history;
}

// Hàm xử lý đăng nhập an toàn (chống SQL injection)
function loginUser($username, $password) {
    global $conn;
    
    $sql = "SELECT id, username, password FROM users WHERE username = ? OR email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $username, $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if(mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Xác thực mật khẩu
        if(password_verify($password, $user['password'])) {
            // Cập nhật thời gian đăng nhập
            $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $user['id']);
            mysqli_stmt_execute($stmt);
            
            // Thiết lập session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            // Kiểm tra điểm thưởng hàng ngày
            checkDailyBonus($user['id']);
            
            return ['success' => true, 'user_id' => $user['id']];
        }
    }
    
    return ['success' => false, 'message' => 'Tên đăng nhập hoặc mật khẩu không đúng'];
}

// Hàm đăng ký người dùng mới
function registerUser($username, $email, $password, $full_name = '') {
    global $conn;
    
    // Kiểm tra username và email đã tồn tại chưa
    $sql = "SELECT id FROM users WHERE username = ? OR email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $username, $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if(mysqli_num_rows($result) > 0) {
        return ['success' => false, 'message' => 'Tên đăng nhập hoặc email đã tồn tại'];
    }
    
    // Mã hóa mật khẩu
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Thêm người dùng mới
    $sql = "INSERT INTO users (username, email, password, full_name, points) 
            VALUES (?, ?, ?, ?, 10)"; // Tặng 10 điểm khi đăng ký
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssss", $username, $email, $hashed_password, $full_name);
    
    if(mysqli_stmt_execute($stmt)) {
        $user_id = mysqli_insert_id($conn);
        
        // Thêm vào lịch sử điểm
        addPoints($user_id, 10, 'registration', 'Điểm thưởng khi đăng ký tài khoản');
        
        return ['success' => true, 'user_id' => $user_id];
    }
    
    return ['success' => false, 'message' => 'Đăng ký không thành công: ' . mysqli_error($conn)];
}

// Hàm lưu câu hỏi vào database
function saveQuestion($user_id, $subject, $skill, $topic, $content, $bot_used) {
    global $conn;
    
    $sql = "INSERT INTO questions (user_id, subject, skill, topic, content, bot_used, points_used) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $points_used = POINTS_PER_QUESTION;
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "isssssi", $user_id, $subject, $skill, $topic, $content, $bot_used, $points_used);
    
    return mysqli_stmt_execute($stmt);
}

// Hàm tạo mã ngẫu nhiên
function generateRandomCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $code;
}

// Hàm tạo mã code mới (dành cho admin)
function createRedeemCode($points, $max_uses = 1, $expiry_days = null) {
    global $conn;
    
    $code = generateRandomCode();
    $expiry_date = null;
    
    if($expiry_days !== null) {
        $expiry_date = date('Y-m-d H:i:s', strtotime("+$expiry_days days"));
    }
    
    $sql = "INSERT INTO redeem_codes (code, points, max_uses, expiry_date) 
            VALUES (?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "siis", $code, $points, $max_uses, $expiry_date);
    
    if(mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'code' => $code];
    }
    
    return ['success' => false, 'message' => 'Không thể tạo mã'];
}
?>