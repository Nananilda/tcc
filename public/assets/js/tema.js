/* tema.js — alternância entre tema claro e escuro (sistema ÁKRON)
 *
 * Como funciona:
 *   - O tema fica no atributo data-tema="claro|escuro" da tag <html>; todo o
 *     visual (style.css) é controlado por variáveis CSS que mudam com ele.
 *   - A escolha do usuário é guardada no localStorage ("akron-tema"). Enquanto
 *     ele não escolher, vale a preferência do sistema operacional
 *     (prefers-color-scheme) e o tema acompanha mudanças dela.
 *   - Este arquivo é carregado no <head>, SEM defer, de propósito: o atributo
 *     é aplicado antes da primeira pintura e a página não "pisca" no tema errado.
 *   - Qualquer elemento com o atributo data-alternar-tema vira um botão de troca.
 *   - A cada troca é disparado o evento "akron:tema" (detail.tema), que
 *     graficos.js escuta para recolorir os gráficos.
 */
(function () {
    'use strict';

    var CHAVE = 'akron-tema'; // chave usada no localStorage
    var raiz = document.documentElement;
    var consultaEscuro = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    /** Tema salvo pelo usuário ('claro' | 'escuro') ou null se nunca escolheu. */
    function lerEscolha() {
        try {
            var valor = window.localStorage.getItem(CHAVE);
            return valor === 'claro' || valor === 'escuro' ? valor : null;
        } catch (erro) {
            return null; // navegação privada/armazenamento bloqueado
        }
    }

    function gravarEscolha(tema) {
        try {
            window.localStorage.setItem(CHAVE, tema);
        } catch (erro) {
            /* sem armazenamento: o tema vale só nesta página */
        }
    }

    /** Preferência do sistema operacional. */
    function temaDoSistema() {
        return consultaEscuro && consultaEscuro.matches ? 'escuro' : 'claro';
    }

    /** Tema aplicado neste momento. */
    function temaAtual() {
        return raiz.getAttribute('data-tema') === 'escuro' ? 'escuro' : 'claro';
    }

    /** Atualiza o texto de acessibilidade dos botões de troca. */
    function atualizarBotoes(tema) {
        var texto = tema === 'escuro' ? 'Mudar para o tema claro' : 'Mudar para o tema escuro';
        var botoes = document.querySelectorAll('[data-alternar-tema]');
        for (var i = 0; i < botoes.length; i++) {
            botoes[i].setAttribute('aria-label', texto);
            botoes[i].setAttribute('title', texto);
        }
    }

    /** Aplica o tema e, se pedido, avisa o restante da página. */
    function aplicar(tema, avisar) {
        raiz.setAttribute('data-tema', tema);
        atualizarBotoes(tema);
        if (avisar && typeof window.CustomEvent === 'function') {
            document.dispatchEvent(new CustomEvent('akron:tema', { detail: { tema: tema } }));
        }
    }

    // 1) Aplica já, antes de o <body> existir (evita o "flash")
    raiz.setAttribute('data-tema', lerEscolha() || temaDoSistema());

    // 2) Liga os botões quando o HTML estiver pronto
    document.addEventListener('DOMContentLoaded', function () {
        atualizarBotoes(temaAtual());

        var botoes = document.querySelectorAll('[data-alternar-tema]');
        for (var i = 0; i < botoes.length; i++) {
            botoes[i].addEventListener('click', function () {
                var novo = temaAtual() === 'escuro' ? 'claro' : 'escuro';
                gravarEscolha(novo);
                aplicar(novo, true);
            });
        }
    });

    // 3) Enquanto não houver escolha explícita, acompanha o sistema operacional
    if (consultaEscuro && consultaEscuro.addEventListener) {
        consultaEscuro.addEventListener('change', function () {
            if (!lerEscolha()) {
                aplicar(temaDoSistema(), true);
            }
        });
    }

    // 4) Mantém várias abas abertas sincronizadas
    window.addEventListener('storage', function (evento) {
        if (evento.key === CHAVE && (evento.newValue === 'claro' || evento.newValue === 'escuro')) {
            aplicar(evento.newValue, true);
        }
    });
})();
