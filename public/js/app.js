/**
 * StoqueOnline v2 - JavaScript de Interatividade e Busca em Tempo Real
 */

document.addEventListener('DOMContentLoaded', () => {
    initSearch();
    initUploadDropzone();
    initMasks();
    initQuantidadeEstoque();
    initVisualizadorFotos();
    initAguarde();
});

/**
 * =====================================================================
 * Visualizador de fotos com ampliação
 * =====================================================================
 *
 * Escrito sem biblioteca externa para não depender de CDN nem carregar
 * peso extra. Suporta roda do mouse, clique duplo, arrastar para deslocar
 * e pinça no celular.
 */
const Visualizador = (() => {
    let overlay = null;
    let imagem = null;
    let legenda = null;
    let contador = null;
    let btnAnterior = null;
    let btnProxima = null;

    let fotos = [];
    let indice = 0;

    let escala = 1;
    let deslocX = 0;
    let deslocY = 0;

    let arrastando = false;
    let iniX = 0;
    let iniY = 0;
    let distanciaPinca = 0;

    const ESCALA_MIN = 1;
    const ESCALA_MAX = 6;

    function montar() {
        if (overlay) return;

        overlay = document.createElement('div');
        overlay.className = 'visualizador-foto';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.innerHTML = `
            <div class="visualizador-topo">
                <span class="visualizador-legenda"></span>
                <span class="visualizador-contador"></span>
                <button type="button" class="visualizador-fechar" aria-label="Fechar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <button type="button" class="visualizador-seta visualizador-anterior" aria-label="Foto anterior">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <div class="visualizador-palco">
                <img class="visualizador-imagem" alt="">
            </div>
            <button type="button" class="visualizador-seta visualizador-proxima" aria-label="Próxima foto">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
            <div class="visualizador-ajuda">
                <span class="d-none d-md-inline">Role para ampliar &bull; arraste para mover &bull; duplo clique alterna</span>
                <span class="d-md-none">Pince para ampliar &bull; arraste para mover</span>
                <button type="button" class="visualizador-reset">Tamanho original</button>
            </div>
        `;

        document.body.appendChild(overlay);

        imagem = overlay.querySelector('.visualizador-imagem');
        legenda = overlay.querySelector('.visualizador-legenda');
        contador = overlay.querySelector('.visualizador-contador');
        btnAnterior = overlay.querySelector('.visualizador-anterior');
        btnProxima = overlay.querySelector('.visualizador-proxima');

        const palco = overlay.querySelector('.visualizador-palco');

        overlay.querySelector('.visualizador-fechar').addEventListener('click', fechar);
        overlay.querySelector('.visualizador-reset').addEventListener('click', (e) => {
            e.stopPropagation();
            reiniciarZoom();
        });

        btnAnterior.addEventListener('click', (e) => { e.stopPropagation(); navegar(-1); });
        btnProxima.addEventListener('click', (e) => { e.stopPropagation(); navegar(1); });

        // Clicar no fundo fecha; clicar na imagem não
        palco.addEventListener('click', (e) => {
            if (e.target === palco) fechar();
        });

        // Roda do mouse amplia no ponto onde o cursor está
        palco.addEventListener('wheel', (e) => {
            e.preventDefault();
            const fator = e.deltaY < 0 ? 1.25 : 0.8;
            aplicarZoom(escala * fator, e.clientX, e.clientY);
        }, { passive: false });

        imagem.addEventListener('dblclick', (e) => {
            e.preventDefault();
            escala > 1 ? reiniciarZoom() : aplicarZoom(2.5, e.clientX, e.clientY);
        });

        // Arrastar com o mouse
        imagem.addEventListener('mousedown', (e) => {
            if (escala <= 1) return;
            e.preventDefault();
            arrastando = true;
            iniX = e.clientX - deslocX;
            iniY = e.clientY - deslocY;
            imagem.classList.add('arrastando');
        });

        window.addEventListener('mousemove', (e) => {
            if (!arrastando) return;
            deslocX = e.clientX - iniX;
            deslocY = e.clientY - iniY;
            aplicarTransformacao();
        });

        window.addEventListener('mouseup', () => {
            arrastando = false;
            if (imagem) imagem.classList.remove('arrastando');
        });

        // Toque: arrastar com um dedo, pinçar com dois
        imagem.addEventListener('touchstart', (e) => {
            if (e.touches.length === 2) {
                distanciaPinca = distancia(e.touches[0], e.touches[1]);
            } else if (e.touches.length === 1 && escala > 1) {
                arrastando = true;
                iniX = e.touches[0].clientX - deslocX;
                iniY = e.touches[0].clientY - deslocY;
            }
        }, { passive: true });

        imagem.addEventListener('touchmove', (e) => {
            if (e.touches.length === 2) {
                e.preventDefault();
                const nova = distancia(e.touches[0], e.touches[1]);
                if (distanciaPinca > 0) {
                    const meioX = (e.touches[0].clientX + e.touches[1].clientX) / 2;
                    const meioY = (e.touches[0].clientY + e.touches[1].clientY) / 2;
                    aplicarZoom(escala * (nova / distanciaPinca), meioX, meioY);
                }
                distanciaPinca = nova;
            } else if (arrastando && e.touches.length === 1) {
                e.preventDefault();
                deslocX = e.touches[0].clientX - iniX;
                deslocY = e.touches[0].clientY - iniY;
                aplicarTransformacao();
            }
        }, { passive: false });

        imagem.addEventListener('touchend', () => {
            arrastando = false;
            distanciaPinca = 0;
        });

        document.addEventListener('keydown', (e) => {
            if (!overlay.classList.contains('aberto')) return;

            if (e.key === 'Escape') fechar();
            if (e.key === 'ArrowLeft') navegar(-1);
            if (e.key === 'ArrowRight') navegar(1);
            if (e.key === '0') reiniciarZoom();
        });
    }

    function distancia(a, b) {
        return Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
    }

    function aplicarZoom(novaEscala, ancoraX, ancoraY) {
        const limitada = Math.min(ESCALA_MAX, Math.max(ESCALA_MIN, novaEscala));
        const retangulo = imagem.getBoundingClientRect();

        // Mantém sob o cursor o ponto que estava sendo olhado
        const centroX = retangulo.left + retangulo.width / 2;
        const centroY = retangulo.top + retangulo.height / 2;
        const razao = limitada / escala;

        deslocX = (deslocX - (ancoraX - centroX)) * razao + (ancoraX - centroX);
        deslocY = (deslocY - (ancoraY - centroY)) * razao + (ancoraY - centroY);

        escala = limitada;

        if (escala === 1) {
            deslocX = 0;
            deslocY = 0;
        }

        aplicarTransformacao();
    }

    function aplicarTransformacao() {
        imagem.style.transform = `translate(${deslocX}px, ${deslocY}px) scale(${escala})`;
        imagem.classList.toggle('ampliada', escala > 1);
        overlay.classList.toggle('com-zoom', escala > 1);
    }

    function reiniciarZoom() {
        escala = 1;
        deslocX = 0;
        deslocY = 0;
        aplicarTransformacao();
    }

    function mostrar() {
        const foto = fotos[indice];
        if (!foto) return;

        imagem.src = foto.grande;
        imagem.alt = foto.titulo || 'Foto do produto';
        legenda.textContent = foto.titulo || '';
        contador.textContent = fotos.length > 1 ? `${indice + 1} / ${fotos.length}` : '';

        btnAnterior.classList.toggle('d-none', fotos.length < 2);
        btnProxima.classList.toggle('d-none', fotos.length < 2);

        reiniciarZoom();
    }

    function navegar(passo) {
        if (fotos.length < 2) return;
        indice = (indice + passo + fotos.length) % fotos.length;
        mostrar();
    }

    function abrir(lista, inicio = 0) {
        if (!lista || !lista.length) return;

        montar();
        fotos = lista;
        indice = inicio;

        overlay.classList.add('aberto');
        document.body.classList.add('visualizador-travado');
        mostrar();
    }

    function fechar() {
        if (!overlay) return;
        overlay.classList.remove('aberto');
        document.body.classList.remove('visualizador-travado');
        imagem.src = '';
    }

    return { abrir, fechar };
})();

