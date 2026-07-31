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

if (!$email || !$codigo) {
    echo json_encode(['success' => false, 'message' => 'Informe o e-mail e o código']);
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

    echo json_encode(['success' => true, 'message' => 'Código válido!']);
} catch (PDOException $e) {
    error_log("Erro ao verificar código: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao verificar código']);
}
