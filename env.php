<?php
// env.php — Carrega variáveis de ambiente a partir do ficheiro .env
// Não precisa de Composer nem de bibliotecas externas.

function carregarEnv(string $caminho): void
{
    if (!file_exists($caminho)) {
        return;
    }

    $linhas = file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($linhas as $linha) {
        $linha = trim($linha);

        // ignora comentários
        if ($linha === '' || str_starts_with($linha, '#')) {
            continue;
        }

        if (strpos($linha, '=') === false) {
            continue;
        }

        [$chave, $valor] = explode('=', $linha, 2);
        $chave = trim($chave);
        $valor = trim($valor);

        // remove aspas envolventes, se existirem
        $valor = trim($valor, "\"'");

        putenv("$chave=$valor");
        $_ENV[$chave] = $valor;
    }
}

carregarEnv(__DIR__ . '/.env');
