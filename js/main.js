/* =========================================================
   Ákron · Athenas — comportamento compartilhado
   ========================================================= */
(function () {
    'use strict';
    var root = document.documentElement;
    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    var $ = function (s, c) { return (c || document).querySelector(s); };
    var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

    /* ---------- Tema claro / escuro ---------- */
    var toggle = $('#themeToggle');
    var metaTheme = $('meta[name="theme-color"]');
    function applyTheme(t, save) {
        root.setAttribute('data-theme', t);
        if (metaTheme) {
            var ak = root.getAttribute('data-brand') === 'akron';
            metaTheme.setAttribute('content', t === 'light' ? (ak ? '#eef3f8' : '#f7efd8') : (ak ? '#0b1118' : '#0d1522'));
        }
        if (save) { try { localStorage.setItem('athenas-tema', t); } catch (e) { } }
    }
    applyTheme(root.getAttribute('data-theme') || 'dark', false);
    if (toggle) toggle.addEventListener('click', function () {
        applyTheme(root.getAttribute('data-theme') === 'light' ? 'dark' : 'light', true);
    });

    /* ---------- Header, progresso, menu ---------- */
    var header = $('#header'), bar = $('#progress');
    function onScroll() {
        var y = window.scrollY || 0;
        if (header) header.classList.toggle('scrolled', y > 8);
        if (bar) {
            var h = document.documentElement.scrollHeight - window.innerHeight;
            bar.style.width = (h > 0 ? Math.min(100, y / h * 100) : 0) + '%';
        }
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    var menuBtn = $('#menuToggle'), nav = $('#nav');
    function setMenu(open) {
        if (!menuBtn || !nav) return;
        nav.classList.toggle('open', open);
        menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        menuBtn.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
    }
    if (menuBtn) {
        menuBtn.addEventListener('click', function () { setMenu(!nav.classList.contains('open')); });
        $$('a', nav).forEach(function (a) { a.addEventListener('click', function () { setMenu(false); }); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMenu(false); });
    }

    /* ---------- Destaque do link ativo (scroll spy) ---------- */
    var spyLinks = $$('.nav a[href^="#"], .toc a[href^="#"]');
    if (spyLinks.length && 'IntersectionObserver' in window) {
        var map = {};
        spyLinks.forEach(function (a) { var id = a.getAttribute('href').slice(1); (map[id] = map[id] || []).push(a); });
        var spy = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) {
                    spyLinks.forEach(function (a) { a.classList.remove('active'); });
                    (map[en.target.id] || []).forEach(function (a) { a.classList.add('active'); });
                }
            });
        }, { rootMargin: '-45% 0px -50% 0px' });
        Object.keys(map).forEach(function (id) { var el = document.getElementById(id); if (el) spy.observe(el); });
    }

    /* ---------- Revelar ao rolar ---------- */
    var revs = $$('.reveal');
    if ('IntersectionObserver' in window && !reduce) {
        var io = new IntersectionObserver(function (es) {
            es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
        }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
        revs.forEach(function (el) { io.observe(el); });
    } else { revs.forEach(function (el) { el.classList.add('in'); }); }

    /* ---------- Contadores ---------- */
    $$('[data-count]').forEach(function (el) {
        var end = parseInt(el.getAttribute('data-count'), 10) || 0;
        if (reduce) { el.textContent = end; return; }
        var done = false;
        var o = new IntersectionObserver(function (es) {
            if (!es[0].isIntersecting || done) return; done = true; o.disconnect();
            var t0 = performance.now(), dur = 1200;
            (function tick(t) {
                var p = Math.min(1, (t - t0) / dur); p = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.round(end * p);
                if (p < 1) requestAnimationFrame(tick);
            })(t0);
        });
        o.observe(el);
    });

    var yr = $('[data-year]'); if (yr) yr.textContent = new Date().getFullYear();

    /* ---------- Demonstração do painel (dados simulados) ---------- */
    var tiles = $$('#tiles .tile');
    if (tiles.length) {
        var N = 24, fmt = function (v, d) { return v.toFixed(d).replace('.', ','); };
        var state = tiles.map(function (t) {
            var base = parseFloat(t.dataset.base), v = parseFloat(t.dataset.var), arr = [];
            for (var i = 0; i < N; i++) arr.push(base + (Math.sin(i / 3.2) + (Math.random() - .5)) * v * .6);
            return { el: t, base: base, v: v, lim: parseFloat(t.dataset.lim), dec: parseInt(t.dataset.dec, 10), data: arr, key: t.dataset.k };
        });
        var chart = $('#chart'), line = $('.line', chart), area = $('.area', chart), toast = $('#toast');
        var tempS = state[0];
        var tick = 0, forcing = 0;

        function path(data, w, h, lo, hi) {
            var step = w / (data.length - 1);
            return data.map(function (v, i) {
                var y = h - (v - lo) / (hi - lo) * h;
                return (i ? 'L' : 'M') + (i * step).toFixed(1) + ' ' + Math.max(1, Math.min(h - 1, y)).toFixed(1);
            }).join(' ');
        }
        function render() {
            state.forEach(function (s) {
                var cur = s.data[s.data.length - 1], over = cur >= s.lim;
                var lo = Math.min.apply(null, s.data) - s.v, hi = Math.max.apply(null, s.data) + s.v;
                $('.spark path', s.el).setAttribute('d', path(s.data, 100, 26, lo, hi));
                $('.tile-val span', s.el).textContent = fmt(cur, s.dec);
                s.el.classList.toggle('warn', over);
                $('.tile-state', s.el).textContent = over ? 'Acima do limite' : 'Normal';
            });
            // gráfico de temperatura: eixo fixo p/ mostrar a linha de limite (30 °C)
            var lo = 22, hi = 31, d = path(tempS.data, 320, 96, lo, hi);
            line.setAttribute('d', d);
            area.setAttribute('d', d + ' L320 96 L0 96 Z');
            var over = tempS.data[tempS.data.length - 1] >= tempS.lim;
            toast.classList.toggle('show', over);
        }
        function step() {
            tick++;
            state.forEach(function (s) {
                var last = s.data[s.data.length - 1];
                var next = last + (s.base - last) * .18 + (Math.random() - .5) * s.v * .9;
                if (s === tempS) {
                    // a cada ciclo, simula um pico de temperatura para mostrar o alerta
                    var phase = tick % 34;
                    if (phase >= 20 && phase < 27) next = Math.max(next, tempS.lim + .3 + Math.random() * .7);
                    else if (phase >= 27 && phase < 29) next = tempS.base + 1;
                }
                s.data.push(next); s.data.shift();
            });
            render();
        }
        // começa já com um ciclo em andamento
        render();
        if (!reduce) setInterval(step, 1100);
    }

    /* ---------- Vídeo ---------- */
    var vid = $('#videoPlayer'), cover = $('#videoCover');
    if (vid && cover) {
        function play() { cover.classList.add('hide'); var p = vid.play(); if (p && p.catch) p.catch(function () { }); }
        cover.addEventListener('click', play);
        vid.addEventListener('ended', function () { cover.classList.remove('hide'); vid.currentTime = 0; });
        $$('[data-seek]').forEach(function (b) {
            b.addEventListener('click', function () {
                function go() { vid.currentTime = parseFloat(b.getAttribute('data-seek')); play(); }
                $('#videoFrame').scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
                if (vid.readyState >= 1) go(); else { vid.addEventListener('loadedmetadata', go, { once: true }); vid.load(); }
            });
        });
    }

    /* ---------- Tabela comparativa (Quadro 1 da documentação) ---------- */
    var cmp = $('#cmp tbody');
    if (cmp) {
        // colunas: tempo real, IoT, amb., alertas, web, mobile, integração, custo, facilidade
        var rows = [
            ['WMS (Senior)', [1, 0, 0, 0, 1, 0, 1, 1, 1]],
            ['Pipefy', [0, 0, 0, 1, 1, 1, 1, 1, 1]],
            ['ERP', [0, 0, 0, 0, 1, 0, 1, 0, 0]],
            ['Rockwell Automation', [1, 1, 0, 1, 1, 0, 1, 0, 0]],
            ['Siemens', [1, 1, 0, 1, 1, 0, 1, 0, 0]],
            ['Bosch Industrial Solutions', [1, 1, 0, 1, 1, 0, 1, 0, 0]],
            ['Ákron', [1, 1, 1, 1, 1, 1, 1, 1, 1]]
        ];
        var yes = '<span class="yes" aria-label="Atende"><svg><use href="#i-check"/></svg></span>';
        var no = '<span class="no" aria-label="Não atende / sem evidência">–</span>';
        cmp.innerHTML = rows.map(function (r, i) {
            return '<tr' + (i === rows.length - 1 ? ' class="me"' : '') + '><th scope="row">' + r[0] + '</th>' +
                r[1].map(function (v) { return '<td>' + (v ? yes : no) + '</td>'; }).join('') + '</tr>';
        }).join('');
    }

    /* ---------- Formulário (abre o e-mail do usuário) ---------- */
    var form = $('#form');
    if (form) {
        var status = $('.form-status', form);
        function setErr(input, msg) {
            var f = input.closest('.field'); f.classList.toggle('err', !!msg); $('small', f).textContent = msg || '';
        }
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var nome = form.nome, email = form.email, msg = form.mensagem, ok = true;
            setErr(nome, ''); setErr(email, ''); setErr(msg, '');
            if (nome.value.trim().length < 3) { setErr(nome, 'Informe seu nome.'); ok = false; }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email.value.trim())) { setErr(email, 'Informe um e-mail válido.'); ok = false; }
            if (msg.value.trim().length < 10) { setErr(msg, 'Escreva uma mensagem com pelo menos 10 caracteres.'); ok = false; }
            if (!ok) { status.className = 'form-status err'; status.textContent = 'Verifique os campos destacados.'; return; }
            var body = msg.value.trim() + '\n\n— ' + nome.value.trim() + ' (' + email.value.trim() + ')';
            var cfg = window.ATHENAS_CONFIG || {};
            var to = cfg.email || 'contatoathenasdev@athenas.dev.br', assunto = cfg.assunto || 'Contato pelo site da Athenas';
            window.location.href = 'mailto:' + to + '?subject=' + encodeURIComponent(assunto) + '&body=' + encodeURIComponent(body);
            status.className = 'form-status ok';
            status.textContent = 'Abrindo o seu aplicativo de e-mail para finalizar o envio…';
        });
        $$('input, textarea', form).forEach(function (i) { i.addEventListener('input', function () { setErr(i, ''); }); });
    }

    /* ---------- Abas (documentação) ---------- */
    $$('[role="tablist"]').forEach(function (list) {
        var tabs = $$('[role="tab"]', list);
        tabs.forEach(function (t) {
            t.addEventListener('click', function () {
                tabs.forEach(function (x) {
                    var on = x === t; x.setAttribute('aria-selected', on ? 'true' : 'false');
                    var panel = document.getElementById(x.getAttribute('aria-controls')); if (panel) panel.hidden = !on;
                });
            });
        });
    });
})();
