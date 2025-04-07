<?php
// File: includes/admin/index.php
require_once '../config.php';
require_once '../functions.php';

// Verificar que el usuario haya iniciado sesión y sea administrador
if (!isLoggedIn()) {
    header('Location: ../../index.php?page=login');
    exit;
}

$user = getUserInfo($_SESSION['user_id']);
if (!$user || $user['role'] !== 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Obtener estadísticas del sistema
$stats = [
    'users' => 0,
    'subjects' => 0,
    'topics' => 0,
    'materials' => 0,
    'assessments' => 0,
    'questions' => 0,
    'codes' => 0
];

// Contar usuarios
$result = $conn->query("SELECT COUNT(*) AS count FROM users");
if ($result) {
    $stats['users'] = $result->fetch_assoc()['count'];
}

// Contar materias
$result = $conn->query("SELECT COUNT(*) AS count FROM study_subjects");
if ($result) {
    $stats['subjects'] = $result->fetch_assoc()['count'];
}

// Contar temas
$result = $conn->query("SELECT COUNT(*) AS count FROM study_topics");
if ($result) {
    $stats['topics'] = $result->fetch_assoc()['count'];
}

// Contar materiales
$result = $conn->query("SELECT COUNT(*) AS count FROM study_materials");
if ($result) {
    $stats['materials'] = $result->fetch_assoc()['count'];
}

// Contar evaluaciones
$result = $conn->query("SELECT COUNT(*) AS count FROM self_assessments");
if ($result) {
    $stats['assessments'] = $result->fetch_assoc()['count'];
}

// Contar preguntas
$result = $conn->query("SELECT COUNT(*) AS count FROM assessment_questions");
if ($result) {
    $stats['questions'] = $result->fetch_assoc()['count'];
}

// Contar códigos
$result = $conn->query("SELECT COUNT(*) AS count FROM redeem_codes");
if ($result) {
    $stats['codes'] = $result->fetch_assoc()['count'];
}

// Obtener usuarios recientes
$recent_users = [];
$result = $conn->query("SELECT id, username, email, created_at, last_login FROM users ORDER BY created_at DESC LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_users[] = $row;
    }
}

// Obtener actividad reciente
$recent_activity = [];
$result = $conn->query("
    SELECT 'question' AS type, u.username, q.topic as title, q.created_at AS date
    FROM questions q
    JOIN users u ON q.user_id = u.id
    UNION ALL
    SELECT 'assessment' AS type, u.username, a.title, r.completed_at AS date
    FROM assessment_results r
    JOIN users u ON r.user_id = u.id
    JOIN self_assessments a ON r.assessment_id = a.id
    ORDER BY date DESC
    LIMIT 10
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_activity[] = $row;
    }
}

// Incluir el encabezado admin
$admin_page = 'dashboard';
include 'header.php';
?>

