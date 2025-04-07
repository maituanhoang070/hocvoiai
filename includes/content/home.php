<?php
// File: content/home.php
requireLogin();
$userPoints = 0;
if (isLoggedIn()) {
    $userInfo = getUserInfo($_SESSION['user_id']);
    $userPoints = $userInfo['points'];
}
?>

<!-- Home Page Content -->
<div class="flex flex-col lg:flex-row gap-6">
    <?php if (isLoggedIn() && $userInfo): ?>
        <div id="userData" class="hidden" data-user-id="<?php echo $userInfo['id']; ?>"></div>
    <?php endif; ?>

    <div class="w-full lg:w-96 lg:flex-shrink-0 order-2 lg:order-1">
        <div class="flex overflow-x-auto hide-scrollbar mb-4 lg:hidden border-b border-gray-200 dark:border-gray-700">
            <button class="tab-btn active px-4 py-2 whitespace-nowrap" data-tab="monhoc">
                <i class="fas fa-book mr-2"></i>Môn học
            </button>
            <button class="tab-btn px-4 py-2 whitespace-nowrap" data-tab="kynang">
                <i class="fas fa-brain mr-2"></i>Kỹ năng
            </button>
            <button class="tab-btn px-4 py-2 whitespace-nowrap" data-tab="botai">
                <i class="fas fa-robot mr-2"></i>Bot AI
            </button>
            <button class="tab-btn px-4 py-2 whitespace-nowrap" data-tab="huongdan">
                <i class="fas fa-info-circle mr-2"></i>Hướng dẫn
            </button>
        </div>

        <div id="monhoc-content" class="tab-content card rounded-xl p-4 mb-4 fade-in">
            <h2 class="text-xl font-semibold mb-4 hidden lg:block" style="color: var(--primary-color)">
                <i class="fas fa-book mr-2"></i>Môn học
            </h2>
            <div class="flex flex-wrap gap-2">
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-subject="Đạo Đức">Đạo Đức</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-subject="Toán">Toán</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-subject="Lý">Lý</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-subject="Hóa">Hóa</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-subject="Văn">Văn</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-subject="Anh">Anh</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-subject="Sinh">Sinh</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-subject="Sử">Sử</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-subject="Địa">Địa</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-subject="GDCD">GDCD</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-subject="Tin">Tin học</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-subject="Khác">Khác</span>
            </div>
        </div>

        <div id="kynang-content" class="tab-content card rounded-xl p-4 mb-4 fade-in hidden">
            <h2 class="text-xl font-semibold mb-4 hidden lg:block" style="color: var(--primary-color)">
                <i class="fas fa-brain mr-2"></i>Kỹ năng phát triển
            </h2>
            <div class="flex flex-wrap gap-2">
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-skill="Tư duy phản biện">Tư duy phản biện</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-skill="Phòng tránh lừa đảo">Phòng tránh lừa đảo</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-skill="Đạo đức và ứng xử">Đạo đức và ứng xử</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-skill="Quản lý thời gian">Quản lý thời gian</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-skill="Tài chính cá nhân">Tài chính cá nhân</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-skill="Giao tiếp nhóm">Giao tiếp nhóm</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-skill="Tự chăm sóc">Tự chăm sóc</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-skill="An toàn công nghệ">An toàn công nghệ</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-skill="Giải quyết vấn đề">Giải quyết vấn đề</span>
                <span class="subject-tag px-3 py-2 rounded-lg text-sm" data-skill="Định hướng nghề">Định hướng nghề</span>
            </div>
        </div>

        <!-- Bot AI tab content -->
