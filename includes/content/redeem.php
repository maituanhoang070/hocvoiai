<?php
// File: includes/content/redeem.php
// Verificar que el usuario haya iniciado sesión
requireLogin();

// Obtener información del usuario
$user = getUserInfo($_SESSION['user_id']);

// Obtener historial de códigos canjeados (últimos 5)
$stmt = $conn->prepare("
    SELECT r.redeemed_at, c.code, r.points_awarded
    FROM code_redemptions r
    JOIN redeem_codes c ON r.code_id = c.id
    WHERE r.user_id = ?
    ORDER BY r.redeemed_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$redeem_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Nhập mã code</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
        <!-- Left Content - Redención de códigos -->
        <div class="md:col-span-3">
            <div class="card p-6 rounded-xl">
                <h2 class="text-xl font-semibold mb-4" style="color: var(--primary-color)">Nhập mã code</h2>
                
                <div id="redeemAlert" class="mb-4"></div>
                
                <form id="redeemCodeForm">
                    <div class="mb-6">
                        <label for="redemptionCode" class="block text-sm font-medium mb-2">Mã code</label>
                        <input type="text" id="redemptionCode" name="code" class="w-full text-base uppercase" placeholder="Nhập mã code tại đây" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-full justify-center py-3">
                        <i class="fas fa-check-circle mr-2"></i>Nhận điểm
                    </button>
                </form>
                
                <div class="mt-6 text-sm text-gray-600 dark:text-gray-400">
                    <p class="mb-2"><i class="fas fa-info-circle mr-1"></i> <strong>Hướng dẫn:</strong></p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Nhập mã code bạn nhận được vào ô trên.</li>
                        <li>Mỗi mã code chỉ có thể sử dụng một lần.</li>
                        <li>Một số mã code có thời hạn sử dụng.</li>
                        <li>Điểm sẽ được cộng vào tài khoản của bạn ngay lập tức.</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Right Sidebar - Puntos e historial -->
        <div class="md:col-span-2">
            <!-- Resumen de puntos -->
            <div class="card p-6 rounded-xl mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold" style="color: var(--primary-color)">Điểm của bạn</h2>
                </div>
                
                <div class="flex items-center justify-center py-4">
                    <div class="text-center">
                        <div class="text-4xl font-bold points-display"><?php echo $user['points']; ?></div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">điểm hiện có</div>
                    </div>
                </div>
            </div>
            
            <!-- Historial de canjes -->
            <div class="card p-6 rounded-xl">
                <h2 class="text-lg font-semibold mb-4" style="color: var(--primary-color)">Lịch sử nhập mã</h2>
                
                <?php if (empty($redeem_history)): ?>
                <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                    <i class="fas fa-history text-3xl mb-2"></i>
                    <p>Bạn chưa nhập mã code nào</p>
                </div>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($redeem_history as $redeem): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div>
                            <div class="font-medium"><?php echo htmlspecialchars($redeem['code']); ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                <?php echo date('d/m/Y H:i', strtotime($redeem['redeemed_at'])); ?>
                            </div>
                        </div>
                        <div class="text-green-600 dark:text-green-400 font-semibold">
                            +<?php echo $redeem['points_awarded']; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejar formulario de redención de códigos
    const redeemForm = document.getElementById('redeemCodeForm');
    if (redeemForm) {
        redeemForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const code = document.getElementById('redemptionCode').value;
            const submitBtn = this.querySelector('button[type="submit"]');
            const alertContainer = document.getElementById('redeemAlert');
            
            // Cambiar estado del botón durante el procesamiento
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang xử lý...';
            alertContainer.innerHTML = '';
            
            // Enviar código
            fetch('includes/api/redeem_code.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'code': code
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redención exitosa
                    alertContainer.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle mr-2"></i>${data.message}
                        </div>
                    `;
                    
                    // Actualizar puntos mostrados
                    const pointsDisplay = document.querySelectorAll('.points-display');
                    if (pointsDisplay.length > 0) {
                        pointsDisplay.forEach(display => {
                            const currentPoints = parseInt(display.innerText);
                            if (!isNaN(currentPoints)) {
                                display.innerText = currentPoints + data.points;
                            }
                        });
                    }
                    
                    // Reiniciar formulario
                    document.getElementById('redemptionCode').value = '';
                    
                    // Actualizar historial (se podría hacer por AJAX, pero es más simple recargar)
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                    
                } else {
                    // Redención fallida
                    alertContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle mr-2"></i>${data.message}
                        </div>
                    `;
                }
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Nhận điểm';
            })
            .catch(error => {
                console.error('Error:', error);
                alertContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle mr-2"></i>Có lỗi xảy ra, vui lòng thử lại sau
                    </div>
                `;
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Nhận điểm';
            });
        });
    }
});
</script>