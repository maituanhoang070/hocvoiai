<?php
// File: includes/config.php
define('SITE_NAME', 'HVAI');
define('GOOGLE_API_KEY', 'AIzaSyCuo5_4d5R87ZCraAC1ilGXIi8R6LEPpw0'); // Thay bằng API key của bạn
define('GOOGLE_CSE_ID', '551c3b2dc73b444d2'); // Thay bằng Search Engine ID của bạn
// File: includes/config.php
// Thông tin kết nối database - cần thay đổi theo thông tin hosting của bạn
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'vfybafce_vip2'); // Thay đổi thành username database của bạn
define('DB_PASSWORD', 'vfybafce_vip2'); // Thay đổi thành password database của bạn
define('DB_NAME', 'vfybafce_vip2');     // Thay đổi thành tên database của bạn

// Kết nối đến MySQL database
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Kiểm tra kết nối
if($conn === false){
    die("ERROR: Không thể kết nối. " . mysqli_connect_error());
}

// Đặt charset là utf8mb4
mysqli_set_charset($conn, "utf8mb4");

// Bắt đầu session
session_start();

// Hằng số cho ứng dụng
define('SITE_NAME', 'HocBai - Trợ lý học tập');
define('POINTS_PER_QUESTION', 1); // Số điểm trừ cho mỗi câu hỏi
define('POINTS_DAILY_BONUS', 5);  // Số điểm thưởng hàng ngày khi đăng nhập

// Timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');
?>