<div class="flex-1 p-6 overflow-auto">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold mb-6">Dashboard</h1>
        
        <!-- Estadísticas generales -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="card p-4 rounded-lg flex items-center">
                <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center mr-4">
                    <i class="fas fa-users text-xl"></i>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Người dùng</div>
                    <div class="text-2xl font-semibold"><?php echo number_format($stats['users']); ?></div>
                </div>
            </div>
            
            <div class="card p-4 rounded-lg flex items-center">
                <div class="w-12 h-12 rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 flex items-center justify-center mr-4">
                    <i class="fas fa-book text-xl"></i>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Môn học</div>
                    <div class="text-2xl font-semibold"><?php echo number_format($stats['subjects']); ?></div>
                </div>
            </div>
            
            <div class="card p-4 rounded-lg flex items-center">
                <div class="w-12 h-12 rounded-full bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400 flex items-center justify-center mr-4">
                    <i class="fas fa-clipboard-list text-xl"></i>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Chủ đề</div>
                    <div class="text-2xl font-semibold"><?php echo number_format($stats['topics']); ?></div>
                </div>
            </div>
            
            <div class="card p-4 rounded-lg flex items-center">
                <div class="w-12 h-12 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center mr-4">
                    <i class="fas fa-tasks text-xl"></i>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Bài kiểm tra</div>
                    <div class="text-2xl font-semibold"><?php echo number_format($stats['assessments']); ?></div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Usuarios recientes -->
            <div class="card rounded-lg p-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold">Người dùng mới</h2>
                    <a href="users.php" class="text-indigo-600 dark:text-indigo-400 text-sm">Xem tất cả</a>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tên đăng nhập</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ngày tạo</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($recent_users as $user): ?>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400 mr-3">
                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                        </div>
                                        <span class="font-medium"><?php echo htmlspecialchars($user['username']); ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="px-4 py-3 whitespace-nowrap"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php if ($user['last_login']): ?>
                                    <span class="inline-flex px-2 py-1 text-xs rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">Hoạt động</span>
                                    <?php else: ?>
                                    <span class="inline-flex px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400">Chưa đăng nhập</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($recent_users)): ?>
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">Không có người dùng nào</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Actividad reciente -->
            <div class="card rounded-lg p-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold">Hoạt động gần đây</h2>
                    <a href="stats.php" class="text-indigo-600 dark:text-indigo-400 text-sm">Xem tất cả</a>
                </div>
                
                <div class="space-y-4">
                    <?php foreach ($recent_activity as $activity): ?>
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full 
                            <?php echo $activity['type'] === 'question' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400' : 'bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400'; ?> 
                            flex items-center justify-center">
                            <i class="fas <?php echo $activity['type'] === 'question' ? 'fa-question' : 'fa-tasks'; ?>"></i>
                        </div>
                        <div class="ml-3">
                            <div class="flex items-center">
                                <span class="font-medium"><?php echo htmlspecialchars($activity['username']); ?></span>
                                <span class="mx-2 text-gray-500 dark:text-gray-400">•</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400"><?php echo date('d/m/Y H:i', strtotime($activity['date'])); ?></span>
                            </div>
                            <p class="text-sm mt-1">
                                <?php if ($activity['type'] === 'question'): ?>
                                Đã đặt câu hỏi về "<?php echo htmlspecialchars($activity['title']); ?>"
                                <?php else: ?>
                                Đã hoàn thành bài kiểm tra "<?php echo htmlspecialchars($activity['title']); ?>"
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($recent_activity)): ?>
                    <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                        Chưa có hoạt động nào
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Acciones rápidas -->
        <div class="card rounded-lg p-4 mb-6">
            <h2 class="text-lg font-semibold mb-4">Thao tác nhanh</h2>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <a href="subjects.php?action=add" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition flex flex-col items-center text-center">
                    <div class="w-12 h-12 rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 flex items-center justify-center mb-3">
                        <i class="fas fa-plus"></i>
                    </div>
                    <h3 class="font-medium mb-1">Thêm môn học</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tạo môn học mới</p>
                </a>
                
                <a href="topics.php?action=add" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition flex flex-col items-center text-center">
                    <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center mb-3">
                        <i class="fas fa-folder-plus"></i>
                    </div>
                    <h3 class="font-medium mb-1">Thêm chủ đề</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tạo chủ đề mới</p>
                </a>
                
                <a href="materials.php?action=add" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition flex flex-col items-center text-center">
                    <div class="w-12 h-12 rounded-full bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400 flex items-center justify-center mb-3">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3 class="font-medium mb-1">Thêm tài liệu</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tạo tài liệu học tập</p>
                </a>
                
                <a href="codes.php?action=add" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition flex flex-col items-center text-center">
                    <div class="w-12 h-12 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center mb-3">
                        <i class="fas fa-gift"></i>
                    </div>
                    <h3 class="font-medium mb-1">Tạo mã code</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tạo mã nhận điểm</p>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir el pie de página admin
include 'footer.php';
?>