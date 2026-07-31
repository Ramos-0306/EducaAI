<?php
session_start();

// Garante que só ADM pode deletar
if (!isset($_SESSION['tipo']) || $_SESSION['tipo'] !== 'ADM') {
    header("Location: ../materias.php");
    exit;
}

include __DIR__ . '/../bd.php';

// Verifica se os dados vieram corretamente
if (!isset($_GET['id'])) {
    header("Location: ../adm.php");
    exit;
}

$id = (int) $_GET['id'];

// Tabela única — basta deletar pelo ID
$stmt = $conn->prepare("DELETE FROM usuarios WHERE id = :id");
$stmt->execute([':id' => $id]);

// Volta para o painel
header("Location: ../adm.php");
exit;
