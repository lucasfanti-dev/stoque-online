<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\RateLimiter;
use App\Models\User;
use App\Core\Session;
use App\Services\MailService;
use App\Services\VerificationCode;

class AuthController extends Controller
{
    /** Onde guardamos o e-mail entre pedir o código e digitá-lo. */
    private const SESSAO_CONFIRMACAO = '_confirmacao_email';
    private const SESSAO_RECUPERACAO = '_recuperacao_email';

    public function showLogin(): void
    {
        $this->render('auth/login');
    }

    public function login(): void
    {
        $this->validateCsrf();

        $email = mb_strtolower($this->text('email', '', 255));
        $password = (string) $this->input('password', '');

        if ($email === '' || $password === '') {
            $this->redirect('login', ['error' => 'Por favor, informe o seu e-mail e senha.']);
        }

        // Proteção contra força bruta
        if (RateLimiter::tooManyAttempts($email)) {
            Auth::logAudit(null, $email, 'login_blocked_rate_limit');
            $this->redirect('login', [
                'error' => 'Muitas tentativas de acesso malsucedidas. Por segurança, aguarde '
                    . RateLimiter::minutesRemaining() . ' minutos antes de tentar novamente.',
            ]);
        }

        $user = User::findByEmail($email);

        // Mensagem propositalmente genérica: não revela se o e-mail existe
        if (!$user || !Auth::verifyAndUpgradePassword($user, $password)) {
            RateLimiter::record($email, false);
            Auth::logAudit($user['id'] ?? null, $email, 'login_failed');
            $this->redirect('login', ['error' => 'E-mail ou senha inválidos.']);
        }

        if (empty($user['allowed_access'])) {
            RateLimiter::record($email, false);
            Auth::logAudit((int) $user['id'], $email, 'login_blocked_suspended');
            $this->redirect('login', ['error' => 'Sua conta está temporariamente suspensa. Entre em contato com o suporte.']);
        }

        if (empty($user['confirmed_activation'])) {
            Auth::logAudit((int) $user['id'], $email, 'login_blocked_not_activated');
            $this->redirect('login', [
                'warning' => 'Sua conta ainda não foi ativada. Verifique o e-mail que enviamos ou solicite um novo link na página de cadastro.',
            ]);
        }

        RateLimiter::record($email, true);
        RateLimiter::clear($email);
        RateLimiter::purgeOld();

        Auth::login($user);

        flash('success', 'Bem-vindo de volta, ' . $user['name'] . '!');
        $this->redirect('');
    }

    public function showRegister(): void
    {
        $this->render('auth/register');
    }

