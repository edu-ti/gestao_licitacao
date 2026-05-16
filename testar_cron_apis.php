<?php
// ==============================================
// TESTE WEB: Verificar FASE 1 + FASE 2
// Acesse: http://seudominio.com.br/testar_cron_apis.php
// ==============================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'Database.php';

echo "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='UTF-8'>
<title>Teste - Motor de Captação</title>
<script src='https://cdn.tailwindcss.com'></script>
</head><body class='bg-gray-100 p-8'>
<div class='container mx-auto max-w-5xl'>";

echo "<h1 class='text-3xl font-bold mb-6'>🔧 Teste Motor de Captação de Licitações</h1>";

try {
    $db = new Database();
    $pdo = $db->connect();
    echo "<div class='bg-green-100 border-l-4 border-green-500 p-4 mb-4'>✅ Conexão com banco OK</div>";

    // ==============================================
    // 1. VERIFICAR TABELAS
    // ==============================================
    echo "<div class='bg-white p-6 rounded-lg shadow mb-6'>";
    echo "<h2 class='text-xl font-bold mb-4'>1. Estrutura do Banco</h2>";

    $tabelas_necessarias = ['boletins', 'boletim_licitacoes', 'log_captacao'];
    $tabelas_existentes = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tabelas_necessarias as $tbl) {
        $existe = in_array($tbl, $tabelas_existentes);
        $cor = $existe ? 'text-green-600' : 'text-red-600';
        $icone = $existe ? '✅' : '❌';
        echo "<p class='{$cor}'>{$icone} {$tbl}: " . ($existe ? 'EXISTE' : 'NÃO EXISTE') . "</p>";

        if ($existe) {
            $cols = $pdo->query("DESCRIBE `{$tbl}`")->fetchAll(PDO::FETCH_ASSOC);
            echo "<ul class='ml-6 text-sm text-gray-600 list-disc'>";
            foreach ($cols as $c) {
                echo "<li>{$c['Field']} ({$c['Type']})</li>";
            }
            echo "</ul>";
        }
    }
    echo "</div>";

    // ==============================================
    // 2. TESTAR APIs DIRETAMENTE
    // ==============================================
    echo "<div class='bg-white p-6 rounded-lg shadow mb-6'>";
    echo "<h2 class='text-xl font-bold mb-4'>2. Teste de APIs Externas</h2>";

    $apis = [
        // 1. TESTE DE CONECTIVIDADE - Servidor consegue acessar internet?
        'TESTE CONECTIVIDADE' => [
            'url' => 'https://httpbin.org/get',
            'headers' => [],
            'desc' => 'Testa se o servidor tem acesso à internet',
        ],
        // 2. PNCP - dataInicial/dataFinal formato AAAAMMDD + codigo OBRIGATÓRIO >= 10
        'PNCP (mod. 12)' => [
            'url' => 'https://pncp.gov.br/api/consulta/v1/contratacoes/publicacao?dataInicial=' . date('Ymd') . '&dataFinal=' . date('Ymd') . '&codigoModalidadeContratacao=12&pagina=1&tamanhoPagina=10',
            'headers' => ['accept: application/json'],
            'desc' => 'PNCP - Credenciamento (código 12)',
        ],
        'PNCP (mod. 10)' => [
            'url' => 'https://pncp.gov.br/api/consulta/v1/contratacoes/publicacao?dataInicial=' . date('Ymd') . '&dataFinal=' . date('Ymd') . '&codigoModalidadeContratacao=10&pagina=1&tamanhoPagina=10',
            'headers' => ['accept: application/json'],
            'desc' => 'PNCP - Manifestação de Interesse (código 10)',
        ],
        // 3. PNCP - endpoint proposta (codigoModalidade OPCIONAL - pega Pregão!)
        'PNCP (propostas abertas)' => [
            'url' => 'https://pncp.gov.br/api/consulta/v1/contratacoes/proposta?dataFinal=' . date('Ymd') . '&pagina=1&tamanhoPagina=10',
            'headers' => ['accept: application/json'],
            'desc' => 'PNCP - Contratações com propostas abertas (inclui Pregão)',
        ],
        // 3. Compras.gov.br - API Legado Módulo Licitações (tamanhoPagina >= 10)
        'Compras.gov.br (legado)' => [
            'url' => 'https://dadosabertos.compras.gov.br/modulo-legado/1_consultarLicitacao?data_publicacao_inicial=' . date('Y-m-d') . '&data_publicacao_final=' . date('Y-m-d') . '&pagina=1&tamanhoPagina=10',
            'headers' => ['accept: application/json'],
            'desc' => 'API Dados Abertos Compras - Licitações (Lei 8.666)',
        ],
        // 4. Compras.gov.br - endpoint pregões (tamanhoPagina >= 10)
        'Compras.gov.br (pregões)' => [
            'url' => 'https://dadosabertos.compras.gov.br/modulo-legado/3_consultarPregoes?dt_data_edital_inicial=' . date('Y-m-d') . '&dt_data_edital_final=' . date('Y-m-d') . '&pagina=1&tamanhoPagina=10',
            'headers' => ['accept: application/json'],
            'desc' => 'API Dados Abertos Compras - Pregões',
        ],
        // 5. PNCP - endpoint documentos públicos (API de integração, sem auth)
        'PNCP (documentos - integração)' => [
            'url' => 'https://pncp.gov.br/api/pncp/v1/tipos-documentos',
            'headers' => ['accept: application/json'],
            'desc' => 'PNCP API Integração - Tipos de documento (teste de acesso público)',
        ],
    ];

    foreach ($apis as $nome => $conf) {
        echo "<div class='mb-4 p-4 border rounded'>";
        echo "<h3 class='font-bold'>🌐 {$nome}</h3>";
        if (!empty($conf['desc'])) echo "<p class='text-sm text-gray-600 mb-1'>{$conf['desc']}</p>";
        echo "<p class='text-xs text-gray-500 mb-2'>URL: " . htmlspecialchars($conf['url']) . "</p>";

        $ch = curl_init();
        $curlOpts = [
            CURLOPT_URL => $conf['url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ];
        if (!empty($conf['method']) && $conf['method'] === 'POST') {
            $curlOpts[CURLOPT_POST] = true;
            $curlOpts[CURLOPT_POSTFIELDS] = $conf['postData'] ?? '';
            echo "<p class='text-xs text-blue-600 mb-1'>Método: POST | Dados: " . htmlspecialchars($conf['postData'] ?? '') . "</p>";
        }
        curl_setopt_array($ch, $curlOpts);
        if (!empty($conf['headers'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $conf['headers']);
        }

        $resposta = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro = curl_error($ch);
        $tamanho = strlen($resposta);
        curl_close($ch);

        echo "<p>HTTP: <strong>{$httpCode}</strong> | Tamanho: <strong>{$tamanho} bytes</strong></p>";

        if ($erro) {
            echo "<p class='text-red-600'>❌ cURL Error: {$erro}</p>";
        } elseif ($resposta) {
            $json = json_decode($resposta, true);
            if ($json !== null) {
                echo "<p class='text-green-600'>✅ JSON válido</p>";
                $primeiro = null;
                if (isset($json['data']) && is_array($json['data'])) {
                    $primeiro = $json['data'][0] ?? null;
                    echo "<p>Registros retornados: " . count($json['data']) . "</p>";
                } elseif (isset($json['_embedded']['licitacoes'])) {
                    $primeiro = $json['_embedded']['licitacoes'][0] ?? null;
                    echo "<p>Registros retornados: " . count($json['_embedded']['licitacoes']) . "</p>";
                } elseif (isset($json['dados'])) {
                    $primeiro = $json['dados'][0] ?? null;
                    echo "<p>Registros retornados: " . count($json['dados']) . "</p>";
                } elseif (isset($json['licitacoes'])) {
                    $primeiro = $json['licitacoes'][0] ?? null;
                    echo "<p>Registros retornados: " . count($json['licitacoes']) . "</p>";
                } elseif (isset($json[0])) {
                    $primeiro = $json[0];
                    echo "<p>Registros retornados (array puro): " . count($json) . "</p>";
                }

                if ($primeiro) {
                    echo "<div class='mt-2 text-xs bg-gray-50 p-2 rounded'>";
                    echo "<p class='font-semibold'>Chaves do primeiro item:</p>";
                    echo "<pre>" . htmlspecialchars(implode(', ', array_keys($primeiro))) . "</pre>";
                    echo "</div>";
                }

                // Preview
                echo "<details class='mt-2'><summary class='text-sm text-blue-600 cursor-pointer'>Ver JSON completo</summary>";
                echo "<pre class='text-xs mt-2 bg-gray-100 p-2 rounded max-h-60 overflow-auto'>" . htmlspecialchars(substr(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 0, 3000)) . "</pre>";
                echo "</details>";
            } else {
                echo "<p class='text-yellow-600'>⚠️ Não é JSON. Primeiros 300 caracteres:</p>";
                echo "<pre class='text-xs bg-gray-100 p-2 rounded'>" . htmlspecialchars(substr($resposta, 0, 300)) . "</pre>";
            }
        } else {
            echo "<p class='text-red-600'>❌ Resposta vazia</p>";
        }
        echo "</div>";
    }
    echo "</div>";

    // ==============================================
    // 3. TESTE DO ENDPOINT DE DOCUMENTOS (EDITAIS)
    // ==============================================
    echo "<div class='bg-white p-6 rounded-lg shadow mb-6'>";
    echo "<h2 class='text-xl font-bold mb-4'>3. Teste de Documentos (Editais) - PNCP</h2>";

    // Tenta buscar um registro PNCP existente no banco
    $stmt = $pdo->prepare("SELECT id_original FROM boletim_licitacoes WHERE portal_origem = 'PNCP' AND id_original IS NOT NULL AND id_original != '' LIMIT 1");
    $stmt->execute();
    $pncpRegistro = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pncpRegistro) {
        $idOriginal = $pncpRegistro['id_original'];
        echo "<p>📄 Testando com registro existente: <code>" . htmlspecialchars($idOriginal) . "</code></p>";

        if (preg_match('/^(\d{14})-\d+-0*(\d+)\/(\d{4})$/', $idOriginal, $m)) {
            $cnpj = $m[1];
            $sequencial = (int)$m[2];
            $ano = (int)$m[3];
            $docUrl = "https://pncp.gov.br/api/pncp/v1/orgaos/$cnpj/compras/$ano/$sequencial/arquivos";

            echo "<p class='text-xs text-gray-500 mb-2'>URL: " . htmlspecialchars($docUrl) . "</p>";

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $docUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0',
                CURLOPT_HTTPHEADER => ['accept: application/json'],
            ]);
            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $erro = curl_error($ch);
            curl_close($ch);

            if ($erro) {
                echo "<p class='text-red-600'>❌ cURL Error: {$erro}</p>";
            } elseif ($httpCode >= 400) {
                echo "<p class='text-red-600'>❌ HTTP {$httpCode}</p>";
            } else {
                $docs = json_decode($resp, true);
                if (!empty($docs)) {
                    if (isset($docs[0])) {
                        echo "<p class='text-green-600'>✅ {$httpCode} - " . count($docs) . " documento(s) encontrado(s)</p>";
                        foreach ($docs as $d) {
                            $tipoNome = $d['tipoDocumentoNome'] ?? $d['tipoDocumentoDescricao'] ?? 'N/A';
                            $link = $d['url'] ?? $d['uri'] ?? '';
                            $editalFlag = ($d['tipoDocumentoId'] ?? null) == 2 ? '📄 EDITAL' : '';
                            echo "<p class='text-sm ml-4'>- {$tipoNome} {$editalFlag}: <a href='" . htmlspecialchars($link) . "' target='_blank' class='text-blue-600 underline'>Download</a></p>";
                        }
                    } else {
                        $tipoNome = $docs['tipoDocumentoNome'] ?? $docs['tipoDocumentoDescricao'] ?? 'N/A';
                        $link = $docs['url'] ?? $docs['uri'] ?? '';
                        $editalFlag = ($docs['tipoDocumentoId'] ?? null) == 2 ? '📄 EDITAL' : '';
                        echo "<p class='text-green-600'>✅ {$httpCode} - 1 documento: {$tipoNome} {$editalFlag}</p>";
                        if ($link) {
                            echo "<p class='ml-4'><a href='" . htmlspecialchars($link) . "' target='_blank' class='text-blue-600 underline'>Download PDF</a></p>";
                        }
                    }
                } else {
                    echo "<p class='text-yellow-600'>⚠️ Nenhum documento encontrado para este registro</p>";
                }
            }
        } else {
            echo "<p class='text-yellow-600'>⚠️ Não foi possível parsear o número de controle PNCP</p>";
        }
    } else {
        echo "<p class='text-yellow-600'>⚠️ Nenhum registro PNCP no banco para testar. Use os testes acima primeiro.</p>";
        // Fallback: testa o endpoint de tipos de documento para provar acesso público
        echo "<p class='text-sm'>Testando acesso público à API de integração PNCP (tipos de documento)...</p>";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://pncp.gov.br/api/pncp/v1/tipos-documentos',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
            CURLOPT_HTTPHEADER => ['accept: application/json'],
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode == 200) {
            $tipos = json_decode($resp, true);
            echo "<p class='text-green-600'>✅ HTTP {$httpCode} - " . count($tipos) . " tipos de documento disponíveis</p>";
        } else {
            echo "<p class='text-red-600'>❌ HTTP {$httpCode}</p>";
        }
    }
    echo "</div>";

    // ==============================================
    // 4. RECOMENDAÇÕES
    // ==============================================
    echo "<div class='bg-blue-50 p-6 rounded-lg shadow mb-6'>";
    echo "<h2 class='text-xl font-bold mb-4'>📋 Próximos Passos</h2>";
    echo "<ul class='list-disc ml-6 space-y-2'>";
    echo "<li>Com base no retorno real das APIs acima, ajustarei os <strong>normalizadores</strong> no <code>cron_apis.php</code> para mapear as chaves JSON corretas.</li>";
    echo "<li>Após ajuste, rode <code>php cron_apis.php</code> no terminal do servidor para testar a ingestão.</li>";
    echo "<li>Verifique a tabela <code>boletim_licitacoes</code> no phpMyAdmin para conferir os dados inseridos e o campo <code>link_edital</code> populado.</li>";
    echo "<li>Se houver registros sem <code>link_edital</code>, rode novamente o cron (backfill automático de até 50 registros por execução).</li>";
    echo "</ul>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='bg-red-100 border-l-4 border-red-500 p-4'>❌ ERRO: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
