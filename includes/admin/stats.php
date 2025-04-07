<?php
// File: includes/admin/stats.php
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

// Datos de estadísticas generales
$stats = [];

// Usuarios
$result = $conn->query("SELECT COUNT(*) as total FROM users");
$stats['users'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
$stats['admin_users'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE last_login IS NOT NULL");
$stats['active_users'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE DATE(created_at) = CURDATE()");
$stats['new_users_today'] = $result->fetch_assoc()['total'];

// Contenido
$result = $conn->query("SELECT COUNT(*) as total FROM study_subjects");
$stats['subjects'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM study_topics");
$stats['topics'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM study_materials");
$stats['materials'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM self_assessments");
$stats['assessments'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM assessment_questions");
$stats['questions'] = $result->fetch_assoc()['total'];

// Interacciones
$result = $conn->query("SELECT COUNT(*) as total FROM questions");
$stats['user_questions'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM assessment_results");
$stats['assessment_attempts'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM redeem_codes");
$stats['redeem_codes'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM code_redemptions");
$stats['codes_used'] = $result->fetch_assoc()['total'];

// Estadística de actividad reciente
$recent_activity = [];
$result = $conn->query("
    (SELECT 'question' AS type, q.created_at AS date, u.username, q.topic AS content
    FROM questions q
    JOIN users u ON q.user_id = u.id
    ORDER BY q.created_at DESC
    LIMIT 10)
    
    UNION ALL
    
    (SELECT 'assessment' AS type, r.completed_at AS date, u.username, a.title AS content
    FROM assessment_results r
    JOIN users u ON r.user_id = u.id
    JOIN self_assessments a ON r.assessment_id = a.id
    ORDER BY r.completed_at DESC
    LIMIT 10)
    
    UNION ALL
    
    (SELECT 'redeem' AS type, c.redeemed_at AS date, u.username, 
            CONCAT(rc.code, ' (', c.points_awarded, ' điểm)') AS content
    FROM code_redemptions c
    JOIN users u ON c.user_id = u.id
    JOIN redeem_codes rc ON c.code_id = rc.id
    ORDER BY c.redeemed_at DESC
    LIMIT 10)
    
    ORDER BY date DESC
    LIMIT 20
");

while ($row = $result->fetch_assoc()) {
    $recent_activity[] = $row;
}

// Estadísticas de materias más populares
$popular_subjects = [];
$result = $conn->query("
    SELECT s.name, COUNT(t.id) as topic_count, 
           SUM((SELECT COUNT(*) FROM study_materials WHERE topic_id = t.id)) as material_count,
           SUM((SELECT COUNT(*) FROM assessment_results r JOIN self_assessments a ON r.assessment_id = a.id WHERE a.topic_id = t.id)) as assessment_count
    FROM study_subjects s
    LEFT JOIN study_topics t ON s.id = t.subject_id
    GROUP BY s.id
    ORDER BY assessment_count DESC, material_count DESC
    LIMIT 5
");

while ($row = $result->fetch_assoc()) {
    $popular_subjects[] = $row;
}

// Estadísticas de usuarios más activos
$active_users = [];
$result = $conn->query("
    SELECT u.username, u.points, 
           (SELECT COUNT(*) FROM questions WHERE user_id = u.id) as question_count,
           (SELECT COUNT(*) FROM assessment_results WHERE user_id = u.id) as assessment_count,
           (SELECT COUNT(*) FROM code_redemptions WHERE user_id = u.id) as redeem_count
    FROM users u
    ORDER BY (question_count + assessment_count) DESC, points DESC
    LIMIT 10
");

while ($row = $result->fetch_assoc()) {
    $active_users[] = $row;
}

// Datos para el gráfico de actividad por día
$activity_dates = [];
$result = $conn->query("
    SELECT dates.date, 
           IFNULL(user_counts.user_count, 0) as user_count,
           IFNULL(question_counts.question_count, 0) as question_count,
           IFNULL(assessment_counts.assessment_count, 0) as assessment_count
    FROM (
        SELECT DATE(CURDATE() - INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY) as date
        FROM (SELECT 0 as a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) as a
        CROSS JOIN (SELECT 0 as a UNION ALL SELECT 1 UNION ALL SELECT 2) as b
        CROSS JOIN (SELECT 0 as a) as c
        WHERE DATE(CURDATE() - INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY) > DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY date DESC
    ) as dates
    LEFT JOIN (
        SELECT DATE(created_at) as date, COUNT(*) as user_count 
        FROM users 
        GROUP BY DATE(created_at)
    ) as user_counts ON dates.date = user_counts.date
    LEFT JOIN (
        SELECT DATE(created_at) as date, COUNT(*) as question_count 
        FROM questions 
        GROUP BY DATE(created_at)
    ) as question_counts ON dates.date = question_counts.date
    LEFT JOIN (
        SELECT DATE(completed_at) as date, COUNT(*) as assessment_count 
        FROM assessment_results 
        GROUP BY DATE(completed_at)
    ) as assessment_counts ON dates.date = assessment_counts.date
    ORDER BY dates.date ASC
");

while ($row = $result->fetch_assoc()) {
    $activity_dates[] = $row;
}

// Incluir el encabezado admin
$admin_page = 'stats';
include 'header.php';
?>

<div class="flex-1 p-6 overflow-auto">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold mb-6">Thống kê hệ thống</h1>
        
        <!-- Overview Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="card rounded-lg p-4 flex items-center">
                <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center mr-4">
                    <i class="fas fa-users text-xl"></i>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Người dùng</div>
                    <div class="text-2xl font-semibold"><?php echo number_format($stats['users']); ?></div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        <span class="text-green-500">+<?php echo $stats['new_users_today']; ?></span> hôm nay
                    </div>
                </div>
            </div>
            
            <div class="card rounded-lg p-4 flex items-center">
                <div class="w-12 h-12 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mr-4">
                    <i class="fas fa-book-open text-xl"></i>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Tài liệu</div>
                    <div class="text-2xl font-semibold"><?php echo number_format($stats['materials']); ?></div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Trong <?php echo $stats['topics']; ?> chủ đề
                    </div>
                </div>
            </div>
            
            <div class="card rounded-lg p-4 flex items-center">
                <div class="w-12 h-12 rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 flex items-center justify-center mr-4">
                    <i class="fas fa-tasks text-xl"></i>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Bài kiểm tra</div>
                    <div class="text-2xl font-semibold"><?php echo number_format($stats['assessments']); ?></div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        <?php echo $stats['questions']; ?> câu hỏi
                    </div>
                </div>
            </div>
            
            <div class="card rounded-lg p-4 flex items-center">
                <div class="w-12 h-12 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center mr-4">
                    <i class="fas fa-clipboard-check text-xl"></i>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Tương tác</div>
                    <div class="text-2xl font-semibold"><?php echo number_format($stats['user_questions'] + $stats['assessment_attempts']); ?></div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        <?php echo $stats['assessment_attempts']; ?> lượt làm bài
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Activity Chart -->
        <div class="card rounded-lg p-6 mb-8">
            <h2 class="text-lg font-semibold mb-4">Hoạt động 30 ngày qua</h2>
            <div class="w-full h-64">
                <canvas id="activityChart"></canvas>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Popular Subjects -->
            <div class="card rounded-lg p-6">
                <h2 class="text-lg font-semibold mb-4">Môn học phổ biến</h2>
                
                <?php if (empty($popular_subjects)): ?>
                <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                    Chưa có dữ liệu
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Môn học</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Chủ đề</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Tài liệu</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Lượt làm bài</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($popular_subjects as $subject): ?>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="font-medium"><?php echo htmlspecialchars($subject['name']); ?></div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <?php echo number_format($subject['topic_count']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <?php echo number_format($subject['material_count']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <?php echo number_format($subject['assessment_count']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Most Active Users -->
            <div class="card rounded-lg p-6">
                <h2 class="text-lg font-semibold mb-4">Người dùng tích cực nhất</h2>
                
                <?php if (empty($active_users)): ?>
                <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                    Chưa có dữ liệu
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Người dùng</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Điểm</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Câu hỏi</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center">Làm bài</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($active_users as $index => $user): ?>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400 mr-3">
                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                        </div>
                                        <div class="font-medium"><?php echo htmlspecialchars($user['username']); ?></div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <?php echo number_format($user['points']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <?php echo number_format($user['question_count']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <?php echo number_format($user['assessment_count']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">Hoạt động gần đây</h2>
            
            <?php if (empty($recent_activity)): ?>
            <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                Chưa có hoạt động nào
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Thời gian</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Người dùng</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Hoạt động</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nội dung</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($recent_activity as $activity): ?>
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo date('d/m/Y H:i', strtotime($activity['date'])); ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="font-medium"><?php echo htmlspecialchars($activity['username']); ?></div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?php 
                                    switch($activity['type']) {
                                        case 'question': echo 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'; break;
                                        case 'assessment': echo 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'; break;
                                        case 'redeem': echo 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400'; break;
                                    }
                                    ?>">
                                    <?php 
                                    switch($activity['type']) {
                                        case 'question': echo 'Đặt câu hỏi'; break;
                                        case 'assessment': echo 'Làm bài kiểm tra'; break;
                                        case 'redeem': echo 'Nhập mã code'; break;
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm"><?php echo htmlspecialchars($activity['content']); ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Charts.js for visualization -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Prepare chart data
    const activityLabels = <?php echo json_encode(array_map(function($item) { 
        return date('d/m', strtotime($item['date'])); 
    }, $activity_dates)); ?>;
    
    const userCounts = <?php echo json_encode(array_map(function($item) { 
        return (int)$item['user_count']; 
    }, $activity_dates)); ?>;
    
    const questionCounts = <?php echo json_encode(array_map(function($item) { 
        return (int)$item['question_count']; 
    }, $activity_dates)); ?>;
    
    const assessmentCounts = <?php echo json_encode(array_map(function($item) { 
        return (int)$item['assessment_count']; 
    }, $activity_dates)); ?>;
    
    // Create activity chart
    const activityCtx = document.getElementById('activityChart').getContext('2d');
    const activityChart = new Chart(activityCtx, {
        type: 'line',
        data: {
            labels: activityLabels,
            datasets: [
                {
                    label: 'Người dùng mới',
                    data: userCounts,
                    borderColor: 'rgb(99, 102, 241)',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Câu hỏi',
                    data: questionCounts,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Làm bài kiểm tra',
                    data: assessmentCounts,
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.3,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            interaction: {
                mode: 'index',
                intersect: false
            }
        }
    });
    
    // Handle dark mode changes for the chart
    function updateChartTheme(isDark) {
        const textColor = isDark ? '#9ca3af' : '#6b7280';
        const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
        
        activityChart.options.scales.x.grid.color = gridColor;
        activityChart.options.scales.y.grid.color = gridColor;
        activityChart.options.scales.x.ticks.color = textColor;
        activityChart.options.scales.y.ticks.color = textColor;
        
        activityChart.update();
    }
    
    // Update chart theme on load
    updateChartTheme(document.documentElement.classList.contains('dark'));
    
    // Update chart theme when dark mode changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => {
        updateChartTheme(event.matches);
    });
</script>

<?php
// Incluir el pie de página admin
include 'footer.php';
?>