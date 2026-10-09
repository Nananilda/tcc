<?php
/**
 * app/controllers/RelatorioController.php
 * Controller: RelatorioController
 * Prepara os dados do "Relatório de Qualidade Ambiental": lê os filtros da
 * requisição, consulta o RelatorioModel e entrega tudo pronto para a tela
 * (app/views/relatorios/relatorio_qualidade.php) ou para a API
 * (api/relatorio.php).
 */

require_once __DIR__ . '/../models/RelatorioModel.php';

class RelatorioController
{
    private RelatorioModel $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new RelatorioModel($pdo);
    }

    /**
     * Executa a consulta com os filtros recebidos (normalmente $_GET).
     *
     * @return array{filtros:array, leituras:array, resumo:array, erro:string}
     *         `filtros`  → valores já sanitizados (para repreencher o formulário);
     *         `erro`     → mensagem amigável quando o banco falha (senão '').
     */
    public function consultar(array $entrada): array
    {
        $filtros = $this->model->filtrosDaRequisicao($entrada);
        $leituras = [];
        $resumo = [];
        $erro = '';

        try {
            $resultado = $this->model->consultar($filtros);
            $leituras = $resultado['leituras'];
            $resumo = $resultado['resumo'];
        } catch (PDOException $e) {
            error_log('[' . APP_NOME . '] Erro no relatório: ' . $e->getMessage());
            $erro = 'Não foi possível consultar as leituras no banco de dados.';
        }

        return compact('filtros', 'leituras', 'resumo', 'erro');
    }

    /** Opções do filtro "Sensor" (chave → rótulo com unidade). */
    public function rotulosSensores(): array
    {
        return $this->model->getLabels();
    }
}