    public function register(): void
    {
        $this->validateCsrf();

        $name            = $this->text('name', '', 150);
        $email           = mb_strtolower($this->text('email', '', 255));
        $phone           = $this->text('phone', '', 50);
        $password        = (string) $this->input('password', '');
        $passwordConfirm = (string) $this->input('password_confirm', '');

        if ($name === '' || $email === '' || $password === '') {
            $this->redirect('cadastro', ['error' => 'Todos os campos obrigatórios devem ser preenchidos.']);
        }

        if (mb_strlen($name) < 3) {
            $this->redirect('cadastro', ['error' => 'Informe o seu nome completo.']);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirect('cadastro', ['error' => 'Por favor, informe um endereço de e-mail válido.']);
        }

        if ($password !== $passwordConfirm) {
            $this->redirect('cadastro', ['error' => 'A confirmação de senha não confere.']);
        }

        if (!$this->isStrongEnough($password)) {
            $this->redirect('cadastro', [
                'error' => 'A senha deve ter no mínimo 8 caracteres, incluindo letras e números.',
            ]);
        }

        $existing = User::findByEmail($email);

        if ($existing) {
            // Conta existente e ainda não ativada: reenvia o link em vez de barrar
            if (empty($existing['confirmed_activation'])) {
                $codigo = VerificationCode::emitir((int) $existing['id'], VerificationCode::ATIVACAO);
                MailService::sendActivationCode($existing['name'], $email, $codigo);
                Session::set(self::SESSAO_CONFIRMACAO, $email);

                $this->redirect('confirmar-email', [
                    'info' => 'Este e-mail já estava cadastrado e ainda não havia sido confirmado. Enviamos um código novo.',
                ]);
            }

            $this->redirect('cadastro', ['error' => 'Este endereço de e-mail já está cadastrado no sistema.']);
        }

        $userId = User::create([
            'name'                 => $name,
            'email'                => $email,
            'phone'                => $phone,
            'password'             => password_hash($password, PASSWORD_DEFAULT),
            'confirmed_activation' => 0,
            'allowed_access'       => 1,
            'is_admin'             => 0,
            'first_access_free'    => 1,
        ]);

        Auth::logAudit($userId, $email, 'register');

        $codigo = VerificationCode::emitir($userId, VerificationCode::ATIVACAO);
        $sent   = MailService::sendActivationCode($name, $email, $codigo);

        // O e-mail fica na sessão para a próxima tela já vir preenchida —
        // quem acabou de digitar o endereço não deveria digitar de novo.
        Session::set(self::SESSAO_CONFIRMACAO, $email);

        if (!$sent) {
            $this->redirect('confirmar-email', [
                'warning' => 'Conta criada, mas não conseguimos enviar o código agora. '
                    . 'Tente reenviar em instantes ou fale com o suporte pelo WhatsApp.',
            ]);
        }

        $this->redirect('confirmar-email', [
            'success' => 'Enviamos um código de 6 dígitos para ' . $email . '. Digite-o abaixo para concluir.',
        ]);
    }

    /**
     * Rota antiga do link de ativação.
     *
     * Os links deixaram de ser emitidos, mas alguém pode ter um e-mail velho
     * na caixa. Em vez de um "token inválido" seco, mandamos para a tela de
     * código com a explicação.
     */
    public function activate(): void
    {
        $this->redirect('confirmar-email', [
            'info' => 'A confirmação agora é por código. Informe o seu e-mail abaixo e clique em "Reenviar código".',
        ]);
    }

    /**
     * Tela onde a pessoa digita o código recebido no e-mail.
     */
    public function showConfirm(): void
    {
        if (Auth::check()) {
            $this->redirect('');
        }

        $this->render('auth/confirm', [
            'email' => (string) Session::get(self::SESSAO_CONFIRMACAO, ''),
        ]);
    }

    /**
     * Confere o código do cadastro. Acertando, a conta é ativada e a pessoa
     * entra direto — ela acabou de provar que é dona da caixa postal e de
     * escolher a senha, não faz sentido pedir login em seguida.
     */
    public function confirm(): void
    {
        $this->validateCsrf();

        $email  = mb_strtolower($this->text('email', '', 255));
        $codigo = $this->text('codigo', '', 10);

        if ($email === '') {
            $email = (string) Session::get(self::SESSAO_CONFIRMACAO, '');
        }

        $user = $email !== '' ? User::findByEmail($email) : null;

        if (!$user) {
            $this->redirect('confirmar-email', ['error' => 'Não encontramos um cadastro com esse e-mail.']);
        }

        Session::set(self::SESSAO_CONFIRMACAO, $email);

        if (!empty($user['confirmed_activation'])) {
            $this->redirect('login', ['info' => 'Esta conta já estava confirmada. É só entrar.']);
        }

        $resultado = VerificationCode::conferir($user, VerificationCode::ATIVACAO, $codigo);

        if (!$resultado['ok']) {
            Auth::logAudit((int) $user['id'], $email, 'activation_code_failed');
            $this->redirect('confirmar-email', ['error' => $this->recadoDoCodigo($resultado)]);
        }

        User::update((int) $user['id'], ['confirmed_activation' => 1]);
        VerificationCode::descartar((int) $user['id'], VerificationCode::ATIVACAO);

        Auth::logAudit((int) $user['id'], $email, 'account_activated');
        Session::remove(self::SESSAO_CONFIRMACAO);

        $atualizado = User::findById((int) $user['id']);
        Auth::login($atualizado);

        $this->redirect('', ['success' => 'Conta confirmada! Bem-vindo ao StoqueOnline, ' . $atualizado['name'] . '.']);
    }

