<?php
// File: includes/content/points.php
// Verificar que el usuario haya iniciado sesión
requireLogin();

// Obtener información del usuario
$user = getUserInfo($_SESSION['user_id']);

// Obtener historial de puntos
$pointHistory = getPointHistory($_SESSION['user_id'], 15); // Últimas 15 transacciones
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Điểm thưởng</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Left Sidebar - Puntos y redención de códigos -->
        <div>
            <!-- Resumen de puntos -->
            <div class="card p-6 rounded-xl mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold" style="color: var(--primary-color)">Số điểm hiện có</h2>
                </div>
                
                <div class="flex items-center justify-center mb-4">
                    <div class="relative">
                        <svg class="progress-ring" width="120" height="120">
                            <circle class="progress-ring__circle-bg" stroke="#e2e8f0" stroke-width="8" fill="transparent" r="50" cx="60" cy="60"/>
                            <circle class="progress-ring__circle" stroke="var(--primary-color)" stroke-width="8" fill="transparent" r="50" cx="60" cy="60"
                                stroke-dasharray="314" stroke-dashoffset="0" />
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center flex-col">
                            <span class="text-3xl font-bold points-display"><?php echo $user['points']; ?></span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">điểm</span>
                        </div>
                    </div>
                </div>
                
                <div class="text-sm text-gray-500 dark:text-gray-400 text-center mb-4">
                    Sử dụng điểm để đặt câu hỏi và sử dụng các tính năng cao cấp
                </div>
                
                <a href="index.php?page=redeem" class="btn btn-primary w-full justify-center">
                    <i class="fas fa-gift mr-2"></i>Nhập mã code
                </a>
            </div>
            
            <!-- Formas de obtener puntos -->
            <div class="card p-6 rounded-xl">
                <h2 class="text-lg font-semibold mb-4" style="color: var(--primary-color)">Cách nhận thêm điểm</h2>
                
                <ul class="space-y-3">
                    <li class="flex items-start">
                        <div class="flex-shrink-0 w-5 h-5 rounded-full bg-green-500 flex items-center justify-center text-white text-xs mt-0.5">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="font-medium">Đăng nhập hàng ngày</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Nhận 5 điểm mỗi ngày khi đăng nhập</p>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <div class="flex-shrink-0 w-5 h-5 rounded-full bg-green-500 flex items-center justify-center text-white text-xs mt-0.5">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="font-medium">Sử dụng mã code</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Nhập mã code từ các sự kiện, thông báo</p>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <div class="flex-shrink-0 w-5 h-5 rounded-full bg-green-500 flex items-center justify-center text-white text-xs mt-0.5">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="font-medium">Chia sẻ với bạn bè</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Nhận điểm khi bạn bè đăng ký từ link giới thiệu</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Right Content - Historial de puntos -->
        <div class="md:col-span-2">
            <div class="card rounded-xl overflow-hidden">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-xl font-semibold" style="color: var(--primary-color)">Lịch sử điểm</h2>
                </div>
                
                <?php if (empty($pointHistory)): ?>
                <div class="p-6 text-center">
                    <div class="text-gray-500 dark:text-gray-400 mb-2">
                        <i class="fas fa-history text-4xl"></i>
                    </div>
                    <p>Chưa có lịch sử điểm nào</p>
                </div>
                <?php else: ?>
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($pointHistory as $history): ?>
                    <div class="p-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-800">
                        <div class="flex items-center">
                            <?php
                            // Determinar icono y color según la acción
                            $icon = 'question';
                            $color = 'gray';
                            
                            switch ($history['action']) {
                                case 'registration':
                                    $icon = 'user-plus';
                                    $color = 'green';
                                    break;
                                case 'daily_bonus':
                                    $icon = 'calendar-check';
                                    $color = 'blue';
                                    break;
                                case 'redeem_code':
                                    $icon = 'gift';
                                    $color = 'purple';
                                    break;
                                case 'question':
                                    $icon = 'comment-dots';
                                    $color = 'red';
                                    break;
                            }
                            ?>
                            <div class="w-10 h-10 rounded-full bg-<?php echo $color; ?>-100 dark:bg-<?php echo $color; ?>-900/30 text-<?php echo $color; ?>-600 dark:text-<?php echo $color; ?>-400 flex items-center justify-center">
                                <i class="fas fa-<?php echo $icon; ?>"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="font-medium">
                                    <?php
                                    // Mostrar descripción según la acción
                                    switch ($history['action']) {
                                        case 'registration':
                                            echo 'Đăng ký tài khoản';
                                            break;
                                        case 'daily_bonus':
                                            echo 'Thưởng đăng nhập hàng ngày';
                                            break;
                                        case 'redeem_code':
                                            echo 'Nhập mã code';
                                            break;
                                        case 'question':
                                            echo 'Đặt câu hỏi';
                                            break;
                                        default:
                                            echo htmlspecialchars($history['action']);
                                    }
                                    ?>
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo date('d/m/Y H:i', strtotime($history['created_at'])); ?>
                                </p>
                                <?php if (!empty($history['details'])): ?>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    <?php echo htmlspecialchars($history['details']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-lg font-semibold <?php echo $history['points'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?>">
                            <?php echo ($history['points'] > 0 ? '+' : '') . $history['points']; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>