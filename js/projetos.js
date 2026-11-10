/* =========================================================
   Athenas — comportamento: cartões giratórios da equipe e
   endereço do Ákron. As animações de rolagem e as abas continuam
   em js/main.js.
   ========================================================= */
(function () {
    'use strict';
    var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

    /* ---------- Cartões giratórios (toque, teclado e mouse) ---------- */
    var noHover = window.matchMedia && matchMedia('(hover: none), (pointer: coarse)').matches;
    var cards = $$('.fcard');
    function setFlip(card, on) { card.classList.toggle('is-flipped', on); }
    cards.forEach(function (card) {
        // toque: vira ao tocar no cartão (links do verso continuam funcionando)
        card.addEventListener('click', function (e) {
            if (e.target.closest('a')) return;
            if (noHover) {
                var on = !card.classList.contains('is-flipped');
                cards.forEach(function (c) { if (c !== card) setFlip(c, false); });
                setFlip(card, on);
            }
        });
        // teclado: Enter/Espaço viram; Esc desvira
        card.addEventListener('keydown', function (e) {
            if (e.target !== card) { if (e.key === 'Escape') { setFlip(card, false); card.focus(); } return; }
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setFlip(card, !card.classList.contains('is-flipped')); }
            else if (e.key === 'Escape') setFlip(card, false);
        });
    });
    // tocar fora fecha o cartão virado (celular)
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.fcard')) cards.forEach(function (c) { setFlip(c, false); });
    });

    /* ---------- Endereço do Ákron (definido em js/config.js) ---------- */
    var cfg = window.ATHENAS_CONFIG || {};
    $$('[data-akron-access]').forEach(function (a) {
        var url = (cfg.akronUrl || '').trim();
        var note = a.parentNode.querySelector('.access-note');
        if (/^https?:\/\//i.test(url)) {
            a.href = url; a.target = '_blank'; a.rel = 'noopener';
            a.removeAttribute('aria-disabled');
            if (note) note.hidden = true;
        } else {
            a.setAttribute('aria-disabled', 'true'); a.removeAttribute('href');
            a.addEventListener('click', function (e) { e.preventDefault(); });
            if (note) note.hidden = false;
        }
    });
})();
