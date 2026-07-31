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
    echo json_encode(['success' => false, 'message' => 'Apenas professores podem convidar alunos']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$turma_id = filter_input(INPUT_POST, 'turma_id', FILTER_VALIDATE_INT);
$email = trim($_POST['email'] ?? '');

if (!$turma_id || !$email) {
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

    $stmtAluno = $conn->prepare("SELECT id FROM usuarios WHERE email = :email AND tipo = 'aluno'");
    $stmtAluno->execute([':email' => $email]);
    $aluno = $stmtAluno->fetch();

    if (!$aluno) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Aluno não encontrado']);
        exit;
    }

    $aluno_id = $aluno['id'];

    $stmtMatriculado = $conn->prepare("SELECT id FROM turma_alunos WHERE turma_id = :turma_id AND aluno_id = :aluno_id");
    $stmtMatriculado->execute([':turma_id' => $turma_id, ':aluno_id' => $aluno_id]);
    if ($stmtMatriculado->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Esse aluno já faz parte da turma.']);
        exit;
    }

    $stmtConvidado = $conn->prepare("SELECT id FROM convites_turma WHERE turma_id = :turma_id AND aluno_id = :aluno_id");
    $stmtConvidado->execute([':turma_id' => $turma_id, ':aluno_id' => $aluno_id]);
    if ($stmtConvidado->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Esse aluno já foi convidado.']);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO convites_turma (turma_id, aluno_id)
        VALUES (:turma_id, :aluno_id)
        RETURNING id
    ");
    $stmt->execute([
        ':turma_id' => $turma_id,
        ':aluno_id' => $aluno_id
    ]);
    $convite_id = $stmt->fetchColumn();

    // Notifica o aluno pelo sino, igual já acontece quando uma pergunta é respondida
    $conn->prepare("
        INSERT INTO notificacoes (usuario_id, ator_id, ator_nome, tipo, turma_id, convite_id)
        VALUES (:usuario_id, :ator_id, :ator_nome, 'convite_turma', :turma_id, :convite_id)
    ")->execute([
        ':usuario_id' => $aluno_id,
        ':ator_id'    => $_SESSION['user_id'],
        ':ator_nome'  => $_SESSION['nome'],
        ':turma_id'   => $turma_id,
        ':convite_id' => $convite_id
    ]);

    echo json_encode(['success' => true, 'message' => 'Convite enviado!']);
} catch (PDOException $e) {
    error_log("Erro ao convidar aluno: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao convidar aluno']);
}
