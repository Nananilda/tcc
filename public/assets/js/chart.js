/* ============================================================
   chart.js — utilitários de gráficos (IndustrialOS)
   Usado pelas views em app/views/graficos/*.php
   Depende da lib Chart.js (carregada via CDN nas views).
   ============================================================ */

/**
 * Cria um gráfico de linha para leituras de um sensor ao longo do tempo.
 * @param {string} canvasId
 * @param {string[]} labels
 * @param {number[]} valores
 * @param {string} rotulo  Ex: "Temperatura (°C)"
 * @param {string} titulo
 */
function criarGraficoLinha(canvasId, labels, valores, rotulo, titulo) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    return new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: rotulo,
                data: valores,
                borderColor: 'rgb(46, 168, 255)',
                backgroundColor: 'rgba(46, 168, 255, 0.15)',
                tension: 0.3,
                fill: true,
                pointRadius: valores.length > 100 ? 0 : 3
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top', labels: { color: '#e6edf3' } },
                title: { display: true, text: titulo, color: '#e6edf3' }
            },
            scales: {
                x: { ticks: { maxTicksLimit: 12, maxRotation: 45, color: '#9fb1c1' }, grid: { color: '#26343f' } },
                y: { ticks: { color: '#9fb1c1' }, grid: { color: '#26343f' } }
            }
        }
    });
}

/**
 * Cria um gráfico de barras (usado na tela de alertas: contagem por severidade/tipo).
 */
function criarGraficoBarras(canvasId, labels, valores, rotulo, titulo, cores) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    return new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: rotulo,
                data: valores,
                backgroundColor: cores || 'rgba(255, 176, 32, 0.6)',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                title: { display: true, text: titulo, color: '#e6edf3' }
            },
            scales: {
                x: { ticks: { color: '#9fb1c1' }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { color: '#9fb1c1' }, grid: { color: '#26343f' } }
            }
        }
    });
}

/**
 * Atualização em tempo real (AJAX).
 * Busca novos pontos em GraficoController.php e substitui os dados
 * do gráfico já criado, sem recarregar a página.
 *
 * @param {Chart} chart          instância retornada por criarGraficoLinha()
 * @param {string} sensor        tipo do sensor (ex: 'temperatura')
 * @param {number} horas         janela de tempo em horas
 * @param {number} intervaloMs   intervalo entre atualizações (padrão 15s)
 */
function iniciarAtualizacaoTempoReal(chart, sensor, horas, intervaloMs) {
    if (!chart) return null;
    intervaloMs = intervaloMs || 15000;

    const indicador = document.querySelector('.grafico-status-tempo-real');

    async function atualizar() {
        try {
            const url = `../../controllers/GraficoController.php?sensor=${encodeURIComponent(sensor)}&horas=${horas}`;
            const resposta = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!resposta.ok) throw new Error('Falha na requisição');

            const dados = await resposta.json();
            chart.data.labels = dados.labels;
            chart.data.datasets[0].data = dados.valores;
            chart.update();

            if (indicador) indicador.title = 'Atualizado às ' + new Date().toLocaleTimeString('pt-BR');
        } catch (erro) {
            console.error('Não foi possível atualizar o gráfico em tempo real:', erro);
        }
    }

    return setInterval(atualizar, intervaloMs);
}
