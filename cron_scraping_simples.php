<?php
// ==============================================
// FASE 3: cron_scraping_simples.php
// Scraping simples com cURL + DOMDocument/DOMXPath
// Portais com HTML server-side renderizado
// ==============================================

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/Database.php';

define('BOLETIM_LICITACOES_POR_PAGINA', 50);
define('TIMEOUT_CURL', 30);

// ==============================================
// FUNÇÕES AUXILIARES (reaproveitadas do cron_apis)
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
        error_log("[cron_scraping] Reconectando ao banco...");
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
            error_log("[cron_scraping] Erro ao inserir licitação do portal $portal_origem: " . $e->getMessage());
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
        $d = DateTime::createFromFormat('d/m/Y', $data);
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
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
    ]);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);
    if ($erro) {
        error_log("[cron_scraping] cURL error para $url: $erro");
        return null;
    }
    if ($httpCode >= 400) {
        error_log("[cron_scraping] HTTP $httpCode para $url");
        return null;
    }
    return $resposta;
}

function fetchPostCurl(string $url, array $postData, array $headers = [], int $timeout = TIMEOUT_CURL): ?string
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
    ]);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);
    if ($erro) {
        error_log("[cron_scraping] POST cURL error para $url: $erro");
        return null;
    }
    if ($httpCode >= 400) {
        error_log("[cron_scraping] POST HTTP $httpCode para $url");
        return null;
    }
    return $resposta;
}

function parseDataPtBr(string $dataStr): ?string
{
    // Formato: "15 mai, 2026"
    $meses = [
        'jan' => '01', 'fev' => '02', 'mar' => '03', 'abr' => '04',
        'mai' => '05', 'jun' => '06', 'jul' => '07', 'ago' => '08',
        'set' => '09', 'out' => '10', 'nov' => '11', 'dez' => '12',
    ];
    if (preg_match('/^(\d{1,2})\s+([a-z]{3})[a-z]*,\s+(\d{4})$/i', trim($dataStr), $m)) {
        $mes = $meses[strtolower($m[2])] ?? null;
        if ($mes) {
            return sprintf('%s-%s-%02s', $m[3], $mes, $m[1]);
        }
    }
    return parseDataBR($dataStr);
}

// ==============================================
// NORMALIZADORES
// ==============================================

function normalizarLicitanet(array $item): array
{
    $buyer = $item['buyer'] ?? '';
    $title = $item['title'] ?? '';
    $edital = '';
    $modalidade = '';

    // Extrai modalidade e edital do título
    // Ex: "AVISO DE SUSPENSAO - PE 025/2026 - MUNICÍPIO DE SEABRA/BA"
    if (preg_match('/\b(PE|PP|TP|CC|CR|DL|RP|IN|CN|DC)\s*-?\s*(\d+[\/.-]\d{4})/i', $title, $m)) {
        $modalidade = match (strtoupper($m[1])) {
            'PE' => 'Pregão Eletrônico',
            'PP' => 'Pregão Presencial',
            'TP' => 'Tomada de Preços',
            'CC' => 'Concorrência',
            'CR' => 'Credenciamento',
            'DL' => 'Dispensa de Licitação',
            'RP' => 'Registro de Preços',
            'IN' => 'Inexigibilidade',
            'CN' => 'Concurso',
            'DC' => 'Diálogo Competitivo',
            default => $m[1],
        };
        $edital = $m[1] . ' ' . $m[2];
    }

    // Extrai estado do buyer: "MUNICÍPIO DE SEABRA/BA" → "BA"
    $estado = null;
    $cidade = null;
    if (preg_match('/\/([A-Z]{2})$/', $buyer, $m)) {
        $estado = $m[1];
        $cidade = trim(preg_replace('/\s*\/[A-Z]{2}$/', '', $buyer));
    }

    $descricao = $item['description'] ?? '';

    return [
        'orgao'           => $buyer,
        'objeto'          => $descricao ?: $title,
        'edital'          => $edital,
        'numero_processo' => null,
        'estado'          => $estado,
        'cidade'          => $cidade,
        'modalidade'      => $modalidade,
        'data_publicacao' => parseDataPtBr($item['datRegister'] ?? ''),
        'data_abertura'   => null,
        'valor_estimado'  => null,
        'link_detalhes'   => 'https://www.licitanet.com.br/noticia/' . ($item['identifier'] ?? ''),
        'id_original'     => (string)($item['identifier'] ?? ''),
    ];
}

