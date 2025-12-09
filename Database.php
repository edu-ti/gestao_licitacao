<?php
// ==============================================
// ARQUIVO: Database.php
// CLASSE DE CONEXÃO COM O BANCO DE DADOS (PDO)
// VERSÃO CORRIGIDA E ROBUSTA
// ==============================================

require_once 'config.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    private $pdo;
    
    public function __construct() {
        $this->host = DB_HOST;
        $this->db_name = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->charset = DB_CHARSET;
    }

    public function connect() {
        if ($this->pdo === null) {
            try {
                $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            } catch (PDOException $e) {
                // =================================================================
                // CORREÇÃO CRÍTICA: Em vez de die(), lança uma exceção.
                // Isso permite que o script que chamou a função trate o erro.
                // =================================================================
                error_log("Erro de conexão com banco de dados (classe Database): " . $e->getMessage());
                // Lança a exceção para ser capturada no auth_api.php
                throw new Exception("Não foi possível conectar ao banco de dados.");
            }
        }
        return $this->pdo;
    }
}
?>
