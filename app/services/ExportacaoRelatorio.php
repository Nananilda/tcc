<?php
require_once __DIR__.'/RelatorioPdf.php';
/** Exporta a consulta integral, com o mesmo escopo e filtros da interface. */
function exportarRelatorio(array $resultado, string $formato): never
{
    header('Cache-Control: no-store');
    if ($formato === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="akron-relatorio.pdf"');
        echo RelatorioPdf::gerar($resultado);
    } else {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="akron-leituras.csv"');
        $saida = fopen('php://output', 'w');
        fwrite($saida, "\xEF\xBB\xBF");
        fputcsv($saida, ['Data e horário','Estoque','Sensor','Tipo','Valor','Unidade','Situação'], ';', '"', '');
        foreach ($resultado['leituras'] as $l) {
            $campos = [dataBr($l['lido_em']),$l['estoque_nome'],$l['sensor_nome'],SensorModel::rotulo($l['sensor_tipo']),$l['valor'],$l['unidade'],['normal'=>'Normal','atencao'=>'Atenção','critico'=>'Crítico'][$l['situacao']]];
            // Impede que nomes cadastrados sejam interpretados como fórmulas na planilha.
            foreach ($campos as &$campo) if (is_string($campo) && preg_match('/^[=+@\-\t\r]/', $campo)) $campo = "'".$campo;
            unset($campo);
            fputcsv($saida, $campos, ';', '"', '');
        }
        fclose($saida);
    }
    exit;
}