function normalizarBECSP(array $item): array
{
    return [
        'orgao'           => $item['orgao'] ?? null,
        'objeto'          => $item['objeto'] ?? null,
        'edital'          => $item['edital'] ?? null,
        'numero_processo' => $item['numero_processo'] ?? null,
        'estado'          => 'SP',
        'cidade'          => $item['cidade'] ?? 'São Paulo',
        'modalidade'      => $item['modalidade'] ?? null,
        'data_publicacao' => parseDataBR($item['data_publicacao'] ?? ''),
        'data_abertura'   => parseDataBR($item['data_abertura'] ?? ''),
        'valor_estimado'  => parseValorMonetario($item['valor_estimado'] ?? null),
        'link_detalhes'   => $item['link_detalhes'] ?? null,
        'id_original'     => (string)($item['id_original'] ?? ''),
    ];
}

// ==============================================
// SCRAPER: Licitanet
// ==============================================

function coletarLicitanet(PDO $pdo, int $boletim_id): int
{
    $total_inseridas = 0;
    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
    ];

    $html = fetchComCurl('https://www.licitanet.com.br/', $headers);
    if (!$html) {
        error_log('[scraper_licitanet] Falha ao acessar homepage');
        return 0;
    }

    // Extrai JSON do atributo data-page (Inertia.js SSR)
    if (!preg_match('/data-page="([^"]+)"/', $html, $m)) {
        error_log('[scraper_licitanet] data-page JSON nao encontrado no HTML');
        return 0;
    }

    $jsonStr = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $dados = json_decode($jsonStr, true);
    if (!$dados || !isset($dados['props']['notices']['data'])) {
        error_log('[scraper_licitanet] JSON invalido ou sem notices');
        return 0;
    }

    $notices = $dados['props']['notices']['data'];
    if (empty($notices)) {
        return 0;
    }

    $itens = [];
    foreach ($notices as $n) {
        $itens[] = normalizarLicitanet($n);
    }

    $inseridas = inserirLicitacoesBatch($pdo, $boletim_id, $itens, 'LICITANET');
    $total_inseridas += $inseridas;

    // Tenta páginas adicionais via Inertia (requer CSRF token + cookie session)
    $csrfToken = $dados['props']['csrfToken'] ?? null;
    $meta = $dados['props']['notices']['meta'] ?? [];
    $totalPages = $meta['totalPages'] ?? 1;
    $currentPage = $meta['currentPage'] ?? 1;

    // Extrai cookie da sessão para manter a conversa Inertia
    $cookies = [];
    preg_match_all('/^Set-Cookie:\s*([^;]+)/mi', $http_response_header ?? '', $cookieMatches);
    // Usa cookie jar via arquivo temporário
    $cookieFile = tempnam(sys_get_temp_dir(), 'licitanet_');

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.licitanet.com.br/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    curl_exec($ch);
    curl_close($ch);

    for ($pg = 2; $pg <= min($totalPages, 3); $pg++) {
        $url = "https://www.licitanet.com.br/?page=" . $pg;
        $inertiaHeaders = array_merge($headers, [
            'X-Inertia: true',
            'X-Inertia-Version: ' . ($dados['version'] ?? ''),
            'X-Requested-With: XMLHttpRequest',
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_HTTPHEADER => $inertiaHeaders,
            CURLOPT_COOKIEFILE => $cookieFile,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$resp) break;

        $pgDados = json_decode($resp, true);
        if (!$pgDados || !isset($pgDados['props']['notices']['data'])) break;

        $pgItens = [];
        foreach ($pgDados['props']['notices']['data'] as $n) {
            $pgItens[] = normalizarLicitanet($n);
        }
        $inseridas = inserirLicitacoesBatch($pdo, $boletim_id, $pgItens, 'LICITANET');
        $total_inseridas += $inseridas;
        usleep(200000);
    }

    @unlink($cookieFile);
    return $total_inseridas;
}

