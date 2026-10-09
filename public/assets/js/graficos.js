/* graficos.js — utilitários de gráficos (ÁKRON)
 * Usado pelas views em app/views/graficos/*.php.
 *
 * Depende da biblioteca Chart.js (public/assets/js/vendor/chart.umd.js, v4),
 * que as views carregam ANTES deste arquivo, e de tema.js (evento "akron:tema").
 *
 * As cores dos gráficos NÃO ficam aqui: são lidas das variáveis CSS
 * --cor-grafico-* (style.css), por isso mudam sozinhas com o tema claro/escuro.
 */

// ── Cores do tema ────────────────────────────────────────────────────────────

/** Lê uma variável CSS do <html> (ex.: '--cor-grafico-linha'). */
function lerVariavelCss(nome, reserva) {
    var valor = getComputedStyle(document.documentElement).getPropertyValue(nome).trim();
    return valor || reserva;
}

/** Paleta atual dos gráficos, conforme o tema ativo. */
function paletaDoTema() {
    return {
        linha: lerVariavelCss('--cor-grafico-linha', '#4f6b85'),
        area: lerVariavelCss('--cor-grafico-area', 'rgba(79, 107, 133, 0.16)'),
        grade: lerVariavelCss('--cor-grafico-grade', '#d9e1ea'),
        texto: lerVariavelCss('--cor-grafico-texto', '#4a5b6c'),
        critico: lerVariavelCss('--cor-grafico-critico', 'rgba(194, 49, 63, 0.78)'),
        atencao: lerVariavelCss('--cor-grafico-atencao', 'rgba(214, 154, 31, 0.85)'),
        info: lerVariavelCss('--cor-grafico-info', 'rgba(79, 107, 133, 0.78)')
    };
}

/**
 * Cor dos pontos da linha, na cor da linha do tema ativo.
 * É uma função (opção "scriptable" do Chart.js) e não um valor fixo porque, com valor
 * fixo, o Chart.js reaproveita as opções antigas dos pontos ao trocar de tema
 * (os pontos ficariam na cor padrão do Chart.js, um azul que não é da marca).
 */
function corDoPonto(contexto) {
    return (contexto.chart.$akronPaleta || paletaDoTema()).linha;
}

/**
 * Aplica a paleta a um gráfico já criado (cores de eixos, grade, título,
 * legenda e séries). Chamada na criação e a cada troca de tema.
 */
function aplicarPaleta(grafico) {
    var p = paletaDoTema();
    grafico.$akronPaleta = p; // lida por corDoPonto()
    var opcoes = grafico.options;

    opcoes.plugins.title.color = p.texto;
    if (opcoes.plugins.legend && opcoes.plugins.legend.labels) {
        opcoes.plugins.legend.labels.color = p.texto;
    }
    opcoes.scales.x.ticks.color = p.texto;
    opcoes.scales.y.ticks.color = p.texto;
    opcoes.scales.x.grid.color = p.grade;
    opcoes.scales.y.grid.color = p.grade;

    var serie = grafico.data.datasets[0];
    if (grafico.$akronTipo === 'linha') {
        serie.borderColor = p.linha;
        serie.backgroundColor = p.area;
    } else if (grafico.$akronTipo === 'barras') {
        // Cada barra recebe a cor da severidade correspondente (critico, atencao, info)
        serie.backgroundColor = grafico.$akronCores.map(function (chave) {
            return p[chave] || p.info;
        });
    }
}

/** Recolore todos os gráficos da página (ouvinte do evento de troca de tema). */
function recolorirGraficos() {
    if (typeof Chart === 'undefined') return;
    Object.keys(Chart.instances).forEach(function (id) {
        var grafico = Chart.instances[id];
        if (grafico.$akronTipo) {
            aplicarPaleta(grafico);
            grafico.update('none'); // sem animação: troca instantânea
        }
    });
}
document.addEventListener('akron:tema', recolorirGraficos);

// ── Formatação ───────────────────────────────────────────────────────────────

/** '2026-10-09 14:30:00' → '09/10 14:30' (rótulo curto do eixo X). */
function formatarRotulo(dataSql) {
    var d = new Date(String(dataSql).replace(' ', 'T'));
    if (isNaN(d.getTime())) return dataSql;
    var dois = function (n) { return (n < 10 ? '0' : '') + n; };
    return dois(d.getDate()) + '/' + dois(d.getMonth() + 1) + ' ' + dois(d.getHours()) + ':' + dois(d.getMinutes());
}