<div id="botai-content" class="tab-content card rounded-xl p-4 mb-4 fade-in hidden">
    <h2 class="text-xl font-semibold mb-4 hidden lg:block" style="color: var(--primary-color)">
        <i class="fas fa-robot mr-2"></i>Chọn Bot AI
    </h2>
    <div class="space-y-3">
        <label class="flex items-center p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer transition">
            <input type="radio" id="hocbai" name="bot" value="HocBai" class="h-4 w-4" checked>
            <div class="ml-3">
                <span class="font-medium block">HocBai</span>
                <span class="text-sm text-gray-500 dark:text-gray-400">Nhanh, hiệu quả (Miễn phí)</span>
            </div>
        </label>
        
        <label class="flex items-center p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer transition">
            <input type="radio" id="gpt4mini" name="bot" value="GPT-4o-mini" class="h-4 w-4">
            <div class="ml-3">
                <span class="font-medium block">GPT-4o-mini</span>
                <span class="text-sm text-gray-500 dark:text-gray-400">Chi tiết, toàn diện (Miễn phí)</span>
            </div>
        </label>
        
        <label class="flex items-center p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer transition">
            <input type="radio" id="claudesonnet" name="bot" value="Claude-3.7-Sonnet" class="h-4 w-4">
            <div class="ml-3">
                <span class="font-medium block">Claude-3.7-Sonnet</span>
                <span class="text-sm text-gray-500 dark:text-gray-400">Chất lượng cao (Premium)</span>
            </div>
        </label>
    </div>