// ==============================================
// SCRAPER: BEC-SP (Bolsa Eletrônica de Compras de SP)
// Utiliza ViewState + POST para navegar nas páginas de Pregão
// ==============================================

function extrairViewState(string $html): array
{
    $fields = [];
    foreach (['__VIEWSTATE', '__VIEWSTATEGENERATOR', '__EVENTVALIDATION', '__LASTFOCUS'] as $f) {
        if (preg_match('/id="' . preg_quote($f, '/') . '".*?value="([^"]*)"/', $html, $m)) {
            $fields[$f] = $m[1];
        }
    }
    return $fields;
}

function coletarBECSP(PDO $pdo, int $boletim_id): int
{
    $homeUrl = 'https://www.bec.sp.gov.br/';
    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
    ];

    // 1º passo: GET da homepage para obter ViewState
    $html = fetchComCurl($homeUrl, $headers);
    if (!$html) return 0;

    // Tenta extrair dados de resultado já presentes na homepage (statísticas agregadas)
    $licitacoes = [];
    $dom = new DOMDocument();
    @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($dom);

    // 2º passo: POST para acionar lnkPregao
    $vs = extrairViewState($html);
    if (empty($vs['__VIEWSTATE'])) {
        // Tenta scraping direto de URLs alternativas
        $urls = [
            'https://www.bec.sp.gov.br/BECSP/Aspx/PregaoFrmListaPregao.aspx',
            'https://www.bec.sp.gov.br/BECSP/Aspx/PregaoFrmPesquisaPregao.aspx',
        ];
        foreach ($urls as $url) {
            $resp = fetchComCurl($url, $headers);
            if ($resp && strlen($resp) > 1000) {
                $html = $resp;
                break;
            }
        }
        if (!$html || strlen($html) < 1000) return 0;
    }

    // Tenta POST com ViewState para obter grid de pregões
    $postData = [
        '__VIEWSTATE' => $vs['__VIEWSTATE'] ?? '',
        '__VIEWSTATEGENERATOR' => $vs['__VIEWSTATEGENERATOR'] ?? '',
        '__EVENTVALIDATION' => $vs['__EVENTVALIDATION'] ?? '',
        '__EVENTTARGET' => 'lnkPregao',
        '__EVENTARGUMENT' => '',
    ];

    $resp2 = fetchPostCurl($homeUrl, $postData, $headers);
    if (!$resp2) return 0;

    // Verifica se a resposta tem dados diferentes da homepage
    if ($resp2 === $html) {
        error_log('[scraper_becsp] POST retornou mesma pagina (ViewState pode precisar de JS)');
        // Tenta extrair dados mínimos da página
    }

    // Parse do HTML da resposta
    $dom2 = new DOMDocument();
    @$dom2->loadHTML(mb_convert_encoding($resp2, 'HTML-ENTITIES', 'UTF-8'));
    $xpath2 = new DOMXPath($dom2);

    // Procura por tabelas com dados
    $tabelas = $xpath2->query('//table[contains(@id, "Grid") or contains(@id, "grid") or contains(@class, "Grid") or contains(@class, "grid")]');
    if ($tabelas->length === 0) {
        // Tenta qualquer tabela com múltiplas linhas
        $tabelas = $xpath2->query('//table[.//tr[position()>1]]');
    }

    if ($tabelas->length > 0) {
        foreach ($tabelas as $tabela) {
            $linhas = $xpath2->query('.//tr[position()>1]', $tabela);
            foreach ($linhas as $linha) {
                $celulas = $xpath2->query('.//td', $linha);
                if ($celulas->length < 3) continue;

                $dados = [];
                foreach ($celulas as $idx => $cel) {
                    $dados['col_' . $idx] = trim($cel->textContent);
                }

                $lic = [
                    'orgao' => $dados['col_0'] ?? null,
                    'objeto' => $dados['col_2'] ?? $dados['col_1'] ?? null,
                    'edital' => $dados['col_1'] ?? null,
                    'numero_processo' => null,
                    'estado' => 'SP',
                    'cidade' => null,
                    'modalidade' => 'Pregão',
                    'data_publicacao' => parseDataBR($dados['col_3'] ?? ''),
                    'data_abertura' => parseDataBR($dados['col_4'] ?? ''),
                    'valor_estimado' => null,
                    'link_detalhes' => $homeUrl,
                    'id_original' => $dados['col_1'] ?? '',
                ];
                $licitacoes[] = $lic;
            }
        }
    }

    if (empty($licitacoes)) {
        return 0;
    }

    return inserirLicitacoesBatch($pdo, $boletim_id, $licitacoes, 'BECSP');
}

