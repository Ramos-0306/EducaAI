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

$email = trim($_POST['email'] ?? '');

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Informe o e-mail']);
    exit;
}

// Resposta genérica — não revela se o e-mail existe ou não na base
$respostaGenerica = ['success' => true, 'message' => 'Se esse e-mail existir na nossa base, enviamos um código de recuperação.'];

try {
    $stmt = $conn->prepare("SELECT id, nome FROM usuarios WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        echo json_encode($respostaGenerica);
        exit;
    }

    // Invalida códigos anteriores não usados desse usuário
    $conn->prepare("DELETE FROM codigos_recuperacao WHERE usuario_id = :usuario_id AND usado = false")
        ->execute(['usuario_id' => $usuario['id']]);

    // Gera código numérico de 6 dígitos
    $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $conn->prepare("
        INSERT INTO codigos_recuperacao (usuario_id, codigo, expira_em)
        VALUES (:usuario_id, :codigo, NOW() + INTERVAL '15 minutes')
    ")->execute([
        'usuario_id' => $usuario['id'],
        'codigo' => $codigo
    ]);

    // Envia o e-mail via Gmail SMTP
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = getenv('GMAIL_SMTP_HOST');
        $mail->SMTPAuth = true;
        $mail->Username = getenv('GMAIL_SMTP_USER');
        $mail->Password = getenv('GMAIL_SMTP_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) getenv('GMAIL_SMTP_PORT');
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(getenv('GMAIL_SMTP_USER'), 'EducaAI');
        $mail->addAddress($email, $usuario['nome']);

        $mail->Subject = 'Código de recuperação de senha - EducaAI';
        $mail->Body = "Olá, {$usuario['nome']}!\n\nSeu código de recuperação de senha é: {$codigo}\n\nEle é válido por 15 minutos. Se você não solicitou essa recuperação, ignore este e-mail.";

        $mail->send();
    } catch (Exception $e) {
        error_log("Erro ao enviar e-mail de recuperação: " . $mail->ErrorInfo);
    }

    echo json_encode($respostaGenerica);
} catch (PDOException $e) {
    error_log("Erro ao processar esqueci-senha: " . $e->getMessage());
    echo json_encode($respostaGenerica);
}
