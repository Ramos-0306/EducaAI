<?php
session_start();
include __DIR__ . '/../bd.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$usuario = trim($_POST['usuario'] ?? '');
$senha = trim($_POST['senha'] ?? '');
$tipo = trim($_POST['tipo'] ?? '');
$identificacao = trim($_POST['identificacao'] ?? '');

if (!$usuario || !$senha || !$tipo) {
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos']);
    exit;
}

try {
    if ($tipo === 'aluno') {
        // Login de aluno
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE nome = :usuario AND tipo = 'aluno'");
        $stmt->execute(['usuario' => $usuario]);
        $user = $stmt->fetch();

        if ($user && password_verify($senha, $user['senha'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['tipo'] = 'aluno';
            $_SESSION['nome'] = $user['nome'];
            $_SESSION['email'] = $user['email'];
            session_write_close();
            echo json_encode(['success' => true]);
        } else {
            session_write_close();
            echo json_encode(['success' => false, 'message' => 'Nome ou senha incorretos']);
        }

    } elseif ($tipo === 'professor') {
        // Login de professor (inclui ADM)
        if (!$identificacao) {
            echo json_encode(['success' => false, 'message' => 'Identificação é obrigatória para professores']);
            exit;
        }

        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE nome = :usuario AND identificacao = :identificacao AND tipo IN ('professor', 'ADM')");
        $stmt->execute(['usuario' => $usuario, 'identificacao' => $identificacao]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Nome ou identificação incorretos']);
            exit;
        }

        if (password_verify($senha, $user['senha'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['tipo'] = $user['tipo']; // 'professor' ou 'ADM'
            $_SESSION['nome'] = $user['nome'];
            $_SESSION['email'] = $user['email'];
            session_write_close();
            echo json_encode(['success' => true]);
        } else {
            session_write_close();
            echo json_encode(['success' => false, 'message' => 'Senha incorreta']);
        }

    } else {
        echo json_encode(['success' => false, 'message' => 'Tipo de usuário inválido']);
    }

} catch (Exception $e) {
    error_log("Erro no login: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro no servidor. Tente novamente.']);
}