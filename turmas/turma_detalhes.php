<?php
session_start();
require __DIR__ . '/../bd.php'; // bd.php define $conn

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$isADM = isset($_SESSION['tipo']) && $_SESSION['tipo'] === 'ADM';

$turma_id = filter_input(INPUT_GET, 'turma_id', FILTER_VALIDATE_INT);

if (!$turma_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Turma inválida']);
    exit;
}

try {
    $stmtTurma = $conn->prepare("
        SELECT t.id, t.nome, t.criado_em, t.professor_id, u.nome AS professor_nome
        FROM turmas t
        JOIN usuarios u ON u.id = t.professor_id
        WHERE t.id = :turma_id
    ");
    $stmtTurma->execute([':turma_id' => $turma_id]);
    $turma = $stmtTurma->fetch();

    if (!$turma) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Turma não encontrada']);
        exit;
    }

    $souDono = $isADM || (int)$turma['professor_id'] === (int)$user_id;

    if (!$souDono) {
        // Precisa estar matriculado para ver a turma
        $stmtMembro = $conn->prepare("SELECT id FROM turma_alunos WHERE turma_id = :turma_id AND aluno_id = :user_id");
        $stmtMembro->execute([':turma_id' => $turma_id, ':user_id' => $user_id]);
        if (!$stmtMembro->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Você não participa dessa turma']);
            exit;
        }
    }

    // LEFT JOIN com contagem pré-agregada em vez de subquery correlacionada
    // por aluno (evita 1 scan extra de "perguntas" para cada aluno da turma).
    $stmtAlunos = $conn->prepare("
        SELECT u.id, u.nome, u.email, COALESCE(pq.qtd, 0) AS qtd_perguntas
        FROM turma_alunos ta
        JOIN usuarios u ON u.id = ta.aluno_id
        LEFT JOIN (SELECT usuario_id, COUNT(*) AS qtd FROM perguntas GROUP BY usuario_id) pq ON pq.usuario_id = u.id
        WHERE ta.turma_id = :turma_id
        ORDER BY u.nome ASC
    ");
    $stmtAlunos->execute([':turma_id' => $turma_id]);
    $alunos = $stmtAlunos->fetchAll();

    // E-mail dos colegas só é exposto para quem gerencia a turma (professor dono/ADM),
    // nunca para os próprios alunos vendo a turma de que participam.
    if (!$souDono) {
        foreach ($alunos as &$aluno) {
            unset($aluno['email']);
        }
        unset($aluno);
    }

    echo json_encode([
        'success' => true,
        'turma' => [
            'id'              => (int)$turma['id'],
            'nome'            => $turma['nome'],
            'professor_nome'  => $turma['professor_nome'],
            'criado_em'       => $turma['criado_em']
        ],
        'alunos'            => $alunos,
        'pode_gerenciar'    => $souDono,
        'pode_ver_perguntas'=> $souDono
    ]);
} catch (PDOException $e) {
    error_log("Erro ao buscar detalhes da turma: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar detalhes da turma']);
}
