<?php
/**
 * Exportação PDF sem dependências externas.
 * Todas as leituras recebidas são escritas; a paginação não impõe limite de registros.
 * Os gráficos usam coordenadas vetoriais e agrupam amostras apenas para desenho,
 * preservando os extremos de cada intervalo. O histórico mantém cada leitura.
 */
final class RelatorioPdf
{
    private const LARGURA = 595.28;
    private const ALTURA = 841.89;
    private const MARGEM = 42.0;
    private array $paginas = [];
    private string $pagina = '';
    private float $y = 0;
    private string $emitidoEm;

    public static function gerar(array $dados): string
    {
        $pdf = new self();
        $pdf->emitidoEm = date('d/m/Y H:i:s');
        $pdf->novaPagina();
        $pdf->secao($dados['titulo']??'Relatório ambiental de estoques');
        $filtros = $dados['filtros'] ?? [];
        $pdf->paragrafo('Período: ' . (!empty($filtros['data_ini'])?dataBr($filtros['data_ini'],false):'não informado') . ' a ' . (!empty($filtros['data_fim'])?dataBr($filtros['data_fim'],false):'não informado'));
        if(!empty($filtros['horas']))$pdf->paragrafo('Janela móvel: últimas '.$filtros['horas'].' horas.');
        $pdf->paragrafo('Emitido em: ' . $pdf->emitidoEm . ' (horário de Brasília)');
        $leituras = $dados['leituras'] ?? [];
        $estoques = [];
        $sensores = [];
        foreach ($leituras as $leitura) {
            if (!empty($leitura['estoque_nome'])) {
                $estoques[(string) $leitura['estoque_nome']] = true;
            }
            $nome = (string) ($leitura['sensor_nome'] ?? $leitura['sensor_id'] ?? 'Sensor');
            $sensores[$nome] = true;
        }
        foreach ($dados['alertas'] ?? [] as $alerta) {
            if (!empty($alerta['estoque_nome'])) {
                $estoques[(string) $alerta['estoque_nome']] = true;
            }
        }
        $pdf->paragrafo('Estoques: ' . ($estoques ? implode(', ', array_keys($estoques)) : (!empty($filtros['estoque_id']) ? 'ID ' . $filtros['estoque_id'] : 'Todos os autorizados')));
        $pdf->paragrafo('Sensores: ' . ($sensores ? implode(', ', array_keys($sensores)) : (!empty($filtros['sensor_id']) ? 'ID ' . $filtros['sensor_id'] : 'Nenhum com leituras no período')));
        if (!empty($filtros['sensor'])) {
            $pdf->paragrafo('Tipo de medição: ' . self::tipo((string) $filtros['sensor']));
        }
        foreach (['val_min' => 'Valor mínimo do filtro', 'val_max' => 'Valor máximo do filtro'] as $chave => $rotulo) {
            if (isset($filtros[$chave]) && $filtros[$chave] !== '') {
                $pdf->paragrafo($rotulo . ': ' . (string) $filtros[$chave]);
            }
        }
        if (!empty($dados['identificacao'])) {
            $pdf->secao('Identificação e condições atuais');
            foreach($dados['identificacao'] as $campo=>$valor) $pdf->paragrafo($campo.': '.($valor??'Não informado'));
        }
        if (!empty($dados['sensores'])) {
            $pdf->secao('Sensores vinculados e configuração');
            foreach($dados['sensores'] as $sensor) {
                $pdf->paragrafo($sensor['nome'].' | '.self::tipo($sensor['tipo']).' | '.$sensor['estoque_nome'].' | '.($sensor['localizacao']?:'Localização não informada').' | '.ucfirst($sensor['status']),true);
                foreach(['atencao'=>'Atenção','critico'=>'Crítico'] as $nivel=>$rotulo) $pdf->paragrafo($rotulo.': inferior '.self::numero($sensor['limite_'.$nivel.'_min']).' / superior '.self::numero($sensor['limite_'.$nivel.'_max']).' '.self::unidade($sensor['tipo']));
                $pdf->paragrafo('Prazo de comunicação: '.$sensor['intervalo_comunicacao'].' segundos.');
            }
        }
        $pdf->secao('Resumo estatístico');
        if (empty($dados['resumo'])) {
            $pdf->paragrafo('Nenhuma leitura no período.');
        }
        foreach ($dados['resumo'] ?? [] as $linha) {
            $tipo = (string) ($linha['sensor_tipo'] ?? $linha['tipo'] ?? '');
            $unidade = (string) ($linha['unidade'] ?? self::unidade($tipo));
            $pdf->paragrafo((!empty($linha['sensor_nome']) ? $linha['sensor_nome'] . ' · ' : '') . self::tipo($tipo)
                . ' | Leituras: ' . ($linha['total'] ?? 0)
                . ' | Média: ' . self::numero($linha['media'] ?? null) . ' ' . $unidade
                . ' | Mínimo: ' . self::numero($linha['minimo'] ?? null) . ' ' . $unidade
                . ' | Máximo: ' . self::numero($linha['maximo'] ?? null) . ' ' . $unidade);
        }
        $pdf->paragrafo('Total de leituras exportadas: ' . count($leituras) . '. Alertas no período: ' . count($dados['alertas'] ?? []) . '.');
        if (isset($dados['periodos']) && is_array($dados['periodos']) && $dados['periodos']) {
            $pdf->secao('Períodos fora dos limites');
            $pdf->paragrafo('Estimativas entre leituras consecutivas, sem extrapolar períodos sem comunicação.');
            foreach ($dados['periodos'] as $periodo) {
                if (!is_array($periodo)) {
                    continue;
                }
                $partes = [];
                foreach ($periodo as $chave => $valor) {
                    if (is_scalar($valor)) {
                        $partes[] = str_replace('_', ' ', (string) $chave) . ': ' . (in_array($chave,['inicio','fim'],true)?dataBr((string)$valor):$valor);
                    }
                }
                $pdf->paragrafo(implode(' | ', $partes));
            }
        }
        $pdf->secao('Evolução das condições ambientais');
        $pdf->graficos($leituras);
        $pdf->secao('Alertas no período');
        if (empty($dados['alertas'])) {
            $pdf->paragrafo('Nenhum alerta encontrado.');
        }
        foreach ($dados['alertas'] ?? [] as $alerta) {
            $pdf->paragrafo(dataBr($alerta['criado_em']??null) . ' | ' . ($alerta['estoque_nome'] ?? '') . ' | '
                . ($alerta['sensor_nome'] ?? '') . ' | ' . self::rotulo((string) ($alerta['severidade'] ?? ''))
                . ' | ' . self::rotulo((string) ($alerta['status'] ?? (!empty($alerta['resolvido']) ? 'resolvido' : 'pendente'))), true);
            $pdf->paragrafo((string) ($alerta['mensagem'] ?? ''), false, 9);
            $pdf->paragrafo('Valor: '.self::numero($alerta['valor']).' '.self::unidade($alerta['sensor_tipo']??'').' | Limite ultrapassado: '.self::numero($alerta['limite_ultrapassado']??null));
            $pdf->paragrafo('Reconhecimento: '.dataBr($alerta['reconhecido_em']??null).' · '.($alerta['reconhecido_nome']??$alerta['reconhecido_por']??'').' | Normalização: '.dataBr($alerta['normalizado_em']??null).' | Resolução: '.dataBr($alerta['resolvido_em']??null).' · '.($alerta['resolvido_nome']??$alerta['resolvido_por']??''));
            if(!empty($alerta['observacoes'])) $pdf->paragrafo('Observações: '.$alerta['observacoes']);
        }
        $pdf->secao('Histórico completo de leituras');
        $pdf->paragrafo('Todos os registros correspondentes aos filtros. As unidades pertencem ao tipo de cada sensor.');
        if (!$leituras) {
            $pdf->paragrafo('Nenhuma leitura no período.');
        }
        foreach ($leituras as $leitura) {
            $tipo = (string) ($leitura['sensor_tipo'] ?? $leitura['tipo'] ?? '');
            $unidade = (string) ($leitura['unidade'] ?? self::unidade($tipo));
            $pdf->paragrafo(dataBr($leitura['lido_em']??null) . ' | ' . ($leitura['estoque_nome'] ?? '') . ' | '
                . ($leitura['sensor_nome'] ?? '') . ' | ' . self::tipo($tipo) . ' | '
                . self::numero($leitura['valor'] ?? null) . ' ' . $unidade . ' | '
                . self::rotulo((string) ($leitura['situacao'] ?? 'normal')), false, 9);
        }
        $pdf->paginas[] = $pdf->pagina;
        return $pdf->montarDocumento();
    }