</div>

        <div id="huongdan-content" class="tab-content card rounded-xl p-4 mb-4 fade-in hidden">
            <h2 class="text-xl font-semibold mb-4" style="color: var(--primary-color)">
                <i class="fas fa-info-circle mr-2"></i>Hướng dẫn sử dụng
            </h2>
            <ol class="list-decimal pl-5 space-y-2 text-sm">
                <li class="mb-2"><strong>Chọn môn học hoặc kỹ năng</strong> bạn muốn tìm hiểu.</li>
                <li class="mb-2"><strong>Nhập chủ đề cụ thể</strong> và nội dung câu hỏi của bạn.</li>
                <li class="mb-2"><strong>Chọn bot AI</strong> phù hợp với nhu cầu của bạn.</li>
                <li class="mb-2"><strong>Nhấn Gửi câu hỏi</strong> và đợi phản hồi.</li>
            </ol>
            <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg text-sm">
                <strong class="text-blue-600 dark:text-blue-400"><i class="fas fa-lightbulb mr-1"></i> Mẹo:</strong> 
                Nêu rõ yêu cầu cụ thể để nhận được câu trả lời chính xác nhất.
            </div>
        </div>

        <?php if(isLoggedIn()): ?>
        <div class="card rounded-xl p-4 mb-4">
            <h2 class="text-xl font-semibold mb-3" style="color: var(--primary-color)">
                <i class="fas fa-coins mr-2"></i>Điểm của bạn
            </h2>
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-2xl font-bold points-display"><?php echo $userPoints; ?></span>
                    <span class="text-gray-500 dark:text-gray-400 text-sm ml-2">điểm</span>
                </div>
                <a href="index.php?page=redeem" class="btn btn-primary text-sm py-1">
                    <i class="fas fa-gift mr-1"></i>Nhập mã
                </a>
            </div>
            <div class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                <p>Mỗi câu hỏi tiêu tốn <strong>1 điểm</strong></p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="w-full order-1 lg:order-2">
        <div class="card rounded-xl p-4 mb-6">
            <div class="mb-4">
                <label for="topic" class="block text-sm font-medium mb-2">Chủ đề cụ thể:</label>
                <input type="text" id="topic" 
                       placeholder="Ví dụ: Hàm số bậc 2, Phản ứng oxi hóa khử..." 
                       class="w-full text-base">
            </div>
            <div class="mb-4">
                <label for="exercise" class="block text-sm font-medium mb-2">Bài tập hoặc câu hỏi:</label>
                <textarea id="exercise" rows="3" 
                          placeholder="Nhập bài tập hoặc câu hỏi của bạn ở đây..." 
                          class="w-full text-base"></textarea>
            </div>
            <div class="mb-4">
                <label for="detail" class="block text-sm font-medium mb-2">Yêu cầu chi tiết (không bắt buộc):</label>
                <input type="text" id="detail" 
                       placeholder="Ví dụ: Cần công thức tính, phương pháp giải chi tiết..." 
                       class="w-full text-base">
            </div>
            <div class="flex justify-center">
                <button id="submitBtn" 
                        class="btn btn-primary px-6 py-3 rounded-lg text-white font-medium text-base flex items-center justify-center w-full sm:w-auto min-w-[180px]">
                    <span>Gửi câu hỏi</span>
                    <i class="fas fa-paper-plane ml-2"></i>
                </button>
            </div>
            
            <?php if(!isLoggedIn()): ?>
            <div class="text-center mt-4">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Đăng nhập để lưu lịch sử và nhận điểm thưởng</p>
                <a href="index.php?page=login" class="btn btn-secondary text-sm py-2 px-4">
                    <i class="fas fa-sign-in-alt mr-1"></i>Đăng nhập
                </a>
            </div>
            <?php endif; ?>
        </div>

        <div id="results" class="card rounded-xl p-4 mb-6 hidden">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold" style="color: var(--primary-color)">Kết quả</h2>
                <div id="botResponding" class="text-sm font-medium text-gray-500 dark:text-gray-400 hidden">
                    <span id="botName"></span>
                    <span class="ml-2 inline-block">
                        <span class="animate-pulse">●</span>
                        <span class="animate-pulse delay-100">●</span>
                        <span class="animate-pulse delay-200">●</span>
                    </span>
                </div>
            </div>
            <div id="loadingIndicator" class="pulse py-6 text-center">
                <p class="mb-3">Đang xử lý câu hỏi của bạn...</p>
                <div class="flex justify-center">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-t-2" style="border-color: var(--primary-color)"></div>
                </div>
            </div>
            <div id="responseContent" class="response-content"></div>
        </div>

        <div class="card rounded-xl p-4 hidden lg:block">
            <h2 class="text-xl font-semibold mb-4" style="color: var(--primary-color)">
                <i class="fas fa-info-circle mr-2"></i>Hướng dẫn sử dụng
            </h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <h3 class="font-medium mb-2 text-lg">Các bước sử dụng:</h3>
                    <ol class="list-decimal pl-5 space-y-1 text-sm">
                        <li><strong>Chọn môn học</strong> hoặc kỹ năng bạn muốn tìm hiểu.</li>
                        <li><strong>Nhập chủ đề cụ thể</strong> cần hỗ trợ.</li>
                        <li><strong>Cung cấp bài tập</strong> hoặc câu hỏi.</li>
                        <li><strong>Yêu cầu chi tiết</strong> về cách giải, công thức nếu cần.</li>
                        <li><strong>Chọn Bot AI</strong> phù hợp với nhu cầu.</li>
                    </ol>
                </div>
                <div>
                    <h3 class="font-medium mb-2 text-lg">Mẹo sử dụng:</h3>
                    <ul class="list-disc pl-5 space-y-1 text-sm">
                        <li><strong>Trình bày rõ ràng</strong> để nhận được câu trả lời chính xác.</li>
                        <li>Với các bài toán, hãy <strong>cung cấp đầy đủ dữ kiện</strong>.</li>
                        <li>Nếu câu trả lời khó hiểu, <strong>yêu cầu giải thích chi tiết hơn</strong>.</li>
                        <li>Sử dụng Grok AI để có <strong>đáp án chi tiết và dễ hiểu</strong>.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            const tabId = this.getAttribute('data-tab');
            document.getElementById(`${tabId}-content`).classList.remove('hidden');
        });
    });
    
    function updateTabVisibility() {
        const isDesktop = window.innerWidth >= 1024;
        
        if (isDesktop) {
            document.querySelectorAll('.tab-content').forEach(content => {
                if (content.id !== 'huongdan-content') {
                    content.classList.remove('hidden');
                }
            });
        } else {
            const activeTab = document.querySelector('.tab-btn.active').getAttribute('data-tab');
            document.querySelectorAll('.tab-content').forEach(content => {
                if (content.id !== `${activeTab}-content`) {
                    content.classList.add('hidden');
                } else {
                    content.classList.remove('hidden');
                }
            });
        }
    }
    
    updateTabVisibility();
    window.addEventListener('resize', updateTabVisibility);
});
</script>