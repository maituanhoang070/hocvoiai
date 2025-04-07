<?php
// File: includes/api/submit_assessment.php
require_once '../config.php';
require_once '../functions.php';

// Verificar que el usuario haya iniciado sesión
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để sử dụng tính năng này']);
    exit;
}

// Verificar que la solicitud sea POST
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}

// Recoger datos del formulario
$assessment_id = isset($_POST['assessment_id']) ? intval($_POST['assessment_id']) : 0;
$time_taken = isset($_POST['time_taken']) ? intval($_POST['time_taken']) : 0;
$max_score = isset($_POST['max_score']) ? intval($_POST['max_score']) : 0;
$answers = isset($_POST['answer']) ? $_POST['answer'] : [];
$question_ids = isset($_POST['question_id']) ? $_POST['question_id'] : [];

if (!$assessment_id || empty($question_ids)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
    exit;
}

// Obtener el ID del usuario actual
$user_id = $_SESSION['user_id'];

// Obtener detalles de la evaluación
$stmt = $conn->prepare("SELECT * FROM self_assessments WHERE id = ?");
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();

if (!$assessment) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Bài kiểm tra không tồn tại']);
    exit;
}

// Calcular puntuación
$score = 0;

// Obtener respuestas correctas para cada pregunta
foreach ($question_ids as $question_id) {
    $stmt = $conn->prepare("SELECT * FROM assessment_questions WHERE id = ?");
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $question = $stmt->get_result()->fetch_assoc();
    
    if ($question) {
        $user_answer = isset($answers[$question_id]) ? $answers[$question_id] : '';
        $correct_answer = $question['correct_answer'];
        $points = $question['points'];
        
        // Comparar respuestas según el tipo de pregunta
        if ($question['question_type'] === 'multiple_choice' || $question['question_type'] === 'true_false') {
            // Para opción múltiple o verdadero/falso, la respuesta debe ser exacta
            if (strcasecmp(trim($user_answer), trim($correct_answer)) === 0) {
                $score += $points;
            }
        } else if ($question['question_type'] === 'short_answer') {
            // Para respuesta corta, usar una comparación más flexible
            // Eliminar espacios extra, convertir a minúsculas
            $user_answer_clean = strtolower(trim($user_answer));
            $correct_answer_clean = strtolower(trim($correct_answer));
            
            // Verificar si la respuesta coincide, considerando posibles respuestas alternativas
            $correct_alternatives = explode('|', $correct_answer_clean);
            foreach ($correct_alternatives as $alt) {
                if (strcasecmp(trim($user_answer_clean), trim($alt)) === 0) {
                    $score += $points;
                    break;
                }
            }
        }
    }
}

// Calcular porcentaje
$percentage = ($max_score > 0) ? ($score / $max_score) * 100 : 0;

// Determinar si aprobó
$passed = $percentage >= $assessment['passing_score'];

// Guardar resultado en la base de datos
$stmt = $conn->prepare("
    INSERT INTO assessment_results (user_id, assessment_id, score, max_score, passed, time_taken_seconds, completed_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param("iiiiis", $user_id, $assessment_id, $score, $max_score, $passed, $time_taken);

if ($stmt->execute()) {
    $result_id = $conn->insert_id;
    
    // Actualizar progreso de estudio
    $topic_id = $assessment['topic_id'];
    
    // Verificar si existe un registro de progreso
    $stmt = $conn->prepare("SELECT id, mastery_level FROM user_study_progress WHERE user_id = ? AND topic_id = ?");
    $stmt->bind_param("ii", $user_id, $topic_id);
    $stmt->execute();
    $progress = $stmt->get_result()->fetch_assoc();
    
    if ($progress) {
        // Calcular nuevo nivel de dominio (promedio)
        $new_mastery = max(min(100, $progress['mastery_level'] + ($passed ? 10 : -5)), 0);
        
        // Actualizar progreso
        $stmt = $conn->prepare("
            UPDATE user_study_progress 
            SET mastery_level = ?, exercises_completed = exercises_completed + 1, last_studied = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $new_mastery, $progress['id']);
        $stmt->execute();
    } else {
        // Crear nuevo registro de progreso
        $initial_mastery = $passed ? 10 : 5;
        $stmt = $conn->prepare("
            INSERT INTO user_study_progress (user_id, topic_id, exercises_completed, mastery_level, last_studied)
            VALUES (?, ?, 1, ?, NOW())
        ");
        $stmt->bind_param("iii", $user_id, $topic_id, $initial_mastery);
        $stmt->execute();
    }
    
    // Devolver éxito
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'result_id' => $result_id]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Không thể lưu kết quả: ' . $conn->error]);
}
?>