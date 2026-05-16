<?php
// ==============================================
// ARQUIVO: cron_apis.php
// MOTOR DE CAPTAÇÃO AUTOMÁTICA - APIs REST
// Portais: PNCP, Compras.gov.br, Portal de Compras Públicas
// ==============================================

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/Database.php';

define('BOLETIM_LICITACOES_POR_PAGINA', 50);
define('TIMEOUT_CURL', 30);

// ==============================================
// FUNÇÕES AUXILIARES
// ==============================================

function logCaptacao(PDO $pdo, string $portal, string $tipo, string $status, int $qtd_inserida, int $qtd_total, ?string $mensagem = null): void
{
    $sql = "INSERT INTO log_captacao (portal, tipo, status, qtd_inserida, qtd_total, mensagem, iniciado_em, finalizado_em) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$portal, $tipo, $status, $qtd_inserida, $qtd_total, $mensagem]);
}

function keepAlive(PDO $pdo, Database $db): PDO
{
    try {
        $pdo->query("SELECT 1");
        return $pdo;
    } catch (PDOException $e) {
        error_log("[cron_apis] Reconectando ao banco...");
        return $db->reconnect();
    }
}

function getOrCreateBoletim(PDO $pdo, string $data): int
{
    $stmt = $pdo->prepare("SELECT id FROM boletins WHERE data_boletim = ?");
    $stmt->execute([$data]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return (int)$row['id'];
    }
    $stmt = $pdo->prepare("INSERT INTO boletins (data_boletim, total_itens, portais_coletados) VALUES (?, 0, '[]')");
    $stmt->execute([$data]);
    return (int)$pdo->lastInsertId();
}

function registrarPortaisBoletim(PDO $pdo, int $boletim_id, string $portal): void
{
    $stmt = $pdo->prepare("SELECT portais_coletados FROM boletins WHERE id = ?");
    $stmt->execute([$boletim_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $portais = json_decode($row['portais_coletados'] ?? '[]', true);
    if (!in_array($portal, $portais, true)) {
        $portais[] = $portal;
        $stmt = $pdo->prepare("UPDATE boletins SET portais_coletados = ? WHERE id = ?");
        $stmt->execute([json_encode($portais), $boletim_id]);
    }
}

function atualizarTotalBoletim(PDO $pdo, int $boletim_id): void
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM boletim_licitacoes WHERE boletim_id = ?");
    $stmt->execute([$boletim_id]);
    $total = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare("UPDATE boletins SET total_itens = ? WHERE id = ?");
    $stmt->execute([$total, $boletim_id]);
}

function inserirLicitacoesBatch(PDO $pdo, int $boletim_id, array $licitacoes, string $portal_origem): int
{
    $inseridas = 0;
    $sql = "INSERT IGNORE INTO boletim_licitacoes 
        (boletim_id, orgao, objeto, edital, numero_processo, estado, cidade, modalidade, 
         data_publicacao, data_abertura, valor_estimado, link_detalhes, status_badge, portal_origem, id_original)
        VALUES 
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'NOVA', ?, ?)";
    $stmt = $pdo->prepare($sql);

    foreach ($licitacoes as $lic) {
        try {
            $stmt->execute([
                $boletim_id,
                $lic['orgao'] ?? null,
                $lic['objeto'] ?? null,
                $lic['edital'] ?? null,
                $lic['numero_processo'] ?? null,
                $lic['estado'] ?? null,
                $lic['cidade'] ?? null,
                $lic['modalidade'] ?? null,
                $lic['data_publicacao'] ?? null,
                $lic['data_abertura'] ?? null,
                $lic['valor_estimado'] ?? null,
                $lic['link_detalhes'] ?? null,
                $portal_origem,
                $lic['id_original'] ?? null
            ]);
            if ($stmt->rowCount() > 0) {
                $inseridas++;
            }
        } catch (Exception $e) {
            error_log("[cron_apis] Erro ao inserir licitação do portal $portal_origem: " . $e->getMessage());
        }
    }
    return $inseridas;
}

function parseValorMonetario($valor): ?float
{
    if ($valor === null || $valor === '' || $valor === 0) return null;
    if (is_numeric($valor)) return (float)$valor;
    $valor = preg_replace('/[R$\s.]/', '', (string)$valor);
    $valor = str_replace(',', '.', $valor);
    return is_numeric($valor) ? (float)$valor : null;
}

