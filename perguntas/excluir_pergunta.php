<?php
session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Acesso negado.";
    exit;
}

$isADM = isset($_SESSION['tipo']) && $_SESSION['tipo'] === 'ADM';
$id = $_POST['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo "ID inválido.";
    exit;
}

// Conexão centralizada
include __DIR__ . '/../bd.php';

// Verificar se usuário pode deletar (usa usuario_id em vez de email)
$stmt = $conn->prepare("SELECT usuario_id FROM perguntas WHERE id = ?");
$stmt->execute([$id]);
$pergunta = $stmt->fetch();

if (!$pergunta) {
    http_response_code(404);
    echo "Pergunta não encontrada.";
    exit;
}

if (!$isADM && $_SESSION['user_id'] != $pergunta['usuario_id']) {
    http_response_code(403);
    echo "Você não tem permissão.";
    exit;
}

// Deletar pergunta (respostas são deletadas automaticamente pela FK ON DELETE CASCADE)
$conn->prepare("DELETE FROM perguntas WHERE id = ?")->execute([$id]);

echo "Pergunta excluída com sucesso.";