/**
 * Liga os elementos com data-zoom-src ao visualizador.
 */
function initVisualizadorFotos() {
    document.addEventListener('click', (e) => {
        const alvo = e.target.closest('[data-zoom-src]');
        if (!alvo) return;

        e.preventDefault();

        // Todas as fotos que estiverem no mesmo bloco viram uma galeria
        const container = alvo.closest('.modal-body, .row, body');
        const irmas = container ? [...container.querySelectorAll('[data-zoom-src]')] : [alvo];

        const lista = irmas.map(el => ({
            grande: el.getAttribute('data-zoom-src'),
            titulo: el.getAttribute('data-zoom-titulo') || ''
        }));

        Visualizador.abrir(lista, Math.max(0, irmas.indexOf(alvo)));
    });
}

/**
 * Caminho base da aplicação.
 *
 * Vem do próprio servidor (<body data-base-uri>), pois deduzir a URL a partir
 * de "/public" quebra quando o site roda na raiz do domínio ou em qualquer
 * subdiretório com reescrita de URL.
 */
function baseUri() {
    const value = document.body ? document.body.getAttribute('data-base-uri') : null;
    return (value || '').replace(/\/$/, '');
}

function apiUrl(path) {
    return baseUri() + '/' + String(path).replace(/^\//, '');
}

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

/**
 * Motor de Busca de Rolamentos
 */
function initSearch() {
    const formSearch = document.getElementById('formSearch');
    if (!formSearch) return;

    const inputKeyword = document.getElementById('palavraChave');
    const inputBrand = document.getElementById('marca');
    const inputMinQty = document.getElementById('quantidadeMinima');
    const inputInclude = document.getElementById('incluindo');
    const inputExclude = document.getElementById('excluindo');
    const explicacaoModo = document.getElementById('explicacaoModo');
    const btnClear = document.getElementById('btnLimpar');
    const btnExport = document.getElementById('btnExportar');

    const divLoading = document.getElementById('searchLoading');
    const divResults = document.getElementById('divResultados');
    const divNoResults = document.getElementById('divSemResultados');
    const tableBody = document.getElementById('tabelaEstoqueBody');
    const totalResultsText = document.getElementById('totalResultadosTexto');
    const divModels = document.getElementById('divModelosEncontrados');
    const listChipsModels = document.getElementById('listaChipsModelos');

    let lastQuery = null;

    // Uma linha dizendo, com exemplo, o que o modo marcado faz
    const EXPLICACAO_MODOS = {
        comeca: 'Começa com: códigos que começam com o que você digitou. Ex.: 6205 → 6205, 6205 2RS, 6205 ZZ C3.',
        contem: 'Contém: o que você digitou aparece em qualquer parte do código. Ex.: 2210 → 2210, NU 2210, 22210 CC.',
        exata:  'Busca exata: só o código igual ao digitado. Espaço, hífen e barra não importam: 6205-2RS = 6205 2RS.'
    };

    function atualizarExplicacaoModo() {
        if (explicacaoModo) explicacaoModo.textContent = EXPLICACAO_MODOS[getSelectedMode()] || '';
    }

    document.querySelectorAll('input[name="tipoBusca"]').forEach(radio => {
        radio.addEventListener('change', atualizarExplicacaoModo);
    });
    atualizarExplicacaoModo();

    if (btnClear) {
        btnClear.addEventListener('click', () => {
            // form.reset() devolve cada campo ao valor do atributo value, e a
            // palavra pesquisada vem escrita nele pelo PHP — por isso o
            // "limpar" antigo deixava a busca de volta na tela. Aqui é campo
            // a campo.
            [inputKeyword, inputBrand, inputMinQty, inputInclude, inputExclude]
                .forEach(campo => { if (campo) campo.value = ''; });

            const modoPadrao = document.getElementById('buscaComeca');
            if (modoPadrao) modoPadrao.checked = true;

            document.querySelectorAll('.quick-brand').forEach(b => b.classList.remove('active'));
            atualizarExplicacaoModo();

            divResults.classList.add('d-none');
            divModels.classList.add('d-none');
            divNoResults.classList.remove('d-none');
            if (btnExport) btnExport.classList.add('d-none');

            tableBody.innerHTML = '';
            listChipsModels.innerHTML = '';
            lastQuery = null;

            // O ?q= continua na barra de endereço depois da busca. Sem tirar
            // daqui, recarregar a página traz a pesquisa "limpa" de volta — e
            // é esse o link que a pessoa copia para mandar para alguém.
            if (window.history && history.replaceState) {
                history.replaceState(null, '', location.pathname);
            }

            if (inputKeyword) inputKeyword.focus();
        });
    }

    document.querySelectorAll('.quick-brand').forEach(btn => {
        btn.addEventListener('click', () => {
            const b = btn.getAttribute('data-brand');
            if (!inputBrand) return;

            inputBrand.value = (inputBrand.value === b) ? '' : b;
            document.querySelectorAll('.quick-brand').forEach(other => {
                other.classList.toggle('active', other.getAttribute('data-brand') === inputBrand.value);
            });

            if (inputKeyword.value.trim() !== '' || inputBrand.value.trim() !== '') {
                performSearch(1);
                fetchModels();
            }
        });
    });

    formSearch.addEventListener('submit', (e) => {
        e.preventDefault();
        performSearch(1);
        fetchModels();
    });

    // Palavra-chave vinda da URL executa a busca automaticamente
    if (inputKeyword && inputKeyword.value.trim() !== '') {
        performSearch(1);
        fetchModels();
    }

    function getSelectedMode() {
        const checked = document.querySelector('input[name="tipoBusca"]:checked');
        return checked ? checked.value : 'comeca';
    }

    function currentFilters() {
        return {
            palavra_chave: inputKeyword.value.trim(),
            tipo_busca: getSelectedMode(),
            marca: inputBrand.value.trim(),
            quantidade_minima: inputMinQty ? inputMinQty.value : '',
            incluindo: inputInclude ? inputInclude.value.trim() : '',
            excluindo: inputExclude ? inputExclude.value.trim() : ''
        };
    }

    function updateExportLink() {
        if (!btnExport) return;

        const filters = currentFilters();
        const params = new URLSearchParams();
        Object.keys(filters).forEach(key => {
            if (filters[key]) params.append(key, filters[key]);
        });

        btnExport.href = apiUrl('pesquisa/exportar') + '?' + params.toString();
        btnExport.classList.remove('d-none');
    }

    function performSearch(page = 1) {
        const filters = currentFilters();

        if (!filters.palavra_chave && !filters.marca) return;

        lastQuery = filters;

        divLoading.classList.remove('d-none');
        divResults.classList.add('d-none');
        divNoResults.classList.add('d-none');

        const formData = new FormData();
        Object.keys(filters).forEach(key => formData.append(key, filters[key]));
        formData.append('pagina', page);

        fetch(apiUrl('api/pesquisa/buscar'), {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken()
            }
        })
        .then(res => res.json())
        .then(res => {
            divLoading.classList.add('d-none');

            if (!res.success || !res.data || res.data.length === 0) {
                divResults.classList.add('d-none');
                divNoResults.classList.remove('d-none');
                divNoResults.innerHTML = `
                    <i class="fa-solid fa-face-frown fs-1 text-muted mb-3 opacity-50"></i>
                    <h5 class="fw-bold text-dark">Nenhum rolamento encontrado</h5>
                    <p class="m-0">Não encontramos peças para os filtros selecionados. Confira o código, tente só o número-base (ex.: <strong>23048</strong>) e use o campo <strong>Incluindo</strong> para o sufixo.</p>
                `;
                oferecerContem(res.sugestao_contem);
                if (btnExport) btnExport.classList.add('d-none');
                return;
            }

            divNoResults.classList.add('d-none');
            divResults.classList.remove('d-none');
            totalResultsText.innerHTML = `<strong>${Number(res.total).toLocaleString('pt-BR')}</strong> resultado(s) encontrado(s)`;

            renderRows(res.data);
            renderPagination(res.page, res.pages);
            updateExportLink();
        })
        .catch(() => {
            divLoading.classList.add('d-none');
            divNoResults.classList.remove('d-none');
            divNoResults.innerHTML = `
                <i class="fa-solid fa-triangle-exclamation fs-1 text-warning mb-3"></i>
                <h5 class="fw-bold text-dark">Não foi possível concluir a busca</h5>
                <p class="m-0">Verifique sua conexão e tente novamente em instantes.</p>
            `;
        });
    }

    /**
     * "Começa com" sem resultado, mas o "Contém" teria: oferece a troca com
     * um clique, dizendo quantos aparecem. A busca não muda sozinha — o
     * resultado na tela é sempre o do modo marcado.
     */
    function oferecerContem(total) {
        if (!(Number(total) > 0)) return;

        const botao = document.createElement('button');
        botao.type = 'button';
        botao.className = 'btn btn-primary btn-sm fw-bold mt-3';
        botao.innerHTML = '<i class="fa-solid fa-asterisk me-1"></i> ';
        botao.appendChild(document.createTextNode(
            `Ver ${Number(total).toLocaleString('pt-BR')} resultado(s) com "Contém"`
        ));
        botao.addEventListener('click', () => {
            const contem = document.getElementById('buscaContem');
            if (contem) contem.checked = true;
            atualizarExplicacaoModo();
            performSearch(1);
            fetchModels();
        });

        divNoResults.appendChild(botao);
    }

    function fetchModels() {
        const filters = currentFilters();

        if (!filters.palavra_chave) {
            divModels.classList.add('d-none');
            return;
        }

        // Os chips seguem os mesmos filtros da tabela
        const formData = new FormData();
        Object.keys(filters).forEach(key => formData.append(key, filters[key]));

        fetch(apiUrl('api/pesquisa/modelos'), {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken()
            }
        })
        .then(res => res.json())
        .then(res => {
            if (!res.success || !res.models || res.models.length <= 1) {
                divModels.classList.add('d-none');
                return;
            }

            divModels.classList.remove('d-none');
            listChipsModels.innerHTML = '';

            res.models.forEach(m => {
                const chip = document.createElement('div');
                chip.className = 'chip-model';

                // Cada parte é inserida como texto: marca e modelo vêm da
                // planilha enviada pelo fornecedor e não podem virar HTML.
                const label = document.createElement('span');
                label.textContent = m.product;

                const badge = document.createElement('span');
                badge.className = 'chip-badge';
                badge.textContent = `${m.brand} (${Number(m.total_pieces).toLocaleString('pt-BR')} un)`;

                chip.appendChild(label);
                chip.appendChild(badge);

                chip.addEventListener('click', () => {
                    inputKeyword.value = m.product;
                    const rExata = document.getElementById('buscaExata');
                    if (rExata) rExata.checked = true;
                    // O modelo escolhido já passou pelo Incluindo/Excluindo.
                    // Mantidos, eles se somariam à busca exata e zerariam a lista.
                    if (inputInclude) inputInclude.value = '';
                    if (inputExclude) inputExclude.value = '';
                    atualizarExplicacaoModo();
                    performSearch(1);
                });

                listChipsModels.appendChild(chip);
            });
        })
        .catch(() => divModels.classList.add('d-none'));
    }

    function renderRows(items) {
        tableBody.innerHTML = '';

        items.forEach(item => {
            const tr = document.createElement('tr');
            tr.className = 'fade-in';

            const localizacao = (item.city && item.state) ? `${item.city}/${item.state}` : 'Brasil';

            // Foto da peça
            const tdFoto = document.createElement('td');
            tdFoto.className = 'ps-4';
            tdFoto.dataset.rotulo = '';

            if (item.foto_url) {
                const img = document.createElement('img');
                // O atributo loading precisa ser definido ANTES do src: no
                // sentido inverso o navegador deixa a imagem presa em "lazy"
                // e ela nunca chega a carregar.
                img.loading = 'lazy';
                img.alt = `${item.brand} ${item.product}`;
                img.className = 'foto-resultado';
                img.title = 'Clique para ampliar';
                img.addEventListener('click', () => abrirFotosDoItem(item));
                img.src = item.foto_url;

                const wrapper = document.createElement('div');
                wrapper.className = 'position-relative d-inline-block';
                wrapper.appendChild(img);

                if (Number(item.fotos_total) > 1) {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-dark position-absolute top-0 start-100 translate-middle';
                    badge.style.fontSize = '.6rem';
                    badge.textContent = item.fotos_total;
                    wrapper.appendChild(badge);
                }

                tdFoto.appendChild(wrapper);
            } else {
                const vazia = document.createElement('span');
                vazia.className = 'foto-vazia';
                vazia.title = 'Sem foto cadastrada';
                vazia.innerHTML = '<i class="fa-solid fa-image"></i>';
                tdFoto.appendChild(vazia);
            }

            // Marca
            const tdBrand = document.createElement('td');
            tdBrand.className = 'ps-4';
            tdBrand.dataset.rotulo = 'Marca';
            const badgeBrand = document.createElement('span');
            badgeBrand.className = 'badge bg-secondary-subtle text-dark fw-bold';
            badgeBrand.textContent = item.brand;
            tdBrand.appendChild(badgeBrand);

            // Produto
            const tdProduct = document.createElement('td');
            tdProduct.dataset.rotulo = 'Código';
            const spanProduct = document.createElement('span');
            spanProduct.className = 'fw-bold text-primary fs-6';
            spanProduct.textContent = item.product;
            tdProduct.appendChild(spanProduct);

            // Quantidade
            const tdQty = document.createElement('td');
            tdQty.className = 'text-center';
            tdQty.dataset.rotulo = 'Quantidade';
            const badgeQty = document.createElement('span');
            badgeQty.className = 'badge bg-success-subtle text-success fs-7 fw-bold px-3 py-2';
            badgeQty.textContent = `${Number(item.quantity).toLocaleString('pt-BR')} un`;
            tdQty.appendChild(badgeQty);

            // Fornecedor
            const tdSupplier = document.createElement('td');
            tdSupplier.dataset.rotulo = 'Distribuidor';
            const divName = document.createElement('div');
            divName.className = 'fw-bold text-dark';
            divName.textContent = item.supplier_name || 'Fornecedor';
            const smallLocation = document.createElement('small');
            smallLocation.className = 'text-muted';
            smallLocation.innerHTML = '<i class="fa-solid fa-location-dot me-1"></i>';
            smallLocation.appendChild(document.createTextNode(localizacao));
            const blocoFornecedor = document.createElement('div');
            blocoFornecedor.className = 'min-width-0';
            blocoFornecedor.appendChild(divName);
            blocoFornecedor.appendChild(smallLocation);
            tdSupplier.appendChild(blocoFornecedor);

            // Ações
            const tdActions = document.createElement('td');
            tdActions.className = 'text-end pe-4';
            tdActions.dataset.rotulo = '';
            const group = document.createElement('div');
            group.className = 'd-inline-flex gap-2 align-items-center';

            const btnDetails = document.createElement('button');
            btnDetails.type = 'button';
            btnDetails.className = 'btn btn-outline-primary btn-sm btn-supplier-modal text-nowrap';
            btnDetails.innerHTML = '<i class="fa-solid fa-eye me-1"></i> Detalhes';
            btnDetails.addEventListener('click', () => openSupplierModal(item.user_id));
            group.appendChild(btnDetails);

            const waHref = safeExternalUrl(item.whatsapp_link);
            if (waHref) {
                const linkWa = document.createElement('a');
                linkWa.href = waHref;
                linkWa.target = '_blank';
                linkWa.rel = 'noopener noreferrer';
                linkWa.className = 'btn btn-success btn-sm px-3 fw-bold text-nowrap';
                linkWa.innerHTML = '<i class="fa-brands fa-whatsapp me-1"></i> WhatsApp';
                group.appendChild(linkWa);
            }

            tdActions.appendChild(group);

            tr.append(tdFoto, tdBrand, tdProduct, tdQty, tdSupplier, tdActions);
            tableBody.appendChild(tr);
        });
    }

    /**
     * Busca todas as fotos da peça e abre o visualizador.
     */
    function abrirFotosDoItem(item) {
        const titulo = `${item.brand} ${item.product}`;

        fetch(apiUrl('api/pesquisa/fotos') + '?item_id=' + encodeURIComponent(item.id), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(res => {
            const lista = (res.success && res.fotos && res.fotos.length)
                ? res.fotos.map(f => ({ grande: f.grande, titulo }))
                : [{ grande: item.foto_url, titulo }];

            Visualizador.abrir(lista, 0);
        })
        .catch(() => Visualizador.abrir([{ grande: item.foto_url, titulo }], 0));
    }

    function renderPagination(page, pages) {
        const nav = document.getElementById('paginacaoControles');
        const info = document.getElementById('infoPaginacao');
        if (!nav) return;

        if (info) info.textContent = `Página ${page} de ${pages}`;
        nav.innerHTML = '';

        if (pages <= 1) return;

        const ul = document.createElement('ul');
        ul.className = 'pagination pagination-sm m-0';

        const addItem = (label, targetPage, isActive) => {
            const li = document.createElement('li');
            li.className = `page-item ${isActive ? 'active' : ''}`;

            const a = document.createElement('a');
            a.className = 'page-link';
            a.href = '#';
            a.innerHTML = label;
            a.addEventListener('click', (e) => {
                e.preventDefault();
                performSearch(targetPage);
                document.getElementById('divResultados').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });

            li.appendChild(a);
            ul.appendChild(li);
        };

        if (page > 1) addItem('&laquo;', page - 1, false);

        for (let p = Math.max(1, page - 2); p <= Math.min(pages, page + 2); p++) {
            addItem(String(p), p, p === page);
        }

        if (page < pages) addItem('&raquo;', page + 1, false);

        nav.appendChild(ul);
    }

    function openSupplierModal(userId) {
        const modalElement = document.getElementById('modalFornecedor');
        const modalTitle = document.getElementById('modalFornecedorTitulo');
        const modalBody = document.getElementById('modalFornecedorCorpo');
        const btnWa = document.getElementById('modalFornecedorBtnWhatsapp');

        if (!modalElement) return;

        modalBody.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="text-muted mt-2 small">Carregando dados da empresa...</div>
            </div>
        `;

        const bsModal = new bootstrap.Modal(modalElement);
        bsModal.show();

        fetch(apiUrl('api/pesquisa/fornecedor') + '?id_user=' + encodeURIComponent(userId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                modalBody.innerHTML = '<div class="alert alert-danger mb-0">Não foi possível carregar os dados deste fornecedor.</div>';
                return;
            }

            const d = res.data;

            modalTitle.innerHTML = '<i class="fa-solid fa-building me-2 text-primary"></i>';
            modalTitle.appendChild(document.createTextNode(d.trading_name || 'Fornecedor'));

            modalBody.innerHTML = '';
            modalBody.appendChild(infoBlock('Razão Social', d.company_name, 'CNPJ: ' + (d.cnpj || 'Não informado')));
            modalBody.appendChild(document.createElement('hr'));
            modalBody.appendChild(infoBlock('Telefone', d.phone));
            modalBody.appendChild(infoBlock('E-mail Comercial', d.contact_email));
            modalBody.appendChild(infoBlock(
                'Endereço',
                [d.address, d.district].filter(Boolean).join(' - '),
                [d.city, d.state].filter(Boolean).join('/')
            ));

            const website = safeExternalUrl(d.website);
            if (website) {
                const wrapper = document.createElement('div');
                wrapper.className = 'mt-3';

                const link = document.createElement('a');
                link.href = website;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.className = 'text-decoration-none';
                link.innerHTML = '<i class="fa-solid fa-globe me-1"></i>';
                link.appendChild(document.createTextNode(website));

                wrapper.appendChild(link);
                modalBody.appendChild(wrapper);
            }

            if (btnWa) {
                const digits = String(d.whatsapp || '').replace(/[^0-9]/g, '');
                if (digits.length >= 10) {
                    btnWa.href = 'https://wa.me/' + (digits.startsWith('55') ? digits : '55' + digits);
                    btnWa.classList.remove('d-none');
                } else {
                    btnWa.classList.add('d-none');
                }
            }
        })
        .catch(() => {
            modalBody.innerHTML = '<div class="alert alert-danger mb-0">Erro de comunicação. Tente novamente.</div>';
        });
    }

    function infoBlock(label, value, extra) {
        const wrapper = document.createElement('div');
        wrapper.className = 'mb-3';

        const labelEl = document.createElement('label');
        labelEl.className = 'text-muted text-uppercase fw-bold d-block';
        labelEl.style.fontSize = '.7rem';
        labelEl.textContent = label;

        const valueEl = document.createElement('div');
        valueEl.className = 'fw-bold text-dark';
        valueEl.textContent = value || 'Não informado';

        wrapper.appendChild(labelEl);
        wrapper.appendChild(valueEl);

        if (extra) {
            const extraEl = document.createElement('small');
            extraEl.className = 'text-muted';
            extraEl.textContent = extra;
            wrapper.appendChild(extraEl);
        }

        return wrapper;
    }
}

/**
 * Só permite http(s) em links externos: bloqueia javascript: e data:
 * mesmo que algum dado do banco tenha sido gravado antes das validações.
 */
function safeExternalUrl(value) {
    if (!value) return null;

    try {
        const parsed = new URL(String(value), window.location.origin);
        return (parsed.protocol === 'http:' || parsed.protocol === 'https:') ? parsed.href : null;
    } catch (e) {
        return null;
    }
}

/**
 * Dropzone de Upload de Arquivos
 */
function initUploadDropzone() {
    const input = document.getElementById('inputArquivoEstoque');
    const label = document.getElementById('labelNomeArquivo');
    const container = document.getElementById('nomeArquivoSelecionado');
    const dropzone = document.querySelector('.upload-dropzone');
    const form = document.getElementById('formUploadEstoque');
    const btnSubmit = document.getElementById('btnEnviarEstoque');

    if (!input || !dropzone) return;

    const showFile = (file) => {
        if (!file) return;
        label.textContent = file.name;
        container.classList.remove('d-none');
    };

    input.addEventListener('change', () => showFile(input.files[0]));

    ['dragenter', 'dragover'].forEach(event => {
        dropzone.addEventListener(event, (e) => {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(event => {
        dropzone.addEventListener(event, (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
        });
    });

    // Arrastar e soltar precisa alimentar o input do formulário
    dropzone.addEventListener('drop', (e) => {
        if (e.dataTransfer && e.dataTransfer.files.length > 0) {
            input.files = e.dataTransfer.files;
            showFile(input.files[0]);
        }
    });

    if (form && btnSubmit) {
        form.addEventListener('submit', () => {
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Importando, aguarde...';
        });
    }
}

/**
 * Ajuste de quantidade na tela "Meu Estoque".
 *
 * O botão de salvar só fica ativo quando o número realmente mudou, para
 * deixar claro o que está pendente de gravação. Sem JavaScript o formulário
 * continua funcionando como um envio comum.
 */
function initQuantidadeEstoque() {
    const formularios = document.querySelectorAll('.form-quantidade');
    if (!formularios.length) return;

    formularios.forEach(form => {
        const campo = form.querySelector('input[name="quantity"]');
        const botao = form.querySelector('.btn-salvar-quantidade');
        if (!campo || !botao) return;

        const original = campo.getAttribute('data-valor-original');

        const atualizar = () => {
            const mudou = campo.value !== '' && campo.value !== original;
            botao.disabled = !mudou;
            botao.classList.toggle('btn-success', mudou);
            botao.classList.toggle('btn-outline-success', !mudou);
            campo.classList.toggle('border-success', mudou);
        };

        atualizar();
        campo.addEventListener('input', atualizar);

        // Enter no campo salva direto
        campo.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !botao.disabled) {
                form.submit();
            }
        });
    });
}

/**
 * Máscaras simples de telefone, CNPJ e CEP
 */
function initMasks() {
    const apply = (selector, formatter) => {
        document.querySelectorAll(selector).forEach(input => {
            const handler = () => { input.value = formatter(input.value); };
            input.addEventListener('input', handler);
            if (input.value) handler();
        });
    };

    apply('[data-mask="phone"]', (v) => {
        const d = v.replace(/\D/g, '').slice(0, 11);
        if (d.length <= 10) {
            return d.replace(/(\d{0,2})(\d{0,4})(\d{0,4})/, (m, a, b, c) =>
                (a ? '(' + a : '') + (a.length === 2 ? ') ' : '') + b + (c ? '-' + c : '')).trim();
        }
        return d.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
    });

    apply('[data-mask="cnpj"]', (v) => {
        const d = v.replace(/\D/g, '').slice(0, 14);
        return d
            .replace(/^(\d{2})(\d)/, '$1.$2')
            .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d)/, '.$1/$2')
            .replace(/(\d{4})(\d)/, '$1-$2');
    });

    apply('[data-mask="cep"]', (v) => {
        const d = v.replace(/\D/g, '').slice(0, 8);
        return d.replace(/^(\d{5})(\d)/, '$1-$2');
    });
}

/**
 * =====================================================================
 * Aviso de espera nos formulários demorados
 * =====================================================================
 *
 * Enviar um e-mail leva de 3 a 4 segundos: o servidor abre conexão com o
 * SMTP, autentica e entrega a mensagem antes de responder. Nesse intervalo
 * a tela ficava parada, sem sinal nenhum — e quem está esperando clica de
 * novo, o que gera um segundo cadastro ou um segundo código.
 *
 * Aqui o formulário avisa o que está acontecendo e se tranca até a resposta
 * chegar. Basta marcar o <form> com data-aguarde="mensagem"; sem JavaScript
 * o envio continua funcionando como sempre, só sem o aviso.
 */
function initAguarde() {
    const formularios = document.querySelectorAll('form[data-aguarde]');
    if (formularios.length === 0) return;

    let capa = null;

    const mostrarCapa = (mensagem) => {
        if (capa) return;

        capa = document.createElement('div');
        capa.className = 'capa-aguarde';
        capa.setAttribute('role', 'status');
        capa.setAttribute('aria-live', 'polite');
        capa.innerHTML = `
            <div class="capa-aguarde-caixa">
                <div class="spinner-border" role="presentation"></div>
                <div class="capa-aguarde-texto"></div>
                <div class="capa-aguarde-dica">Não feche nem atualize esta página.</div>
            </div>
        `;
        capa.querySelector('.capa-aguarde-texto').textContent = mensagem;

        // Sem requestAnimationFrame de proposito: ele nao roda com a aba em
        // segundo plano e pode nao rodar a tempo enquanto o navegador ja esta
        // saindo da pagina. A aparicao fica por conta de uma animacao de CSS,
        // que comeca sozinha quando o elemento entra no documento.
        document.body.appendChild(capa);
    };

    const esconderCapa = () => {
        if (!capa) return;
        capa.remove();
        capa = null;
    };

    formularios.forEach((form) => {
        const botao = form.querySelector('[type="submit"]');
        const original = botao ? botao.innerHTML : '';
        let enviando = false;

        form.addEventListener('submit', (evento) => {
            // Segundo clique enquanto o primeiro ainda está em curso
            if (enviando) {
                evento.preventDefault();
                return;
            }

            // Campo obrigatório vazio: o navegador barra o envio sozinho e
            // não faz sentido mostrar "aguarde" para algo que não saiu.
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return;
            }

            enviando = true;

            if (botao) {
                botao.disabled = true;
                botao.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-2" role="presentation"></span>'
                    + (botao.dataset.aguardeRotulo || 'Enviando...');
            }

            mostrarCapa(form.dataset.aguarde);
        });

        // Voltar pelo botão do navegador pode restaurar a página do cache
        // com o botão travado e a capa por cima. Aqui ela volta ao normal.
        window.addEventListener('pageshow', (evento) => {
            if (!evento.persisted) return;

            enviando = false;
            esconderCapa();

            if (botao) {
                botao.disabled = false;
                botao.innerHTML = original;
            }
        });
    });
}
