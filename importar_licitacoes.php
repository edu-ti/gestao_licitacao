<?php
// ARQUIVO: importar_licitacoes.php
// Rode este arquivo via Cron Job (ex: todo dia às 02:00 da manhã)

require_once 'config.php';
require_once 'Database.php';

// Configurações para não dar timeout
ini_set('max_execution_time', 300);
date_default_timezone_set('America/Sao_Paulo');

$db = new Database();
$conn = $db->getConnection();

echo "Iniciando captura de licitações...\n";

// 1. EXTRAÇÃO DO PNCP (API REST)
function extrair_pncp($conn, $dataConsulta)
{
    echo "Buscando no PNCP...\n";

    // O PNCP exige formato YYYYMMDD
    $dataFormatada = date('Ymd', strtotime($dataConsulta));

    // Endpoint oficial de busca do PNCP (exemplo de busca de editais do dia)
    $url = "https://pncp.gov.br/api/consulta/v1/contratacoes/publicacao?dataInicial={$dataFormatada}&dataFinal={$dataFormatada}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // O PNCP pode exigir User-Agent
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) GestaoLicitacaoBot/1.0');

    $response = curl_exec($ch);
    curl_close($ch);

    $dados = json_decode($response, true);

    if (isset($dados['data']) && is_array($dados['data'])) {
        foreach ($dados['data'] as $item) {
            // Normaliza os dados para o seu banco
            $orgao = $item['orgaoEntidade']['razaoSocial'] ?? 'Órgão não informado';
            $objeto = $item['objetoCompra'] ?? '';
            $edital = $item['numeroCompra'] ?? '';
            $estado = $item['unidadeOrgao']['ufSigla'] ?? '';
            $modalidade = $item['modalidadeNome'] ?? '';
            $link = $item['linkSistemaOrigem'] ?? '';
            $dataAbertura = isset($item['dataAberturaProposta']) ? date('Y-m-d H:i:s', strtotime($item['dataAberturaProposta'])) : null;

            salvar_no_banco($conn, 'PNCP', $orgao, $objeto, $edital, $estado, $modalidade, $dataAbertura, $link);
        }
        echo "PNCP: " . count($dados['data']) . " registros processados.\n";
    }
}

// 2. EXTRAÇÃO DO COMPRAS.GOV.BR (API REST)
function extrair_compras_gov($conn, $dataConsulta)
{
    echo "Buscando no Compras.gov.br...\n";

    $dataFiltro = date('Y-m-d', strtotime($dataConsulta));
    // Endpoint de Editais da API de Dados Abertos
    $url = "https://dadosabertos.compras.gov.br/modulo-editais/1_licitacoes?data_publicacao={$dataFiltro}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    $dados = json_decode($response, true);

    if (isset($dados['resultado']) && is_array($dados['resultado'])) {
        foreach ($dados['resultado'] as $item) {
            $orgao = $item['nome_uasg'] ?? 'Órgão Federal';
            $objeto = $item['objeto'] ?? '';
            $edital = $item['numero_licitacao'] ?? '';
            $estado = $item['uf_uasg'] ?? '';
            $modalidade = 'Pregão/Concorrência (Federal)';
            $link = "https://comprasnet.gov.br/";
            $dataAbertura = isset($item['data_abertura']) ? date('Y-m-d H:i:s', strtotime($item['data_abertura'])) : null;

            salvar_no_banco($conn, 'Compras.gov', $orgao, $objeto, $edital, $estado, $modalidade, $dataAbertura, $link);
        }
        echo "Compras.gov: " . count($dados['resultado']) . " registros processados.\n";
    }
}

// 3. ESTRUTURA PARA OS PORTAIS PRIVADOS (WEB SCRAPING)
function extrair_bnc($conn)
{
    echo "Buscando no BNC (Web Scraping)...\n";
    // Aqui você usará file_get_contents() ou a biblioteca Guzzle+DOMCrawler
    // para acessar a URL de licitações públicas do BNC e extrair o HTML.
    // ...
}

function extrair_pe_integrado($conn)
{
    // Para PE Integrado, recomendo chamar um script Python via shell_exec()
    // shell_exec("python3 scrapers/pe_integrado.py");
}

// FUNÇÃO PARA INSERIR NO BANCO MANTENDO PADRÃO
function salvar_no_banco($conn, $origem, $orgao, $objeto, $edital, $estado, $modalidade, $dataAbertura, $link)
{
    // Evita duplicidade validando Órgão + Edital + Origem
    $check = $conn->prepare("SELECT id FROM boletim_licitacoes WHERE orgao = ? AND edital = ? AND portal_origem = ?");
    $check->execute([$orgao, $edital, $origem]);

    if ($check->rowCount() == 0) {
        $stmt = $conn->prepare("INSERT INTO boletim_licitacoes 
            (boletim_id, orgao, objeto, edital, estado, modalidade, data_abertura, link_detalhes, portal_origem, status_badge) 
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, 'NOVA')"); // boletim_id 1 = Boletim Dinâmico do Dia

        $stmt->execute([
            substr($orgao, 0, 250),
            $objeto,
            substr($edital, 0, 100),
            substr($estado, 0, 2),
            substr($modalidade, 0, 100),
            $dataAbertura,
            substr($link, 0, 500),
            $origem
        ]);
    }
}

// EXECUÇÃO
$dataDeHoje = date('Y-m-d');
extrair_pncp($conn, $dataDeHoje);
extrair_compras_gov($conn, $dataDeHoje);
// extrair_bnc($conn); // Ative quando criar a função com DOMCrawler

echo "Processo finalizado com sucesso!\n";
?>