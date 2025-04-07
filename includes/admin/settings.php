<?php
// File: includes/admin/settings.php
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

// Inicializar mensajes
$message = '';
$error = '';

// Cargar configuración actual
$settings = [
    'site_name' => SITE_NAME,
    'points_per_question' => POINTS_PER_QUESTION,
    'points_daily_bonus' => POINTS_DAILY_BONUS
];

// Array de opciones para el registro
$registration_options = ['open', 'closed', 'invite'];
$current_registration = 'open'; // Por defecto

// Intentar cargar configuración de un archivo
$settings_file = __DIR__ . '/../settings.json';
if (file_exists($settings_file)) {
    $saved_settings = json_decode(file_get_contents($settings_file), true);
    if (is_array($saved_settings)) {
        $settings = array_merge($settings, $saved_settings);
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    // Recoger datos del formulario
    $new_settings = [
        'site_name' => trim($_POST['site_name']),
        'points_per_question' => intval($_POST['points_per_question']),
        'points_daily_bonus' => intval($_POST['points_daily_bonus']),
        'registration' => $_POST['registration']
    ];
    
    // Validar
    if (empty($new_settings['site_name'])) {
        $error = 'Tên hệ thống không được để trống!';
    } else if ($new_settings['points_per_question'] < 0) {
        $error = 'Điểm cho mỗi câu hỏi không được âm!';
    } else if ($new_settings['points_daily_bonus'] < 0) {
        $error = 'Điểm thưởng đăng nhập không được âm!';
    } else if (!in_array($new_settings['registration'], $registration_options)) {
        $error = 'Tùy chọn đăng ký không hợp lệ!';
    } else {
        // Guardar la configuración en el archivo
        if (file_put_contents($settings_file, json_encode($new_settings, JSON_PRETTY_PRINT))) {
            $settings = $new_settings;
            $message = 'Đã lưu cài đặt thành công!';
        } else {
            $error = 'Không thể lưu cài đặt! Kiểm tra quyền ghi file.';
        }
    }
}

// Verificar si algún archivo PHP es editable
$is_editable = is_writable(__DIR__ . '/../config.php');

// Incluir el encabezado admin
$admin_page = 'settings';
include 'header.php';
?>

<div class="flex-1 p-6 overflow-auto">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-2xl font-bold mb-6">Cài đặt hệ thống</h1>
        
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
        
        <div class="card rounded-lg p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Cài đặt chung</h2>
            
            <form method="post" action="settings.php">
                <div class="mb-4">
                    <label for="site_name" class="block text-sm font-medium mb-1">Tên hệ thống</label>
                    <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>" class="w-full">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label for="points_per_question" class="block text-sm font-medium mb-1">Điểm cho mỗi câu hỏi</label>
                        <input type="number" id="points_per_question" name="points_per_question" value="<?php echo intval($settings['points_per_question']); ?>" min="0" class="w-full">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Số điểm trừ khi học viên đặt câu hỏi
                        </p>
                    </div>
                    
                    <div>
                        <label for="points_daily_bonus" class="block text-sm font-medium mb-1">Điểm thưởng đăng nhập</label>
                        <input type="number" id="points_daily_bonus" name="points_daily_bonus" value="<?php echo intval($settings['points_daily_bonus']); ?>" min="0" class="w-full">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Số điểm thưởng khi đăng nhập mỗi ngày
                        </p>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label for="registration" class="block text-sm font-medium mb-1">Đăng ký tài khoản</label>
                    <select id="registration" name="registration" class="w-full">
                        <option value="open" <?php echo (isset($settings['registration']) && $settings['registration'] === 'open') ? 'selected' : ''; ?>>Mở (cho phép tất cả)</option>
                        <option value="closed" <?php echo (isset($settings['registration']) && $settings['registration'] === 'closed') ? 'selected' : ''; ?>>Đóng (không cho phép đăng ký)</option>
                        <option value="invite" <?php echo (isset($settings['registration']) && $settings['registration'] === 'invite') ? 'selected' : ''; ?>>Chỉ mời (cần mã mời)</option>
                    </select>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" name="save_settings" class="btn btn-primary">
                        <i class="fas fa-save mr-2"></i>Lưu cài đặt
                    </button>
                </div>
            </form>
        </div>
        
        <div class="card rounded-lg p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Sao lưu / Phục hồi</h2>
            
            <div class="mb-6">
                <p class="mb-4">Sao lưu dữ liệu hệ thống để phòng trường hợp mất dữ liệu.</p>
                
                <div class="flex space-x-4">
                    <a href="#" id="backup-database" class="btn btn-secondary">
                        <i class="fas fa-database mr-2"></i>Sao lưu cơ sở dữ liệu
                    </a>
                    
                    <a href="#" id="backup-settings" class="btn btn-secondary">
                        <i class="fas fa-cog mr-2"></i>Sao lưu cấu hình
                    </a>
                </div>
            </div>
            
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                <h3 class="font-medium mb-4">Phục hồi dữ liệu</h3>
                
                <div class="mb-4">
                    <label for="restore-file" class="block text-sm font-medium mb-1">Tệp phục hồi</label>
                    <input type="file" id="restore-file" name="restore_file" class="w-full">
                </div>
                
                <div class="flex justify-end">
                    <button type="button" id="restore-button" class="btn btn-primary">
                        <i class="fas fa-upload mr-2"></i>Phục hồi
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">Thông tin hệ thống</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Phiên bản PHP</h3>
                    <p><?php echo phpversion(); ?></p>
                </div>
                
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Máy chủ</h3>
                    <p><?php echo $_SERVER['SERVER_SOFTWARE']; ?></p>
                </div>
                
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Database</h3>
                    <p>MySQL <?php echo mysqli_get_server_info($conn); ?></p>
                </div>
                
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Upload Max Size</h3>
                    <p><?php echo ini_get('upload_max_filesize'); ?></p>
                </div>
                
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Timezone</h3>
                    <p><?php echo date_default_timezone_get(); ?></p>
                </div>
                
                <div>
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Memory Limit</h3>
                    <p><?php echo ini_get('memory_limit'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Función para descargar un archivo
    function downloadFile(content, fileName, contentType) {
        const a = document.createElement('a');
        const file = new Blob([content], { type: contentType });
        a.href = URL.createObjectURL(file);
        a.download = fileName;
        a.click();
        URL.revokeObjectURL(a.href);
    }
    
    // Backup database (simulated - actual database dumps would need server-side processing)
    document.getElementById('backup-database').addEventListener('click', function(e) {
        e.preventDefault();
        
        if (confirm('Tính năng này cần được thực hiện trên máy chủ. Bạn có muốn mở hướng dẫn không?')) {
            alert('Để sao lưu cơ sở dữ liệu, bạn nên sử dụng phpMyAdmin hoặc công cụ quản lý cơ sở dữ liệu khác của nhà cung cấp hosting.');
        }
    });
    
    // Backup settings
    document.getElementById('backup-settings').addEventListener('click', function(e) {
        e.preventDefault();
        
        const settings = {
            site_name: "<?php echo addslashes($settings['site_name']); ?>",
            points_per_question: <?php echo intval($settings['points_per_question']); ?>,
            points_daily_bonus: <?php echo intval($settings['points_daily_bonus']); ?>,
            registration: "<?php echo isset($settings['registration']) ? $settings['registration'] : 'open'; ?>"
        };
        
        downloadFile(
            JSON.stringify(settings, null, 2),
            'hocbai_settings_backup_' + new Date().toISOString().slice(0, 10) + '.json',
            'application/json'
        );
    });
    
    // Restore functionality
    document.getElementById('restore-button').addEventListener('click', function() {
        const fileInput = document.getElementById('restore-file');
        
        if (!fileInput.files || fileInput.files.length === 0) {
            alert('Vui lòng chọn tệp phục hồi!');
            return;
        }
        
        const file = fileInput.files[0];
        if (!file.name.endsWith('.json')) {
            alert('Vui lòng chọn tệp JSON hợp lệ!');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const content = JSON.parse(e.target.result);
                
                // Validate content
                if (!content.site_name || 
                    typeof content.points_per_question !== 'number' || 
                    typeof content.points_daily_bonus !== 'number') {
                    throw new Error('Tệp không chứa dữ liệu cấu hình hợp lệ!');
                }
                
                if (confirm('Bạn có chắc chắn muốn phục hồi cấu hình từ tệp này?')) {
                    // In a real implementation, this would need to send the file to the server via AJAX
                    alert('Tính năng này cần được thực hiện trên máy chủ. Vui lòng liên hệ quản trị viên.');
                }
            } catch (error) {
                alert('Lỗi: ' + error.message);
            }
        };
        reader.readAsText(file);
    });
</script>

<?php
// Incluir el pie de página admin
include 'footer.php';
?>