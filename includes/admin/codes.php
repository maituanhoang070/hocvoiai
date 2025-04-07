<?php
// File: includes/admin/codes.php
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
$code_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$error = '';

// Datos iniciales para editar/añadir
$code = [
    'code' => generateRandomCode(),
    'points' => 10,
    'max_uses' => 1,
    'expiry_date' => date('Y-m-d', strtotime('+30 days'))
];

// Manejar acciones CRUD
if ($action === 'delete' && $code_id) {
    // Verificar si ya ha sido utilizado
    $stmt = $conn->prepare("SELECT current_uses FROM redeem_codes WHERE id = ?");
    $stmt->bind_param("i", $code_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result && $result['current_uses'] > 0) {
        $error = "Không thể xóa mã này vì đã được sử dụng " . $result['current_uses'] . " lần!";
    } else {
        $stmt = $conn->prepare("DELETE FROM redeem_codes WHERE id = ?");
        $stmt->bind_param("i", $code_id);
        
        if ($stmt->execute()) {
            $message = 'Đã xóa mã thành công!';
        } else {
            $error = 'Lỗi: ' . $conn->error;
        }
    }
} else if (($action === 'edit' && $code_id) || $action === 'add') {
    // Cargar datos para editar
    if ($action === 'edit' && $code_id) {
        $stmt = $conn->prepare("SELECT * FROM redeem_codes WHERE id = ?");
        $stmt->bind_param("i", $code_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $code = $row;
        } else {
            $error = 'Không tìm thấy mã!';
        }
    }
    
    // Procesar formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $code['code'] = trim($_POST['code'] ?? '');
        $code['points'] = intval($_POST['points'] ?? 0);
        $code['max_uses'] = intval($_POST['max_uses'] ?? 1);
        $code['expiry_date'] = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        
        if (empty($code['code'])) {
            $error = 'Mã không được để trống!';
        } else if ($code['points'] <= 0) {
            $error = 'Số điểm phải lớn hơn 0!';
        } else {
            // Verificar que no exista otro con el mismo código
            $stmt = $conn->prepare("SELECT id FROM redeem_codes WHERE code = ? AND id != ?");
            $stmt->bind_param("si", $code['code'], $code_id);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            
            if ($exists) {
                $error = 'Mã này đã tồn tại!';
            } else {
                if ($action === 'edit') {
                    $stmt = $conn->prepare("UPDATE redeem_codes SET code = ?, points = ?, max_uses = ?, expiry_date = ? WHERE id = ?");
                    $stmt->bind_param("siiis", $code['code'], $code['points'], $code['max_uses'], $code['expiry_date'], $code_id);
                } else {
                    $stmt = $conn->prepare("INSERT INTO redeem_codes (code, points, max_uses, expiry_date, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->bind_param("siis", $code['code'], $code['points'], $code['max_uses'], $code['expiry_date']);
                }
                
                if ($stmt->execute()) {
                    $message = $action === 'edit' ? 'Đã cập nhật mã thành công!' : 'Đã thêm mã thành công!';
                    if ($action === 'add') {
                        $code_id = $conn->insert_id;
                    }
                    
                    // Redirigir después de procesar
                    header('Location: codes.php?message=' . urlencode($message));
                    exit;
                } else {
                    $error = 'Lỗi: ' . $conn->error;
                }
            }
        }
    }
} else if ($action === 'generate_bulk') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $quantity = intval($_POST['quantity'] ?? 0);
        $points = intval($_POST['points'] ?? 0);
        $max_uses = intval($_POST['max_uses'] ?? 1);
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        
        if ($quantity <= 0 || $quantity > 100) {
            $error = 'Số lượng phải từ 1 đến 100!';
        } else if ($points <= 0) {
            $error = 'Số điểm phải lớn hơn 0!';
        } else {
            $success_count = 0;
            $codes_generated = [];
            
            // Generar varios códigos
            for ($i = 0; $i < $quantity; $i++) {
                $new_code = generateRandomCode();
                
                // Verificar que no exista
                $stmt = $conn->prepare("SELECT id FROM redeem_codes WHERE code = ?");
                $stmt->bind_param("s", $new_code);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    // Si existe, intentar otro
                    $i--;
                    continue;
                }
                
                $stmt = $conn->prepare("INSERT INTO redeem_codes (code, points, max_uses, expiry_date, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->bind_param("siis", $new_code, $points, $max_uses, $expiry_date);
                
                if ($stmt->execute()) {
                    $success_count++;
                    $codes_generated[] = $new_code;
                }
            }
            
            if ($success_count > 0) {
                $message = "Đã tạo thành công $success_count mã!";
                $bulk_codes = implode("\n", $codes_generated);
            } else {
                $error = 'Không thể tạo mã!';
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
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Construir la consulta
$query = "SELECT * FROM redeem_codes WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM redeem_codes WHERE 1=1";

$query_params = [];
$param_types = "";

if (!empty($search)) {
    $query .= " AND code LIKE ?";
    $count_query .= " AND code LIKE ?";
    $search_param = "%$search%";
    $query_params[] = $search_param;
    $param_types .= "s";
}

if ($status === 'active') {
    $query .= " AND (expiry_date IS NULL OR expiry_date >= CURDATE()) AND (max_uses = 0 OR current_uses < max_uses)";
    $count_query .= " AND (expiry_date IS NULL OR expiry_date >= CURDATE()) AND (max_uses = 0 OR current_uses < max_uses)";
} else if ($status === 'used') {
    $query .= " AND max_uses > 0 AND current_uses >= max_uses";
    $count_query .= " AND max_uses > 0 AND current_uses >= max_uses";
} else if ($status === 'expired') {
    $query .= " AND expiry_date < CURDATE()";
    $count_query .= " AND expiry_date < CURDATE()";
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
$query .= " ORDER BY created_at DESC LIMIT ?, ?";
$query_params[] = $offset;
$query_params[] = $limit;
$param_types .= "ii";

// Ejecutar consulta principal
$stmt = $conn->prepare($query);
if (!empty($param_types)) {
    $stmt->bind_param($param_types, ...$query_params);
}
$stmt->execute();
$codes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Incluir el encabezado admin
$admin_page = 'codes';
include 'header.php';
?>

<div class="flex-1 p-6 overflow-auto">
    <div class="max-w-7xl mx-auto">
        <?php if ($action === 'edit' || $action === 'add'): ?>
        <!-- Formulario de edición/adición -->
        <div class="flex items-center mb-6">
            <a href="codes.php" class="mr-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-2xl font-bold"><?php echo $action === 'edit' ? 'Chỉnh sửa mã' : 'Thêm mã mới'; ?></h1>
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
            <form method="post" action="codes.php?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $code_id : ''; ?>">
                <div class="mb-4">
                    <label for="code" class="block text-sm font-medium mb-1">Mã code <span class="text-red-500">*</span></label>
                    <input type="text" id="code" name="code" value="<?php echo htmlspecialchars($code['code']); ?>" class="w-full" required>
                    <?php if ($action === 'add'): ?>
                    <div class="mt-1 text-right">
                        <button type="button" id="generate-code" class="text-sm text-indigo-600 dark:text-indigo-400">
                            <i class="fas fa-sync-alt mr-1"></i>Tạo mã mới
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-4">
                    <label for="points" class="block text-sm font-medium mb-1">Số điểm <span class="text-red-500">*</span></label>
                    <input type="number" id="points" name="points" value="<?php echo htmlspecialchars($code['points']); ?>" min="1" class="w-full" required>
                </div>
                
                <div class="mb-4">
                    <label for="max_uses" class="block text-sm font-medium mb-1">Số lần sử dụng tối đa (0 = không giới hạn)</label>
                    <input type="number" id="max_uses" name="max_uses" value="<?php echo htmlspecialchars($code['max_uses']); ?>" min="0" class="w-full">
                </div>
                
                <div class="mb-6">
                    <label for="expiry_date" class="block text-sm font-medium mb-1">Ngày hết hạn (để trống = không hết hạn)</label>
                    <input type="date" id="expiry_date" name="expiry_date" value="<?php echo $code['expiry_date'] ? date('Y-m-d', strtotime($code['expiry_date'])) : ''; ?>" class="w-full">
                </div>
                
                <div class="flex justify-end">
                    <a href="codes.php" class="btn btn-secondary mr-3">
                        Hủy
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $action === 'edit' ? 'Cập nhật' : 'Thêm mới'; ?>
                    </button>
                </div>
            </form>
        </div>
        <?php elseif ($action === 'generate_bulk'): ?>
        <!-- Formulario para generar varios códigos -->
        <div class="flex items-center mb-6">
            <a href="codes.php" class="mr-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-2xl font-bold">Tạo nhiều mã cùng lúc</h1>
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
        
        <?php if (!empty($message) && !empty($bulk_codes)): ?>
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
        
        <div class="card rounded-lg p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Danh sách mã đã tạo</h2>
            <div class="mb-4">
                <textarea class="w-full h-40 font-mono" readonly><?php echo $bulk_codes; ?></textarea>
            </div>
            <div class="flex justify-between">
                <button type="button" id="copy-codes" class="btn btn-secondary">
                    <i class="fas fa-copy mr-2"></i>Sao chép
                </button>
                <a href="codes.php" class="btn btn-primary">
                    <i class="fas fa-arrow-right mr-2"></i>Quay lại danh sách
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="card rounded-lg p-6 mb-6">
            <form method="post" action="codes.php?action=generate_bulk">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="quantity" class="block text-sm font-medium mb-1">Số lượng mã (1-100) <span class="text-red-500">*</span></label>
                        <input type="number" id="quantity" name="quantity" value="10" min="1" max="100" class="w-full" required>
                    </div>
                    
                    <div>
                        <label for="points" class="block text-sm font-medium mb-1">Số điểm mỗi mã <span class="text-red-500">*</span></label>
                        <input type="number" id="points" name="points" value="10" min="1" class="w-full" required>
                    </div>
                    
                    <div>
                        <label for="max_uses" class="block text-sm font-medium mb-1">Số lần sử dụng tối đa (0 = không giới hạn)</label>
                        <input type="number" id="max_uses" name="max_uses" value="1" min="0" class="w-full">
                    </div>
                    
                    <div>
                        <label for="expiry_date" class="block text-sm font-medium mb-1">Ngày hết hạn (để trống = không hết hạn)</label>
                        <input type="date" id="expiry_date" name="expiry_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" class="w-full">
                    </div>
                </div>
                
                <div class="flex justify-end mt-6">
                    <a href="codes.php" class="btn btn-secondary mr-3">
                        Hủy
                    </a>
                    <button type="submit" class="btn btn-primary">
                        Tạo mã
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <!-- Lista de códigos -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <h1 class="text-2xl font-bold mb-4 md:mb-0">Quản lý mã nhận điểm</h1>
            
            <div class="flex flex-col sm:flex-row gap-3">
                <a href="codes.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus mr-2"></i>Thêm mã
                </a>
                <a href="codes.php?action=generate_bulk" class="btn btn-secondary">
                    <i class="fas fa-layer-group mr-2"></i>Tạo nhiều mã
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
            <form action="codes.php" method="get" class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label for="search" class="block text-sm font-medium mb-1">Tìm kiếm</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nhập mã..." class="w-full">
                </div>
                
                <div class="w-full md:w-48">
                    <label for="status" class="block text-sm font-medium mb-1">Trạng thái</label>
                    <select id="status" name="status" class="w-full">
                        <option value="">Tất cả</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Còn hiệu lực</option>
                        <option value="used" <?php echo $status === 'used' ? 'selected' : ''; ?>>Đã sử dụng hết</option>
                        <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>Đã hết hạn</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary h-10 px-4">
                        <i class="fas fa-search mr-2"></i>Lọc
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Codes List -->
        <div class="card rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Mã</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Số điểm</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sử dụng</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ngày tạo</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Trạng thái</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($codes as $code_item): ?>
                        <tr>
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400 mr-3">
                                        <i class="fas fa-gift"></i>
                                    </div>
                                    <div>
                                        <div class="font-mono font-medium"><?php echo htmlspecialchars($code_item['code']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-lg"><?php echo number_format($code_item['points']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($code_item['max_uses'] > 0): ?>
                                <div><?php echo $code_item['current_uses']; ?> / <?php echo $code_item['max_uses']; ?></div>
                                <?php else: ?>
                                <div><?php echo $code_item['current_uses']; ?> / ∞</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div><?php echo date('d/m/Y', strtotime($code_item['created_at'])); ?></div>
                                <?php if ($code_item['expiry_date']): ?>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    Hết hạn: <?php echo date('d/m/Y', strtotime($code_item['expiry_date'])); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php
                                $is_expired = $code_item['expiry_date'] && strtotime($code_item['expiry_date']) < time();
                                $is_used_up = $code_item['max_uses'] > 0 && $code_item['current_uses'] >= $code_item['max_uses'];
                                
                                if ($is_expired): ?>
                                <span class="inline-flex px-2 py-1 text-xs rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                                    Đã hết hạn
                                </span>
                                <?php elseif ($is_used_up): ?>
                                <span class="inline-flex px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400">
                                    Đã dùng hết
                                </span>
                                <?php else: ?>
                                <span class="inline-flex px-2 py-1 text-xs rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                    Còn hiệu lực
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex flex-col space-y-2">
                                    <a href="codes.php?action=edit&id=<?php echo $code_item['id']; ?>" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                                        <i class="fas fa-edit mr-1"></i>Sửa
                                    </a>
                                    
                                    <?php if ($code_item['current_uses'] == 0): ?>
                                    <a href="codes.php?action=delete&id=<?php echo $code_item['id']; ?>" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 confirm-delete">
                                        <i class="fas fa-trash-alt mr-1"></i>Xóa
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($codes)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                Không tìm thấy mã nào
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="bg-gray-50 dark:bg-gray-800 px-4 py-3 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 sm:px-6">
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700 dark:text-gray-400">
                            Hiển thị <span class="font-medium"><?php echo count($codes); ?></span> trên tổng số <span class="font-medium"><?php echo $total_records; ?></span> mã
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                            <a href="?page_num=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1): ?>
                            <a href="?page_num=1&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                                1
                            </a>
                            <?php if ($start_page > 2): ?>
                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">
                                ...
                            </span>
                            <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?page_num=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium <?php echo $page === $i ? 'text-indigo-600 dark:text-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'; ?>">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">
                                ...
                            </span>
                            <?php endif; ?>
                            <a href="?page_num=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <?php echo $total_pages; ?>
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?page_num=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Generador de códigos aleatorios
    const generateCodeBtn = document.getElementById('generate-code');
    const codeInput = document.getElementById('code');
    
    if (generateCodeBtn && codeInput) {
        generateCodeBtn.addEventListener('click', function() {
            // Generar código aleatorio (letras y números, 8 caracteres)
            const chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            let code = '';
            for (let i = 0; i < 8; i++) {
                code += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            codeInput.value = code;
        });
    }
    
    // Copiador de códigos
    const copyCodesBtn = document.getElementById('copy-codes');
    if (copyCodesBtn) {
        copyCodesBtn.addEventListener('click', function() {
            const textarea = this.parentElement.previousElementSibling.querySelector('textarea');
            textarea.select();
            document.execCommand('copy');
            
            // Cambiar texto del botón temporalmente
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-check mr-2"></i>Đã sao chép';
            setTimeout(() => {
                this.innerHTML = originalText;
            }, 2000);
        });
    }
</script>

<?php
// Incluir el pie de página admin
include 'footer.php';
?>