<?php
/**
 * app/models/RelatorioModel.php
 * Model: RelatorioModel
 * Consultas do "Relatório de Qualidade Ambiental": leituras detalhadas e
 * resumo estatístico (média, mínimo, máximo) por tipo de sensor, com filtros
 * de período, sensor e faixa de valores.
 *
 * Usado pela tela web (RelatorioController) e pela API (api/relatorio.php).
 * O tipo do sensor vem do JOIN entre `leitura_sensor` e `sensores`.
 */

require_once __DIR__ . '/SensorModel.php'; // catálogo de tipos de sensor

class RelatorioModel
{
    /** Máximo de leituras detalhadas devolvidas. */
    public const LIMITE_LEITURAS = 200;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── Metadados para as telas ──────────────────────────────────────────────

    /** Chaves dos tipos de sensor disponíveis no filtro. */
    public function getSensorTipos(): array
    {
        return array_keys(SensorModel::TIPOS);
    }

    /** Rótulos com unidade, indexados pelo tipo (ex.: 'ruido' => 'Ruído (dB)'). */
    public function getLabels(): array
    {
        $labels = [];
        foreach (array_keys(SensorModel::TIPOS) as $tipo) {
            $labels[$tipo] = SensorModel::rotuloComUnidade($tipo);
        }
        return $labels;
    }

    public function getLabelPorTipo(string $tipo): string
    {
        return SensorModel::tipoValido($tipo) ? SensorModel::rotuloComUnidade($tipo) : $tipo;
    }

    // ── Filtros ──────────────────────────────────────────────────────────────

    /**
     * Lê e sanitiza os filtros vindos da requisição (normalmente $_GET).
     * Datas inválidas voltam ao padrão (últimos 7 dias) e, se vierem
     * invertidas, são trocadas de posição.
     *
     * @return array{sensor:string,data_ini:string,data_fim:string,val_min:string,val_max:string}
     */
    public function filtrosDaRequisicao(array $entrada): array
    {
        $dataIni = $this->dataValida($entrada['data_ini'] ?? '') ?? date('Y-m-d', strtotime('-7 days'));
        $dataFim = $this->dataValida($entrada['data_fim'] ?? '') ?? date('Y-m-d');
        if ($dataIni > $dataFim) {
            [$dataIni, $dataFim] = [$dataFim, $dataIni];
        }

        $sensor = (string) ($entrada['sensor'] ?? '');

        return [
            'sensor' => SensorModel::tipoValido($sensor) ? $sensor : '',
            'data_ini' => $dataIni,
            'data_fim' => $dataFim,
            'val_min' => $this->numeroOuVazio($entrada['val_min'] ?? ''),
            'val_max' => $this->numeroOuVazio($entrada['val_max'] ?? ''),
        ];
    }

    /** Devolve a data (Y-m-d) se for real, ou null. */
    private function dataValida($valor): ?string
    {
        $valor = (string) $valor;
        $data = DateTime::createFromFormat('Y-m-d', $valor);
        return ($data && $data->format('Y-m-d') === $valor) ? $valor : null;
    }

    /** Mantém o valor apenas se for numérico; senão, string vazia. */
    private function numeroOuVazio($valor): string
    {
        $valor = trim((string) $valor);
        return ($valor !== '' && is_numeric($valor)) ? $valor : '';
    }

    /**
     * Recebe os filtros já sanitizados e devolve
     * ['where' => string, 'params' => array] para as consultas abaixo.
     */
    public function montarFiltros(
        string $dataIni,
        string $dataFim,
        string $sensor = '',
        string $valMin = '',
        string $valMax = ''
    ): array {
        $params = [
            ':ini' => $dataIni . ' 00:00:00',
            ':fim' => $dataFim . ' 23:59:59',
        ];
        $where = "WHERE l.lido_em BETWEEN :ini AND :fim";

        if ($sensor !== '' && SensorModel::tipoValido($sensor)) {
            $where .= " AND s.tipo = :tipo";
            $params[':tipo'] = $sensor;
        }
        if ($valMin !== '' && is_numeric($valMin)) {
            $where .= " AND l.valor >= :vmin";
            $params[':vmin'] = (float) $valMin;
        }
        if ($valMax !== '' && is_numeric($valMax)) {
            $where .= " AND l.valor <= :vmax";
            $params[':vmax'] = (float) $valMax;
        }

        return ['where' => $where, 'params' => $params];
    }

    // ── Consultas ────────────────────────────────────────────────────────────

    /**
     * Retorna até LIMITE_LEITURAS leituras detalhadas (mais recentes primeiro).
     */
    public function buscarLeituras(string $where, array $params): array
    {
        $limite = self::LIMITE_LEITURAS;
        $stmt = $this->pdo->prepare("
            SELECT s.tipo AS sensor_tipo, s.nome AS sensor_nome, l.valor, l.lido_em
            FROM leitura_sensor l
            JOIN sensores s ON s.id = l.sensor_id
            $where
            ORDER BY l.lido_em DESC
            LIMIT $limite
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna o resumo estatístico agrupado por tipo de sensor, considerando
     * TODAS as leituras do filtro (não apenas as 200 exibidas no detalhe).
     */
    public function buscarResumo(string $where, array $params): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.tipo               AS sensor_tipo,
                   COUNT(*)             AS total,
                   ROUND(AVG(l.valor), 2) AS media,
                   ROUND(MIN(l.valor), 2) AS minimo,
                   ROUND(MAX(l.valor), 2) AS maximo
            FROM leitura_sensor l
            JOIN sensores s ON s.id = l.sensor_id
            $where
            GROUP BY s.tipo
            ORDER BY s.tipo
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Atalho: aplica os filtros e devolve ['leituras' => [...], 'resumo' => [...]].
     *
     * @param array $filtros resultado de filtrosDaRequisicao()
     */
    public function consultar(array $filtros): array
    {
        $montado = $this->montarFiltros(
            $filtros['data_ini'],
            $filtros['data_fim'],
            $filtros['sensor'],
            $filtros['val_min'],
            $filtros['val_max']
        );

        return [
            'leituras' => $this->buscarLeituras($montado['where'], $montado['params']),
            'resumo' => $this->buscarResumo($montado['where'], $montado['params']),
        ];
    }
}
