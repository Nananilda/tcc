document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('loginForm');
    const loginInput = document.getElementById('login');
    const senhaInput = document.getElementById('senha');
    const btnLogin = document.getElementById('btnLogin');

    // Regex: apenas letras, números, ponto, hífen, underscore — igual ao back-end
    const reLogin = /^[a-zA-Z0-9._\-]{3,50}$/;

    function mostrarErro(input, mensagem) {
        let span = input.parentElement.querySelector('.campo-erro');
        if (!span) {
            span = document.createElement('span');
            span.className = 'campo-erro';
            span.style.color = 'red';
            span.style.fontSize = '0.85em';
            input.parentElement.appendChild(span);
        }
        span.textContent = mensagem;
        input.setAttribute('aria-invalid', 'true');
    }

    function limparErro(input) {
        const span = input.parentElement.querySelector('.campo-erro');
        if (span) span.textContent = '';
        input.removeAttribute('aria-invalid');
    }

    loginInput.addEventListener('blur', function () {
        const val = loginInput.value.trim();
        if (val === '') {
            mostrarErro(loginInput, 'Identificação obrigatória.');
        } else if (!reLogin.test(val)) {
            mostrarErro(loginInput, 'Use apenas letras, números, ponto, hífen ou underscore (3–50 chars).');
        } else {
            limparErro(loginInput);
        }
    });

    senhaInput.addEventListener('blur', function () {
        if (senhaInput.value.length < 1) {
            mostrarErro(senhaInput, 'Credencial de acesso obrigatória.');
        } else {
            limparErro(senhaInput);
        }
    });

    form.addEventListener('submit', function (e) {
        let valido = true;

        const loginVal = loginInput.value.trim();
        if (!reLogin.test(loginVal)) {
            mostrarErro(loginInput, 'Identificação inválida.');
            valido = false;
        }

        if (senhaInput.value.length < 1) {
            mostrarErro(senhaInput, 'Informe a credencial de acesso.');
            valido = false;
        }

        if (!valido) {
            e.preventDefault();
            return;
        }

        // Desabilita botão para evitar duplo envio
        btnLogin.disabled = true;
        btnLogin.textContent = 'Autenticando...';
    });
});