// ── Criação dos gráficos ─────────────────────────────────────────────────────

/**
 * Cria um gráfico de linha para leituras de um sensor ao longo do tempo.
 * @param {string}   canvasId  id do <canvas>
 * @param {string[]} labels    datas ('AAAA-MM-DD HH:MM:SS')
 * @param {number[]} valores   valores medidos
 * @param {string}   rotulo    Ex: "Temperatura (°C)"
 * @param {string}   titulo    título exibido sobre o gráfico
 * @returns {Chart|null}
 */
function criarGraficoLinha(canvasId, labels, valores, rotulo, titulo) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    var grafico = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: labels.map(formatarRotulo),
            datasets: [{
                label: rotulo,
                data: valores,
                tension: 0.3,
                fill: true,
                pointRadius: valores.length > 60 ? 0 : 3,
                pointHoverRadius: 5,
                pointBackgroundColor: corDoPonto,
                pointBorderColor: corDoPonto
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // a altura vem do CSS (.grafico-area)
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: {} },
                title: { display: true, text: titulo }
            },
            scales: {
                x: { ticks: { maxTicksLimit: 10, maxRotation: 45 }, grid: {} },
                y: { ticks: {}, grid: {} }
            }
        }
    });
    grafico.$akronTipo = 'linha';
    aplicarPaleta(grafico);
    grafico.update('none');
    return grafico;
}

/**
 * Cria um gráfico de barras (tela de alertas: contagem por severidade).
 * @param {string}   canvasId id do <canvas>
 * @param {string[]} labels   nome de cada barra
 * @param {number[]} valores  altura de cada barra
 * @param {string}   rotulo   nome da série
 * @param {string}   titulo   título exibido sobre o gráfico
 * @param {string[]} cores    chave da paleta de cada barra: 'critico' | 'atencao' | 'info'
 * @returns {Chart|null}
 */
function criarGraficoBarras(canvasId, labels, valores, rotulo, titulo, cores) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    var grafico = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{ label: rotulo, data: valores, borderRadius: 6, maxBarThickness: 90 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // a altura vem do CSS (.grafico-area)
            plugins: {
                legend: { display: false },
                title: { display: true, text: titulo }
            },
            scales: {
                x: { ticks: {}, grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: {} }
            }
        }
    });
    grafico.$akronTipo = 'barras';
    grafico.$akronCores = cores || [];
    aplicarPaleta(grafico);
    grafico.update('none');
    return grafico;
}

// ── Atualização em tempo real ────────────────────────────────────────────────

/**
 * Atualização em tempo real (AJAX).
 * Busca novos pontos no endpoint e substitui os dados do gráfico já criado,
 * sem recarregar a página.
 *
 * @param {Chart}  grafico      instância retornada por criarGraficoLinha()
 * @param {string} urlDados     endereço do endpoint JSON (routes/grafico_dados.php)
 * @param {string} sensor       tipo do sensor (ex.: 'temperatura')
 * @param {number} horas        janela de tempo em horas
 * @param {number} intervaloMs  intervalo entre atualizações (padrão 15 s)
 * @returns {number|null} id do temporizador (use clearInterval para parar)
 */
function iniciarAtualizacaoTempoReal(grafico, urlDados, sensor, horas, intervaloMs) {
    if (!grafico) return null;
    intervaloMs = intervaloMs || 15000;

    var indicador = document.querySelector('.grafico-status-tempo-real');
    var totalEl = document.getElementById('graficoTotalLeituras');
    var temporizador = null;

    async function atualizar() {
        try {
            var url = urlDados + '?sensor=' + encodeURIComponent(sensor) + '&horas=' + encodeURIComponent(horas);
            var resposta = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

            if (resposta.status === 401) { // sessão expirou: para de consultar
                clearInterval(temporizador);
                if (indicador) indicador.title = 'Sessão expirada — recarregue a página';
                return;
            }
            if (!resposta.ok) throw new Error('Falha na requisição (' + resposta.status + ')');

            var dados = await resposta.json();
            grafico.data.labels = dados.labels.map(formatarRotulo);
            grafico.data.datasets[0].data = dados.valores;
            grafico.update();

            if (totalEl) totalEl.textContent = dados.total;
            if (indicador) indicador.title = 'Atualizado às ' + new Date().toLocaleTimeString('pt-BR');
        } catch (erro) {
            console.error('Não foi possível atualizar o gráfico em tempo real:', erro);
        }
    }

    temporizador = setInterval(atualizar, intervaloMs);
    return temporizador;
}
