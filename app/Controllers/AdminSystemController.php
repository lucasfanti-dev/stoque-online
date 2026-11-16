<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\User;
use App\Services\EnvFile;
use App\Services\SystemInfo;

/**
 * Tela técnica do sistema.
 *
 * É a tela mais perigosa que existe aqui: dentro do .env estão a senha do
 * banco, a senha do e-mail e as chaves dos meios de pagamento. Três travas
 * seguram isso.
 *
 *  1. Os segredos aparecem mascarados. Abrir a tela não revela nada.
 *
 *  2. Para ver ou trocar um segredo é preciso digitar a senha do próprio
 *     administrador de novo. É o que diferencia "alguém pegou a sessão
 *     aberta" de "é o dono da conta". A liberação vale 10 minutos.
 *
 *  3. Toda gravação faz cópia do arquivo antes, e mudança nos dados do banco
 *     é testada numa conexão nova antes de valer. Um erro de digitação na
 *     senha do banco derrubaria o site inteiro — e sem isso não haveria como
 *     voltar pela web, porque a web estaria fora do ar.
 */
class AdminSystemController extends Controller
{
    /** Onde fica registrado que o administrador reconfirmou a senha. */
    private const SESSAO_LIBERADO = '_env_liberado_ate';

    /** Por quanto tempo a confirmação vale, em segundos. */
    private const VALIDADE_LIBERACAO = 600;

    public function index(): void
    {
        $liberado = $this->estaLiberado();

        $chaves = [];
        foreach (EnvFile::linhas() as $linha) {
            if ($linha['tipo'] !== 'chave') {
                continue;
            }

            $chave = (string) $linha['chave'];
            $valor = (string) $linha['valor'];
            $secreta = EnvFile::ehSecreta($chave);

            $chaves[] = [
                'chave'    => $chave,
                'secreta'  => $secreta,
                'vazia'    => $valor === '',
                // O valor real só sai daqui depois da confirmação de senha
                'valor'    => (!$secreta || $liberado) ? $valor : null,
                'mascara'  => EnvFile::mascarar($valor),
                'travada'  => EnvFile::ehSomenteLeitura($chave),
            ];
        }

        $this->render('admin/system', [
            'adminAtivo'   => 'sistema',
            'liberado'     => $liberado,
            'expiraEm'     => $liberado ? (int) ceil(((int) Session::get(self::SESSAO_LIBERADO, 0) - time()) / 60) : 0,
            'envExiste'    => EnvFile::existe(),
            'envGravavel'  => EnvFile::gravavel(),
            'envCaminho'   => EnvFile::caminho(),
            'chaves'       => $chaves,
            'copias'       => EnvFile::copias(),
            'php'          => SystemInfo::php(),
            'banco'        => SystemInfo::banco(),
            'migracoes'    => SystemInfo::migracoes(),
            'pastas'       => SystemInfo::pastas(),
            'disco'        => SystemInfo::disco(),
            'conferencias' => SystemInfo::conferencias(),
            'logs'         => SystemInfo::logs(),
        ]);
    }

    /**
     * Confirmação de senha para liberar os segredos.
     */
    public function unlock(): void
    {
        $this->validateCsrf();

        $senha = (string) $this->input('senha', '');
        $user = User::findById((int) Auth::id());

        if (!$user || $senha === '' || !Auth::verifyAndUpgradePassword($user, $senha)) {
            Auth::logAudit(Auth::id(), $user['email'] ?? null, 'env_unlock_failed');

            $this->redirect('admin/sistema', [
                'error' => 'Senha incorreta. Os valores continuam escondidos.',
            ]);
        }

        Session::set(self::SESSAO_LIBERADO, time() + self::VALIDADE_LIBERACAO);
        Auth::logAudit(Auth::id(), $user['email'], 'env_unlocked');

        $this->redirect('admin/sistema', [
            'success' => 'Valores liberados por ' . (self::VALIDADE_LIBERACAO / 60) . ' minutos.',
        ]);
    }

    public function lock(): void
    {
        $this->validateCsrf();
        Session::remove(self::SESSAO_LIBERADO);

        $this->redirect('admin/sistema', ['info' => 'Valores escondidos de novo.']);
    }

    /**
     * Grava as alterações do .env.
     */
    public function saveEnv(): void
    {
        $this->validateCsrf();

        if (!$this->estaLiberado()) {
            $this->redirect('admin/sistema', [
                'error' => 'Confirme a sua senha antes de alterar o .env.',
            ]);
        }

        $alteracoes = [];
        foreach (EnvFile::valores() as $chave => $atual) {
            $enviado = $this->input('env_' . $chave, null);

            if ($enviado === null) {
                continue;
            }

            $alteracoes[$chave] = trim((string) $enviado);
        }

        // Chave nova, digitada nos dois campos do rodapé do formulário
        $novaChave = strtoupper(trim((string) $this->input('nova_chave', '')));
        $novoValor = trim((string) $this->input('novo_valor', ''));

        if ($novaChave !== '') {
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $novaChave) !== 1) {
                $this->redirect('admin/sistema', [
                    'error' => 'Nome de chave inválido. Use só letras maiúsculas, números e sublinhado — por exemplo NOVA_CHAVE.',
                ]);
            }

            $alteracoes[$novaChave] = $novoValor;
        }

        $resultado = EnvFile::salvar($alteracoes);

        if (!$resultado['ok']) {
            Auth::logAudit(Auth::id(), Auth::user()['email'] ?? null, 'env_save_failed');
            $this->redirect('admin/sistema', ['error' => (string) $resultado['erro']]);
        }

        if ($resultado['alteradas'] === []) {
            $this->redirect('admin/sistema', ['info' => 'Nada mudou: os campos em branco mantêm o valor que já estava gravado.']);
        }

        // Só os nomes das chaves entram na auditoria. O valor, nunca.
        Auth::logAudit(
            Auth::id(),
            Auth::user()['email'] ?? null,
            'env_updated: ' . implode(', ', $resultado['alteradas'])
        );

        $this->redirect('admin/sistema', [
            'success' => count($resultado['alteradas']) . ' chave(s) alterada(s): '
                . implode(', ', $resultado['alteradas'])
                . '. Cópia de segurança guardada'
                . ($resultado['copia'] ? ' em ' . $resultado['copia'] : '')
                . '. As mudanças valem a partir da próxima página aberta.',
        ]);
    }

    private function estaLiberado(): bool
    {
        return (int) Session::get(self::SESSAO_LIBERADO, 0) > time();
    }
}