function parseDataBR(string $data): ?string
{
    $data = trim($data);
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data)) {
        $d = \DateTime::createFromFormat('d/m/Y', $data);
        return $d ? $d->format('Y-m-d') : null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        return $data;
    }
    return null;
}

function fetchComCurl(string $url, array $headers = [], int $timeout = TIMEOUT_CURL): ?string
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ]);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);
    if ($erro) {
        error_log("[cron_apis] cURL error para $url: $erro");
        return null;
    }
    if ($httpCode >= 400) {
        error_log("[cron_apis] HTTP $httpCode para $url");
        return null;
    }
    return $resposta;
}

// ==============================================
// NORMALIZADORES POR PORTAL
// ==============================================

function normalizarPNCP(array $item): array
{
    $orgao = $item['orgaoEntidade']['razaoSocial'] ?? $item['orgaoEntidadeRazaoSocial'] ?? null;
    $unidade = $item['unidadeOrgao'] ?? [];
    $estado = $unidade['ufSigla'] ?? $item['unidadeOrgaoUfSigla'] ?? null;
    $cidade = $unidade['municipioNome'] ?? $item['unidadeOrgaoMunicipioNome'] ?? null;
    $link = $item['linkSistemaOrigem'] ?? null;
    if (!$link && !empty($item['numeroControlePNCP'])) {
        $link = "https://pncp.gov.br/app/contratacoes/{$item['numeroControlePNCP']}";
    }
    return [
        'orgao'            => $orgao,
        'objeto'           => $item['objetoCompra'] ?? null,
        'edital'           => $item['numeroCompra'] ?? null,
        'numero_processo'  => $item['processo'] ?? null,
        'estado'           => $estado,
        'cidade'           => $cidade,
        'modalidade'       => $item['modalidadeNome'] ?? null,
        'data_publicacao'  => parseDataBR($item['dataPublicacaoPncp'] ?? ''),
        'data_abertura'    => parseDataBR($item['dataAberturaProposta'] ?? ''),
        'valor_estimado'   => parseValorMonetario($item['valorTotalEstimado'] ?? null),
        'link_detalhes'    => $link,
        'id_original'      => (string)($item['numeroControlePNCP'] ?? $item['numeroCompra'] ?? ''),
        '_pncp_cnpj'       => $item['orgaoEntidade']['cnpj'] ?? $item['orgaoEntidadeCnpj'] ?? null,
        '_pncp_ano'        => $item['anoCompra'] ?? null,
        '_pncp_sequencial' => $item['sequencialCompra'] ?? null,
    ];
}

function normalizarComprasGov(array $item): array
{
    $link = null;
    if (!empty($item['linkSistemaOrigem'])) {
        $link = $item['linkSistemaOrigem'];
    } elseif (!empty($item['numero_aviso']) && !empty($item['uasg'])) {
        $link = "https://www.gov.br/compras/edital/{$item['uasg']}-{$item['numero_aviso']}";
    }

    return [
        'orgao'           => 'UASG ' . ($item['uasg'] ?? '') . ($item['nomeUasg'] ? ' - ' . $item['nomeUasg'] : ''),
        'objeto'          => $item['objeto'] ?? null,
        'edital'          => (string)($item['numero_aviso'] ?? $item['identificador'] ?? ''),
        'numero_processo' => $item['numero_processo'] ?? null,
        'estado'          => null,
        'cidade'          => null,
        'modalidade'      => $item['nome_modalidade'] ?? $item['modalidade'] ?? null,
        'data_publicacao' => parseDataBR($item['data_publicacao'] ?? ''),
        'data_abertura'   => parseDataBR($item['data_abertura_proposta'] ?? ''),
        'valor_estimado'  => parseValorMonetario($item['valor_estimado_total'] ?? null),
        'link_detalhes'   => $link,
        'id_original'     => (string)($item['id_compra'] ?? $item['identificador'] ?? ''),
    ];
}

