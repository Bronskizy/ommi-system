<?php
require_once __DIR__ . '/helpers.php';

function app_absolute_url(string $path): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . BASE_URL . '/' . ltrim($path, '/');
}

function smtp_read($socket): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function smtp_command($socket, string $command, array $expectedCodes): string
{
    fwrite($socket, $command . "\r\n");
    $response = smtp_read($socket);
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP error after ' . $command . ': ' . trim($response));
    }
    return $response;
}

function smtp_send_mail(string $toEmail, string $toName, string $subject, string $body): bool
{
    $host = setting('smtp_host', 'smtp.gmail.com');
    $port = (int) setting('smtp_port', 587);
    $username = setting('smtp_username', '');
    $password = setting('smtp_password', '');
    $fromEmail = setting('mail_from_email', $username ?: 'no-reply@ommi.local');
    $fromName = setting('mail_from_name', 'OMMI Company Ltd');

    if ($username === '' || $password === '') {
        return false;
    }

    $socket = stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        throw new RuntimeException('SMTP connection failed: ' . $errstr);
    }
    stream_set_timeout($socket, 20);

    try {
        $response = smtp_read($socket);
        if ((int) substr($response, 0, 3) !== 220) {
            throw new RuntimeException('SMTP greeting failed: ' . trim($response));
        }

        smtp_command($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
        smtp_command($socket, 'STARTTLS', [220]);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Could not enable SMTP TLS encryption.');
        }
        smtp_command($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode($username), [334]);
        smtp_command($socket, base64_encode($password), [235]);
        smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $headers = [];
        $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
        $headers[] = 'To: ' . $toName . ' <' . $toEmail . '>';
        $headers[] = 'Subject: ' . $subject;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Date: ' . date(DATE_RFC2822);

        $message = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $body);
        $message = str_replace("\r\n.", "\r\n..", $message);
        fwrite($socket, $message . "\r\n.\r\n");
        $response = smtp_read($socket);
        if ((int) substr($response, 0, 3) !== 250) {
            throw new RuntimeException('SMTP send failed: ' . trim($response));
        }

        smtp_command($socket, 'QUIT', [221]);
        fclose($socket);
        return true;
    } catch (Throwable $e) {
        fclose($socket);
        audit('smtp_error', 'mail', null, $e->getMessage());
        return false;
    }
}

function send_password_reset_email(string $toEmail, string $toName, string $resetLink): bool
{
    $fromName = setting('mail_from_name', 'OMMI Company Ltd');
    $subject = 'Reset your OMMI account password';

    $body = "Hello {$toName},\n\n";
    $body .= "We received a request to reset your password for " . APP_NAME . ".\n\n";
    $body .= "Use this link to set a new password:\n{$resetLink}\n\n";
    $body .= "This link expires in 15 minutes and can only be used once.\n\n";
    $body .= "If you did not request this reset, ignore this email.\n\n";
    $body .= "Regards,\n{$fromName}";

    if (setting('mail_driver', 'smtp') === 'smtp') {
        return smtp_send_mail($toEmail, $toName, $subject, $body);
    }

    $fromEmail = setting('mail_from_email', 'no-reply@ommi.local');
    $headers = [
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    return mail($toEmail, $subject, $body, implode("\r\n", $headers));
}
