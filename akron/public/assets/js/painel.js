/* painel.js — Painel inicial: indicadores e alertas recentes.
 * Os números vêm sempre de routes/painel_dados.php (mesma fonte do servidor); nada é inventado.
 * Dados do servidor são inseridos como texto (textContent), nunca como HTML. */
(() => {
    'use strict';
    const cfg = window.AKRON_PAINEL; if (!cfg) return;
    const reduzir = matchMedia('(prefers-reduced-motion: reduce)');
    const rotulos = { critico: 'Crítico', atencao: 'Atenção', info: 'Informativo', pendente: 'Pendente', reconhecido: 'Reconhecido', normalizado: 'Normalizado', resolvido: 'Resolvido' };
    const el = (tag, texto, classe) => { const e = document.createElement(tag); if (texto !== undefined) e.textContent = texto; if (classe) e.className = classe; return e; };

    /** Troca o número com uma contagem curta (sem animar para quem prefere menos movimento). */
    function atribuir(alvo, novo) {
        const antes = Number(alvo.textContent) || 0;
        if (antes === novo || reduzir.matches) { alvo.textContent = novo; return; }
        const t0 = performance.now(), dur = 420;
        const passo = agora => { const k = Math.min(1, (agora - t0) / dur); alvo.textContent = Math.round(antes + (novo - antes) * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(passo); };
        requestAnimationFrame(passo);
        const cartao = alvo.closest('.indicador'); if (cartao) { cartao.classList.remove('atualizado'); void cartao.offsetWidth; cartao.classList.add('atualizado'); }
    }

    function apresentar(r) {
        document.querySelectorAll('.indicador[data-chave]').forEach(c => {
            const valor = Number(r[c.dataset.chave] ?? 0);
            atribuir(c.querySelector('.indicador-valor'), valor);
            if (c.dataset.problema === '1') { c.classList.toggle('ind-atencao', valor > 0); c.classList.toggle('ind-ok', valor === 0); }
        });
        const ultima = document.getElementById('painel-ultima');
        if (ultima) ultima.textContent = r.ultima_atualizacao ? dataBr(r.ultima_atualizacao) : 'Nenhuma leitura recebida';
        const area = document.getElementById('painel-alertas'); area.replaceChildren();
        const lista = r.alertas_recentes || [];
        if (!lista.length) { area.append(el('p', 'Nenhum alerta ativo. Os alertas aparecem aqui quando alguma medição ultrapassar os limites.', 'estado-vazio')); return; }
        lista.forEach(a => {
            const item = el('article', undefined, 'alerta-resumo alerta-resumo-' + a.severidade);
            item.append(el('span', rotulos[a.severidade] || a.severidade, 'badge badge-' + a.severidade), el('strong', a.mensagem),
                el('p', [a.estoque_nome, a.sensor_nome, dataBr(a.criado_em), rotulos[a.status] || a.status].join(' · '), 'texto-auxiliar'));
            area.append(item);
        });
    }

    async function atualizar() {
        const status = document.getElementById('painel-status');
        try {
            const resp = await fetch(cfg.url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: AbortSignal.timeout(12000) });
            if (resp.status === 401) throw new Error('Sessão expirada. Entre novamente.');
            const d = await resp.json(); if (!resp.ok || !d.sucesso) throw new Error(d.mensagem || 'Não foi possível atualizar o painel.');
            apresentar(d.resumo); status.textContent = '';
        } catch (e) { status.textContent = e.message + ' Os dados exibidos podem estar desatualizados.'; }
    }

    apresentar(cfg.resumo);
    let ocupado = false;
    setInterval(async () => { if (ocupado || document.hidden) return; ocupado = true; try { await atualizar(); } finally { ocupado = false; } }, 15000);
})();
