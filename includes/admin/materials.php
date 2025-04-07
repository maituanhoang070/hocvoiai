<?php
// File: includes/admin/materials.php
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
$material_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$topic_id = isset($_GET['topic_id']) ? intval($_GET['topic_id']) : 0;
$message = '';
$error = '';

// Datos iniciales para editar/añadir
$material = [
    'topic_id' => $topic_id,
    'title' => '',
    'content' => '',
    'material_type' => 'explanation'
];

// Manejar acciones CRUD
if ($action === 'delete' && $material_id) {
    $stmt = $conn->prepare("DELETE FROM study_materials WHERE id = ?");
    $stmt->bind_param("i", $material_id);
    
    if ($stmt->execute()) {
        $message = 'Đã xóa tài liệu thành công!';
    } else {
        $error = 'Lỗi: ' . $conn->error;
    }
} else if (($action === 'edit' && $material_id) || $action === 'add') {
    // Cargar datos para editar
    if ($action === 'edit' && $material_id) {
        $stmt = $conn->prepare("SELECT * FROM study_materials WHERE id = ?");
        $stmt->bind_param("i", $material_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $material = $row;
            $topic_id = $material['topic_id'];
        } else {
            $error = 'Không tìm thấy tài liệu!';
        }
    }
    
    // Procesar formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $material['topic_id'] = intval($_POST['topic_id'] ?? 0);
        $material['title'] = trim($_POST['title'] ?? '');
        $material['content'] = $_POST['content'] ?? '';
        $material['material_type'] = $_POST['material_type'] ?? 'explanation';
        
        if (empty($material['title'])) {
            $error = 'Tiêu đề tài liệu không được để trống!';
        } else if (empty($material['content'])) {
            $error = 'Nội dung tài liệu không được để trống!';
        } else if (empty($material['topic_id'])) {
            $error = 'Vui lòng chọn chủ đề!';
        } else {
            if ($action === 'edit') {
                $stmt = $conn->prepare("UPDATE study_materials SET topic_id = ?, title = ?, content = ?, material_type = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("isssi", $material['topic_id'], $material['title'], $material['content'], $material['material_type'], $material_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO study_materials (topic_id, title, content, material_type, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->bind_param("isss", $material['topic_id'], $material['title'], $material['content'], $material['material_type']);
            }
            
            if ($stmt->execute()) {
                $message = $action === 'edit' ? 'Đã cập nhật tài liệu thành công!' : 'Đã thêm tài liệu thành công!';
                if ($action === 'add') {
                    $material_id = $conn->insert_id;
                }
                
                // Redirigir después de procesar
                header('Location: materials.php?topic_id=' . $material['topic_id'] . '&message=' . urlencode($message));
                exit;
            } else {
                $error = 'Lỗi: ' . $conn->error;
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
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';

// Obtener todos los temas para el formulario
$topics = [];
$stmt = $conn->query("SELECT t.id, t.name, s.name as subject_name FROM study_topics t JOIN study_subjects s ON t.subject_id = s.id ORDER BY s.name, t.name");
while ($row = $stmt->fetch_assoc()) {
    $topics[] = $row;
}

// Construir la consulta
$query = "SELECT m.*, t.name as topic_name, s.name as subject_name
          FROM study_materials m
          JOIN study_topics t ON m.topic_id = t.id
          JOIN study_subjects s ON t.subject_id = s.id
          WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM study_materials m WHERE 1=1";

$query_params = [];
$param_types = "";

if (!empty($search)) {
    $query .= " AND (m.title LIKE ? OR m.content LIKE ?)";
    $count_query .= " AND (title LIKE ? OR content LIKE ?)";
    $search_param = "%$search%";
    $query_params[] = $search_param;
    $query_params[] = $search_param;
    $param_types .= "ss";
}

if (!empty($type)) {
    $query .= " AND m.material_type = ?";
    $count_query .= " AND material_type = ?";
    $query_params[] = $type;
    $param_types .= "s";
}

if ($topic_id) {
    $query .= " AND m.topic_id = ?";
    $count_query .= " AND topic_id = ?";
    $query_params[] = $topic_id;
    $param_types .= "i";
    
    // Obtener información del tema
    $stmt = $conn->prepare("SELECT t.name, t.subject_id, s.name as subject_name FROM study_topics t JOIN study_subjects s ON t.subject_id = s.id WHERE t.id = ?");
    $stmt->bind_param("i", $topic_id);
    $stmt->execute();
    $current_topic = $stmt->get_result()->fetch_assoc();
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
$query .= " ORDER BY m.created_at DESC LIMIT ?, ?";
$query_params[] = $offset;
$query_params[] = $limit;
$param_types .= "ii";

// Ejecutar consulta principal
$stmt = $conn->prepare($query);
if (!empty($param_types)) {
    $stmt->bind_param($param_types, ...$query_params);
}
$stmt->execute();
$materials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Incluir el encabezado admin
$admin_page = 'materials';
include 'header.php';
?>

<div class="flex-1 p-6 overflow-auto">
    <div class="max-w-7xl mx-auto">
        <?php if ($action === 'edit' || $action === 'add'): ?>
        <!-- Formulario de edición/adición -->
        <div class="flex items-center mb-6">
            <a href="materials.php<?php echo $topic_id ? '?topic_id=' . $topic_id : ''; ?>" class="mr-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-2xl font-bold"><?php echo $action === 'edit' ? 'Chỉnh sửa tài liệu' : 'Thêm tài liệu mới'; ?></h1>
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
            <form method="post" action="materials.php?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $material_id : ''; ?>">
                <div class="mb-4">
                    <label for="topic_id" class="block text-sm font-medium mb-1">Chủ đề <span class="text-red-500">*</span></label>
                    <select id="topic_id" name="topic_id" class="w-full" required>
                        <option value="">-- Chọn chủ đề --</option>
                        <?php foreach ($topics as $topic): ?>
                        <option value="<?php echo $topic['id']; ?>" <?php echo $material['topic_id'] == $topic['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($topic['subject_name'] . ' - ' . $topic['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="title" class="block text-sm font-medium mb-1">Tiêu đề <span class="text-red-500">*</span></label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($material['title']); ?>" class="w-full" required>
                </div>
                
                <div class="mb-4">
                    <label for="material_type" class="block text-sm font-medium mb-1">Loại tài liệu</label>
                    <select id="material_type" name="material_type" class="w-full">
                        <option value="explanation" <?php echo $material['material_type'] === 'explanation' ? 'selected' : ''; ?>>Giải thích</option>
                        <option value="formula" <?php echo $material['material_type'] === 'formula' ? 'selected' : ''; ?>>Công thức</option>
                        <option value="example" <?php echo $material['material_type'] === 'example' ? 'selected' : ''; ?>>Ví dụ</option>
                        <option value="quiz" <?php echo $material['material_type'] === 'quiz' ? 'selected' : ''; ?>>Câu hỏi nhanh</option>
                    </select>
                </div>
                
                <div class="mb-1">
                    <label for="content" class="block text-sm font-medium mb-1">Nội dung <span class="text-red-500">*</span></label>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                        <span>Hỗ trợ định dạng Markdown. Ví dụ:</span>
                        <ul class="list-disc pl-5 mt-1">
                            <li># Tiêu đề 1, ## Tiêu đề 2</li>
                            <li>**in đậm**, *in nghiêng*</li>
                            <li>[Liên kết](https://example.com)</li>
                            <li>- Danh sách không số, 1. Danh sách có số</li>
                        </ul>
                    </div>
                    <textarea id="content" name="content" rows="12" class="w-full font-mono text-sm" required><?php echo htmlspecialchars($material['content']); ?></textarea>
                </div>
                
                <div class="mb-6">
                    <div class="flex justify-between items-center">
                        <label class="block text-sm font-medium">Xem trước</label>
                        <button type="button" id="refresh-preview" class="text-sm text-indigo-600 dark:text-indigo-400">
                            <i class="fas fa-sync mr-1"></i>Cập nhật
                        </button>
                    </div>
                    <div id="content-preview" class="mt-2 p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 overflow-auto max-h-96">
                        <!-- Preview content will be displayed here -->
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <a href="materials.php<?php echo $topic_id ? '?topic_id=' . $topic_id : ''; ?>" class="btn btn-secondary mr-3">
                        Hủy
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $action === 'edit' ? 'Cập nhật' : 'Thêm mới'; ?>
                    </button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <!-- Lista de materiales -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <?php if ($topic_id && isset($current_topic)): ?>
            <div>
                <div class="flex items-center mb-2">
                    <a href="topics.php?subject_id=<?php echo $current_topic['subject_id']; ?>" class="mr-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h1 class="text-2xl font-bold">Tài liệu: <?php echo htmlspecialchars($current_topic['name']); ?></h1>
                </div>
                <p class="text-gray-600 dark:text-gray-400">
                    Môn học: <?php echo htmlspecialchars($current_topic['subject_name']); ?>
                </p>
            </div>
            <?php else: ?>
            <div>
                <h1 class="text-2xl font-bold mb-2">Quản lý tài liệu học tập</h1>
                <p class="text-gray-600 dark:text-gray-400">Quản lý tất cả các tài liệu học tập trong hệ thống</p>
            </div>
            <?php endif; ?>
            
            <div class="mt-4 md:mt-0">
                <a href="materials.php?action=add<?php echo $topic_id ? '&topic_id=' . $topic_id : ''; ?>" class="btn btn-primary">
                    <i class="fas fa-plus mr-2"></i>Thêm tài liệu
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
            <form action="materials.php" method="get" class="flex flex-col md:flex-row gap-4">
                <?php if ($topic_id): ?>
                <input type="hidden" name="topic_id" value="<?php echo $topic_id; ?>">
                <?php else: ?>
                <div class="w-full md:w-64">
                    <label for="topic_id" class="block text-sm font-medium mb-1">Chủ đề</label>
                    <select id="topic_id" name="topic_id" class="w-full">
                        <option value="">Tất cả chủ đề</option>
                        <?php foreach ($topics as $topic): ?>
                        <option value="<?php echo $topic['id']; ?>" <?php echo $topic_id == $topic['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($topic['subject_name'] . ' - ' . $topic['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="flex-1">
                    <label for="search" class="block text-sm font-medium mb-1">Tìm kiếm</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tiêu đề, nội dung..." class="w-full">
                </div>
                
                <div class="w-full md:w-48">
                    <label for="type" class="block text-sm font-medium mb-1">Loại tài liệu</label>
                    <select id="type" name="type" class="w-full">
                        <option value="">Tất cả</option>
                        <option value="explanation" <?php echo $type === 'explanation' ? 'selected' : ''; ?>>Giải thích</option>
                        <option value="formula" <?php echo $type === 'formula' ? 'selected' : ''; ?>>Công thức</option>
                        <option value="example" <?php echo $type === 'example' ? 'selected' : ''; ?>>Ví dụ</option>
                        <option value="quiz" <?php echo $type === 'quiz' ? 'selected' : ''; ?>>Câu hỏi nhanh</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary h-10 px-4">
                        <i class="fas fa-search mr-2"></i>Lọc
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Materials List -->
        <div class="space-y-6">
            <?php foreach ($materials as $material_item): ?>
            <div class="card rounded-xl overflow-hidden shadow-sm">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full 
                                <?php 
                                switch($material_item['material_type']) {
                                    case 'explanation': echo 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400'; break;
                                    case 'formula': echo 'bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400'; break;
                                    case 'example': echo 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400'; break;
                                    case 'quiz': echo 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400'; break;
                                }
                                ?> flex items-center justify-center mr-3">
                                <i class="fas fa-<?php 
                                    switch($material_item['material_type']) {
                                        case 'explanation': echo 'info-circle'; break;
                                        case 'formula': echo 'square-root-alt'; break;
                                        case 'example': echo 'lightbulb'; break;
                                        case 'quiz': echo 'question-circle'; break;
                                    }
                                ?>"></i>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold"><?php echo htmlspecialchars($material_item['title']); ?></h2>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo htmlspecialchars($material_item['subject_name'] . ' - ' . $material_item['topic_name']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <span class="inline-flex px-2 py-1 text-xs rounded-full 
                                <?php 
                                switch($material_item['material_type']) {
                                    case 'explanation': echo 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'; break;
                                    case 'formula': echo 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400'; break;
                                    case 'example': echo 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'; break;
                                    case 'quiz': echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'; break;
                                }
                                ?>">
                                <?php 
                                switch($material_item['material_type']) {
                                    case 'explanation': echo 'Giải thích'; break;
                                    case 'formula': echo 'Công thức'; break;
                                    case 'example': echo 'Ví dụ'; break;
                                    case 'quiz': echo 'Câu hỏi nhanh'; break;
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="prose dark:prose-invert max-w-none truncate-3-lines">
                            <?php echo htmlspecialchars(substr($material_item['content'], 0, 200)); ?>
                            <?php echo strlen($material_item['content']) > 200 ? '...' : ''; ?>
                        </div>
                        <button type="button" class="toggle-content text-sm text-indigo-600 dark:text-indigo-400 mt-2">
                            <i class="fas fa-chevron-down mr-1"></i>Xem thêm
                        </button>
                        <div class="hidden-content prose dark:prose-invert max-w-none mt-3 hidden">
                            <?php echo nl2br(htmlspecialchars($material_item['content'])); ?>
                        </div>
                    </div>
                    
                    <div class="flex justify-between pt-3 border-t border-gray-200 dark:border-gray-700">
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            <span>Tạo: <?php echo date('d/m/Y H:i', strtotime($material_item['created_at'])); ?></span>
                            <?php if ($material_item['updated_at']): ?>
                            <span class="ml-3">Cập nhật: <?php echo date('d/m/Y H:i', strtotime($material_item['updated_at'])); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <a href="materials.php?action=edit&id=<?php echo $material_item['id']; ?>" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm">
                                <i class="fas fa-edit mr-1"></i>Sửa
                            </a>
                            
                            <a href="materials.php?action=delete&id=<?php echo $material_item['id']; ?><?php echo $topic_id ? '&topic_id=' . $topic_id : ''; ?>" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 text-sm ml-4 confirm-delete">
                                <i class="fas fa-trash-alt mr-1"></i>Xóa
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($materials)): ?>
            <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                <i class="fas fa-search text-5xl mb-4"></i>
                <p>Không tìm thấy tài liệu nào</p>
                <a href="materials.php?action=add<?php echo $topic_id ? '&topic_id=' . $topic_id : ''; ?>" class="btn btn-primary mt-4">
                    <i class="fas fa-plus mr-2"></i>Thêm tài liệu mới
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-6 flex justify-center">
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <?php if ($page > 1): ?>
                <a href="?page_num=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?><?php echo $topic_id ? '&topic_id=' . $topic_id : ''; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                <a href="?page_num=1&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?><?php echo $topic_id ? '&topic_id=' . $topic_id : ''; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                    1
                </a>
                <?php if ($start_page > 2): ?>
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">
                    ...
                </span>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?page_num=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?><?php echo $topic_id ? '&topic_id=' . $topic_id : ''; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium <?php echo $page === $i ? 'text-indigo-600 dark:text-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">
                    ...
                </span>
                <?php endif; ?>
                <a href="?page_num=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?><?php echo $topic_id ? '&topic_id=' . $topic_id : ''; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                    <?php echo $total_pages; ?>
                </a>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page_num=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?><?php echo $topic_id ? '&topic_id=' . $topic_id : ''; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // Preview del contenido en Markdown
    const contentTextarea = document.getElementById('content');
    const contentPreview = document.getElementById('content-preview');
    const refreshPreviewBtn = document.getElementById('refresh-preview');
    
    // Manejar preview del contenido
    function updatePreview() {
        if (contentTextarea && contentPreview) {
            contentPreview.innerHTML = marked.parse(contentTextarea.value || '');
        }
    }
    
    if (contentTextarea && contentPreview) {
        // Actualizar preview inicialmente
        updatePreview();
        
        // Actualizar al hacer clic en el botón de refresh
        if (refreshPreviewBtn) {
            refreshPreviewBtn.addEventListener('click', updatePreview);
        }
    }
    
    // Manejar toggle de contenido
    document.querySelectorAll('.toggle-content').forEach(button => {
        button.addEventListener('click', function() {
            const hiddenContent = this.nextElementSibling;
            hiddenContent.classList.toggle('hidden');
            
            if (hiddenContent.classList.contains('hidden')) {
                this.innerHTML = '<i class="fas fa-chevron-down mr-1"></i>Xem thêm';
            } else {
                this.innerHTML = '<i class="fas fa-chevron-up mr-1"></i>Thu gọn';
            }
        });
    });
</script>

<style>
    .truncate-3-lines {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    /* Estilos para el contenido Markdown */
    .prose {
        color: var(--text-color);
    }
    
    .prose h1, .prose h2, .prose h3, .prose h4 {
        color: var(--text-color);
        margin-top: 1.5em;
        margin-bottom: 0.75em;
        font-weight: 600;
    }
    
    .prose h1 {
        font-size: 1.875em;
    }
    
    .prose h2 {
        font-size: 1.5em;
    }
    
    .prose h3 {
        font-size: 1.25em;
    }
    
    .prose p, .prose ul, .prose ol {
        margin-top: 1em;
        margin-bottom: 1em;
    }
    
    .prose strong {
        font-weight: 600;
    }
    
    .prose a {
        color: var(--primary-color);
        text-decoration: underline;
    }
    
    .prose ul, .prose ol {
        padding-left: 1.5em;
    }
    
    .prose ul {
        list-style-type: disc;
    }
    
    .prose ol {
        list-style-type: decimal;
    }
    
    .prose code {
        color: var(--text-color);
        background-color: rgba(0, 0, 0, 0.1);
        padding: 0.2em 0.4em;
        border-radius: 0.25em;
        font-family: monospace;
    }
    
    .prose pre {
        background-color: rgba(0, 0, 0, 0.1);
        padding: 1em;
        border-radius: 0.5em;
        overflow-x: auto;
    }
    
    .dark .prose pre, .dark .prose code {
        background-color: rgba(255, 255, 255, 0.1);
    }
</style>

<?php
// Incluir el pie de página admin
include 'footer.php';
?>