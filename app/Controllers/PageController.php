<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Session;
use App\Services\MailService;

/**
 * Páginas institucionais que existiam na v1 (Sobre Nós, Perguntas Frequentes
 * e Contato) e o envio do formulário de contato.
 */
class PageController extends Controller
{
    /** Intervalo mínimo entre dois envios do formulário de contato */
    private const CONTACT_THROTTLE_SECONDS = 60;

    public function about(): void
    {
        $this->render('pages/about');
    }

    public function faq(): void
    {
        $this->render('pages/faq');
    }

    public function contact(): void
    {
        $this->render('pages/contact');
    }

    /**
     * Mapa do site para os buscadores. Só entram páginas públicas —
     * área logada, administração e pagamento ficam de fora.
     */
    public function sitemap(): void
    {
        $paginas = [
            ['', 'daily', '1.0'],
            ['pesquisa', 'daily', '0.9'],
            ['planos', 'weekly', '0.8'],
            ['sobre-nos', 'monthly', '0.5'],
            ['perguntas-frequentes', 'monthly', '0.5'],
            ['contato', 'monthly', '0.4'],
            ['cadastro', 'monthly', '0.6'],
        ];

        header('Content-Type: application/xml; charset=utf-8');

        $xml = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>'
        );

        $hoje = date('Y-m-d');

        foreach ($paginas as [$caminho, $frequencia, $prioridade]) {
            $url = $xml->addChild('url');
            $url->addChild('loc', htmlspecialchars(url($caminho), ENT_XML1));
            $url->addChild('lastmod', $hoje);
            $url->addChild('changefreq', $frequencia);
            $url->addChild('priority', $prioridade);
        }

        echo $xml->asXML();
        exit;
    }

    /** Mesma resposta para o envio real e para o robo apanhado no campo-armadilha. */
    private const RECADO_ENVIADO = 'Mensagem enviada com sucesso! Nossa equipe responderá em breve.';

    public function sendContact(): void
    {
        $this->validateCsrf();

        // Campo invisível para humanos: se vier preenchido, é robô
        if ($this->text('website_url', '', 100) !== '') {
            $this->redirect('contato', ['success' => self::RECADO_ENVIADO]);
        }

        $lastSent = (int) Session::get('_contact_last_sent', 0);
        if ($lastSent && (time() - $lastSent) < self::CONTACT_THROTTLE_SECONDS) {
            $this->redirect('contato', [
                'warning' => 'Aguarde um instante antes de enviar outra mensagem.',
            ]);
        }

        $name    = $this->text('name', '', 150);
        $email   = $this->text('email', '', 255);
        $phone   = $this->text('phone', '', 40);
        $subject = $this->text('subject', '', 150);
        $message = $this->text('message', '', 5000);

        if ($name === '' || $email === '' || $message === '') {
            $this->redirect('contato', ['error' => 'Preencha nome, e-mail e mensagem.']);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirect('contato', ['error' => 'Informe um endereço de e-mail válido.']);
        }

        if (mb_strlen($message) < 10) {
            $this->redirect('contato', ['error' => 'Escreva um pouco mais de detalhes na mensagem.']);
        }

        $sent = MailService::sendContactMessage(
            $name,
            $email,
            $phone,
            $subject !== '' ? $subject : 'Contato pelo site',
            $message
        );

        Auth::logAudit(Auth::id(), $email, 'contact_form_submitted');

        if (!$sent) {
            $this->redirect('contato', [
                'error' => 'Não conseguimos enviar sua mensagem agora. '
                    . 'Por favor, chame no WhatsApp ' . setting('contato_telefone')
                    . ' ou escreva para ' . setting('contato_email') . '.',
            ]);
        }

        Session::set('_contact_last_sent', time());

        $this->redirect('contato', ['success' => self::RECADO_ENVIADO]);
    }
}
