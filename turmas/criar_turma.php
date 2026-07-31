<?php
session_start();
require __DIR__ . '/../bd.php'; // bd.php define $conn

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if (!in_array($_SESSION['tipo'] ?? '', ['professor', 'ADM'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Apenas professores podem criar turmas']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$nome = trim(filter_input(INPUT_POST, 'nome', FILTER_DEFAULT) ?? '');

if ($nome === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Informe um nome para a turma']);
    exit;
}

try {
    $stmtExiste = $conn->prepare("SELECT id FROM turmas WHERE professor_id = :professor_id AND nome = :nome");
    $stmtExiste->execute([
        ':professor_id' => $_SESSION['user_id'],
        ':nome'         => $nome
    ]);
    if ($stmtExiste->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Você já tem uma turma com esse nome.']);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO turmas (nome, professor_id)
        VALUES (:nome, :professor_id)
        RETURNING id, nome, criado_em
    ");
    $stmt->execute([
        ':nome'         => $nome,
        ':professor_id' => $_SESSION['user_id']
    ]);
    $turma = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'turma' => [
            'id'          => (int)$turma['id'],
            'nome'        => $turma['nome'],
            'criado_em'   => $turma['criado_em'],
            'qtd_alunos'  => 0
        ]
    ]);
} catch (PDOException $e) {
    // 23505 = violação de unicidade (rede de segurança contra corrida de requisições simultâneas)
    if ($e->getCode() === '23505') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Você já tem uma turma com esse nome.']);
        exit;
    }
    error_log("Erro ao criar turma: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao criar turma']);
}
