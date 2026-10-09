<?php
/**
 * app/views/partials/icones.php
 * Conjunto de ícones do sistema em SVG (traço de 24×24), no mesmo estilo.
 *
 * Os ícones herdam a cor do texto (currentColor), então acompanham o tema
 * claro/escuro automaticamente. Uso:
 *
 *     <?php echo iconeSvg('grafico'); ?>
 *
 * Nomes disponíveis: grafico, alerta, relatorio, lista, sensor, usuarios,
 * cadastrar, editar, excluir, sol, lua, sair.
 */

/**
 * Devolve o markup <svg> do ícone pedido (string vazia se o nome não existir).
 * O tamanho e a espessura do traço são definidos pelo CSS de cada tela.
 */
function iconeSvg(string $nome): string
{
    static $icones = [
        // Gráficos de Sensores: eixos + linha de tendência
        'grafico' => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
        // Alertas: triângulo de atenção
        'alerta' => '<path d="M12 3 2.5 20h19L12 3z"/><path d="M12 10v4"/><path d="M12 17.5v.01"/>',
        // Relatórios: documento com linhas
        'relatorio' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h6"/>',
        // Consulta de Sensores: lista
        'lista' => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        // Gestão de Sensores: sinal de rádio
        'sensor' => '<circle cx="12" cy="12" r="2.2"/><path d="M7.8 7.8a6 6 0 0 0 0 8.4M16.2 7.8a6 6 0 0 1 0 8.4M4.9 4.9a10 10 0 0 0 0 14.2M19.1 4.9a10 10 0 0 1 0 14.2"/>',
        // Gestão de Cadastro: duas pessoas
        'usuarios' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18.5 14a6.5 6.5 0 0 1 3 6"/>',
        // Realizar Cadastro: pessoa + sinal de mais
        'cadastrar' => '<circle cx="10" cy="8" r="3.5"/><path d="M3 20a7 7 0 0 1 14 0"/><path d="M19 8v6M16 11h6"/>',
        // Editar Usuário: lápis
        'editar' => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4z"/><path d="M13.5 6.5l4 4"/>',
        // Excluir Cadastro: lixeira
        'excluir' => '<path d="M4 7h16M10 11v6M14 11v6"/><path d="M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        // Botão de tema: sol e lua
        'sol' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'lua' => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        // Sair
        'sair' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
    ];

    if (!isset($icones[$nome])) {
        return '';
    }
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $icones[$nome] . '</svg>';
}

/**
 * Botão de alternar tema claro/escuro (usado na topbar e na tela de login).
 * A lógica fica em public/assets/js/tema.js (procura por data-alternar-tema).
 */
function botaoTema(string $classeExtra = ''): string
{
    return '<button type="button" class="tema-botao ' . escapar($classeExtra) . '" data-alternar-tema'
        . ' aria-label="Alternar tema claro/escuro" title="Alternar tema claro/escuro">'
        . '<span class="icone-sol">' . iconeSvg('sol') . '</span>'
        . '<span class="icone-lua">' . iconeSvg('lua') . '</span>'
        . '</button>';
}
