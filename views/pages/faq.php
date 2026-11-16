<?php
$title = 'Perguntas Frequentes - StoqueOnline';
$metaDescription = 'Dúvidas sobre planos, upload de estoque, busca de rolamentos e pagamentos no StoqueOnline.';

$faqs = [
    [
        'q' => 'Preciso pagar para pesquisar uma peça?',
        'a' => 'Não. A pesquisa de rolamentos e a visualização dos fornecedores são gratuitas. '
             . 'O plano é necessário para <strong>publicar o seu estoque</strong> na rede e aparecer nas buscas.',
    ],
    [
        'q' => 'Como funciona o teste grátis?',
        'a' => 'Toda conta nova tem direito a um período de teste com acesso completo ao upload de estoque. '
             . 'Basta criar a conta, confirmar o e-mail e ativar o teste na página de Planos. O direito é único por empresa.',
    ],
    [
        'q' => 'Em qual formato devo enviar minha planilha?',
        'a' => 'Use <strong>.xlsx</strong> ou <strong>.csv</strong> com três colunas, nesta ordem: Marca, Produto e Quantidade. '
             . 'Baixe a planilha modelo na tela de upload. Arquivos .xls (Excel 97-2003) precisam ser convertidos para .xlsx antes do envio.',
    ],
    [
        'q' => 'O que acontece com o estoque antigo quando envio uma planilha nova?',
        'a' => 'A carga anterior é substituída integralmente pela nova. Isso garante que nenhuma peça já vendida '
             . 'continue aparecendo para os compradores. O histórico dos envios continua visível na tela de upload.',
    ],
    [
        'q' => 'Quantas pessoas da minha equipe podem usar a conta?',
        'a' => 'Depende do plano contratado. O titular ocupa uma vaga e as demais podem ser atribuídas a colaboradores '
             . 'em <em>Minha Empresa &rarr; Colaboradores</em>. Cada colaborador acessa com o próprio e-mail e senha.',
    ],
    [
        'q' => 'Quais são as formas de pagamento?',
        'a' => 'O pagamento é processado pelo PicPay, com Pix (QR Code) ou cartão. '
             . 'A licença é liberada automaticamente assim que o pagamento é confirmado.',
    ],
    [
        'q' => 'Paguei e minha licença não foi liberada. O que faço?',
        'a' => 'Acesse <em>Histórico de Pagamentos</em> e clique em <strong>Verificar pagamento</strong>: '
             . 'o sistema consulta o PicPay na hora e libera a licença se o valor já tiver sido compensado. '
             . 'Se ainda assim não resolver, fale com o suporte pelo WhatsApp.',
    ],
    [
        'q' => 'Consigo cancelar uma cobrança que ainda não paguei?',
        'a' => 'Sim. Em <em>Histórico de Pagamentos</em>, pedidos pendentes têm a opção de cancelamento, '
             . 'que também cancela a cobrança no PicPay.',
    ],
    [
        'q' => 'Meus preços de venda ficam visíveis para os concorrentes?',
        'a' => 'Não. A plataforma exibe apenas marca, código, quantidade disponível e os dados de contato '
             . 'comercial da empresa. Nenhuma informação de preço é solicitada ou publicada.',
    ],
    [
        'q' => 'Esqueci minha senha. Como recupero?',
        'a' => 'Clique em <em>Esqueceu a senha?</em> na tela de login. Você receberá um link válido por 1 hora '
             . 'para cadastrar uma nova senha.',
    ],
];
?>

<section class="bg-dark text-white py-5">
    <div class="container py-3 text-center">
        <span class="badge bg-primary-subtle text-primary text-uppercase fw-bold mb-3">Ajuda</span>
        <h1 class="fw-bold display-6 mb-2">Perguntas Frequentes</h1>
        <p class="text-white-50 m-0">As dúvidas mais comuns de quem usa a rede todos os dias.</p>
    </div>
</section>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="accordion accordion-flush" id="accordionFaq">
                <?php foreach ($faqs as $index => $faq): ?>
                    <div class="accordion-item border-0 shadow-sm rounded-4 mb-3 overflow-hidden">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?> fw-bold"
                                    type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq<?= $index ?>">
                                <?= e($faq['q']) ?>
                            </button>
                        </h2>
                        <div id="faq<?= $index ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>"
                             data-bs-parent="#accordionFaq">
                            <div class="accordion-body text-muted">
                                <?= $faq['a'] // conteúdo fixo desta página, definido acima ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card border-0 shadow-sm rounded-4 p-4 mt-4 text-center">
                <h5 class="fw-bold mb-2">Não encontrou sua resposta?</h5>
                <p class="text-muted text-small mb-3">Nossa equipe responde em horário comercial.</p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="<?= url('contato') ?>" class="btn btn-primary fw-bold px-4">
                        <i class="fa-solid fa-envelope me-2"></i> Enviar mensagem
                    </a>
                    <a href="<?= e(whatsapp_link()) ?>" target="_blank" rel="noopener" class="btn btn-success fw-bold px-4">
                        <i class="fa-brands fa-whatsapp me-2"></i> WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
