<?php
session_start();
require __DIR__ . '/../bd.php'; // bd.php define $conn

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$isADM = isset($_SESSION['tipo']) && $_SESSION['tipo'] === 'ADM';
$isProfessorOuADM = in_array($_SESSION['tipo'] ?? '', ['professor', 'ADM']);

if (!$isProfessorOuADM) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Apenas professores podem ver as perguntas dos alunos']);
    exit;
}

$turma_id = filter_input(INPUT_GET, 'turma_id', FILTER_VALIDATE_INT);
$aluno_id = filter_input(INPUT_GET, 'aluno_id', FILTER_VALIDATE_INT);

if (!$turma_id || !$aluno_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

try {
    $stmtTurma = $conn->prepare("SELECT professor_id FROM turmas WHERE id = :turma_id");
    $stmtTurma->execute([':turma_id' => $turma_id]);
    $turma = $stmtTurma->fetch();

    if (!$turma) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Turma não encontrada']);
        exit;
    }

    if (!$isADM && (int)$turma['professor_id'] !== (int)$_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Você não gerencia essa turma']);
        exit;
    }

    $stmtMembro = $conn->prepare("SELECT id FROM turma_alunos WHERE turma_id = :turma_id AND aluno_id = :aluno_id");
    $stmtMembro->execute([':turma_id' => $turma_id, ':aluno_id' => $aluno_id]);
    if (!$stmtMembro->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Aluno não faz parte dessa turma']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT id, descricao, materia, criado_em
        FROM perguntas
        WHERE usuario_id = :aluno_id
        ORDER BY criado_em DESC
    ");
    $stmt->execute([':aluno_id' => $aluno_id]);

    echo json_encode(['success' => true, 'perguntas' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    error_log("Erro ao buscar perguntas do aluno: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar perguntas do aluno']);
}