    /**
     * Manda outro código de confirmação.
     */
    public function resendConfirm(): void
    {
        $this->validateCsrf();

        $email = mb_strtolower($this->text('email', '', 255));
        if ($email === '') {
            $email = (string) Session::get(self::SESSAO_CONFIRMACAO, '');
        }

        $user = $email !== '' ? User::findByEmail($email) : null;

        if (!$user) {
            $this->redirect('confirmar-email', ['error' => 'Não encontramos um cadastro com esse e-mail.']);
        }

        Session::set(self::SESSAO_CONFIRMACAO, $email);

        if (!empty($user['confirmed_activation'])) {
            $this->redirect('login', ['info' => 'Esta conta já estava confirmada. É só entrar.']);
        }

        $espera = VerificationCode::esperaParaReenvio($user, VerificationCode::ATIVACAO);
        if ($espera > 0) {
            $this->redirect('confirmar-email', [
                'warning' => 'Acabamos de enviar um código. Aguarde ' . $espera . ' segundo(s) para pedir outro.',
            ]);
        }

        $codigo = VerificationCode::emitir((int) $user['id'], VerificationCode::ATIVACAO);
        MailService::sendActivationCode((string) $user['name'], $email, $codigo);
        Auth::logAudit((int) $user['id'], $email, 'activation_code_resent');

        $this->redirect('confirmar-email', ['success' => 'Código novo enviado para ' . $email . '.']);
    }

    public function showForgot(): void
    {
        $this->render('auth/forgot');
    }

    /**
     * Pede o código de recuperação.
     */
    public function forgot(): void
    {
        $this->validateCsrf();

        $email = mb_strtolower($this->text('email', '', 255));
        $user = User::findByEmail($email);

        if ($user) {
            $espera = VerificationCode::esperaParaReenvio($user, VerificationCode::RECUPERACAO);

            if ($espera === 0) {
                $codigo = VerificationCode::emitir((int) $user['id'], VerificationCode::RECUPERACAO);
                MailService::sendPasswordResetCode((string) $user['name'], (string) $user['email'], $codigo);
                Auth::logAudit((int) $user['id'], (string) $user['email'], 'password_reset_requested');
            }
        }

        // O e-mail vai para a sessão exista ou não a conta: a tela seguinte
        // precisa dele preenchido, e a resposta tem de ser a mesma nos dois
        // casos para não revelar quem está cadastrado.
        Session::set(self::SESSAO_RECUPERACAO, $email);

        $this->redirect('redefinir-senha', [
            'success' => 'Se esse e-mail estiver cadastrado, o código de 6 dígitos já está a caminho.',
        ]);
    }

    /**
     * Tela do código de recuperação e da nova senha.
     */
    public function showReset(): void
    {
        $this->render('auth/reset', [
            'email' => (string) Session::get(self::SESSAO_RECUPERACAO, ''),
        ]);
    }

