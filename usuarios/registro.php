<?php
session_start();
include __DIR__ . '/../bd.php';
header('Content-Type: application/json');

$nome = $_POST['nome'] ?? '';
$email = $_POST['email'] ?? '';
$senha = $_POST['senha'] ?? '';

// Whitelist: cadastro público só pode criar 'aluno' ou 'professor'.
// 'ADM' nunca pode ser definido por este endpoint, senão qualquer pessoa
// conseguiria se autopromover a administrador.
$tipoRecebido = $_POST['tipo'] ?? 'aluno';
$tipo = in_array($tipoRecebido, ['aluno', 'professor'], true) ? $tipoRecebido : 'aluno';

if (!$nome || !$email || !$senha) {
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos']);
    exit;
}

try {
    // 1️⃣ Verifica se o email já existe
    $stmt = $conn->prepare("SELECT email FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Este email já está cadastrado']);
        exit;
    }

    // 2️⃣ Hash da senha
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    // 3️⃣ Cadastro (tabela única)
    $stmt = $conn->prepare(
        "INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$nome, $email, $senhaHash, $tipo]);

    if ($tipo === 'aluno') {
        // Login automático do aluno
        $idInserido = $conn->lastInsertId('usuarios_id_seq');
        $_SESSION['user_id'] = $idInserido;
        $_SESSION['tipo'] = 'aluno';
        $_SESSION['nome'] = $nome;
        $_SESSION['email'] = $email;

        echo json_encode(['success' => true, 'tipo' => 'aluno']);
    } else {
        // Professor — aguarda identificação por email
        echo json_encode(['success' => true, 'tipo' => 'professor']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar']);
}
