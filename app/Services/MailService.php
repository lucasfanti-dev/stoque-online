<?php

namespace App\Services;

class MailService
{
    /**
     * Caixa com o código, usada nos dois e-mails de verificação.
     * Fonte grande, espaçada e monoespaçada: o número é para ser lido do
     * celular e digitado em outra tela.
     */
    private static function caixaDeCodigo(string $codigo): string
    {
        return '
            <div style="text-align:center;margin:28px 0;">
                <div style="display:inline-block;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:10px;padding:18px 34px;">
                    <div style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Seu código</div>
                    <div style="font-family:Consolas,Menlo,monospace;font-size:34px;font-weight:bold;letter-spacing:8px;color:#1e293b;">'
                        . e(\App\Services\VerificationCode::paraLeitura($codigo)) .
                    '</div>
                </div>
            </div>';
    }

    /**
     * Código de confirmação do cadastro.
     */
    public static function sendActivationCode(string $name, string $email, string $codigo): bool
    {
        $minutos = \App\Services\VerificationCode::VALIDADE_MINUTOS;
        $subject = 'Seu código de confirmação | StoqueOnline';

        $body = '
            <h2 style="color:#4f46e5;margin:0 0 16px;">Bem-vindo ao StoqueOnline!</h2>
            <p>Olá, <strong>' . e($name) . '</strong>,</p>
            <p>Falta só confirmar o seu e-mail. Digite o código abaixo na tela em que você parou:</p>'
            . self::caixaDeCodigo($codigo) . '
            <p style="font-size:13px;color:#475569;text-align:center;">O código vale por ' . $minutos . ' minutos.</p>
            <p style="font-size:12px;color:#777;">Se você não se cadastrou no StoqueOnline, pode ignorar este e-mail — sem o código, ninguém entra na conta.</p>';

        return self::send($email, $name, $subject, self::layout($body));
    }

    /**
     * Código de recuperação de senha.
     */
    public static function sendPasswordResetCode(string $name, string $email, string $codigo): bool
    {
        $minutos = \App\Services\VerificationCode::VALIDADE_MINUTOS;
        $subject = 'Seu código de recuperação | StoqueOnline';

        $body = '
            <h2 style="color:#4f46e5;margin:0 0 16px;">Recuperação de senha</h2>
            <p>Olá, <strong>' . e($name) . '</strong>,</p>
            <p>Recebemos um pedido para trocar a sua senha. Use o código abaixo na tela de recuperação:</p>'
            . self::caixaDeCodigo($codigo) . '
            <p style="font-size:13px;color:#475569;text-align:center;">O código vale por ' . $minutos . ' minutos.</p>
            <p style="font-size:12px;color:#777;">Se não foi você quem pediu, ignore este e-mail: sua senha continua a mesma.</p>';

        return self::send($email, $name, $subject, self::layout($body));
    }
    public static function sendPaymentConfirmation(string $name, string $email, string $planName, string $endDate): bool
    {
        $subject = 'Pagamento confirmado | StoqueOnline';

        $body = '
            <h2 style="color:#198754;margin:0 0 16px;">Pagamento confirmado!</h2>
            <p>Olá, <strong>' . e($name) . '</strong>,</p>
            <p>Recebemos a confirmação do seu pagamento e o seu plano já está ativo.</p>
            <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#777;">Plano contratado</td><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">' . e($planName) . '</td></tr>
                <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#777;">Válido até</td><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">' . e(date('d/m/Y', strtotime($endDate))) . '</td></tr>
            </table>
            <div style="text-align:center;margin:30px 0;">
                <a href="' . e(url('estoque/upload')) . '" style="background-color:#0d6efd;color:#ffffff;padding:12px 25px;text-decoration:none;border-radius:5px;font-weight:bold;display:inline-block;">Carregar Meu Estoque</a>
            </div>';

        return self::send($email, $name, $subject, self::layout($body));
    }

