<?php
// File: includes/api/web_search_proxy.php
require_once '../config.php';

// Thư viện để parse HTML (cần tải về từ http://simplehtmldom.sourceforge.net/)
require_once '../simple_html_dom.php';

header('Content-Type: application/json');

// Đọc dữ liệu từ yêu cầu
$input = json_decode(file_get_contents('php://input'), true);
$prompt = isset($input['prompt']) ? $input['prompt'] : '';
$bot = isset($input['bot']) ? $input['bot'] : 'HocBai';

if (empty($prompt)) {
    echo json_encode([
        'success' => false,
        'message' => 'Prompt không được để trống'
    ]);
    exit;
}

// Tạo truy vấn tìm kiếm từ prompt
$searchQuery = urlencode($prompt);

// Gọi Google Custom Search API
$apiKey = GOOGLE_API_KEY;
$cseId = GOOGLE_CSE_ID;
$searchUrl = "https://www.googleapis.com/customsearch/v1?key=$apiKey&cx=$cseId&q=$searchQuery&num=5";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $searchUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi khi gọi Google Custom Search API: ' . $response
    ]);
    exit;
}

// Phân tích kết quả tìm kiếm
$searchResults = json_decode($response, true);
$items = $searchResults['items'] ?? [];

if (empty($items)) {
    echo json_encode([
        'success' => false,
        'message' => 'Không tìm thấy thông tin phù hợp trên web'
    ]);
    exit;
}

// Tổng hợp câu trả lời
$responseContent = "# Phản hồi cho câu hỏi của bạn\n\n";
$responseContent .= "## Câu hỏi\n\n";
$responseContent .= $prompt . "\n\n";
$responseContent .= "## Thông tin từ web\n\n";

$additionalContent = '';
foreach ($items as $index => $item) {
    $title = $item['title'] ?? 'Không có tiêu đề';
    $link = $item['link'] ?? '#';
    $snippet = $item['snippet'] ?? 'Không có nội dung tóm tắt';
    
    $responseContent .= "### Kết quả " . ($index + 1) . ": [$title]($link)\n";
    $responseContent .= "$snippet\n\n";

    // Trích xuất nội dung từ webpage (chỉ lấy từ kết quả đầu tiên để tối ưu tốc độ)
    if ($index === 0) {
        $html = @file_get_html($link);
        if ($html) {
            $paragraphs = $html->find('p');
            $content = '';
            foreach ($paragraphs as $p) {
                $content .= $p->plaintext . "\n";
                if (strlen($content) > 500) break; // Giới hạn độ dài nội dung
            }
            $additionalContent = $content;
            $html->clear();
            unset($html);
        }
    }
}

$responseContent .= "## Tổng hợp\n\n";
$responseContent .= "Dựa trên các thông tin thu thập được, đây là câu trả lời chi tiết:\n\n";
$responseContent .= "- **Thông tin chính**: " . ($items[0]['snippet'] ?? 'Không có thông tin') . "\n\n";
if ($additionalContent) {
    $responseContent .= "- **Chi tiết từ nguồn**: \n\n" . substr($additionalContent, 0, 500) . "...\n\n";
}
$responseContent .= "- **Nguồn tham khảo**: Các liên kết phía trên có thể cung cấp thêm chi tiết.\n\n";
$responseContent .= "Nếu bạn cần giải thích sâu hơn, vui lòng cung cấp thêm thông tin!";

echo json_encode([
    'success' => true,
    'response' => $responseContent
]);