    private function novaPagina(): void
    {
        if ($this->pagina !== '') {
            $this->paginas[] = $this->pagina;
        }
        $this->pagina = '';
        $this->y = self::ALTURA - self::MARGEM;
        $this->texto(self::MARGEM, $this->y, 'ÁKRON', 15, true);
        $this->texto(125, $this->y + 1, 'Monitoramento ambiental de estoques', 9);
        $this->pagina .= "0.15 0.35 0.38 RG 0.7 w 42 784 m 553 784 l S\n";
        $this->y = 763;
    }

    private function garantirEspaco(float $altura): void
    {
        if ($this->y - $altura < 54) {
            $this->novaPagina();
        }
    }

    private function secao(string $titulo): void
    {
        $this->garantirEspaco(48);
        $this->y -= 12;
        $this->texto(self::MARGEM, $this->y, $titulo, 12, true);
        $this->y -= 21;
    }

    private function paragrafo(string $texto, bool $negrito = false, int $tamanho = 10): void
    {
        // Helvetica: a largura máxima de cada linha é medida em pontos para
        // impedir cortes em nomes longos. Conversão feita antes da medição.
        $texto = self::ansi($texto);
        $maximo = self::LARGURA - 2 * self::MARGEM;
        $linhas = $this->quebrarTexto($texto, $maximo, $tamanho);
        foreach ($linhas as $linha) {
            $this->garantirEspaco($tamanho + 6);
            $this->texto(self::MARGEM, $this->y, $linha, $tamanho, $negrito, true);
            $this->y -= $tamanho + 5;
        }
        $this->y -= 3;
    }