    /**
     * Mensagem enviada pelo formulário público de contato para a equipe interna.
     */
    public static function sendContactMessage(
        string $name,
        string $email,
        string $phone,
        string $subjectText,
        string $message
    ): bool {
        // MAIL_CONTACT_ADDRESS no .env continua vencendo, para quem quiser uma
        // caixa separada. Sem ele, vale o e-mail de contato do painel: e o
        // mesmo que aparece no site, entao e para onde a pessoa espera escrever.
        $to = (string) env('MAIL_CONTACT_ADDRESS', \App\Models\Setting::obter('contato_email'));

        if ($to === '') {
            $to = (string) env('MAIL_FROM_ADDRESS', '');
        }

        $body = '
            <h2 style="color:#0d6efd;margin:0 0 16px;">Nova mensagem pelo site</h2>
            <table style="width:100%;border-collapse:collapse;">
                <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#777;width:140px;">Nome</td><td style="padding:8px;border-bottom:1px solid #eee;">' . e($name) . '</td></tr>
                <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#777;">E-mail</td><td style="padding:8px;border-bottom:1px solid #eee;">' . e($email) . '</td></tr>
                <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#777;">Telefone</td><td style="padding:8px;border-bottom:1px solid #eee;">' . e($phone) . '</td></tr>
                <tr><td style="padding:8px;border-bottom:1px solid #eee;color:#777;">Assunto</td><td style="padding:8px;border-bottom:1px solid #eee;">' . e($subjectText) . '</td></tr>
            </table>
            <p style="margin-top:20px;color:#777;font-size:12px;text-transform:uppercase;font-weight:bold;">Mensagem</p>
            <div style="white-space:pre-wrap;border-left:3px solid #0d6efd;padding:10px 15px;background:#f8f9fa;">' . e($message) . '</div>';

        $subject = str_starts_with(mb_strtolower($subjectText), 'contato pelo site')
            ? $subjectText
            : 'Contato pelo site: ' . $subjectText;

        return self::send($to, 'Equipe StoqueOnline', $subject, self::layout($body), $email);
    }

    /**
     * Envia via SMTP autenticado quando MAIL_HOST estiver configurado.
     * Sem SMTP configurado, cai para mail() como último recurso.
     */
    public static function send(
        string $to,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $replyTo = null
    ): bool {
        $fromName  = (string) env('MAIL_FROM_NAME', 'StoqueOnline');
        $host      = (string) env('MAIL_HOST', '');
        $smtpUser  = trim((string) env('MAIL_USER', ''));
        $fromEmail = trim((string) env('MAIL_FROM_ADDRESS', ''));

        // O remetente tem de ser a caixa que autentica no SMTP. Um endereço
        // de outro domínio faz o destinatário checar o SPF daquele domínio e
        // não achar este servidor: a mensagem cai em spam ou volta. O Gmail
        // esconde o erro reescrevendo o campo sozinho; a Hostinger não.
        // Vazio também não serve — provedor nenhum aceita From em branco.
        if ($smtpUser !== '' && strcasecmp($fromEmail, $smtpUser) !== 0) {
            $fromEmail = $smtpUser;
        }

        if ($fromEmail === '') {
            self::logFailure($to, $subject, 'MAIL_FROM_ADDRESS e MAIL_USER estao vazios no .env.');
            return false;
        }

        if ($host !== '') {
            $smtp = new Smtp(
                $host,
                (int) env('MAIL_PORT', 587),
                (string) env('MAIL_USER', ''),
                (string) env('MAIL_PASS', ''),
                (string) env('MAIL_ENCRYPTION', 'tls')
            );

            if ($smtp->send($fromEmail, $fromName, $to, $toName, $subject, $htmlBody, $replyTo)) {
                return true;
            }

            self::logFailure($to, $subject, $smtp->getLastError());
            return false;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            "From: {$fromName} <{$fromEmail}>",
            'Reply-To: ' . ($replyTo ?: $fromEmail),
            'X-Mailer: StoqueOnline',
        ];

        $sent = @mail(
            $to,
            '=?UTF-8?B?' . base64_encode($subject) . '?=',
            $htmlBody,
            implode("\r\n", $headers)
        );

        if (!$sent) {
            self::logFailure($to, $subject, 'Falha na função mail() nativa (SMTP não configurado).');
        }

        return $sent;
    }

    private static function layout(string $innerHtml): string
    {
        return '<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f6f9;">
    <div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:24px auto;padding:28px;background:#ffffff;border:1px solid #e0e0e0;border-radius:8px;color:#212529;line-height:1.5;">
        ' . $innerHtml . '
        <hr style="border:none;border-top:1px solid #eee;margin:28px 0 16px;">
        <p style="font-size:11px;color:#999;margin:0;text-align:center;">
            StoqueOnline &middot; Rua General Osório, 299 Sala 1 - São Paulo/SP<br>
            Este é um e-mail automático, não é necessário respondê-lo.
        </p>
    </div>
</body></html>';
    }

    private static function logFailure(string $to, string $subject, string $error): void
    {
        $line = sprintf(
            "[%s] Falha ao enviar para %s | Assunto: %s | Erro: %s%s",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $error,
            PHP_EOL
        );

        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($dir . '/mail-errors.log', $line, FILE_APPEND);
    }
}
