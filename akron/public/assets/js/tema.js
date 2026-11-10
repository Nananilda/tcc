/* Tema claro/escuro/automático.
 * - Usuário logado: a preferência vem do banco (data-preferencia-tema) e é salva pela API de conta.
 * - Visitante (tela de login): usa o último tema deste navegador (espelhado em 'akron-tema:0').
 * A alternância fica somente em Configurações pessoais, que chama window.akronTema.definir().
 * Este script é carregado sem defer para aplicar o tema antes da primeira pintura. */
(() => {
    const raiz = document.documentElement, usuario = raiz.dataset.usuario || '0';
    const OPCOES = ['automatico', 'claro', 'escuro'];
    const chave = 'akron-tema:' + usuario, chaveVisitante = 'akron-tema:0', sistema = matchMedia('(prefers-color-scheme: dark)');
    const ler = k => { try { return localStorage.getItem(k); } catch { return null; } };
    const gravar = (k, v) => { try { localStorage.setItem(k, v); } catch { /* armazenamento indisponível: segue sem espelho */ } };
    let escolha = raiz.dataset.preferenciaTema || 'automatico';
    if (usuario === '0') { const guardado = ler(chaveVisitante); if (OPCOES.includes(guardado)) escolha = guardado; }
    if (!OPCOES.includes(escolha)) escolha = 'automatico';

    function aplicar() {
        const tema = escolha === 'automatico' ? (sistema.matches ? 'escuro' : 'claro') : escolha;
        raiz.dataset.tema = tema;
        document.dispatchEvent(new CustomEvent('akron:tema', { detail: { tema, escolha } }));
    }
    aplicar();
    if (usuario !== '0') gravar(chaveVisitante, escolha); // a tela de login acompanha o último tema usado neste aparelho
    sistema.addEventListener('change', () => { if (escolha === 'automatico') aplicar(); });
    window.addEventListener('storage', e => { if (e.key === chave && OPCOES.includes(e.newValue)) { escolha = e.newValue; aplicar(); } });

    window.akronTema = {
        escolha: () => escolha,
        /** Aplica na hora e salva na conta; se o servidor recusar, volta ao tema anterior e rejeita. */
        async definir(nova) {
            if (!OPCOES.includes(nova)) throw new Error('Tema inválido.');
            const anterior = escolha; escolha = nova; aplicar();
            try {
                if (usuario !== '0') {
                    const r = await fetch(raiz.dataset.contaUrl, { method: 'POST', body: new URLSearchParams({ acao: 'tema', tema: nova, csrf_token: raiz.dataset.csrf }) });
                    const d = await r.json().catch(() => ({}));
                    if (!r.ok || !d.sucesso) throw new Error('Não foi possível salvar o tema. Tente novamente.');
                }
                gravar(chave, nova); gravar(chaveVisitante, nova);
            } catch (e) { escolha = anterior; aplicar(); throw e; }
        },
    };
})();
