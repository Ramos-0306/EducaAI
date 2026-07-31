<?php
// bd.php — Conexão Supabase (PostgreSQL)
// As credenciais vêm do ficheiro .env (não commitado no Git).
// Consulta o .env.example para veres quais variáveis são necessárias.

require_once __DIR__ . '/env.php';

$host     = getenv('DB_HOST');
$port     = getenv('DB_PORT');
$dbname   = getenv('DB_NAME');
$user     = getenv('DB_USER');
$pass     = getenv('DB_PASS');

try {
    $conn = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require",
        $user,
        $pass
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Conexão falhou: " . $e->getMessage());
}
?>
