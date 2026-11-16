<?php

namespace App\Services;

/**
 * Cliente SMTP nativo (sem dependências externas).
 *
 * Suporta STARTTLS (porta 587), SSL implícito (porta 465) e autenticação
 * AUTH LOGIN / AUTH PLAIN. Escrito para substituir a função mail(), que não
 * é entregue de forma confiável em hospedagem compartilhada.
 */
class Smtp
{
    private $socket = null;
    private array $log = [];
    private string $lastError = '';

    public function __construct(
        private string $host,
        private int $port = 587,
        private string $username = '',
        private string $password = '',
        private string $encryption = 'tls',
        private int $timeout = 20
    ) {
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getLog(): array
    {
        return $this->log;
    }

    /**
     * Envia uma mensagem HTML. Retorna true em caso de sucesso.
     */
    public function send(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $replyTo = null
    ): bool {
        try {
            if (!$this->connect()) {
                return false;
            }

            if (!$this->command("MAIL FROM:<{$fromEmail}>", [250])) {
                return $this->abort('Remetente recusado pelo servidor SMTP.');
            }

            if (!$this->command("RCPT TO:<{$toEmail}>", [250, 251])) {
                return $this->abort('Destinatário recusado pelo servidor SMTP.');
            }

            if (!$this->command('DATA', [354])) {
                return $this->abort('Servidor SMTP recusou o início dos dados.');
            }

            $message = $this->buildMessage($fromEmail, $fromName, $toEmail, $toName, $subject, $htmlBody, $replyTo);
            $this->write($message . "\r\n.\r\n");

            if (!$this->expect([250])) {
                return $this->abort('Servidor SMTP recusou o conteúdo da mensagem.');
            }

            $this->command('QUIT', [221]);
            $this->close();

            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->close();
            return false;
        }
    }

    private function connect(): bool
    {
        $encryption = strtolower($this->encryption);
        $remote = ($encryption === 'ssl')
            ? 'ssl://' . $this->host . ':' . $this->port
            : 'tcp://' . $this->host . ':' . $this->port;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $this->socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            $this->lastError = "Não foi possível conectar em {$this->host}:{$this->port} ({$errstr})";
            return false;
        }

        stream_set_timeout($this->socket, $this->timeout);

        if (!$this->expect([220])) {
            return $this->abort('O servidor SMTP não respondeu com a saudação inicial.');
        }

        $hostname = $this->clientHostname();

        if (!$this->command("EHLO {$hostname}", [250])) {
            // Alguns servidores antigos só aceitam HELO
            if (!$this->command("HELO {$hostname}", [250])) {
                return $this->abort('Handshake EHLO/HELO recusado.');
            }
        }

        if ($encryption === 'tls') {
            if (!$this->command('STARTTLS', [220])) {
                return $this->abort('O servidor SMTP não aceitou STARTTLS.');
            }

            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }

            if (!@stream_socket_enable_crypto($this->socket, true, $crypto)) {
                return $this->abort('Falha ao estabelecer a camada TLS com o servidor SMTP.');
            }

            // Após o STARTTLS é obrigatório repetir o EHLO
            if (!$this->command("EHLO {$hostname}", [250])) {
                return $this->abort('Handshake EHLO pós-TLS recusado.');
            }
        }

        if ($this->username !== '') {
            if (!$this->authenticate()) {
                return false;
            }
        }

        return true;
    }

    private function authenticate(): bool
    {
        // AUTH LOGIN é o mais compatível (Gmail, Hostinger, Locaweb)
        if ($this->command('AUTH LOGIN', [334])) {
            if (!$this->command(base64_encode($this->username), [334])) {
                return $this->abort('Usuário SMTP recusado.');
            }
            if (!$this->command(base64_encode($this->password), [235])) {
                return $this->abort('Senha SMTP recusada. Verifique MAIL_USER/MAIL_PASS.');
            }
            return true;
        }

        // Fallback para AUTH PLAIN
        $plain = base64_encode("\0" . $this->username . "\0" . $this->password);
        if ($this->command('AUTH PLAIN ' . $plain, [235])) {
            return true;
        }

        return $this->abort('O servidor SMTP recusou a autenticação.');
    }

    private function buildMessage(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $replyTo
    ): string {
        $messageId = '<' . bin2hex(random_bytes(16)) . '@' . $this->clientHostname() . '>';

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: ' . $messageId,
            'From: ' . $this->encodeHeaderName($fromName) . ' <' . $fromEmail . '>',
            'To: ' . ($toName !== '' ? $this->encodeHeaderName($toName) . ' ' : '') . '<' . $toEmail . '>',
            'Subject: ' . $this->encodeHeaderValue($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'X-Mailer: StoqueOnline',
        ];

        if ($replyTo) {
            $headers[] = 'Reply-To: <' . $replyTo . '>';
        }

        // base64 nunca gera linha iniciada por ponto, dispensando dot-stuffing
        $body = rtrim(chunk_split(base64_encode($htmlBody), 76, "\r\n"));

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private function encodeHeaderName(string $value): string
    {
        $clean = str_replace(["\r", "\n"], '', $value);
        return preg_match('/^[\x20-\x7E]*$/', $clean)
            ? '"' . addcslashes($clean, '"\\') . '"'
            : $this->encodeHeaderValue($clean);
    }

    private function encodeHeaderValue(string $value): string
    {
        $clean = str_replace(["\r", "\n"], '', $value);
        if (preg_match('/^[\x20-\x7E]*$/', $clean)) {
            return $clean;
        }
        return '=?UTF-8?B?' . base64_encode($clean) . '?=';
    }

    private function clientHostname(): string
    {
        $host = $_SERVER['SERVER_NAME'] ?? gethostname() ?: 'localhost';
        $host = preg_replace('/[^a-zA-Z0-9.\-]/', '', (string)$host);
        return $host !== '' ? $host : 'localhost';
    }

    private function command(string $command, array $expectedCodes): bool
    {
        $this->write($command . "\r\n");
        return $this->expect($expectedCodes);
    }

    private function write(string $data): void
    {
        if (!$this->socket) {
            throw new \RuntimeException('Conexão SMTP não estabelecida.');
        }
        fwrite($this->socket, $data);
    }

    private function expect(array $codes): bool
    {
        $response = $this->readResponse();
        $code = (int) substr($response, 0, 3);

        if (!in_array($code, $codes, true)) {
            $this->lastError = trim($response);
            return false;
        }

        return true;
    }

    private function readResponse(): string
    {
        $response = '';

        while ($this->socket && !feof($this->socket)) {
            $line = fgets($this->socket, 1024);
            if ($line === false) {
                break;
            }

            $response .= $line;
            $this->log[] = trim($line);

            // Resposta multilinha: "250-..." continua, "250 ..." encerra
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        return $response;
    }

    private function abort(string $message): bool
    {
        $this->lastError = $message . ($this->lastError !== '' ? ' [' . $this->lastError . ']' : '');
        $this->close();
        return false;
    }

    private function close(): void
    {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }
}
