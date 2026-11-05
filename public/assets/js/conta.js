/* Minha conta: escolha de foto com prévia, validação prévia e cancelamento.
 * A validação definitiva (tipo real, tamanho, dimensões) é feita no servidor; aqui só se evita um envio inútil. */
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
