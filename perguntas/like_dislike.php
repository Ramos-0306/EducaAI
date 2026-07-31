<?php
session_start();

if(empty($_SESSION['user_id'])){
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit;
}

header('Content-Type: application/json');

// Conexão centralizada
include __DIR__ . '/../bd.php';

$tipo = $_POST['tipo'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$acao = $_POST['acao'] ?? '';
$user_id = $_SESSION['user_id'];

if(!$id || !in_array($acao, ['like', 'dislike']) || !$tipo){
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

try {
    // Verifica se o usuário já votou
    $stmt = $conn->prepare("SELECT acao FROM likes_dislikes WHERE user_id=? AND item_id=? AND tipo=?");
    $stmt->execute([$user_id, $id, $tipo]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    // Remove voto se clicou na mesma ação
    if($registro && $registro['acao'] === $acao){
        $conn->prepare("DELETE FROM likes_dislikes WHERE user_id=? AND item_id=? AND tipo=?")
            ->execute([$user_id, $id, $tipo]);
        $acao_atual = null;
    }
    // Atualiza voto se clicou na ação contrária
    elseif($registro && $registro['acao'] !== $acao){
        $conn->prepare("UPDATE likes_dislikes SET acao=? WHERE user_id=? AND item_id=? AND tipo=?")
            ->execute([$acao, $user_id, $id, $tipo]);
        $acao_atual = $acao;
    }
    // Insere novo voto
    else{
        $conn->prepare("INSERT INTO likes_dislikes (user_id, item_id, tipo, acao) VALUES (?, ?, ?, ?)")
            ->execute([$user_id, $id, $tipo, $acao]);
        $acao_atual = $acao;
    }

    // Contadores atualizados (1 query em vez de 2, usando FILTER)
    $countStmt = $conn->prepare("
        SELECT COUNT(*) FILTER (WHERE acao = 'like') AS likes,
               COUNT(*) FILTER (WHERE acao = 'dislike') AS dislikes
        FROM likes_dislikes WHERE item_id = ? AND tipo = ?
    ");
    $countStmt->execute([$id, $tipo]);
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);
    $likes = $counts['likes'];
    $dislikes = $counts['dislikes'];

    echo json_encode([
        'success' => true,
        'likes' => $likes,
        'dislikes' => $dislikes,
        'acao_atual' => $acao_atual
    ]);

} catch (PDOException $e) {
    error_log("Erro no like_dislike: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro no banco de dados. Tente novamente.'
    ]);
}
