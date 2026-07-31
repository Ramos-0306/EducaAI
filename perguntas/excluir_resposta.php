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

session_write_close();

// Conexão centralizada
include __DIR__ . '/../bd.php';

$stmt = $conn->prepare("SELECT usuario_id FROM respostas WHERE id = ?");
$stmt->execute([$id]);
$resposta = $stmt->fetch();

if (!$resposta) {
    http_response_code(404);
    echo "Resposta não encontrada.";
    exit;
}

if (!$isADM && $_SESSION['user_id'] != $resposta['usuario_id']) {
    http_response_code(403);
    echo "Você não tem permissão.";
    exit;
}

$conn->prepare("DELETE FROM respostas WHERE id = ?")->execute([$id]);

echo "Resposta excluída com sucesso.";
