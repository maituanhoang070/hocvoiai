<?php
// File: index.php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Xác định trang hiện tại
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Lấy thông tin người dùng nếu đã đăng nhập
$user = null;
if (isLoggedIn()) {
    $user = getUserInfo($_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
</head>
<body class="min-h-screen bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
    <!-- Header Navigation -->
    <header class="app-header py-3 px-4 bg-white dark:bg-gray-800 shadow-sm">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center">
                <h1 class="text-xl sm:text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                    <a href="index.php"><i class="fas fa-book-open mr-2"></i>HVAI</a>
                </h1>
                <nav class="hidden md:flex ml-8">
                    <a href="index.php" class="mx-2 py-1 px-3 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition <?php echo $page == 'home' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 font-medium' : ''; ?>">Trang chủ</a>
                    <a href="index.php?page=study" class="mx-2 py-1 px-3 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition <?php echo $page == 'study' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 font-medium' : ''; ?>">Ôn Tập</a>
                    <a href="index.php?page=explore" class="mx-2 py-1 px-3 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition <?php echo $page == 'explore' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 font-medium' : ''; ?>">Khám phá</a>
                    <a href="index.php?page=points" class="mx-2 py-1 px-3 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition <?php echo $page == 'points' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 font-medium' : ''; ?>">Điểm thưởng</a>
                </nav>
            </div>
            
            <div class="flex items-center">
                <?php if (isLoggedIn() && $user): ?>
                <!-- Đã đăng nhập - hiển thị thông tin người dùng -->
                <div class="mr-4 flex items-center bg-gray-100 dark:bg-gray-700 rounded-full px-3 py-1">
                    <i class="fas fa-coins text-yellow-500 mr-2"></i>
                    <span class="font-medium"><?php echo $user['points']; ?></span>
                </div>
                
                <div class="relative group user-dropdown">
                    <button class="flex items-center focus:outline-none dropdown-toggle">
                        <div class="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center text-white font-medium">
                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                        </div>
                        <span class="ml-2 hidden sm:block"><?php echo $user['username']; ?></span>
                        <i class="fas fa-chevron-down ml-1 text-xs"></i>
                    </button>
                    
                    <div class="dropdown-menu absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-md overflow-hidden shadow-lg z-10 hidden">
                        <a href="index.php?page=profile" class="block px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                            <i class="fas fa-user mr-2"></i>Hồ sơ
                        </a>
                        <!-- Hiển thị nút Vào trang Admin nếu role là admin -->
                        <?php if ($user['role'] === 'admin'): ?>
                        <a href="includes/admin/index.php" class="block px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 text-green-600 dark:text-green-400">
                            <i class="fas fa-cog mr-2"></i>Vào trang Admin
                        </a>
                        <?php endif; ?>
                        <a href="index.php?page=history" class="block px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                            <i class="fas fa-history mr-2"></i>Lịch sử
                        </a>
                        <a href="index.php?page=redeem" class="block px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                            <i class="fas fa-gift mr-2"></i>Nhập mã
                        </a>
                        <div class="border-t border-gray-200 dark:border-gray-700"></div>
                        <a href="includes/auth/logout.php" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                            <i class="fas fa-sign-out-alt mr-2"></i>Đăng xuất
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <!-- Chưa đăng nhập - hiển thị nút đăng nhập/đăng ký -->
                <a href="index.php?page=login" class="py-2 px-4 bg-gray-200 dark:bg-gray-700 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition mr-2">Đăng nhập</a>
                <a href="index.php?page=register" class="py-2 px-4 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">Đăng ký</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Mobile Navigation (hiển thị ở dưới footer) -->
    <div class="block md:hidden fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-800 shadow-lg z-50">
        <div class="flex justify-around">
            <a href="index.php" class="flex flex-col items-center py-2 <?php echo $page == 'home' ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400'; ?>">
                <i class="fas fa-home text-lg"></i>
                <span class="text-xs mt-1">Trang chủ</span>
            </a>
            <a href="index.php?page=study" class="flex flex-col items-center py-2 <?php echo $page == 'study' ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400'; ?>">
                <i class="fa-solid fa-book-open"></i>
                <span class="text-xs mt-1">Ôn Tập</span>
            </a>
            <a href="index.php?page=explore" class="flex flex-col items-center py-2 <?php echo $page == 'explore' ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400'; ?>">
                <i class="fas fa-compass text-lg"></i>
                <span class="text-xs mt-1">Khám phá</span>
            </a>
            <a href="index.php?page=points" class="flex flex-col items-center py-2 <?php echo $page == 'points' ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400'; ?>">
                <i class="fas fa-coins text-lg"></i>
                <span class="text-xs mt-1">Điểm</span>
            </a>
            <a href="index.php?page=profile" class="flex flex-col items-center py-2 <?php echo $page == 'profile' ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400'; ?>">
                <i class="fas fa-user text-lg"></i>
                <span class="text-xs mt-1">Hồ sơ</span>
            </a>
        </div>
    </div>

    
    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6 mb-16 md:mb-0">
        <?php
        // Nạp file content tương ứng
        $content_file = 'includes/content/' . $page . '.php';
        if (file_exists($content_file)) {
            include $content_file;
        } else {
            if ($page == 'login' || $page == 'register' || $page == 'reset_password') {
                include 'includes/content/auth.php';
            } else {
                // Trang không tồn tại
                include 'includes/content/404.php';
            }
        }
        ?>
    </main>

    <!-- Script -->
    <script src="assets/js/main.js"></script>
    <?php if ($page == 'login' || $page == 'register' || $page == 'reset_password'): ?>
    <script src="assets/js/auth.js"></script>
    <?php endif; ?>
    <?php if ($page == 'home'): ?>
    <script src="assets/js/home.js"></script>
    <?php endif; ?>
</body>
</html>