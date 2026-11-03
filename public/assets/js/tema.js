/* Preferência no banco por conta; visitantes usam armazenamento local separado. */
(() => {
    const raiz=document.documentElement, usuario=raiz.dataset.usuario||'0';
    const chave='akron-tema:'+usuario, sistema=matchMedia('(prefers-color-scheme: dark)');
    let escolha=raiz.dataset.preferenciaTema||'automatico';
    if(usuario==='0') { try { escolha=localStorage.getItem(chave)||'automatico'; } catch {} }
    function aplicar(){const tema=escolha==='automatico'?(sistema.matches?'escuro':'claro'):escolha;raiz.dataset.tema=tema;document.querySelectorAll('[data-alternar-tema]').forEach(b=>{b.title=b.ariaLabel='Alterar tema (atual: '+escolha+')';});document.dispatchEvent(new CustomEvent('akron:tema',{detail:{tema}}));}
    aplicar();
    sistema.addEventListener('change',()=>{if(escolha==='automatico')aplicar();});
    window.addEventListener('storage',e=>{if(e.key===chave&&['claro','escuro','automatico'].includes(e.newValue)){escolha=e.newValue;aplicar();}});
    document.addEventListener('DOMContentLoaded',()=>{
        aplicar();
        document.querySelectorAll('[data-alternar-tema]').forEach(b=>b.addEventListener('click',async()=>{
            const anterior=escolha;escolha=['automatico','claro','escuro'][(['automatico','claro','escuro'].indexOf(escolha)+1)%3];aplicar();b.disabled=true;
            try {
                if(usuario!=='0') {const r=await fetch(raiz.dataset.contaUrl,{method:'POST',body:new URLSearchParams({acao:'tema',tema:escolha,csrf_token:raiz.dataset.csrf})});if(!r.ok||!(await r.json()).sucesso)throw Error('Não foi possível salvar o tema. Tente novamente.');}
                try{localStorage.setItem(chave,escolha);}catch{}
            } catch(e){escolha=anterior;aplicar();window.akronAviso?.(e.message);} finally{b.disabled=false;}
        }));
    });
})();
