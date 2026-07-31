<?php
session_start();
include __DIR__ . '/../bd.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$codigo = trim($_POST['codigo'] ?? '');
$novaSenha = trim($_POST['nova_senha'] ?? '');

if (!$email || !$codigo || !$novaSenha) {
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos']);
    exit;
}

if (strlen($novaSenha) < 6) {
    echo json_encode(['success' => false, 'message' => 'A senha precisa ter pelo menos 6 caracteres']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'Código inválido']);
        exit;
    }

    // Revalida o código do zero (não confia apenas na etapa anterior)
    $stmtCodigo = $conn->prepare("
        SELECT id, codigo, expira_em, tentativas
        FROM codigos_recuperacao
        WHERE usuario_id = :usuario_id AND usado = false
        ORDER BY criado_em DESC
        LIMIT 1
    ");
    $stmtCodigo->execute(['usuario_id' => $usuario['id']]);
    $registro = $stmtCodigo->fetch();

    if (!$registro) {
        echo json_encode(['success' => false, 'message' => 'Nenhum código pendente. Solicite um novo.']);
        exit;
    }

    if ($registro['tentativas'] >= 5) {
        echo json_encode(['success' => false, 'message' => 'Muitas tentativas erradas. Solicite um novo código.']);
        exit;
    }

    if (strtotime($registro['expira_em']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Código expirado. Solicite um novo.']);
        exit;
    }

    if (!hash_equals($registro['codigo'], $codigo)) {
        $conn->prepare("UPDATE codigos_recuperacao SET tentativas = tentativas + 1 WHERE id = :id")
            ->execute(['id' => $registro['id']]);
        echo json_encode(['success' => false, 'message' => 'Código inválido.']);
        exit;
    }

    $conn->beginTransaction();

    $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
    $conn->prepare("UPDATE usuarios SET senha = :senha WHERE id = :id")
        ->execute(['senha' => $hash, 'id' => $usuario['id']]);

    $conn->prepare("UPDATE codigos_recuperacao SET usado = true WHERE id = :id")
        ->execute(['id' => $registro['id']]);

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Senha redefinida com sucesso! Você já pode fazer login.']);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Erro ao redefinir senha: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao redefinir senha']);
}
