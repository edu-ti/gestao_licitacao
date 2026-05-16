<?php
require_once 'Database.php';

echo "=== TESTE DE CONEXÃO ===\n";
try {
    $db = new Database();
    $pdo = $db->connect();
    echo "Conectado ao banco: " . DB_NAME . "\n\n";
} catch (Exception $e) {
    die("ERRO conexão: " . $e->getMessage() . "\n");
}

echo "=== VERIFICAR TABELAS EXISTENTES ===\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Tabelas existentes: " . implode(', ', $tables) . "\n\n";

echo "=== VERIFICAR SE AS TABELAS DO BOLETIM JÁ EXISTEM ===\n";
$check = ['boletins', 'boletim_licitacoes', 'log_captacao'];
foreach ($check as $tbl) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$tbl'");
    echo "$tbl: " . ($stmt->rowCount() > 0 ? "EXISTE" : "NÃO EXISTE") . "\n";
}
echo "\n";

echo "=== EXECUTAR MIGRAÇÃO SQL ===\n";
$sql = file_get_contents('migracao_boletim_licitacoes.sql');
$statements = explode(';', $sql);
$ok = 0;
$fail = 0;
foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if (empty($stmt)) continue;
    try {
        $pdo->exec($stmt);
        $ok++;
    } catch (Exception $e) {
        echo "  ERRO: " . $e->getMessage() . "\n";
        $fail++;
    }
}
echo "Migração: $ok comandos OK, $fail erros\n\n";

echo "=== TESTAR APIs ===\n";

function testAPI($nome, $url, $headers = []) {
    echo "Testando $nome...\n";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);
    if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $resposta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    $tamanho = strlen($resposta);
    curl_close($ch);

    if ($erro) {
        echo "  ERRO cURL: $erro\n";
        return;
    }
    echo "  HTTP $httpCode, $tamanho bytes\n";
    if ($resposta) {
        $json = json_decode($resposta, true);
        if ($json !== null) {
            echo "  JSON válido. Chaves: " . implode(', ', array_keys($json)) . "\n";
            if (isset($json['data']) && is_array($json['data'])) {
                echo "  Total registros: " . count($json['data']) . "\n";
                if (count($json['data']) > 0) {
                    echo "  Primeiro item chaves: " . implode(', ', array_keys($json['data'][0])) . "\n";
                }
            }
        } else {
            echo "  (não é JSON, exibindo primeiros 200 chars)\n";
            echo "  " . substr($resposta, 0, 200) . "\n";
        }
    }
}

// PNCP
testAPI('PNCP', 'https://pncp.gov.br/api/consulta/v1/contratacoes/publicacao?dataInicio=' . date('Y-m-d') . '&dataFim=' . date('Y-m-d') . '&pagina=1&tamanhoPagina=5', ['accept: application/json']);

// Compras.gov.br
testAPI('Compras.gov.br', 'https://dadosabertos.compras.gov.br/modulo-editais/1_licitacoes?pagina=1&limite=5', ['accept: application/json']);

// Portal de Compras Públicas
testAPI('Portal Compras Públicas', 'https://www.portaldecompraspublicas.com.br/opcom/OrgaoPublico/ListaEditaisAjax?pagina=1&qtdRegistrosPorPagina=5', ['accept: application/json', 'X-Requested-With: XMLHttpRequest']);

echo "\n=== TABELAS CRIADAS ===\n";
foreach ($check as $tbl) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$tbl'");
    echo "$tbl: " . ($stmt->rowCount() > 0 ? "OK" : "FALHOU") . "\n";
    if ($stmt->rowCount() > 0) {
        $cols = $pdo->query("DESCRIBE `$tbl`")->fetchAll(PDO::FETCH_ASSOC);
        echo "  Colunas: " . implode(', ', array_column($cols, 'Field')) . "\n";
    }
}
