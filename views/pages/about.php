<?php
$title = 'Sobre Nós - StoqueOnline';
$metaDescription = 'Conheça o StoqueOnline, a rede B2B que conecta distribuidores de rolamentos industriais e compradores em todo o Brasil.';
?>

<section class="bg-dark text-white py-5">
    <div class="container py-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="badge bg-primary-subtle text-primary text-uppercase fw-bold mb-3">Quem somos</span>
                <h1 class="fw-bold display-6 mb-3">A rede que conecta o estoque de rolamentos do Brasil</h1>
                <p class="text-white-50 mb-0">
                    O StoqueOnline nasceu dentro do setor de reposição industrial, da rotina de quem passa
                    o dia ligando de distribuidor em distribuidor atrás de uma peça parada em algum canto do país.
                    Reunimos esses estoques em um único lugar, com busca por código e contato direto entre as empresas.
                </p>
            </div>
            <div class="col-lg-5 text-center">
                <i class="fa-solid fa-circle-notch fa-spin text-primary" style="font-size: 9rem; --fa-animation-duration: 18s;"></i>
            </div>
        </div>
    </div>
</section>

<div class="container py-5">
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-4">
                <div class="text-primary mb-3"><i class="fa-solid fa-bullseye fs-3"></i></div>
                <h5 class="fw-bold">Nossa missão</h5>
                <p class="text-muted text-small m-0">
                    Reduzir o tempo de parada de máquina no Brasil, encurtando o caminho entre quem
                    precisa de um rolamento hoje e quem já tem essa peça em estoque.
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-4">
                <div class="text-primary mb-3"><i class="fa-solid fa-handshake fs-3"></i></div>
                <h5 class="fw-bold">Como funciona</h5>
                <p class="text-muted text-small m-0">
                    O distribuidor assina um plano e sobe a planilha do seu estoque. A partir daí as peças
                    ficam pesquisáveis na rede, e os interessados falam diretamente com a empresa.
                    Não intermediamos a venda nem cobramos comissão.
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-4">
                <div class="text-primary mb-3"><i class="fa-solid fa-shield-halved fs-3"></i></div>
                <h5 class="fw-bold">Seus dados</h5>
                <p class="text-muted text-small m-0">
                    Publicamos apenas marca, código, quantidade e os dados comerciais que a própria
                    empresa preenche no cadastro. Preço de custo, cliente final e margem nunca são exibidos.
                </p>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5">
        <div class="row g-4 align-items-center">
            <div class="col-lg-8">
                <h4 class="fw-bold mb-3">Feito para o dia a dia da reposição industrial</h4>
                <p class="text-muted mb-3">
                    A busca entende o jeito que o mercado escreve os códigos: <strong>6205-2RS</strong>,
                    <strong>6205 2rs</strong> ou <strong>62052RS</strong> chegam ao mesmo resultado. Você
                    filtra por marca, quantidade mínima e ainda inclui ou exclui trechos do código para
                    separar sufixos como C3, ZZ ou DDU.
                </p>
                <p class="text-muted m-0">
                    Para o distribuidor, manter o estoque atualizado é enviar a planilha de novo: a carga
                    anterior é substituída inteira, sem sobra de item vendido.
                </p>
            </div>
            <div class="col-lg-4">
                <div class="bg-light rounded-4 p-4">
                    <div class="mb-3">
                        <div class="text-muted text-uppercase fw-bold" style="font-size:.7rem;">Endereço</div>
                        <div class="fw-bold">Rua General Osório, 299 Sala 1<br>Santa Ifigênia - São Paulo/SP</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted text-uppercase fw-bold" style="font-size:.7rem;">Atendimento</div>
                        <div class="fw-bold">Segunda a sexta, 8h às 18h</div>
                    </div>
                    <a href="<?= url('contato') ?>" class="btn btn-primary w-100 fw-bold">
                        <i class="fa-solid fa-envelope me-2"></i> Fale conosco
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
