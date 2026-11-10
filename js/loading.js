/* =========================================================
   Athenas — controle da tela de carregamento
   • aparece só na primeira carga da sessão (não reaparece ao navegar)
   • duração curta e limitada: nunca espera além de ~4 s
   • encerra mesmo se algum recurso demorar; não bloqueia o site
   ========================================================= */
(function () {
    'use strict';
    var root = document.documentElement;
    var loader = document.getElementById('loader');
    function done() { root.classList.remove('has-loader'); if (loader && loader.parentNode) loader.parentNode.removeChild(loader); }
    if (!loader || !root.classList.contains('has-loader')) { if (loader) loader.parentNode.removeChild(loader); return; }

    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    var start = Date.now();
    var MIN = reduce ? 700 : 1700;      // algumas batidas de asa antes de decolar
    var MAX = 4000;                      // teto absoluto, mesmo com recursos lentos
    var FLY = reduce ? 0 : 1000;         // duração da subida
    var loaded = document.readyState === 'complete', started = false;

    function leave() {
        if (started) return; started = true;
        try { sessionStorage.setItem('athenas-intro', '1'); } catch (e) { }
        if (!reduce) loader.classList.add('is-flying');
        // revela a página enquanto a logo sobe
        setTimeout(function () { loader.classList.add('is-leaving'); }, reduce ? 0 : FLY * 0.55);
        setTimeout(done, (reduce ? 300 : FLY * 0.55 + 600) + 50);
    }
    function check() {
        var elapsed = Date.now() - start;
        if (loaded && elapsed >= MIN) leave();
        else if (elapsed >= MAX) leave();
        else setTimeout(check, 60);
    }
    if (!loaded) window.addEventListener('load', function () { loaded = true; });
    check();
    // Esc ou clique: pular
    loader.addEventListener('click', leave);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') leave(); });
})();
