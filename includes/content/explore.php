<?php
// File: includes/content/explore.php
// Obtener temas populares (ejemplo: temas con más preguntas)
requireLogin();
$stmt = $conn->prepare("
    SELECT subject, COUNT(*) as count 
    FROM questions 
    WHERE subject IS NOT NULL AND subject != ''
    GROUP BY subject 
    ORDER BY count DESC 
    LIMIT 12
");
$stmt->execute();
$popular_subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("
    SELECT skill, COUNT(*) as count 
    FROM questions 
    WHERE skill IS NOT NULL AND skill != ''
    GROUP BY skill 
    ORDER BY count DESC 
    LIMIT 12
");
$stmt->execute();
$popular_skills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="max-w-6xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Khám phá</h1>
    
    <!-- Temas populares -->
    <div class="mb-8">
        <h2 class="text-xl font-bold mb-4">Môn học phổ biến</h2>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <?php if (empty($popular_subjects)): ?>
            <div class="col-span-full text-center py-8 text-gray-500 dark:text-gray-400">
                <p>Chưa có dữ liệu về các môn học phổ biến</p>
            </div>
            <?php else: ?>
            <?php foreach ($popular_subjects as $subject): ?>
            <a href="index.php?subject=<?php echo urlencode($subject['subject']); ?>" class="card p-4 rounded-xl text-center hover:shadow-md transition">
                <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-book"></i>
                </div>
                <h3 class="font-medium mb-1"><?php echo htmlspecialchars($subject['subject']); ?></h3>
                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $subject['count']; ?> câu hỏi</p>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Habilidades populares -->
    <div class="mb-8">
        <h2 class="text-xl font-bold mb-4">Kỹ năng được quan tâm</h2>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <?php if (empty($popular_skills)): ?>
            <div class="col-span-full text-center py-8 text-gray-500 dark:text-gray-400">
                <p>Chưa có dữ liệu về các kỹ năng được quan tâm</p>
            </div>
            <?php else: ?>
            <?php foreach ($popular_skills as $skill): ?>
            <a href="index.php?skill=<?php echo urlencode($skill['skill']); ?>" class="card p-4 rounded-xl text-center hover:shadow-md transition">
                <div class="w-12 h-12 rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-brain"></i>
                </div>
                <h3 class="font-medium mb-1"><?php echo htmlspecialchars($skill['skill']); ?></h3>
                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $skill['count']; ?> câu hỏi</p>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bot AI más usados -->
    <div class="mb-8">
        <h2 class="text-xl font-bold mb-4">Bot AI phổ biến</h2>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
            <div class="card p-6 rounded-xl hover:shadow-md transition">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                        <i class="fas fa-robot text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="font-semibold text-lg">o3-mini</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Bot AI tốc độ cao</p>
                    </div>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Phản hồi nhanh, hiệu quả cho các câu hỏi đơn giản và thường gặp. Lý tưởng cho việc tìm kiếm thông tin nhanh chóng.
                </p>
                <div class="mt-auto">
                    <a href="index.php?bot=o3-mini" class="text-purple-600 dark:text-purple-400 hover:underline text-sm font-medium flex items-center">
                        Sử dụng o3-mini
                        <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            
            <div class="card p-6 rounded-xl hover:shadow-md transition">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                        <i class="fas fa-robot text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="font-semibold text-lg">GPT-4o-mini</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Bot AI toàn diện</p>
                    </div>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Chi tiết và toàn diện, phù hợp cho các câu hỏi phức tạp. Cung cấp giải thích sâu sắc và nhiều ví dụ minh họa.
                </p>
                <div class="mt-auto">
                    <a href="index.php?bot=GPT-4o-mini" class="text-blue-600 dark:text-blue-400 hover:underline text-sm font-medium flex items-center">
                        Sử dụng GPT-4o-mini
                        <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            
            <div class="card p-6 rounded-xl hover:shadow-md transition">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                        <i class="fas fa-robot text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="font-semibold text-lg">Claude-3.7-Sonnet</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Bot AI cao cấp</p>
                    </div>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Bot AI chất lượng cao với khả năng phân tích sâu sắc. Hoàn hảo cho các câu hỏi phức tạp, học thuật và nghiên cứu.
                </p>
                <div class="mt-auto">
                    <a href="index.php?bot=Claude-3.7-Sonnet" class="text-indigo-600 dark:text-indigo-400 hover:underline text-sm font-medium flex items-center">
                        Sử dụng Claude-3.7-Sonnet
                        <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sugerencias y consejos -->
    <div>
        <h2 class="text-xl font-bold mb-4">Lời khuyên cho việc học tập hiệu quả</h2>
        
        <div class="card p-6 rounded-xl">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="font-semibold text-lg mb-3 flex items-center" style="color: var(--primary-color)">
                        <i class="fas fa-lightbulb mr-2"></i>Phương pháp học tập
                    </h3>
                    <ul class="space-y-2">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>Chia nhỏ nội dung học tập thành các phần dễ quản lý.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>Sử dụng kỹ thuật ôn tập ngắt quãng để ghi nhớ lâu hơn.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>Kết hợp học tập chủ động (giải bài tập, giải thích) với học tập thụ động (đọc, nghe).</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>Tạo môi trường học tập không bị phân tâm.</span>
                        </li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="font-semibold text-lg mb-3 flex items-center" style="color: var(--primary-color)">
                        <i class="fas fa-brain mr-2"></i>Tối ưu hóa việc sử dụng AI
                    </h3>
                    <ul class="space-y-2">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>Đặt câu hỏi cụ thể để nhận được câu trả lời chất lượng.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>Yêu cầu bot giải thích từng bước thay vì chỉ cung cấp đáp án.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>Kết hợp AI với tài liệu học tập truyền thống để đạt hiệu quả tốt nhất.</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>Sử dụng o3-mini cho câu hỏi đơn giản, Claude-3.7-Sonnet cho câu hỏi phức tạp.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>