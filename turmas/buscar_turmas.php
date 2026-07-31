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
$isProfessorOuADM = in_array($_SESSION['tipo'] ?? '', ['professor', 'ADM']);

try {
    if ($isProfessorOuADM) {
        // Turmas criadas pelo professor logado, com contagem de alunos
        $stmt = $conn->prepare("
            SELECT t.id, t.nome, t.criado_em, COUNT(ta.id) AS qtd_alunos
            FROM turmas t
            LEFT JOIN turma_alunos ta ON ta.turma_id = t.id
            WHERE t.professor_id = :user_id
            GROUP BY t.id, t.nome, t.criado_em
            ORDER BY t.criado_em DESC
        ");
        $stmt->execute([':user_id' => $user_id]);
        $turmas = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'tipo'    => 'professor',
            'turmas'  => $turmas
        ]);
    } else {
        // Convites pendentes do aluno
        $stmtConvites = $conn->prepare("
            SELECT c.id AS convite_id, t.id AS turma_id, t.nome AS turma_nome,
                   u.nome AS professor_nome, c.criado_em
            FROM convites_turma c
            JOIN turmas t ON t.id = c.turma_id
            JOIN usuarios u ON u.id = t.professor_id
            WHERE c.aluno_id = :user_id
            ORDER BY c.criado_em DESC
        ");
        $stmtConvites->execute([':user_id' => $user_id]);
        $convites = $stmtConvites->fetchAll();

        // Turmas em que o aluno já está matriculado
        $stmtTurmas = $conn->prepare("
            SELECT t.id, t.nome, t.criado_em, u.nome AS professor_nome
            FROM turma_alunos ta
            JOIN turmas t ON t.id = ta.turma_id
            JOIN usuarios u ON u.id = t.professor_id
            WHERE ta.aluno_id = :user_id
            ORDER BY ta.entrou_em DESC
        ");
        $stmtTurmas->execute([':user_id' => $user_id]);
        $turmas = $stmtTurmas->fetchAll();

        echo json_encode([
            'success'  => true,
            'tipo'     => 'aluno',
            'convites' => $convites,
            'turmas'   => $turmas
        ]);
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar turmas: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar turmas']);
}
