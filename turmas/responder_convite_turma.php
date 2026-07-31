<?php
session_start();
require __DIR__ . '/../bd.php'; // bd.php define $conn

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$convite_id = filter_input(INPUT_POST, 'convite_id', FILTER_VALIDATE_INT);
$acao = $_POST['acao'] ?? '';

if (!$convite_id || !in_array($acao, ['aceitar', 'recusar'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT turma_id, aluno_id FROM convites_turma WHERE id = :id");
    $stmt->execute([':id' => $convite_id]);
    $convite = $stmt->fetch();

    if (!$convite) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Convite não encontrado']);
        exit;
    }

    if ((int)$convite['aluno_id'] !== (int)$_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Este convite não é seu']);
        exit;
    }

    $conn->beginTransaction();

    if ($acao === 'aceitar') {
        $conn->prepare("
            INSERT INTO turma_alunos (turma_id, aluno_id)
            VALUES (:turma_id, :aluno_id)
            ON CONFLICT (turma_id, aluno_id) DO NOTHING
        ")->execute([
            ':turma_id' => $convite['turma_id'],
            ':aluno_id' => $convite['aluno_id']
        ]);
    }

    $conn->prepare("DELETE FROM convites_turma WHERE id = :id")->execute([':id' => $convite_id]);

    $conn->commit();

    echo json_encode([
        'success'  => true,
        'acao'     => $acao,
        'turma_id' => (int)$convite['turma_id']
    ]);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Erro ao responder convite: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao responder convite']);
}
