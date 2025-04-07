<?php
// File: includes/content/history.php
// Verificar que el usuario haya iniciado sesión
requireLogin();

// Obtener historial de preguntas
$questions = getUserQuestions($_SESSION['user_id'], 20); // Obtener las últimas 20 preguntas
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Lịch sử câu hỏi</h1>
    
    <?php if (empty($questions)): ?>
    <div class="card p-6 rounded-xl text-center">
        <div class="text-gray-500 dark:text-gray-400 mb-4">
            <i class="fas fa-history text-5xl"></i>
        </div>
        <h3 class="text-xl font-medium mb-2">Bạn chưa đặt câu hỏi nào</h3>
        <p class="text-gray-500 dark:text-gray-400 mb-4">Hãy quay lại trang chủ để đặt câu hỏi đầu tiên của bạn.</p>
        <a href="index.php" class="btn btn-primary inline-flex items-center">
            <i class="fas fa-home mr-2"></i>Quay về trang chủ
        </a>
    </div>
    <?php else: ?>
    <div class="card rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Thời gian</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Môn học/Kỹ năng</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Chủ đề</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bot</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($questions as $question): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <?php echo date('d/m/Y H:i', strtotime($question['created_at'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <?php if (!empty($question['subject'])): ?>
                            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                <?php echo htmlspecialchars($question['subject']); ?>
                            </span>
                            <?php elseif (!empty($question['skill'])): ?>
                            <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                <?php echo htmlspecialchars($question['skill']); ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                <?php echo htmlspecialchars($question['topic']); ?>
                            </div>
                            <div class="text-gray-500 dark:text-gray-400 text-xs mt-1 line-clamp-1">
                                <?php echo htmlspecialchars(substr($question['content'], 0, 100) . (strlen($question['content']) > 100 ? '...' : '')); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300">
                                <?php echo htmlspecialchars($question['bot_used']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>