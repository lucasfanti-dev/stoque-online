<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Setting;

/**
 * Configurações do site.
 *
 * Telefone, e-mail, endereço e o crédito do rodapé ficavam escritos dentro
 * das telas; trocar qualquer um deles exigia editar arquivo e subir de novo.
 * Aqui viram formulário.
 */
class AdminSettingsController extends Controller
{
    /**
     * Rótulo, dica e tipo de cada chave. A tela é montada a partir desta
     * lista, então acrescentar uma configuração é acrescentar uma linha.
     *
     * @var array<string,array{0:string,1:string,2:string}>
     */
    private const CAMPOS = [
        'contato_email' => [
            'E-mail de contato',
            'Aparece na barra de topo, no rodapé e na página Fale Conosco. As mensagens do formulário de contato também chegam aqui.',
            'email',
        ],
        'contato_telefone' => [
            'Telefone exibido',
            'Como o número aparece escrito para quem visita. Ex.: (11) 99527-0932',
            'text',
        ],
        'contato_whatsapp' => [
            'Número do WhatsApp',
            'Só os dígitos, com DDD. O 55 do Brasil é acrescentado sozinho se você não digitar. Ex.: 11995270932',
            'text',
        ],
        'contato_endereco' => [
            'Endereço',
            'Mostrado no rodapé e na página Fale Conosco.',
            'text',
        ],
        'contato_horario' => [
            'Horário de atendimento',
            'Mostrado na barra de topo. Ex.: Seg - Sex: 08:00 às 18:00',
            'text',
        ],
    ];

    public function index(): void
    {
        $this->render('admin/settings', [
            'campos'     => self::CAMPOS,
            'valores'    => Setting::todos(),
            'adminAtivo' => 'configuracoes',
        ]);
    }

    public function save(): void
    {
        $this->validateCsrf();

        $valores = [];
        foreach (array_keys(self::CAMPOS) as $chave) {
            $valores[$chave] = trim((string) $this->input($chave, ''));
        }

        if ($valores['contato_email'] !== '' && !filter_var($valores['contato_email'], FILTER_VALIDATE_EMAIL)) {
            $this->redirect('admin/configuracoes', ['error' => 'O e-mail de contato não parece válido. Confira e tente de novo.']);
        }

        // Guardamos só os dígitos: as telas montam o link do wa.me a partir
        // daqui, e um número com parênteses quebraria o link.
        $valores['contato_whatsapp'] = preg_replace('/\D+/', '', $valores['contato_whatsapp']) ?? '';

        if ($valores['contato_whatsapp'] !== '' && strlen($valores['contato_whatsapp']) < 10) {
            $this->redirect('admin/configuracoes', ['error' => 'O número do WhatsApp parece curto demais. Informe DDD + número.']);
        }

        Setting::salvar($valores);

        Auth::logAudit(Auth::id(), Auth::user()['email'] ?? null, 'settings_updated');

        $this->redirect('admin/configuracoes', ['success' => 'Configurações salvas. As telas já estão usando os novos dados.']);
    }
}