function normalizarComprasGovPregao(array $item): array
{
    return [
        'orgao'           => ($item['orgao'] ?? $item['nmorgao'] ?? '') . ($item['uasg'] ? ' - UASG ' . $item['uasg'] : ''),
        'objeto'          => $item['objeto'] ?? $item['ds_objeto'] ?? null,
        'edital'          => (string)($item['nu_edital'] ?? $item['numero_aviso'] ?? ''),
        'numero_processo' => $item['nu_processo'] ?? $item['processo'] ?? null,
        'estado'          => $item['sg_uf'] ?? null,
        'cidade'          => $item['nm_cidade'] ?? null,
        'modalidade'      => 'Pregão',
        'data_publicacao' => parseDataBR($item['dt_publicacao'] ?? $item['dt_publicacao_aviso'] ?? ''),
        'data_abertura'   => parseDataBR($item['dt_abertura'] ?? $item['dt_abertura_proposta'] ?? ''),
        'valor_estimado'  => parseValorMonetario($item['vl_estimado'] ?? $item['valor_estimado_total'] ?? null),
        'link_detalhes'   => $item['link'] ?? $item['url_detalhe'] ?? null,
        'id_original'     => (string)($item['id_pregao'] ?? $item['id'] ?? ''),
    ];
}

function normalizarPortalComprasPublicas(array $item): array
{
    return [
        'orgao'           => $item['orgao'] ?? $item['nm_orgao'] ?? null,
        'objeto'          => $item['objeto'] ?? $item['ds_objeto'] ?? null,
        'edital'          => $item['edital'] ?? $item['nu_edital'] ?? null,
        'numero_processo' => $item['processo'] ?? $item['nu_processo'] ?? null,
        'estado'          => $item['estado'] ?? $item['sg_uf'] ?? null,
        'cidade'          => $item['cidade'] ?? $item['nm_cidade'] ?? null,
        'modalidade'      => $item['modalidade'] ?? $item['ds_modalidade'] ?? null,
        'data_publicacao' => parseDataBR($item['data_publicacao'] ?? $item['dt_publicacao'] ?? ''),
        'data_abertura'   => parseDataBR($item['data_abertura'] ?? $item['dt_abertura'] ?? ''),
        'valor_estimado'  => parseValorMonetario($item['valor_estimado'] ?? $item['vl_estimado'] ?? null),
        'link_detalhes'   => $item['link'] ?? $item['url_detalhe'] ?? null,
        'id_original'     => (string)($item['id'] ?? $item['codigo'] ?? ''),
    ];
}

// ==============================================
// COLETORES POR PORTAL
// ==============================================

function coletarPNCP(PDO $pdo, int $boletim_id): int
{
    $data = date('Ymd');
    $hoje = date('Y-m-d');
    $total_inseridas = 0;

    // 1. Endpoint "proposta" - capta TODAS as modalidades com propostas abertas (inclui Pregão!)
    //    codigoModalidadeContratacao é OPCIONAL aqui
    $pagina = 1;
    do {
        $url = "https://pncp.gov.br/api/consulta/v1/contratacoes/proposta" . 
               "?dataFinal=$data" .
               "&pagina=$pagina&tamanhoPagina=" . BOLETIM_LICITACOES_POR_PAGINA;

        $json = fetchComCurl($url, ['accept: application/json']);
        if (!$json) break;

        $dados = json_decode($json, true);
        if (!$dados || empty($dados['data'])) break;

        $itens = [];
        foreach ($dados['data'] as $item) {
            $itens[] = normalizarPNCP($item);
        }
        $inseridas = inserirLicitacoesBatch($pdo, $boletim_id, $itens, 'PNCP');
        $total_inseridas += $inseridas;

        // Captura links dos editais dos itens desta página
        foreach ($itens as $licItem) {
            capturarDocumentosPNCP($pdo, $licItem);
            usleep(100000);
        }

        $totalPaginas = $dados['totalPaginas'] ?? 1;
        $pagina++;
    } while ($pagina <= $totalPaginas);

    // 2. Endpoint "publicacao" - complementar para outras modalidades (códigos >= 10)
    $modalidades = [10, 11, 12, 13, 14, 15];
    foreach ($modalidades as $codModalidade) {
        $pagina = 1;
        do {
            $url = "https://pncp.gov.br/api/consulta/v1/contratacoes/publicacao" . 
                   "?dataInicial=$data&dataFinal=$data" .
                   "&codigoModalidadeContratacao=$codModalidade" .
                   "&pagina=$pagina&tamanhoPagina=" . BOLETIM_LICITACOES_POR_PAGINA;

            $json = fetchComCurl($url, ['accept: application/json']);
            if (!$json) break;

            $dados = json_decode($json, true);
            if (!$dados || empty($dados['data'])) break;

            $itens = [];
            foreach ($dados['data'] as $item) {
                $itens[] = normalizarPNCP($item);
            }
            $inseridas = inserirLicitacoesBatch($pdo, $boletim_id, $itens, 'PNCP');
            $total_inseridas += $inseridas;

            foreach ($itens as $licItem) {
                capturarDocumentosPNCP($pdo, $licItem);
                usleep(100000);
            }

            $totalPaginas = $dados['totalPaginas'] ?? 1;
            $pagina++;
        } while ($pagina <= $totalPaginas);
    }

    return $total_inseridas;
}

