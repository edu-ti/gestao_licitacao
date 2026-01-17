<?php
// ==============================================
// ARQUIVO: monitor_pe.php
// PROTÓTIPO DE MONITORAMENTO DO PE INTEGRADO
// ==============================================

require_once 'config.php'; // Ensure this file exists or remove if not needed for standalone test
require_once 'Database.php'; // Ensure this file exists or remove if not needed for standalone test

// Aumenta tempo de execução
set_time_limit(300);

class MonitorPE
{
    private $historyFile = 'monitor_history.json'; // Apenas hashes (controle)
    private $logFile = 'monitor_logs.json';       // Histórico visível (dashboard)
    private $configFile = 'monitor_config.json';
    private $monitorName = 'PE_INTEGRADO_TESTE';
    private $seenHashes = [];
    private $config = [];

    public function __construct()
    {
        // 1. Carrega histórico
        if (file_exists($this->historyFile)) {
            $this->seenHashes = json_decode(file_get_contents($this->historyFile), true) ?? [];
        }

        // 2. Carrega Configurações
        $this->loadConfig();
    }

    private function loadConfig()
    {
        $defaultConfig = [
            'keywords' => [
                ['term' => 'iminência', 'active' => true],
                ['term' => 'recurso', 'active' => true]
            ],
            'auto_delete_days' => 0,
            'alerts' => ['keywords' => true]
        ];

        if (file_exists($this->configFile)) {
            $this->config = json_decode(file_get_contents($this->configFile), true) ?? $defaultConfig;
        } else {
            $this->config = $defaultConfig;
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

        // 4. Salva o histórico atualizado
        file_put_contents($this->historyFile, json_encode($this->seenHashes, JSON_PRETTY_PRINT));

        // 5. Limpeza Automática
        $this->autoCleanup();

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

        // Verifica Palavras-Chave (Dinâmico da Config)
        $isRelevant = false;
        $matchedKeyword = '';

        // Filtra apenas ativas
        $activeKeywords = array_filter($this->config['keywords'] ?? [], function ($k) {
            return !empty($k['active']) && $k['active'] == true;
        });

        foreach ($activeKeywords as $kwConfig) {
            $keyword = $kwConfig['term'];
            if (mb_stripos($msg['texto'], $keyword) !== false) {
                $isRelevant = true;
                $matchedKeyword = $keyword;
                break;
            }
        }

        // Se alerta de palavras-chave estiver DESATIVADO na config, podemos decidir não alertar
        if ($isRelevant && empty($this->config['alerts']['keywords'])) {
            // Opcional: Se 'alert_keywords' estiver false, talvez não devêssemos gerar ALERTA, apenas logar.
            // Mas para simplicidade, vamos manter is_alert true mas o frontend decide se toca som.
            // Ou podemos setar is_alert = false aqui.
            // Decisão: Vamos manter true para visualização, mas o som depende do front.
            // Alternativa: Se desativado, nem considera relevante? Não, melhor capturar.
        }

        // Se for relevante, notificamos
        if ($isRelevant) {
            echo "   [!] ALERTA: Mensagem Relevante encontrada! (Gatilho: '$matchedKeyword')\n";
            echo "       Texto: " . substr($msg['texto'], 0, 100) . "...\n";

            $this->logActivity($msg, true, $matchedKeyword);

            // Marca como vista
            $this->seenHashes[] = $hash;
            return true;
        }

        // Se não é relevante, também marcamos como vista
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

        // Mantém apenas os últimos 100-200 logs
        if (count($currentLogs) > 200) {
            $currentLogs = array_slice($currentLogs, 0, 200);
        }

        file_put_contents($this->logFile, json_encode($currentLogs, JSON_PRETTY_PRINT));
    }

    private function autoCleanup()
    {
        $days = intval($this->config['auto_delete_days'] ?? 0);
        if ($days <= 0)
            return; // Se 0, "Nunca" excluir

        if (file_exists($this->logFile)) {
            $logs = json_decode(file_get_contents($this->logFile), true) ?? [];
            $initialCount = count($logs);

            $cutoff = strtotime("-$days days");

            // Filtra logs mais novos que o cutoff
            $logs = array_filter($logs, function ($log) use ($cutoff) {
                // Tenta parsear a data do log. Formato timestamp: Y-m-d H:i:s
                $logTime = strtotime($log['timestamp']);
                return $logTime >= $cutoff;
            });

            // Se houve remoção, salva
            if (count($logs) < $initialCount) {
                file_put_contents($this->logFile, json_encode(array_values($logs), JSON_PRETTY_PRINT));
                echo "--> Limpeza automática: " . ($initialCount - count($logs)) . " logs antigos removidos (> $days dias).\n";
            }
        }
    }

    private function parseMessages($html)
    {
        $messages = [];
        $cleanHtml = str_replace(["\r", "\n"], ' ', $html);
        $cleanHtml = strip_tags($cleanHtml);

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $fullText = $dom->textContent;

        $pattern = '/([^\n\r]+?)\s*\((\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2})\)/';

        preg_match_all($pattern, $fullText, $matches, PREG_OFFSET_CAPTURE);

        if (!empty($matches[0])) {
            $count = count($matches[0]);

            for ($i = 0; $i < $count; $i++) {
                $remetente = trim($matches[1][$i][0]);
                $data = $matches[2][$i][0];

                $startPos = $matches[0][$i][1] + strlen($matches[0][$i][0]);

                if ($i < $count - 1) {
                    $endPos = $matches[0][$i + 1][1];
                } else {
                    $endPos = strlen($fullText);
                }

                $length = $endPos - $startPos;
                $textoRaw = substr($fullText, $startPos, $length);
                $texto = trim($textoRaw);
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
        if (file_exists('pe_chat_sample.html')) {
            return file_get_contents('pe_chat_sample.html');
        }
        return "";
    }
}

// Execução
// Se chamado via CLI ou include
if (php_sapi_name() === 'cli' || basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $monitor = new MonitorPE();
    $monitor->run();
}
?>