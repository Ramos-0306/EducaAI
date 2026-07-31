<?php
session_start();
require __DIR__ . '/../bd.php'; // bd.php define $conn

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autenticado']);
    exit;
}

$usuario_id = $_SESSION['user_id'];
session_write_close();

try {
    $stmt = $conn->prepare("
        SELECT n.id, n.ator_nome, n.tipo, n.pergunta_id, n.resposta_id, n.turma_id, n.convite_id,
               n.lida, n.criado_em,
               p.descricao AS pergunta_descricao,
               t.nome AS turma_nome
        FROM notificacoes n
        LEFT JOIN perguntas p ON p.id = n.pergunta_id
        LEFT JOIN turmas t ON t.id = n.turma_id
        WHERE n.usuario_id = :usuario_id AND n.lida = false
        ORDER BY n.criado_em DESC
    ");
    $stmt->execute([':usuario_id' => $_SESSION['user_id']]);
    $notificacoes = $stmt->fetchAll();

    echo json_encode(['notificacoes' => $notificacoes]);
} catch (PDOException $e) {
    error_log("Erro ao buscar notificações: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao buscar notificações']);
}
