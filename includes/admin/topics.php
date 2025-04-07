<?php
// File: includes/admin/topics.php
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

// Procesar acciones
$action = isset($_GET['action']) ? $_GET['action'] : '';
$topic_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$message = '';
$error = '';

// Datos iniciales para editar/añadir
$topic = [
    'subject_id' => $subject_id,
    'name' => '',
    'description' => '',
    'difficulty' => 'intermediate'
];

// Manejar acciones CRUD
if ($action === 'delete' && $topic_id) {
    // Verificar si hay contenidos asociados
    $stmt = $conn->prepare("SELECT 
        (SELECT COUNT(*) FROM study_materials WHERE topic_id = ?) +
        (SELECT COUNT(*) FROM practice_exercises WHERE topic_id = ?) +
        (SELECT COUNT(*) FROM self_assessments WHERE topic_id = ?) +
        (SELECT COUNT(*) FROM study_flashcards WHERE topic_id = ?) as total");
    $stmt->bind_param("iiii", $topic_id, $topic_id, $topic_id, $topic_id);
    $stmt->execute();
    $content_count = $stmt->get_result()->fetch_assoc()['total'];
    
    if ($content_count > 0) {
        $error = "Không thể xóa chủ đề này vì có $content_count nội dung liên quan!";
    } else {
        $stmt = $conn->prepare("DELETE FROM study_topics WHERE id = ?");
        $stmt->bind_param("i", $topic_id);
        
        if ($stmt->execute()) {
            $message = 'Đã xóa chủ đề thành công!';
        } else {
            $error = 'Lỗi: ' . $conn->error;
        }
    }
} else if (($action === 'edit' && $topic_id) || $action === 'add') {
    // Cargar datos para editar
    if ($action === 'edit' && $topic_id) {
        $stmt = $conn->prepare("SELECT * FROM study_topics WHERE id = ?");
        $stmt->bind_param("i", $topic_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $topic = $row;
            $subject_id = $topic['subject_id'];
        } else {
            $error = 'Không tìm thấy chủ đề!';
        }
    }
    
    // Procesar formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $topic['subject_id'] = intval($_POST['subject_id'] ?? 0);
        $topic['name'] = trim($_POST['name'] ?? '');
        $topic['description'] = trim($_POST['description'] ?? '');
        $topic['difficulty'] = $_POST['difficulty'] ?? 'intermediate';
        
        if (empty($topic['name'])) {
            $error = 'Tên chủ đề không được để trống!';
        } else if (empty($topic['subject_id'])) {
            $error = 'Vui lòng chọn môn học!';
        } else {
            // Verificar que no exista otro con el mismo nombre en el mismo subject
            $stmt = $conn->prepare("SELECT id FROM study_topics WHERE name = ? AND subject_id = ? AND id != ?");
            $stmt->bind_param("sii", $topic['name'], $topic['subject_id'], $topic_id);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            
            if ($exists) {
                $error = 'Chủ đề với tên này đã tồn tại trong môn học!';
            } else {
                if ($action === 'edit') {
                    $stmt = $conn->prepare("UPDATE study_topics SET subject_id = ?, name = ?, description = ?, difficulty = ? WHERE id = ?");
                    $stmt->bind_param("isssi", $topic['subject_id'], $topic['name'], $topic['description'], $topic['difficulty'], $topic_id);
                } else {
                    $stmt = $conn->prepare("INSERT INTO study_topics (subject_id, name, description, difficulty, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->bind_param("isss", $topic['subject_id'], $topic['name'], $topic['description'], $topic['difficulty']);
                }
                
                if ($stmt->execute()) {
                    $message = $action === 'edit' ? 'Đã cập nhật chủ đề thành công!' : 'Đã thêm chủ đề thành công!';
                    
                    if ($action === 'add') {
                        $topic_id = $conn->insert_id;
                    }
                    
                    // Redirigir después de procesar
                    header('Location: topics.php?subject_id=' . $topic['subject_id'] . '&message=' . urlencode($message));
                    exit;
                } else {
                    $error = 'Lỗi: ' . $conn->error;
                }
            }
        }
    }
}

// Obtener mensaje de la URL
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

// Paginación y filtrado
$page = isset($_GET['page_num']) ? intval($_GET['page_num']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$difficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';

// Obtener todas las materias para el formulario
$subjects = [];
$stmt = $conn->query("SELECT id, name FROM study_subjects ORDER BY name");
while ($row = $stmt->fetch_assoc()) {
    $subjects[] = $row;
}

// Construir la consulta
$query = "SELECT t.*, s.name as subject_name, 
           (SELECT COUNT(*) FROM study_materials WHERE topic_id = t.id) as material_count,
           (SELECT COUNT(*) FROM practice_exercises WHERE topic_id = t.id) as exercise_count,
           (SELECT COUNT(*) FROM self_assessments WHERE topic_id = t.id) as assessment_count
           FROM study_topics t 
           LEFT JOIN study_subjects s ON t.subject_id = s.id 
           WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM study_topics t WHERE 1=1";

$query_params = [];
$param_types = "";

if (!empty($search)) {
    $query .= " AND (t.name LIKE ? OR t.description LIKE ?)";
    $count_query .= " AND (name LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $query_params[] = $search_param;
    $query_params[] = $search_param;
    $param_types .= "ss";
}

if (!empty($difficulty)) {
    $query .= " AND t.difficulty = ?";
    $count_query .= " AND difficulty = ?";
    $query_params[] = $difficulty;
    $param_types .= "s";
}

if ($subject_id) {
    $query .= " AND t.subject_id = ?";
    $count_query .= " AND subject_id = ?";
    $query_params[] = $subject_id;
    $param_types .= "i";
    
    // Obtener información de la materia
    $stmt = $conn->prepare("SELECT name FROM study_subjects WHERE id = ?");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $current_subject = $stmt->get_result()->fetch_assoc();
}

// Ejecutar consulta de conteo
$stmt = $conn->prepare($count_query);
if (!empty($param_types)) {
    $stmt->bind_param($param_types, ...$query_params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];

$total_pages = ceil($total_records / $limit);

// Añadir límites para paginación
$query .= " ORDER BY t.name LIMIT ?, ?";
$query_params[] = $offset;
$query_params[] = $limit;
$param_types .= "ii";

// Ejecutar consulta principal
$stmt = $conn->prepare($query);
if (!empty($param_types)) {
    $stmt->bind_param($param_types, ...$query_params);
}
$stmt->execute();
$topics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Incluir el encabezado admin
$admin_page = 'topics';
include 'header.php';
?>

<div class="flex-1 p-6 overflow-auto">
    <div class="max-w-7xl mx-auto">
        <?php if ($action === 'edit' || $action === 'add'): ?>
        <!-- Formulario de edición/adición -->
        <div class="flex items-center mb-6">
            <a href="topics.php<?php echo $subject_id ? '?subject_id=' . $subject_id : ''; ?>" class="mr-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-2xl font-bold"><?php echo $action === 'edit' ? 'Chỉnh sửa chủ đề' : 'Thêm chủ đề mới'; ?></h1>
        </div>
        
        <?php if (!empty($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="ml-3">
                    <p><?php echo $error; ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card rounded-lg p-6 mb-6">
            <form method="post" action="topics.php?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $topic_id : ''; ?>">
                <div class="mb-4">
                    <label for="subject_id" class="block text-sm font-medium mb-1">Môn học <span class="text-red-500">*</span></label>
                    <select id="subject_id" name="subject_id" class="w-full" required>
                        <option value="">-- Chọn môn học --</option>
                        <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>" <?php echo $topic['subject_id'] == $subject['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium mb-1">Tên chủ đề <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($topic['name']); ?>" class="w-full" required>
                </div>
                
                <div class="mb-4">
                    <label for="difficulty" class="block text-sm font-medium mb-1">Độ khó</label>
                    <select id="difficulty" name="difficulty" class="w-full">
                        <option value="beginner" <?php echo $topic['difficulty'] === 'beginner' ? 'selected' : ''; ?>>Cơ bản</option>
                        <option value="intermediate" <?php echo $topic['difficulty'] === 'intermediate' ? 'selected' : ''; ?>>Trung bình</option>
                        <option value="advanced" <?php echo $topic['difficulty'] === 'advanced' ? 'selected' : ''; ?>>Nâng cao</option>
                    </select>
                </div>
                
                <div class="mb-6">
                    <label for="description" class="block text-sm font-medium mb-1">Mô tả</label>
                    <textarea id="description" name="description" rows="4" class="w-full"><?php echo htmlspecialchars($topic['description']); ?></textarea>
                </div>
                
                <div class="flex justify-end">
                    <a href="topics.php<?php echo $subject_id ? '?subject_id=' . $subject_id : ''; ?>" class="btn btn-secondary mr-3">
                        Hủy
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $action === 'edit' ? 'Cập nhật' : 'Thêm mới'; ?>
                    </button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <!-- Lista de temas -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <?php if ($subject_id && isset($current_subject)): ?>
            <div>
                <div class="flex items-center mb-2">
                    <a href="topics.php" class="mr-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h1 class="text-2xl font-bold">Chủ đề: <?php echo htmlspecialchars($current_subject['name']); ?></h1>
                </div>
                <p class="text-gray-600 dark:text-gray-400">Quản lý các chủ đề trong môn học này</p>
            </div>
            <?php else: ?>
            <div>
                <h1 class="text-2xl font-bold mb-2">Quản lý chủ đề</h1>
                <p class="text-gray-600 dark:text-gray-400">Quản lý tất cả các chủ đề học tập</p>
            </div>
            <?php endif; ?>
            
            <div class="mt-4 md:mt-0">
                <a href="topics.php?action=add<?php echo $subject_id ? '&subject_id=' . $subject_id : ''; ?>" class="btn btn-primary">
                    <i class="fas fa-plus mr-2"></i>Thêm chủ đề
                </a>
            </div>
        </div>
        
        <?php if (!empty($message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="ml-3">
                    <p><?php echo $message; ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="ml-3">
                    <p><?php echo $error; ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="card rounded-lg p-4 mb-6">
            <form action="topics.php" method="get" class="flex flex-col md:flex-row gap-4">
                <?php if ($subject_id): ?>
                <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                <?php else: ?>
                <div class="w-full md:w-64">
                    <label for="subject_id" class="block text-sm font-medium mb-1">Môn học</label>
                    <select id="subject_id" name="subject_id" class="w-full">
                        <option value="">Tất cả môn học</option>
                        <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>" <?php echo $subject_id == $subject['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="flex-1">
                    <label for="search" class="block text-sm font-medium mb-1">Tìm kiếm</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tên chủ đề, mô tả..." class="w-full">
                </div>
                
                <div class="w-full md:w-48">
                    <label for="difficulty" class="block text-sm font-medium mb-1">Độ khó</label>
                    <select id="difficulty" name="difficulty" class="w-full">
                        <option value="">Tất cả</option>
                        <option value="beginner" <?php echo $difficulty === 'beginner' ? 'selected' : ''; ?>>Cơ bản</option>
                        <option value="intermediate" <?php echo $difficulty === 'intermediate' ? 'selected' : ''; ?>>Trung bình</option>
                        <option value="advanced" <?php echo $difficulty === 'advanced' ? 'selected' : ''; ?>>Nâng cao</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary h-10 px-4">
                        <i class="fas fa-search mr-2"></i>Lọc
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Topics List -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($topics as $topic_item): ?>
            <div class="card rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 rounded-full 
                            <?php 
                            switch($topic_item['difficulty']) {
                                case 'beginner': echo 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400'; break;
                                case 'intermediate': echo 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400'; break;
                                case 'advanced': echo 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400'; break;
                            }
                            ?> flex items-center justify-center mr-3">
                            <i class="fas fa-<?php 
                                switch($topic_item['difficulty']) {
                                    case 'beginner': echo 'star'; break;
                                    case 'intermediate': echo 'star-half-alt'; break;
                                    case 'advanced': echo 'award'; break;
                                }
                            ?>"></i>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold"><?php echo htmlspecialchars($topic_item['name']); ?></h2>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                <?php echo htmlspecialchars($topic_item['subject_name']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($topic_item['description'])): ?>
                    <p class="text-sm mb-4 line-clamp-2"><?php echo htmlspecialchars($topic_item['description']); ?></p>
                    <?php else: ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4 italic">Không có mô tả</p>
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-3 gap-2 mb-4">
                        <div class="px-2 py-1 text-center">
                            <div class="text-lg font-semibold"><?php echo $topic_item['material_count']; ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Tài liệu</div>
                        </div>
                        <div class="px-2 py-1 text-center">
                            <div class="text-lg font-semibold"><?php echo $topic_item['exercise_count']; ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Bài tập</div>
                        </div>
                        <div class="px-2 py-1 text-center">
                            <div class="text-lg font-semibold"><?php echo $topic_item['assessment_count']; ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Kiểm tra</div>
                        </div>
                    </div>
                    
                    <div class="flex justify-between pt-3 border-t border-gray-200 dark:border-gray-700">
                        <div>
                            <a href="materials.php?topic_id=<?php echo $topic_item['id']; ?>" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 text-sm">
                                <i class="fas fa-book-open mr-1"></i>Tài liệu
                            </a>
                            <a href="assessments.php?topic_id=<?php echo $topic_item['id']; ?>" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 text-sm ml-3">
                                <i class="fas fa-tasks mr-1"></i>Kiểm tra
                            </a>
                        </div>
                        
                        <div>
                            <a href="topics.php?action=edit&id=<?php echo $topic_item['id']; ?>" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm">
                                <i class="fas fa-edit mr-1"></i>Sửa
                            </a>
                            
                            <?php if ($topic_item['material_count'] == 0 && $topic_item['exercise_count'] == 0 && $topic_item['assessment_count'] == 0): ?>
                            <a href="topics.php?action=delete&id=<?php echo $topic_item['id']; ?><?php echo $subject_id ? '&subject_id=' . $subject_id : ''; ?>" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 text-sm ml-3 confirm-delete">
                                <i class="fas fa-trash-alt mr-1"></i>Xóa
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($topics)): ?>
            <div class="col-span-full text-center py-12 text-gray-500 dark:text-gray-400">
                <i class="fas fa-search text-5xl mb-4"></i>
                <p>Không tìm thấy chủ đề nào</p>
                <a href="topics.php?action=add<?php echo $subject_id ? '&subject_id=' . $subject_id : ''; ?>" class="btn btn-primary mt-4">
                    <i class="fas fa-plus mr-2"></i>Thêm chủ đề mới
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-6 flex justify-center">
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <?php if ($page > 1): ?>
                <a href="?page_num=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&difficulty=<?php echo urlencode($difficulty); ?><?php echo $subject_id ? '&subject_id=' . $subject_id : ''; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                <a href="?page_num=1&search=<?php echo urlencode($search); ?>&difficulty=<?php echo urlencode($difficulty); ?><?php echo $subject_id ? '&subject_id=' . $subject_id : ''; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                    1
                </a>
                <?php if ($start_page > 2): ?>
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">
                    ...
                </span>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?page_num=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&difficulty=<?php echo urlencode($difficulty); ?><?php echo $subject_id ? '&subject_id=' . $subject_id : ''; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium <?php echo $page === $i ? 'text-indigo-600 dark:text-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">
                    ...
                </span>
                <?php endif; ?>
                <a href="?page_num=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&difficulty=<?php echo urlencode($difficulty); ?><?php echo $subject_id ? '&subject_id=' . $subject_id : ''; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                    <?php echo $total_pages; ?>
                </a>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page_num=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&difficulty=<?php echo urlencode($difficulty); ?><?php echo $subject_id ? '&subject_id=' . $subject_id : ''; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
// Incluir el pie de página admin
include 'footer.php';
?>