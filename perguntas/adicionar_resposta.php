<?php
session_start();
require __DIR__ . '/../bd.php'; // bd.php define $conn

// Apenas usuários logados podem responder
if (!isset($_SESSION['user_id'], $_SESSION['nome'], $_SESSION['tipo'])) {
    header('Location: ../usuarios/login.php');
    exit;
}

// Só aceita requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../materias.php');
    exit;
}

// Validação básica dos dados do POST
$pergunta_id = filter_input(INPUT_POST, 'pergunta_id', FILTER_VALIDATE_INT);
$resposta_texto = trim(filter_input(INPUT_POST, 'resposta', FILTER_DEFAULT) ?? '');

if (!$pergunta_id || empty($resposta_texto)) {
    header('Location: ../materias.php');
    exit;
}

try {
    // Usando $conn, que é o que existe no bd.php
    $stmt = $conn->prepare("
        INSERT INTO respostas (pergunta_id, usuario_id, usuario_nome, usuario_tipo, resposta)
        VALUES (:pergunta_id, :usuario_id, :usuario_nome, :usuario_tipo, :resposta)
        RETURNING id
    ");

    $stmt->execute([
        ':pergunta_id' => $pergunta_id,
        ':usuario_id'  => $_SESSION['user_id'],
        ':usuario_nome'=> $_SESSION['nome'],
        ':usuario_tipo'=> $_SESSION['tipo'],
        ':resposta'    => $resposta_texto
    ]);

    $resposta_id = $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("Erro ao adicionar resposta: " . $e->getMessage());
    header('Location: ../materias.php');
    exit;
}

// Notifica o autor da pergunta (se não for ele mesmo a responder)
try {
    $stmtPergunta = $conn->prepare("SELECT usuario_id FROM perguntas WHERE id = :pergunta_id");
    $stmtPergunta->execute([':pergunta_id' => $pergunta_id]);
    $autorPerguntaId = $stmtPergunta->fetchColumn();

    if ($autorPerguntaId && (int)$autorPerguntaId !== (int)$_SESSION['user_id']) {
        $stmtNotif = $conn->prepare("
            INSERT INTO notificacoes (usuario_id, ator_id, ator_nome, pergunta_id, resposta_id)
            VALUES (:usuario_id, :ator_id, :ator_nome, :pergunta_id, :resposta_id)
        ");
        $stmtNotif->execute([
            ':usuario_id'   => $autorPerguntaId,
            ':ator_id'      => $_SESSION['user_id'],
            ':ator_nome'    => $_SESSION['nome'],
            ':pergunta_id'  => $pergunta_id,
            ':resposta_id'  => $resposta_id
        ]);
    }
} catch (PDOException $e) {
    error_log("Erro ao criar notificação: " . $e->getMessage());
}

header('Location: ../materias.php?toast=resposta_adicionada');
exit;
?>