    private function quebrarTexto(string $texto, float $largura, int $tamanho): array
    {
        // Limite conservador pela maior largura dos caracteres latinos comuns.
        // Usa métrica Helvetica por grupo para não desperdiçar a página.
        $linhas = [];
        foreach (explode("\n", str_replace("\r", '', $texto)) as $paragrafo) {
            $linha = '';
            $medida = 0.0;
            foreach (preg_split('/ +/', $paragrafo) ?: [] as $palavra) {
                $comEspaco = ($linha === '' ? '' : ' ') . $palavra;
                $novaMedida = self::larguraTexto($comEspaco, $tamanho);
                if ($linha !== '' && $medida + $novaMedida > $largura) {
                    $linhas[] = $linha;
                    $linha = '';
                    $medida = 0;
                    $comEspaco = $palavra;
                }
                foreach (str_split($comEspaco) as $caractere) {
                    $incremento = self::larguraTexto($caractere, $tamanho);
                    if ($linha !== '' && $medida + $incremento > $largura) {
                        $linhas[] = $linha;
                        $linha = '';
                        $medida = 0;
                    }
                    $linha .= $caractere;
                    $medida += $incremento;
                }
            }
            $linhas[] = $linha;
        }
        return $linhas;
    }

    private static function larguraTexto(string $texto, int $tamanho): float
    {
        $largura = 0.0;
        foreach (str_split($texto) as $letra) {
            $largura += str_contains('ilI.,:;!\'| ', $letra) ? .31
                : (str_contains('MW@%', $letra) ? 1.0 : .65);
        }
        return $largura * $tamanho;
    }