// ==============================================
// MAPA DE SCRAPERS (fácil de estender)
// ==============================================

$scrapers = [
    'LICITANET' => [
        'funcao' => 'coletarLicitanet',
        'tipo' => 'SCRAPING_SIMPLES',
        'descricao' => 'Licitanet - avisos de licitações (SSR Inertia)',
    ],
    'BECSP' => [
        'funcao' => 'coletarBECSP',
        'tipo' => 'SCRAPING_SIMPLES',
        'descricao' => 'Bolsa Eletrônica de Compras SP - Pregões (ASP.NET ViewState)',
    ],
    // === Próximos scrapers (adicionar aqui) ===
    // 'BNC' => [
    //     'funcao' => 'coletarBNC',
    //     'tipo' => 'SCRAPING_SIMPLES',
    //     'descricao' => 'BNC - requer reCAPTCHA + login (usar Fase 4)',
    //     'ativa' => false,
    // ],
    // 'BLL' => [
    //     'funcao' => 'coletarBLL',
    //     'tipo' => 'SCRAPING_SIMPLES',
    //     'descricao' => 'BLL - requer reCAPTCHA + login (usar Fase 4)',
    //     'ativa' => false,
    // ],
    // 'PORTAL_COMPRAS_PUBLICAS' => [
    //     'funcao' => 'coletarPortalComprasPublicasScraping',
    //     'tipo' => 'SCRAPING_SIMPLES',
    //     'descricao' => 'Portal de Compras Públicas - requer cadastro/login',
    //     'ativa' => false,
    // ],
];

// ==============================================
// EXECUÇÃO PRINCIPAL
// ==============================================

try {
    $db = new Database();
    $pdo = $db->connect();

    $hoje = date('Y-m-d');
    $boletim_id = getOrCreateBoletim($pdo, $hoje);

    foreach ($scrapers as $portal => $config) {
        if (isset($config['ativa']) && $config['ativa'] === false) continue;

        $pdo = keepAlive($pdo, $db);
        $inicio = microtime(true);
        $qtd = 0;
        $erro = null;

        try {
            $qtd = call_user_func($config['funcao'], $pdo, $boletim_id);
            if ($qtd > 0) {
                registrarPortaisBoletim($pdo, $boletim_id, $portal);
            }
            $status = 'SUCESSO';
        } catch (Exception $e) {
            $status = 'ERRO';
            $erro = $e->getMessage();
            error_log("[cron_scraping] Erro no scraper $portal: $erro");
        }

        logCaptacao($pdo, $portal, $config['tipo'], $status, $qtd, $qtd, $erro);
        $tempo = round(microtime(true) - $inicio, 2);
        error_log("[cron_scraping] $portal: $qtd licitações em {$tempo}s");
    }

    atualizarTotalBoletim($pdo, $boletim_id);
    echo "[cron_scraping] Finalizado.\n";

} catch (Exception $e) {
    error_log("[cron_scraping] Erro fatal: " . $e->getMessage());
    echo "[cron_scraping] Erro fatal: " . $e->getMessage() . "\n";
    exit(1);
}
