// File: assets/js/home.js
document.addEventListener('DOMContentLoaded', function() {
    // Xử lý chọn môn học/kỹ năng
    let selectedSubject = '';
    let selectedSkill = '';
    
    // Xử lý chọn môn học
    document.querySelectorAll('.subject-tag[data-subject]').forEach(tag => {
        tag.addEventListener('click', function() {
            document.querySelectorAll('.subject-tag[data-subject]').forEach(t => {
                t.classList.remove('selected');
            });
            this.classList.add('selected');
            selectedSubject = this.getAttribute('data-subject');
            selectedSkill = '';
            document.querySelectorAll('.subject-tag[data-skill]').forEach(t => {
                t.classList.remove('selected');
            });
        });
    });
    
    // Xử lý chọn kỹ năng
    document.querySelectorAll('.subject-tag[data-skill]').forEach(tag => {
        tag.addEventListener('click', function() {
            document.querySelectorAll('.subject-tag[data-skill]').forEach(t => {
                t.classList.remove('selected');
            });
            this.classList.add('selected');
            selectedSkill = this.getAttribute('data-skill');
            selectedSubject = '';
            document.querySelectorAll('.subject-tag[data-subject]').forEach(t => {
                t.classList.remove('selected');
            });
        });
    });
    
    // Xử lý gửi câu hỏi
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        submitBtn.addEventListener('click', async function() {
            const topic = document.getElementById('topic').value.trim();
            const exercise = document.getElementById('exercise').value.trim();
            const detail = document.getElementById('detail').value.trim();
            
            // Get selected bot
            const selectedBot = document.querySelector('input[name="bot"]:checked').value;
            const botName = document.querySelector('input[name="bot"]:checked').parentElement.querySelector('.font-medium').textContent;
            
            // Basic validation
            if ((!selectedSubject && !selectedSkill) || (exercise === '' && topic === '')) {
                alert('Vui lòng chọn một môn học hoặc kỹ năng, và nhập chủ đề hoặc bài tập');
                return;
            }
            
            // Kiểm tra đăng nhập
            const loggedInUser = document.getElementById('userData');
            if (!loggedInUser) {
                // Hiển thị thông báo đăng nhập
                const loginModal = document.getElementById('loginPromptModal');
                if (loginModal) {
                    loginModal.classList.add('active');
                    return;
                } else {
                    alert('Vui lòng đăng nhập để sử dụng tính năng này');
                    window.location.href = 'index.php?page=login';
                    return;
                }
            }
            
            // Kiểm tra điểm
            const pointsDisplay = document.querySelector('.points-display');
            if (pointsDisplay) {
                const currentPoints = parseInt(pointsDisplay.innerText);
                if (isNaN(currentPoints) || currentPoints < 1) {
                    alert('Bạn không đủ điểm để đặt câu hỏi. Vui lòng nhập mã code để nhận thêm điểm.');
                    return;
                }
            }
            
            // Show results section and loading indicator
            document.getElementById('results').classList.remove('hidden');
            document.getElementById('loadingIndicator').style.display = 'block';
            document.getElementById('responseContent').innerHTML = '';
            
            // Show which bot is responding
            document.getElementById('botResponding').classList.remove('hidden');
            document.getElementById('botName').textContent = botName;
            
            // Build the prompt
            let prompt = '';
            
            if (selectedSubject) {
                prompt += `Môn học: ${selectedSubject}\n`;
            } else if (selectedSkill) {
                prompt += `Kỹ năng: ${selectedSkill}\n`;
            }
            
            if (topic) {
                prompt += `Chủ đề: ${topic}\n`;
            }
            
            if (exercise) {
                prompt += `Bài tập/Câu hỏi: ${exercise}\n`;
            }
            
            if (detail) {
                prompt += `Yêu cầu chi tiết: ${detail}\n`;
            }
            
            prompt += '\nVui lòng hỗ trợ học tập theo yêu cầu trên. Phân tích kỹ, giải thích chi tiết theo từng bước và cung cấp ví dụ cụ thể nếu có thể. Trả lời bằng tiếng Việt và kết cấu rõ ràng bằng markdown.';
            
            try {
                // Scroll to results if on mobile
                if (window.innerWidth < 768) {
                    document.getElementById('results').scrollIntoView({ behavior: 'smooth' });
                }
                
                // Lưu lịch sử câu hỏi và trừ điểm
                const saveQuestionResponse = await fetch('includes/api/add_question.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'subject': selectedSubject,
                        'skill': selectedSkill,
                        'topic': topic,
                        'content': exercise + (detail ? '\n' + detail : ''),
                        'bot_used': selectedBot
                    })
                });
                const saveQuestionData = await saveQuestionResponse.json();
                if (saveQuestionData.success) {
                    // Cập nhật điểm hiển thị
                    if (pointsDisplay) {
                        const currentPoints = parseInt(pointsDisplay.innerText);
                        if (!isNaN(currentPoints)) {
                            pointsDisplay.innerText = currentPoints - 1;
                        }
                    }
                } else {
                    console.error('Failed to save question:', saveQuestionData.message);
                }

                // Gọi proxy server để tìm kiếm trên web
                const proxyResponse = await fetch('includes/api/web_search_proxy.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        prompt: prompt,
                        bot: selectedBot
                    })
                });

                const proxyData = await proxyResponse.json();
                if (proxyData.success) {
                    // Hiển thị kết quả từ web search
                    document.getElementById('responseContent').innerHTML = marked.parse(proxyData.response);
                } else {
                    document.getElementById('responseContent').innerHTML = `
                        <div class="p-4 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg">
                            <p><i class="fas fa-exclamation-circle mr-2"></i> Lỗi: ${proxyData.message || "Không thể xử lý yêu cầu"}</p>
                        </div>`;
                }
            } catch (error) {
                console.error("Error:", error);
                document.getElementById('responseContent').innerHTML = `
                    <div class="p-4 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg">
                        <p><i class="fas fa-exclamation-circle mr-2"></i> Lỗi: ${error.message || "Không thể xử lý yêu cầu"}</p>
                    </div>`;
            } finally {
                document.getElementById('loadingIndicator').style.display = 'none';
                document.getElementById('botResponding').classList.add('hidden');
            }
        });
    }
    
    // Xử lý modal đăng nhập (nếu có)
    const closeModalBtns = document.querySelectorAll('.close-modal');
    if (closeModalBtns) {
        closeModalBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const modal = this.closest('.modal-backdrop');
                if (modal) {
                    modal.classList.remove('active');
                }
            });
        });
    }
    
    // Đóng modal khi click bên ngoài
    const modals = document.querySelectorAll('.modal-backdrop');
    if (modals) {
        modals.forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    }
});