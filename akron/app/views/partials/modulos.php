<?php
/**
 * app/views/partials/modulos.php
 * Registro ÚNICO dos módulos do ÁKRON. É usado pelo painel (cartões de navegação)
 * e pelo cabeçalho (nome/ícone/grupo da página atual), para que os dois nunca
 * divirjam. Permissões: o campo 'admin' apenas oculta o atalho; o acesso real
 * continua sendo exigido em cada página (exigirAdmin) e no backend.
 */

/** Grupos de navegação, na ordem de exibição. */
function gruposModulos(): array
{
    return [
        'monitoramento' => ['titulo' => 'Monitoramento', 'descricao' => 'Acompanhe o que está acontecendo agora e no histórico.'],
        'gestao'        => ['titulo' => 'Gestão',        'descricao' => 'Organize estoques, sensores e acessos.'],
        'pessoal'       => ['titulo' => 'Pessoal',       'descricao' => 'Sua conta e preferências.'],
    ];
}

/**
 * Módulos disponíveis para o usuário logado (admin vê todos; usuário comum não vê os de administração).
 * 'cor' é a classe de destaque (mod-*) definida em componentes.css.
 */
function modulosAkron(bool $incluirRestritos = false): array
{
    $admin = function_exists('ehAdmin') && ehAdmin();
    $todos = [
        'graficos' => [
            'grupo' => 'monitoramento', 'titulo' => 'Gráficos de sensores', 'cor' => 'mod-graficos', 'icone' => 'grafico',
            'rota' => 'graficos/graficos_sensores.php', 'admin' => false,
            'descricao' => 'Valores atuais e histórico de cada variável ambiental.',
        ],
        'alertas' => [
            'grupo' => 'monitoramento', 'titulo' => 'Alertas', 'cor' => 'mod-alertas', 'icone' => 'alerta',
            'rota' => 'graficos/alerta.php', 'admin' => false,
            'descricao' => 'Irregularidades detectadas, com reconhecimento e resolução.',
        ],
        'relatorios' => [
            'grupo' => 'monitoramento', 'titulo' => 'Relatórios', 'cor' => 'mod-relatorios', 'icone' => 'relatorio',
            'rota' => 'relatorios/relatorio_qualidade.php', 'admin' => false,
            'descricao' => 'Estatísticas por período e exportação em CSV ou PDF.',
        ],
        'consulta' => [
            'grupo' => 'monitoramento', 'titulo' => 'Consulta de sensores', 'cor' => 'mod-consulta', 'icone' => 'consulta',
            'rota' => 'sensores/listar_sensores.php', 'admin' => false,
            'descricao' => 'Última leitura e situação de comunicação de cada sensor.',
        ],
        'estoques' => [
            'grupo' => 'gestao', 'titulo' => 'Gestão de estoques', 'cor' => 'mod-estoques', 'icone' => 'estoque',
            'rota' => 'estoques/listar.php', 'admin' => false,
            'descricao' => 'Condições ambientais, sensores e alertas de cada estoque.',
            'descricao_admin' => 'Cadastre estoques e acompanhe condições, sensores e alertas.',
        ],
        'sensores' => [
            'grupo' => 'gestao', 'titulo' => 'Gestão de sensores', 'cor' => 'mod-sensores', 'icone' => 'sensor',
            'rota' => 'sensores/listar_sensores.php?modo=gestao', 'admin' => true,
            'descricao' => 'Cadastro, limites ambientais e situação dos sensores.',
        ],
        'usuarios' => [
            'grupo' => 'gestao', 'titulo' => 'Gestão de usuários', 'cor' => 'mod-usuarios', 'icone' => 'usuarios',
            'rota' => 'usuarios/gestao_usuarios.php', 'admin' => true,
            'descricao' => 'Perfis, situação das contas e estoques autorizados.',
        ],
        'pessoal' => [
            'grupo' => 'pessoal', 'titulo' => 'Configurações pessoais', 'cor' => 'mod-pessoal', 'icone' => 'config',
            'rota' => 'usuarios/perfil.php', 'admin' => false,
            'descricao' => 'Foto, nome, aparência, senha e estoques autorizados.',
        ],
    ];
    if ($incluirRestritos) {
        return $todos;
    }
    return array_filter($todos, static fn(array $m): bool => !$m['admin'] || $admin);
}

/** Identifica o módulo pelo caminho do script atual (as páginas podem forçar com $moduloAtual). */
function moduloPorCaminho(string $caminho): ?string
{
    $caminho = str_replace('\\', '/', $caminho);
    $mapa = [
        '/graficos/graficos_sensores.php' => 'graficos',
        '/graficos/alerta.php'            => 'alertas',
        '/graficos/novo_alerta.php'       => 'alertas',
        '/relatorios/'                    => 'relatorios',
        '/sensores/editar_sensores.php'   => 'sensores',
        '/sensores/excluir_sensores.php'  => 'sensores',
        '/sensores/gestao_sensores.php'   => 'sensores',
        '/sensores/'                      => 'consulta',
        '/estoques/'                      => 'estoques',
        '/usuarios/perfil.php'            => 'pessoal',
        '/usuarios/'                      => 'usuarios',
    ];
    foreach ($mapa as $trecho => $chave) {
        if (str_contains($caminho, $trecho)) {
            return $chave;
        }
    }
    return null;
}
