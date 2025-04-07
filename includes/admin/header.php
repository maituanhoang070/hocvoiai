<?php
// File: includes/admin/header.php
// Este archivo debe ser incluido en todas las páginas de administración
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #5D5CDE;
            --primary-hover: #4B4ABF;
            --primary-light: #E8E8FF;
            --primary-dark: #3F3E9D;
            --success-color: #10B981;
            --warning-color: #F59E0B;
            --danger-color: #EF4444;
            --info-color: #3B82F6;
            
            --light-bg: #FFFFFF;
            --light-bg-secondary: #F9FAFB;
            --dark-bg: #121212;
            --dark-bg-secondary: #1E1E1E;
            
            --light-text: #333333;
            --light-text-secondary: #6B7280;
            --dark-text: #EEEEEE;
            --dark-text-secondary: #9CA3AF;
            
            --light-card: #F5F7FA;
            --dark-card: #262626;
            
            --light-border: #E5E7EB;
            --dark-border: #3F3F46;
            
            --light-hover: #E8E8FF;
            --dark-hover: #3A3A3A;
            
            --light-input: #F9FAFB;
            --dark-input: #1F1F1F;
        }

        .dark {
            --bg-color: var(--dark-bg);
            --bg-color-secondary: var(--dark-bg-secondary);
            --text-color: var(--dark-text);
            --text-secondary: var(--dark-text-secondary);
            --card-bg: var(--dark-card);
            --hover-color: var(--dark-hover);
            --border-color: var(--dark-border);
            --input-bg: var(--dark-input);
        }

        :root:not(.dark) {
            --bg-color: var(--light-bg);
            --bg-color-secondary: var(--light-bg-secondary);
            --text-color: var(--light-text);
            --text-secondary: var(--light-text-secondary);
            --card-bg: var(--light-card);
            --hover-color: var(--light-hover);
            --border-color: var(--light-border);
            --input-bg: var(--light-input);
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        }

        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .sidebar {
            background-color: var(--bg-color-secondary);
            border-right: 1px solid var(--border-color);
        }

        .nav-item {
            position: relative;
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .nav-item:hover {
            background-color: var(--hover-color);
        }

        .nav-item.active {
            background-color: var(--primary-light);
            color: var(--primary-color);
        }

        .dark .nav-item.active {
            background-color: var(--primary-dark);
            color: var(--dark-text);
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            min-width: 12rem;
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            z-index: 50;
        }

        .custom-dropdown:hover .dropdown-content {
            display: block;
        }

        input[type="text"], input[type="password"], input[type="email"], textarea, select {
            background-color: var(--input-bg);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            transition: all 0.2s ease;
            border-radius: 0.5rem;
            padding: 0.75rem;
            font-size: 1rem;
            width: 100%;
        }

        input[type="text"]:focus, input[type="password"]:focus, input[type="email"]:focus, textarea:focus, select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(93, 92, 222, 0.25);
            outline: none;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .btn-secondary {
            background-color: var(--bg-color-secondary);
            color: var(--text-color);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background-color: var(--hover-color);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #DC2626;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.05);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(0,0,0,0.2);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(0,0,0,0.3);
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="sidebar w-64 flex-shrink-0 hidden md:block overflow-y-auto">
            <div class="p-4">
                <div class="flex items-center mb-8">
                    <h1 class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                        <i class="fas fa-cogs mr-2"></i>Admin
                    </h1>
                </div>
                
                <nav class="space-y-1">
                    <a href="index.php" class="nav-item <?php echo $admin_page === 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt w-6"></i>
                        <span class="ml-3">Dashboard</span>
                    </a>
                    
                    <a href="users.php" class="nav-item <?php echo $admin_page === 'users' ? 'active' : ''; ?>">
                        <i class="fas fa-users w-6"></i>
                        <span class="ml-3">Người dùng</span>
                    </a>
                    
                    <a href="subjects.php" class="nav-item <?php echo $admin_page === 'subjects' ? 'active' : ''; ?>">
                        <i class="fas fa-book w-6"></i>
                        <span class="ml-3">Môn học</span>
                    </a>
                    
                    <a href="topics.php" class="nav-item <?php echo $admin_page === 'topics' ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-list w-6"></i>
                        <span class="ml-3">Chủ đề</span>
                    </a>
                    
                    <a href="materials.php" class="nav-item <?php echo $admin_page === 'materials' ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt w-6"></i>
                        <span class="ml-3">Tài liệu</span>
                    </a>
                    
                    <a href="assessments.php" class="nav-item <?php echo $admin_page === 'assessments' ? 'active' : ''; ?>">
                        <i class="fas fa-tasks w-6"></i>
                        <span class="ml-3">Bài kiểm tra</span>
                    </a>
                    
                    <a href="questions.php" class="nav-item <?php echo $admin_page === 'questions' ? 'active' : ''; ?>">
                        <i class="fas fa-question-circle w-6"></i>
                        <span class="ml-3">Câu hỏi</span>
                    </a>
                    
                    <a href="codes.php" class="nav-item <?php echo $admin_page === 'codes' ? 'active' : ''; ?>">
                        <i class="fas fa-gift w-6"></i>
                        <span class="ml-3">Mã code</span>
                    </a>
                    
                    <a href="stats.php" class="nav-item <?php echo $admin_page === 'stats' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar w-6"></i>
                        <span class="ml-3">Thống kê</span>
                    </a>
                    
                    <a href="settings.php" class="nav-item <?php echo $admin_page === 'settings' ? 'active' : ''; ?>">
                        <i class="fas fa-cog w-6"></i>
                        <span class="ml-3">Cài đặt</span>
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-dark dark:bg-gray-800 shadow-sm">
                <div class="flex items-center justify-between p-4">
                    <div class="flex items-center md:hidden">
                        <button id="mobile-menu-button" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
                            <i class="fas fa-bars"></i>
                        </button>
                    </div>
                    
                    <div class="flex items-center ml-auto">
                        <a href="../../index.php" class="mr-4 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300" target="_blank">
                            <i class="fas fa-external-link-alt"></i>
                            <span class="ml-1 hidden sm:inline-block">Xem trang chủ</span>
                        </a>
                        
                        
            </header>
            
            <!-- Mobile menu (oculto por defecto) -->
            <div id="mobile-menu" class="md:hidden bg-dark dark:bg-gray-800 shadow-lg fixed inset-0 z-50 transform -translate-x-full transition-transform duration-300 ease-in-out">
                <div class="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-xl font-bold text-indigo-600 dark:text-indigo-400">
                        <i class="fas fa-cogs mr-2"></i>Admin
                    </h2>
                    <button id="close-mobile-menu" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <nav class="p-4">
                    <div class="space-y-2">
                        <a href="index.php" class="nav-item <?php echo $admin_page === 'dashboard' ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt w-6"></i>
                            <span class="ml-3">Dashboard</span>
                        </a>
                        
                        <a href="users.php" class="nav-item <?php echo $admin_page === 'users' ? 'active' : ''; ?>">
                            <i class="fas fa-users w-6"></i>
                            <span class="ml-3">Người dùng</span>
                        </a>
                        
                        <a href="subjects.php" class="nav-item <?php echo $admin_page === 'subjects' ? 'active' : ''; ?>">
                            <i class="fas fa-book w-6"></i>
                            <span class="ml-3">Môn học</span>
                        </a>
                        
                        <a href="topics.php" class="nav-item <?php echo $admin_page === 'topics' ? 'active' : ''; ?>">
                            <i class="fas fa-clipboard-list w-6"></i>
                            <span class="ml-3">Chủ đề</span>
                        </a>
                        
                        <a href="materials.php" class="nav-item <?php echo $admin_page === 'materials' ? 'active' : ''; ?>">
                            <i class="fas fa-file-alt w-6"></i>
                            <span class="ml-3">Tài liệu</span>
                        </a>
                        
                        <a href="assessments.php" class="nav-item <?php echo $admin_page === 'assessments' ? 'active' : ''; ?>">
                            <i class="fas fa-tasks w-6"></i>
                            <span class="ml-3">Bài kiểm tra</span>
                        </a>
                        
                        <a href="questions.php" class="nav-item <?php echo $admin_page === 'questions' ? 'active' : ''; ?>">
                            <i class="fas fa-question-circle w-6"></i>
                            <span class="ml-3">Câu hỏi</span>
                        </a>
                        
                        <a href="codes.php" class="nav-item <?php echo $admin_page === 'codes' ? 'active' : ''; ?>">
                            <i class="fas fa-gift w-6"></i>
                            <span class="ml-3">Mã code</span>
                        </a>
                        
                        <a href="stats.php" class="nav-item <?php echo $admin_page === 'stats' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar w-6"></i>
                            <span class="ml-3">Thống kê</span>
                        </a>
                        
                        <a href="settings.php" class="nav-item <?php echo $admin_page === 'settings' ? 'active' : ''; ?>">
                            <i class="fas fa-cog w-6"></i>
                            <span class="ml-3">Cài đặt</span>
                        </a>
                    </div>
                </nav>
            </div>
            <!-- Main content -->

            