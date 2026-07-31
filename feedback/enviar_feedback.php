<?php
session_start();
include __DIR__ . '/../bd.php';

require __DIR__ . '/../lib/phpmailer/Exception.php';
require __DIR__ . '/../lib/phpmailer/PHPMailer.php';
require __DIR__ . '/../lib/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$dados = json_decode(file_get_contents('php://input'), true);
$mensagem = trim($dados['feedback'] ?? '');

if (!$mensagem) {
    echo json_encode(['success' => false, 'message' => 'Escreva uma mensagem']);
    exit;
}

$destinatario = getenv('FEEDBACK_EMAIL') ?: getenv('GMAIL_SMTP_USER');

// Identifica quem enviou, quando estiver logado
if (!empty($_SESSION['user_id'])) {
    $remetente = "{$_SESSION['nome']} ({$_SESSION['email']}) — tipo: {$_SESSION['tipo']}";
} else {
    $remetente = 'Visitante não logado';
}

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = getenv('GMAIL_SMTP_HOST');
    $mail->SMTPAuth = true;
    $mail->Username = getenv('GMAIL_SMTP_USER');
    $mail->Password = getenv('GMAIL_SMTP_PASS');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int) getenv('GMAIL_SMTP_PORT');
    $mail->CharSet = 'UTF-8';

    $mail->setFrom(getenv('GMAIL_SMTP_USER'), 'EducaAI - Feedback');
    $mail->addAddress($destinatario);

    $mail->Subject = 'Novo feedback recebido - EducaAI';
    $mail->Body = "Novo feedback enviado pelo site:\n\nDe: {$remetente}\n\nMensagem:\n{$mensagem}";

    $mail->send();

    echo json_encode(['success' => true, 'message' => 'Obrigado pela sua mensagem!']);
} catch (Exception $e) {
    error_log("Erro ao enviar feedback: " . ($mail->ErrorInfo ?? $e->getMessage()));
    echo json_encode(['success' => false, 'message' => 'Erro ao enviar. Tente novamente.']);
}
