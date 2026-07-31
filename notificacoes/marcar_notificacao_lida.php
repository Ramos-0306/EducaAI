<?php
session_start();
require __DIR__ . '/../bd.php'; // bd.php define $conn

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

try {
    if ($id) {
        // Marca apenas uma notificação específica (pertencente ao usuário logado)
        $stmt = $conn->prepare("
            UPDATE notificacoes SET lida = true
            WHERE id = :id AND usuario_id = :usuario_id
        ");
        $stmt->execute([
            ':id'         => $id,
            ':usuario_id' => $_SESSION['user_id']
        ]);
    } else {
        // Marca todas as notificações do usuário como lidas
        $stmt = $conn->prepare("
            UPDATE notificacoes SET lida = true
            WHERE usuario_id = :usuario_id AND lida = false
        ");
        $stmt->execute([':usuario_id' => $_SESSION['user_id']]);
    }

    echo json_encode(['sucesso' => true]);
} catch (PDOException $e) {
    error_log("Erro ao marcar notificação como lida: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao marcar notificação como lida']);
}