    private function texto(float $x, float $y, string $texto, int $tamanho = 10, bool $negrito = false, bool $jaAnsi = false): void
    {
        $texto = $jaAnsi ? $texto : self::ansi($texto);
        $texto = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $texto);
        $fonte = $negrito ? 'F2' : 'F1';
        $this->pagina .= sprintf("BT 0.12 0.18 0.21 rg /%s %d Tf %.2F %.2F Td (%s) Tj ET\n", $fonte, $tamanho, $x, $y, $texto);
    }

    private function graficos(array $leituras): void
    {
        $grupos = [];
        foreach ($leituras as $leitura) {
            if (!isset($leitura['valor']) || !is_numeric($leitura['valor'])) {
                continue;
            }
            $chave = (string) ($leitura['sensor_id'] ?? $leitura['sensor_nome'] ?? 'sensor')
                . ':' . ($leitura['estoque_id'] ?? $leitura['estoque_nome'] ?? '')
                . ':' . ($leitura['sensor_tipo'] ?? $leitura['tipo'] ?? '');
            $grupos[$chave][] = $leitura;
        }
        if (!$grupos) {
            $this->paragrafo('Sem dados para gerar gráficos.');
        }
        foreach ($grupos as $amostras) {
            usort($amostras, fn(array $a, array $b): int => strcmp($a['lido_em'] ?? '', $b['lido_em'] ?? ''));
            $primeiro = $amostras[0];
            $ultimo = $amostras[count($amostras) - 1];
            $this->garantirEspaco(210);
            $this->paragrafo(($primeiro['sensor_nome'] ?? 'Sensor') . ' · '
                . ($primeiro['estoque_nome'] ?? '') . ' · '
                . self::tipo((string) ($primeiro['sensor_tipo'] ?? $primeiro['tipo'] ?? '')), true);
            $valores = array_map(fn(array $l): float => (float) $l['valor'], $amostras);
            $min = min($valores);
            $max = max($valores);
            foreach($amostras as $amostra) foreach(['atencao_min','atencao_max','critico_min','critico_max'] as $limite) if(isset($amostra['limite_'.$limite])) { $min=min($min,(float)$amostra['limite_'.$limite]);$max=max($max,(float)$amostra['limite_'.$limite]); }
            if ($min === $max) {
                $min -= 1;
                $max += 1;
            }
            $inicio = strtotime($primeiro['lido_em'] ?? '') ?: 0;
            $fim = strtotime($ultimo['lido_em'] ?? '') ?: $inicio;
            $duracao = max(1, $fim - $inicio);
            $baseX = 82.0;
            $baseY = $this->y - 130;
            $largura = 460.0;
            $altura = 112.0;
            $this->pagina .= sprintf("0.65 0.70 0.72 RG 0.5 w %.2F %.2F m %.2F %.2F l %.2F %.2F l S\n", $baseX, $baseY + $altura, $baseX, $baseY, $baseX + $largura, $baseY);
            $this->texto(42, $baseY + $altura - 2, self::numero($max), 8);
            $this->texto(42, $baseY, self::numero($min), 8);
            $this->texto(82, $baseY - 15, dataBr($primeiro['lido_em']??null,false), 8);
            $this->texto(410, $baseY - 15, dataBr($ultimo['lido_em']??null,false), 8);
            // Limites de exibição: dois extremos por intervalo de desenho.
            // Todas as amostras permanecem na tabela completa do documento.
            $pontos = [];
            if (count($amostras) > 480) {
                $blocos = [];
                foreach ($amostras as $amostra) {
                    $tempo = strtotime($amostra['lido_em'] ?? '') ?: $inicio;
                    $bloco = (int) floor(($tempo - $inicio) / $duracao * 239);
                    if (!isset($blocos[$bloco])) {
                        $blocos[$bloco] = [$amostra, $amostra];
                    } else {
                        if ((float) $amostra['valor'] < (float) $blocos[$bloco][0]['valor']) {
                            $blocos[$bloco][0] = $amostra;
                        }
                        if ((float) $amostra['valor'] > (float) $blocos[$bloco][1]['valor']) {
                            $blocos[$bloco][1] = $amostra;
                        }
                    }
                }
                foreach ($blocos as $extremos) {
                    usort($extremos, fn(array $a, array $b): int => strcmp($a['lido_em'], $b['lido_em']));
                    array_push($pontos, ...$extremos);
                }
            } else {
                $pontos = $amostras;
            }
            $this->pagina .= "0.08 0.42 0.45 RG 1 w\n";
            foreach ($pontos as $indice => $ponto) {
                $tempo = strtotime($ponto['lido_em'] ?? '') ?: $inicio;
                $x = $baseX + ($tempo - $inicio) / $duracao * $largura;
                $y = $baseY + ((float) $ponto['valor'] - $min) / ($max - $min) * $altura;
                $this->pagina .= sprintf("%.2F %.2F %s\n", $x, $y, $indice === 0 ? 'm' : 'l');
            }
            $this->pagina .= "S\n";
            foreach(['atencao_min','atencao_max','critico_min','critico_max'] as $limite) {
                $this->pagina .= (str_starts_with($limite,'critico')?"0.7 0.16 0.2 RG":"0.65 0.43 0.08 RG")." 0.6 w [4 3] 0 d\n";
                $aberto=false;
                foreach($amostras as $ponto){if(!isset($ponto['limite_'.$limite])){if($aberto)$this->pagina.="S\n";$aberto=false;continue;}$x=$baseX+((strtotime($ponto['lido_em'])-$inicio)/$duracao)*$largura;$y=$baseY+((float)$ponto['limite_'.$limite]-$min)/($max-$min)*$altura;$this->pagina.=sprintf("%.2F %.2F %s\n",$x,$y,$aberto?'l':'m');$aberto=true;}
                if($aberto)$this->pagina.="S\n";
            }
            $this->pagina.="[] 0 d\n";
            if (count($pontos) === 1) {
                $this->pagina .= sprintf("0.08 0.42 0.45 rg %.2F %.2F 4 4 re f\n", $baseX - 2, $baseY + $altura / 2 - 2);
            }
            $this->y = $baseY - 33;
            $this->paragrafo(count($amostras) . ' leituras · Tracejado: atenção (ocre) / crítico (vermelho) · Unidade: ' . ($primeiro['unidade'] ?? self::unidade((string) ($primeiro['sensor_tipo'] ?? $primeiro['tipo'] ?? ''))), false, 8);
        }
    }

    private function montarDocumento(): string
    {
        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $filhos = [];
        $total = count($this->paginas);
        foreach ($this->paginas as $indice => $conteudo) {
            $idPagina = 5 + $indice * 2;
            $idConteudo = $idPagina + 1;
            $filhos[] = $idPagina . ' 0 R';
            $rodape = self::ansi('ÁKRON · ' . ($indice + 1) . ' / ' . $total . ' · Emitido em ' . $this->emitidoEm);
            $rodape = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $rodape);
            $conteudo .= "BT 0.4 0.45 0.48 rg /F1 8 Tf 42 30 Td ($rodape) Tj ET\n";
            $objetos[$idPagina] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $idConteudo . ' 0 R >>';
            $objetos[$idConteudo] = '<< /Length ' . strlen($conteudo) . ">>\nstream\n" . $conteudo . 'endstream';
        }
        $objetos[2] = '<< /Type /Pages /Kids [' . implode(' ', $filhos) . '] /Count ' . $total . ' >>';
        ksort($objetos);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objetos as $id => $conteudo) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $conteudo . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $tamanho = count($objetos) + 1;
        $pdf .= "xref\n0 $tamanho\n0000000000 65535 f \n";
        for ($i = 1; $i < $tamanho; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        return $pdf . "trailer\n<< /Size $tamanho /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }

    private static function ansi(string $texto): string
    {
        $texto = str_replace(["\0", "\t"], ['', ' '], $texto);
        if (function_exists('iconv')) {
            $convertido = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $texto);
            if ($convertido !== false) {
                return $convertido;
            }
        }
        // Mantém os acentos portugueses mesmo em PHP sem a extensão iconv.
        return strtr($texto, [
            'Á'=>"\xC1", 'À'=>"\xC0", 'Â'=>"\xC2", 'Ã'=>"\xC3", 'É'=>"\xC9", 'Ê'=>"\xCA", 'Í'=>"\xCD", 'Ó'=>"\xD3", 'Ô'=>"\xD4", 'Õ'=>"\xD5", 'Ú'=>"\xDA", 'Ç'=>"\xC7",
            'á'=>"\xE1", 'à'=>"\xE0", 'â'=>"\xE2", 'ã'=>"\xE3", 'é'=>"\xE9", 'ê'=>"\xEA", 'í'=>"\xED", 'ó'=>"\xF3", 'ô'=>"\xF4", 'õ'=>"\xF5", 'ú'=>"\xFA", 'ç'=>"\xE7", 'ü'=>"\xFC",
            '°'=>"\xB0", '·'=>"\xB7", '—'=>'-', '–'=>'-', '…'=>'...',
        ]);
    }

    private static function numero(mixed $numero): string
    {
        return is_numeric($numero) ? number_format((float) $numero, 2, ',', '.') : '—';
    }

    private static function tipo(string $tipo): string
    {
        return ['temperatura'=>'Temperatura', 'umidade'=>'Umidade', 'qualidade_ar'=>'Qualidade do ar', 'ruido'=>'Ruído', 'pressao'=>'Pressão', 'uv'=>'UV'][$tipo] ?? $tipo;
    }

    private static function unidade(string $tipo): string
    {
        return ['temperatura'=>'°C', 'umidade'=>'%', 'qualidade_ar'=>'AQI', 'ruido'=>'dB', 'pressao'=>'hPa', 'uv'=>'índice'][$tipo] ?? '';
    }

    private static function rotulo(string $valor): string
    {
        return ['normal'=>'Normal', 'atencao'=>'Atenção', 'critico'=>'Crítico', 'info'=>'Informativo', 'pendente'=>'Pendente', 'reconhecido'=>'Reconhecido', 'normalizado'=>'Normalizado', 'resolvido'=>'Resolvido'][$valor] ?? ucfirst($valor);
    }
}
