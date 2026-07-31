<?php
session_start();
require __DIR__ . '/../bd.php'; // bd.php define $conn

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$ultimoId = filter_input(INPUT_GET, 'ultimo_id', FILTER_VALIDATE_INT) ?: 0;
$ultimaRespostaId = filter_input(INPUT_GET, 'ultima_resposta_id', FILTER_VALIDATE_INT) ?: 0;
$materiaSelecionada = $_GET['materia'] ?? '';
$idsVisiveis = array_values(array_unique(array_filter(
    array_map('intval', explode(',', $_GET['ids_visiveis'] ?? '')),
    fn($v) => $v > 0
)));
$idsRespostasVisiveis = array_values(array_unique(array_filter(
    array_map('intval', explode(',', $_GET['ids_respostas_visiveis'] ?? '')),
    fn($v) => $v > 0
)));

$user_id = $_SESSION['user_id'];
$isADM = isset($_SESSION['tipo']) && $_SESSION['tipo'] === 'ADM';

// Liberta o lock do ficheiro de sessão assim que possível: este endpoint é
// chamado em polling contínuo e, enquanto guarda a sessão aberta, bloqueia
// qualquer outro pedido do mesmo utilizador (ex.: clicar num filtro) até terminar.
session_write_close();

try {
    if ($materiaSelecionada !== '') {
        $stmt = $conn->prepare("
            SELECT p.id, p.materia, p.descricao, p.criado_em, p.usuario_id,
                   u.nome AS usuario_nome, u.tipo AS usuario_tipo
            FROM perguntas p
            JOIN usuarios u ON p.usuario_id = u.id
            WHERE p.id > :ultimo_id AND p.materia = :materia
            ORDER BY p.criado_em ASC
        ");
        $stmt->execute([':ultimo_id' => $ultimoId, ':materia' => $materiaSelecionada]);
    } else {
        $stmt = $conn->prepare("
            SELECT p.id, p.materia, p.descricao, p.criado_em, p.usuario_id,
                   u.nome AS usuario_nome, u.tipo AS usuario_tipo
            FROM perguntas p
            JOIN usuarios u ON p.usuario_id = u.id
            WHERE p.id > :ultimo_id
            ORDER BY p.criado_em ASC
        ");
        $stmt->execute([':ultimo_id' => $ultimoId]);
    }

    $perguntas = array_map(function ($p) use ($isADM, $user_id) {
        return [
            'id'            => (int)$p['id'],
            'materia'       => $p['materia'],
            'descricao'     => $p['descricao'],
            'criado_em'     => $p['criado_em'],
            'usuario_nome'  => $p['usuario_nome'],
            'usuario_tipo'  => $p['usuario_tipo'],
            'pode_excluir'  => $isADM || (int)$p['usuario_id'] === (int)$user_id,
        ];
    }, $stmt->fetchAll());

    // Perguntas que sumiram (excluídas por outro usuário) desde a última checagem
    $perguntasRemovidas = [];
    if ($idsVisiveis) {
        $placeholders = implode(',', array_fill(0, count($idsVisiveis), '?'));
        $stmtExistentes = $conn->prepare("SELECT id FROM perguntas WHERE id IN ($placeholders)");
        $stmtExistentes->execute($idsVisiveis);
        $existentes = array_map('intval', array_column($stmtExistentes->fetchAll(), 'id'));
        $perguntasRemovidas = array_values(array_diff($idsVisiveis, $existentes));
    }

    // Novas respostas desde a última checagem (denormalizadas: usuario_nome/tipo já ficam na própria tabela)
    if ($materiaSelecionada !== '') {
        $stmtResp = $conn->prepare("
            SELECT r.id, r.pergunta_id, r.usuario_id, r.usuario_nome, r.usuario_tipo, r.resposta, r.criado_em
            FROM respostas r
            JOIN perguntas p ON p.id = r.pergunta_id
            WHERE r.id > :ultima_resposta_id AND p.materia = :materia
            ORDER BY r.criado_em ASC
        ");
        $stmtResp->execute([':ultima_resposta_id' => $ultimaRespostaId, ':materia' => $materiaSelecionada]);
    } else {
        $stmtResp = $conn->prepare("
            SELECT id, pergunta_id, usuario_id, usuario_nome, usuario_tipo, resposta, criado_em
            FROM respostas
            WHERE id > :ultima_resposta_id
            ORDER BY criado_em ASC
        ");
        $stmtResp->execute([':ultima_resposta_id' => $ultimaRespostaId]);
    }

    $novasRespostas = array_map(function ($r) use ($isADM, $user_id) {
        return [
            'id'           => (int)$r['id'],
            'pergunta_id'  => (int)$r['pergunta_id'],
            'usuario_nome' => $r['usuario_nome'],
            'usuario_tipo' => $r['usuario_tipo'],
            'resposta'     => $r['resposta'],
            'criado_em'    => $r['criado_em'],
            'pode_excluir' => $isADM || (int)$r['usuario_id'] === (int)$user_id,
        ];
    }, $stmtResp->fetchAll());

    // Respostas que sumiram (excluídas por outro usuário) desde a última checagem
    $respostasRemovidas = [];
    if ($idsRespostasVisiveis) {
        $placeholders = implode(',', array_fill(0, count($idsRespostasVisiveis), '?'));
        $stmtRespExistentes = $conn->prepare("SELECT id FROM respostas WHERE id IN ($placeholders)");
        $stmtRespExistentes->execute($idsRespostasVisiveis);
        $respExistentes = array_map('intval', array_column($stmtRespExistentes->fetchAll(), 'id'));
        $respostasRemovidas = array_values(array_diff($idsRespostasVisiveis, $respExistentes));
    }

    // Contagem de notificações não lidas: vai "carona" neste polling para não
    // precisar de um pedido/ligação à BD separado a cada intervalo.
    $notifStmt = $conn->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = :usuario_id AND lida = false");
    $notifStmt->execute([':usuario_id' => $user_id]);
    $notificacoesNaoLidas = (int)$notifStmt->fetchColumn();

    echo json_encode([
        'success'              => true,
        'perguntas'            => $perguntas,
        'perguntas_removidas'  => $perguntasRemovidas,
        'novas_respostas'      => $novasRespostas,
        'respostas_removidas'  => $respostasRemovidas,
        'notificacoes_nao_lidas' => $notificacoesNaoLidas,
    ]);
} catch (PDOException $e) {
    error_log("Erro ao buscar novas perguntas: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar novas perguntas']);
}
