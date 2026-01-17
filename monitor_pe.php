<?php
// ==============================================
// ARQUIVO: monitor_pe.php
// PROTÓTIPO DE MONITORAMENTO DO PE INTEGRADO
// ==============================================

require_once 'config.php';
require_once 'Database.php';

// Aumenta tempo de execução
set_time_limit(300);

class MonitorPE
{
    private $historyFile = 'monitor_history.json'; // Apenas hashes (controle)
    private $logFile = 'monitor_logs.json';       // Histórico visível (dashboard)
    private $monitorName = 'PE_INTEGRADO_TESTE';
    private $seenHashes = [];

    public function __construct()
    {
        // Carrega histórico do arquivo JSON
        if (file_exists($this->historyFile)) {
            $this->seenHashes = json_decode(file_get_contents($this->historyFile), true) ?? [];
        }
    }

    public function run()
    {
        echo "--> Iniciando Monitoramento: " . date('Y-m-d H:i:s') . "\n";
        echo "--> Modo: ARQUIVO LOCAL (JSON) - Sem dependência de Banco de Dados\n";

        // 1. Simular Busca de Conteúdo (Mock)
        $html = $this->fetchMockContent();

        // 2. Extrair Mensagens
        $messages = $this->parseMessages($html);
        echo "--> Encontradas " . count($messages) . " mensagens no total.\n";

        // 3. Processar cada mensagem
        $newCount = 0;
        foreach ($messages as $msg) {
            if ($this->processMessage($msg)) {
                $newCount++;
            }
        }

        // Salva o histórico atualizado
        file_put_contents($this->historyFile, json_encode($this->seenHashes, JSON_PRETTY_PRINT));

        echo "--> Fim da execução. Novas mensagens relevantes: $newCount\n";
    }

    private function processMessage($msg)
    {
        // Gera Hash Único da mensagem
        $hash = md5($msg['data'] . $msg['remetente'] . $msg['texto']);

        // Verifica se já vimos essa mensagem no histórico local
        if (in_array($hash, $this->seenHashes)) {
            return false; // Já processada
        }

        // Verifica Palavras-Chave
        $isRelevant = false;
        $matchedKeyword = '';

        // DEBUG TEXT
        // echo "   [DEBUG CHECK] " . substr($msg['texto'], 0, 50) . "...\n";

        foreach (MONITOR_KEYWORDS as $keyword) {
            if (mb_stripos($msg['texto'], $keyword) !== false) {
                $isRelevant = true;
                $matchedKeyword = $keyword;
                break;
            }
        }

        // Se for relevante, notificamos
        if ($isRelevant) {
            echo "   [!] ALERTA: Mensagem Relevante encontrada! (Gatilho: '$matchedKeyword')\n";
            echo "       Texto: " . substr($msg['texto'], 0, 100) . "...\n";
            echo "       -> (Simulação: E-mail enviado para suporte@frpe.app.br)\n";

            $this->logActivity($msg, true, $matchedKeyword);

            // Marca como vista para não alertar novamente
            $this->seenHashes[] = $hash;
            return true;
        }

        // Se não é relevante, também marcamos como vista para o "Radar" não ficar lendo ela todo dia
        // (A menos que você queira reanalisar mensagens antigas se mudar as keywords)
        $this->logActivity($msg, false); // Loga como "checado"
        $this->seenHashes[] = $hash;

        return false;
    }

    private function logActivity($msg, $isAlert, $keyword = null)
    {
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'remetente' => $msg['remetente'],
            'data_mensagem' => $msg['data'],
            'texto' => $msg['texto'],
            'is_alert' => $isAlert,
            'keyword' => $keyword
        ];

        $currentLogs = [];
        if (file_exists($this->logFile)) {
            $currentLogs = json_decode(file_get_contents($this->logFile), true) ?? [];
        }

        // Adiciona no início
        array_unshift($currentLogs, $entry);

        // Mantém apenas os últimos 100 logs para não explodir o arquivo
        if (count($currentLogs) > 100) {
            $currentLogs = array_slice($currentLogs, 0, 100);
        }

        file_put_contents($this->logFile, json_encode($currentLogs, JSON_PRETTY_PRINT));
    }

    private function parseMessages($html)
    {
        // Parser adaptado para o formato visual do PE Integrado:
        // Ex: "Coordenador (14/01/2026 10:15) Texto da mensagem..."
        // O regex busca "QualquerTexto (dd/mm/aaaa HH:MM) RestoDoTexto"

        $messages = [];

        // Remove quebras de linha extras para facilitar o regex
        $cleanHtml = str_replace(["\r", "\n"], ' ', $html);
        $cleanHtml = strip_tags($cleanHtml); // Remove tags HTML, deixando só o texto puro

        // Regex para capturar: [Autor] ([Data]) [Texto]
        // Procura por padrões de data "dd/mm/aaaa HH:MM"
        $pattern = '/(.*?)\((\d{2}\/\d{2}\/\d{4} \d{2}:\d{2})\)\s+(.*)/';

        // Como removemos as tags, o texto ficou "linguiça". Vamos tentar separar por blocos.
        // Melhor abordagem com DOMDocument para não misturar mensagens

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html); // Hack para UTF-8
        $xpath = new DOMXPath($dom);

        // No print, parece que cada mensagem é um bloco de texto. 
        // Vamos pegar todos os textos e tentar aplicar o regex em cada nó de texto ou div.
        //$nodes = $xpath->query('//div | //span | //td | //p'); 

        // ABORDAGEM OFFSSET-BASED (Muito mais robusta para multilinhas)
        $fullText = $dom->textContent;

        // Regex para encontrar TODOS os cabeçalhos: "Autor (Data)"
        // Captura offsets para recortar o texto entre eles
        $pattern = '/([^\n\r]+?)\s*\((\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2})\)/';

        preg_match_all($pattern, $fullText, $matches, PREG_OFFSET_CAPTURE);

        if (!empty($matches[0])) {
            $count = count($matches[0]);

            for ($i = 0; $i < $count; $i++) {
                $remetente = trim($matches[1][$i][0]);
                $data = $matches[2][$i][0];

                // Onde termina este cabeçalho (começa o texto)
                $startPos = $matches[0][$i][1] + strlen($matches[0][$i][0]);

                // Onde começa o próximo cabeçalho (termina o texto)
                if ($i < $count - 1) {
                    $endPos = $matches[0][$i + 1][1];
                } else {
                    $endPos = strlen($fullText);
                }

                $length = $endPos - $startPos;
                $textoRaw = substr($fullText, $startPos, $length);
                $texto = trim($textoRaw);

                // Limpeza extra de espaços duplicados
                $texto = preg_replace('/\s+/', ' ', $texto);

                if (!empty($texto)) {
                    $hash = md5($data . $remetente . $texto);
                    $messages[$hash] = [
                        'data' => $data,
                        'remetente' => $remetente,
                        'texto' => $texto
                    ];
                }
            }
        }

        return array_values($messages);
    }

    private function fetchMockContent()
    {
        // MODO REAL/TESTE: Lê o arquivo local que criamos baseados nos Prints
        if (file_exists('pe_chat_sample.html')) {
            return file_get_contents('pe_chat_sample.html');
        }

        return [];
    }
}

// Execução
$monitor = new MonitorPE();
$monitor->run();
?>