/**
 * Captura o link de download do edital no PNCP para uma licitação
 * usando o endpoint público de documentos da API de integração
 */
function capturarDocumentosPNCP(PDO $pdo, array $item): void
{
    $cnpj = $item['_pncp_cnpj'] ?? null;
    $ano = $item['_pncp_ano'] ?? null;
    $sequencial = $item['_pncp_sequencial'] ?? null;

    // Fallback: extrai do numeroControlePNCP se os campos não estiverem disponíveis
    if (!$cnpj || !$ano || !$sequencial) {
        $idOriginal = $item['id_original'] ?? '';
        if (preg_match('/^(\d{14})-\d+-0*(\d+)\/(\d{4})$/', $idOriginal, $m)) {
            $cnpj = $m[1];
            $sequencial = (int)$m[2];
            $ano = (int)$m[3];
        } else {
            return;
        }
    }

    $url = "https://pncp.gov.br/api/pncp/v1/orgaos/$cnpj/compras/$ano/$sequencial/arquivos";
    $json = fetchComCurl($url, ['accept: application/json']);
    if (!$json) return;

    $documentos = json_decode($json, true);
    if (!$documentos || !is_array($documentos)) return;

    // Normaliza para array se retornou objeto único
    if (!isset($documentos[0])) {
        $documentos = [$documentos];
    }

    // Procura o documento do tipo "Edital" (tipoDocumentoId = 2)
    $linkEdital = null;
    foreach ($documentos as $doc) {
        if (($doc['tipoDocumentoId'] ?? null) == 2) {
            $linkEdital = $doc['url'] ?? $doc['uri'] ?? null;
            break;
        }
    }
    // Se não achou Edital, pega o primeiro documento disponível
    if (!$linkEdital && !empty($documentos[0]['url'])) {
        $linkEdital = $documentos[0]['url'];
    }

    if (!$linkEdital) return;

    $sql = "UPDATE boletim_licitacoes SET link_edital = ? WHERE portal_origem = 'PNCP' AND id_original = ? AND (link_edital IS NULL OR link_edital = '')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$linkEdital, $item['id_original'] ?? '']);
}

/**
 * Captura editais pendentes de lotes anteriores (backfill)
 */
function capturarEditaisPendentesPNCP(PDO $pdo): int
{
    $atualizados = 0;
    $stmt = $pdo->prepare("SELECT id, id_original FROM boletim_licitacoes WHERE portal_origem = 'PNCP' AND id_original IS NOT NULL AND id_original != '' AND (link_edital IS NULL OR link_edital = '') LIMIT 50");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $item = ['id_original' => $row['id_original']];
        capturarDocumentosPNCP($pdo, $item);
        usleep(100000);
        $atualizados++;
    }
    return $atualizados;
}

function coletarComprasGov(PDO $pdo, int $boletim_id): int
{
    $data = date('Y-m-d');
    $total_inseridas = 0;
    $pagina = 1;

    do {
        $url = "https://dadosabertos.compras.gov.br/modulo-legado/1_consultarLicitacao" .
               "?data_publicacao_inicial=$data&data_publicacao_final=$data" .
               "&pagina=$pagina&tamanhoPagina=" . BOLETIM_LICITACOES_POR_PAGINA;

        $json = fetchComCurl($url, [
            'accept: application/json',
        ]);

        if (!$json) break;

        $dados = json_decode($json, true);
        if (!$dados) break;

        $itens = [];
        $lista = $dados['resultado'] ?? $dados['data'] ?? [];
        if (empty($lista)) break;

        foreach ($lista as $item) {
            $itens[] = normalizarComprasGov($item);
        }

        $inseridas = inserirLicitacoesBatch($pdo, $boletim_id, $itens, 'COMPRAS_GOV');
        $total_inseridas += $inseridas;

        $paginasRestantes = $dados['paginasRestantes'] ?? 0;
        $pagina = $paginasRestantes > 0 ? $pagina + 1 : 0;

    } while ($pagina > 0);

    return $total_inseridas;
}