    /**
     * Confere o código e grava a senha nova.
     */
    public function reset(): void
    {
        $this->validateCsrf();

        $email           = mb_strtolower($this->text('email', '', 255));
        $codigo          = $this->text('codigo', '', 10);
        $password        = (string) $this->input('password', '');
        $passwordConfirm = (string) $this->input('password_confirm', '');

        if ($email === '') {
            $email = (string) Session::get(self::SESSAO_RECUPERACAO, '');
        }

        Session::set(self::SESSAO_RECUPERACAO, $email);

        if ($password !== $passwordConfirm) {
            $this->redirect('redefinir-senha', ['error' => 'A confirmação de senha não confere.']);
        }

        if (!$this->isStrongEnough($password)) {
            $this->redirect('redefinir-senha', [
                'error' => 'A senha deve ter no mínimo 8 caracteres, incluindo letras e números.',
            ]);
        }

        $user = $email !== '' ? User::findByEmail($email) : null;

        // Sem conta: mesma mensagem de código errado. Dizer "esse e-mail não
        // existe" aqui entregaria quem é cliente e quem não é.
        if (!$user) {
            $this->redirect('redefinir-senha', ['error' => 'Código inválido ou expirado. Peça um novo código.']);
        }

        $resultado = VerificationCode::conferir($user, VerificationCode::RECUPERACAO, $codigo);

        if (!$resultado['ok']) {
            Auth::logAudit((int) $user['id'], $email, 'password_reset_code_failed');
            $this->redirect('redefinir-senha', ['error' => $this->recadoDoCodigo($resultado)]);
        }

        User::update((int) $user['id'], [
            'password' => password_hash($password, PASSWORD_DEFAULT),
            // Quem recebeu o código provou que é dono da caixa postal
            'confirmed_activation' => 1,
        ]);

        VerificationCode::descartar((int) $user['id'], VerificationCode::RECUPERACAO);
        VerificationCode::descartar((int) $user['id'], VerificationCode::ATIVACAO);

        RateLimiter::clear((string) $user['email']);
        Auth::logAudit((int) $user['id'], (string) $user['email'], 'password_reset_completed');
        Session::remove(self::SESSAO_RECUPERACAO);

        $atualizado = User::findById((int) $user['id']);

        if (empty($atualizado['allowed_access'])) {
            $this->redirect('login', ['success' => 'Senha alterada com sucesso! Você já pode entrar com a nova senha.']);
        }

        Auth::login($atualizado);

        $this->redirect('', ['success' => 'Senha alterada e acesso liberado. Bem-vindo de volta!']);
    }

    /**
     * Manda outro código de recuperação.
     */
    public function resendReset(): void
    {
        $this->validateCsrf();

        $email = mb_strtolower($this->text('email', '', 255));
        if ($email === '') {
            $email = (string) Session::get(self::SESSAO_RECUPERACAO, '');
        }

        Session::set(self::SESSAO_RECUPERACAO, $email);
        $user = $email !== '' ? User::findByEmail($email) : null;

        if ($user) {
            $espera = VerificationCode::esperaParaReenvio($user, VerificationCode::RECUPERACAO);

            if ($espera > 0) {
                $this->redirect('redefinir-senha', [
                    'warning' => 'Acabamos de enviar um código. Aguarde ' . $espera . ' segundo(s) para pedir outro.',
                ]);
            }

            $codigo = VerificationCode::emitir((int) $user['id'], VerificationCode::RECUPERACAO);
            MailService::sendPasswordResetCode((string) $user['name'], (string) $user['email'], $codigo);
            Auth::logAudit((int) $user['id'], $email, 'password_reset_code_resent');
        }

        $this->redirect('redefinir-senha', [
            'success' => 'Se esse e-mail estiver cadastrado, o código novo já está a caminho.',
        ]);
    }

    /**
     * Traduz o motivo da recusa para uma frase que ajuda a pessoa a agir.
     *
     * @param array{ok:bool, motivo:string, restantes:int} $resultado
     */
    private function recadoDoCodigo(array $resultado): string
    {
        return match ($resultado['motivo']) {
            'sem_codigo' => 'Não há código pendente para esse e-mail. Clique em "Reenviar código".',
            'expirado'   => 'O código expirou. Clique em "Reenviar código" para receber outro.',
            'bloqueado'  => 'Muitas tentativas erradas. Por segurança o código foi cancelado — peça um novo.',
            default      => 'Código incorreto. Você ainda tem ' . $resultado['restantes'] . ' tentativa(s).',
        };
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('login', ['info' => 'Você saiu com segurança do sistema.']);
    }


    private function isStrongEnough(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Za-z]/', $password) === 1
            && preg_match('/\d/', $password) === 1;
    }
}
