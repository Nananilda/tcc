/* Configurações pessoais.
 * 1) Aparência: os cartões de tema aplicam e salvam na hora (window.akronTema, em tema.js).
 *    Sem JavaScript, o tema escolhido segue no formulário e é salvo em "Salvar alterações".
 * 2) Foto: escolha com prévia, validação prévia e cancelamento. A validação definitiva
 *    (tipo real, tamanho, dimensões) é feita no servidor; aqui só se evita um envio inútil. */
(() => {
    const opcoes = document.querySelector('.tema-opcoes'), estado = document.getElementById('tema-estado');
    if (opcoes && window.akronTema) {
        const marcar = valor => opcoes.querySelectorAll('input[name="tema"]').forEach(r => { r.checked = r.value === valor; });
        marcar(window.akronTema.escolha());
        /* Salvar o tema não é "alteração pendente" do formulário: os eventos não sobem até ele. */
        ['input', 'change'].forEach(tipo => opcoes.addEventListener(tipo, e => e.stopPropagation()));
        opcoes.addEventListener('change', async e => {
            const r = e.target; if (!r.matches('input[name="tema"]')) return;
            const rotulo = r.closest('.tema-opcao').querySelector('strong').textContent;
            estado.textContent = 'Salvando…'; opcoes.setAttribute('aria-busy', 'true');
            try { await window.akronTema.definir(r.value); estado.textContent = 'Tema ' + rotulo.toLowerCase() + ' aplicado e salvo na sua conta.'; }
            catch (erro) { marcar(window.akronTema.escolha()); estado.textContent = erro.message; }
            finally { opcoes.removeAttribute('aria-busy'); }
        });
    }
})();
(() => {
    const campo = document.getElementById('campo-foto'); if (!campo) return;
    const previa = document.getElementById('foto-previa'), cancelar = document.getElementById('cancelar-foto'), erro = document.getElementById('foto-erro'),
        nome = document.getElementById('foto-nome'), botao = document.getElementById('botao-foto'), remover = document.querySelector('[name="remover_foto"]');
    const original = previa.innerHTML; let url = null;
    const TIPOS = ['image/jpeg', 'image/png'], LIMITE = 2 * 1024 * 1024;
    function limpar() { if (url) URL.revokeObjectURL(url); url = null; campo.value = ''; previa.innerHTML = original; cancelar.hidden = true; nome.hidden = true; nome.textContent = ''; if (remover) remover.disabled = false; }
    campo.addEventListener('change', () => {
        erro.textContent = ''; const arq = campo.files[0]; if (!arq) { limpar(); return; }
        if (!TIPOS.includes(arq.type)) { limpar(); erro.textContent = 'Formato não aceito. Escolha uma imagem JPG ou PNG.'; return; }
        if (arq.size > LIMITE) { limpar(); erro.textContent = 'A imagem tem mais de 2 MB. Escolha um arquivo menor.'; return; }
        if (url) URL.revokeObjectURL(url); url = URL.createObjectURL(arq);
        const img = document.createElement('img'); img.className = 'avatar'; img.alt = 'Prévia da foto selecionada'; img.src = url;
        previa.replaceChildren(img); cancelar.hidden = false; nome.hidden = false; nome.textContent = 'Selecionada: ' + arq.name + ' (' + (arq.size / 1024).toFixed(0) + ' KB) · ainda não salva';
        if (remover) { remover.checked = false; remover.disabled = true; }
    });
    cancelar.addEventListener('click', () => { limpar(); erro.textContent = ''; botao.focus(); });
    botao.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); campo.click(); } });
})();
