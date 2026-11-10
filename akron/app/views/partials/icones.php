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
 * Nomes disponíveis: painel, estoque, grafico, alerta, relatorio, lista, sensor,
 * usuarios, cadastrar, editar, excluir, sol, lua, consulta, config, perfil,
 * paleta, seguranca, chave, seta, voltar, info, relogio, sinal-off, monitor,
 * alvo, sair.
 */

/**
 * Devolve o markup <svg> do ícone pedido (string vazia se o nome não existir).
 * O tamanho e a espessura do traço são definidos pelo CSS de cada tela.
 */
function iconeSvg(string $nome): string
{
    static $icones = [
        'painel' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'estoque' => '<path d="M3 10 12 4l9 6v11H3V10z"/><path d="M8 21v-8h8v8M8 17h8"/>',
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
        // Consulta de sensores: lupa sobre lista
        'consulta' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m20 20-4.9-4.9"/><path d="M8 9h5M8 12h3"/>',
        // Configurações pessoais: controles deslizantes
        'config' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        // Perfil / aparência / segurança / acessos
        'perfil' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'paleta' => '<path d="M12 3a9 9 0 1 0 0 18c1.4 0 2-.9 2-1.8 0-1.4-1.2-1.6-1.2-2.9 0-1 .8-1.8 1.9-1.8H17a4 4 0 0 0 4-4C21 6.2 17 3 12 3z"/><path d="M7.5 11.5h.01M10 7.5h.01M14.5 7.5h.01"/>',
        'seguranca' => '<path d="M12 3 4.5 6v5.5c0 4.6 3.1 8 7.5 9.5 4.4-1.5 7.5-4.9 7.5-9.5V6L12 3z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
        'chave' => '<circle cx="8" cy="15" r="4"/><path d="m10.8 12.2 8.7-8.7M16 7l3 3M14 9l2 2"/>',
        // Seta de navegação
        'seta' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'voltar' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.01"/>',
        'relogio' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'sinal-off' => '<path d="M3 3l18 18"/><path d="M8.5 8.5a5 5 0 0 0 .3 6.6M15.5 8.5a5 5 0 0 1 1.3 4.6M5.6 5.6a9 9 0 0 0-.4 12.2M18.4 5.6a9 9 0 0 1 2 7"/><circle cx="12" cy="12" r="1.6"/>',
        'monitor' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
        'alvo' => '<path d="M12 3 4 7v5c0 4.4 3.1 7.7 8 9 4.9-1.3 8-4.6 8-9V7l-8-4z"/><path d="M12 8v4M12 15v.01"/>',
        // Sair
        'sair' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
    ];

    if (!isset($icones[$nome])) {
        return '';
    }
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $icones[$nome] . '</svg>';
}