function coletarComprasGovPregoes(PDO $pdo, int $boletim_id): int
{
    $data = date('Y-m-d');
    $total_inseridas = 0;
    $pagina = 1;

    do {
        $url = "https://dadosabertos.compras.gov.br/modulo-legado/3_consultarPregoes" .
               "?dt_data_edital_inicial=$data&dt_data_edital_final=$data" .
               "&pagina=$pagina&tamanhoPagina=" . BOLETIM_LICITACOES_POR_PAGINA;

        $json = fetchComCurl($url, ['accept: application/json']);
        if (!$json) break;

        $dados = json_decode($json, true);
        if (!$dados) break;

        $lista = $dados['resultado'] ?? $dados['data'] ?? [];
        if (empty($lista)) break;

        $itens = [];
        foreach ($lista as $item) {
            $itens[] = normalizarComprasGovPregao($item);
        }

        $inseridas = inserirLicitacoesBatch($pdo, $boletim_id, $itens, 'COMPRAS_GOV_PREGOES');
        $total_inseridas += $inseridas;

        $paginasRestantes = $dados['paginasRestantes'] ?? 0;
        $pagina = $paginasRestantes > 0 ? $pagina + 1 : 0;

    } while ($pagina > 0);

    return $total_inseridas;
}

function coletarPortalComprasPublicas(PDO $pdo, int $boletim_id): int
{
    // API do Portal de Compras Públicas é privada (requer cadastro/login)
    // Será implementado via web scraping na Fase 3
    return 0;
}

// ==============================================
// EXECUÇÃO PRINCIPAL
// ==============================================

try {
    $db = new Database();
    $pdo = $db->connect();

    $hoje = date('Y-m-d');
    $boletim_id = getOrCreateBoletim($pdo, $hoje);

    $portais = [
        'PNCP' => [
            'funcao' => 'coletarPNCP',
            'tipo'   => 'API',
        ],
        'COMPRAS_GOV' => [
            'funcao' => 'coletarComprasGov',
            'tipo'   => 'API',
        ],
        'COMPRAS_GOV_PREGOES' => [
            'funcao' => 'coletarComprasGovPregoes',
            'tipo'   => 'API',
        ],
        'PORTAL_COMPRAS_PUBLICAS' => [
            'funcao' => 'coletarPortalComprasPublicas',
            'tipo'   => 'API',
        ],
    ];

    foreach ($portais as $portal => $config) {
        // Reconecta antes de cada portal (evita "MySQL has gone away")
        $pdo = $db->reconnect();
        $inicio = microtime(true);
        $qtd = 0;
        $erro = null;

        try {
            $qtd = call_user_func($config['funcao'], $pdo, $boletim_id);
            if ($qtd > 0) {
                registrarPortaisBoletim($pdo, $boletim_id, $portal);
            }
            $status = $qtd > 0 ? 'SUCESSO' : 'SUCESSO';
        } catch (Exception $e) {
            $status = 'ERRO';
            $erro = $e->getMessage();
            error_log("[cron_apis] Erro no portal $portal: $erro");
        }

        $total = $qtd;
        logCaptacao($pdo, $portal, $config['tipo'], $status, $qtd, $total, $erro);
        $tempo = round(microtime(true) - $inicio, 2);
        error_log("[cron_apis] $portal: $qtd licitações inseridas em {$tempo}s");
    }

    // Backfill: captura editais de licitações de dias anteriores que ainda não têm link
    $backfill = capturarEditaisPendentesPNCP($pdo);
    if ($backfill > 0) {
        error_log("[cron_apis] Backfill de editais: $backfill registros atualizados");
    }

    atualizarTotalBoletim($pdo, $boletim_id);
    echo "[cron_apis] Finalizado com sucesso.\n";

} catch (Exception $e) {
    error_log("[cron_apis] Erro fatal: " . $e->getMessage());
    echo "[cron_apis] Erro fatal: " . $e->getMessage() . "\n";
    exit(1);
}
