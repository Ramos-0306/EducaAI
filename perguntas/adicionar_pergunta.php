<?php
session_start();

if (empty($_SESSION['nome']) || empty($_SESSION['email'])) {
    header("Location: ../index.php");
    exit;
}

// Liberta o lock da sessão já aqui: este pedido pode ficar bloqueado bastante
// tempo à espera da IA (ver chamada ao Ollama mais abaixo), e enquanto isso
// nenhuma outra aba/pedido do mesmo utilizador conseguiria avançar.
session_write_close();

// Conexão centralizada
include __DIR__ . '/../bd.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descricao = $_POST['descricao'] ?? '';
    $materia = $_POST['materia'] ?? '';

    if (!empty($descricao) && !empty($materia)) {
        //  Salvar pergunta (normalizado — usa usuario_id)
        $stmt = $conn->prepare("
            INSERT INTO perguntas (descricao, materia, usuario_id)
            VALUES (:descricao, :materia, :usuario_id)
            RETURNING id
        ");
        $stmt->execute([
            'descricao' => $descricao,
            'materia' => $materia,
            'usuario_id' => $_SESSION['user_id']
        ]);
        $pergunta_id = $stmt->fetchColumn();

        //  Chamar IA (Ollama)
        $dados = [
            "model" => getenv('OLLAMA_MODEL') ?: 'llama3',
            "prompt" => "Responda de forma curta e direta: ".$descricao,
            "stream" => false
        ];

        $ollamaUrl = getenv('OLLAMA_URL') ?: 'http://localhost:11434/api/generate';

        $ch = curl_init($ollamaUrl);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($dados),
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 60
]);

$resposta = curl_exec($ch);

if ($resposta === false) {
    $respostaIA = "Ollama indisponível no momento, aguarde manutenção.";
} else {

    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($http != 200) {
        $respostaIA = "Erro HTTP $http do Ollama.";
    } else {

        $resultado = json_decode($resposta, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $respostaIA = "Resposta inválida do Ollama.";
        } elseif (isset($resultado['error'])) {
            $respostaIA = "Ollama: " . $resultado['error'];
        } else {
            $respostaIA = $resultado['response'] ?? "A IA não devolveu resposta.";
        }

    }

}

curl_close($ch);

        // 3️⃣ Salvar resposta da IA (usuario_id=0 para IA)
        $stmt2 = $conn->prepare("
            INSERT INTO respostas (pergunta_id, usuario_id, usuario_nome, usuario_tipo, resposta)
            VALUES (:pergunta_id, 0, 'IA', 'IA', :resposta)
        ");
        $stmt2->execute([
            ':pergunta_id' => $pergunta_id,
            ':resposta' => $respostaIA
        ]);
    }
}

// Redirecionar
header("Location: ../materias.php?toast=pergunta_adicionada");
exit;
?>