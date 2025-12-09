<?php
// ==============================================
// ARQUIVO: functions.php
// Contém funções de ajuda globais para o sistema
// ==============================================

// Inicia a sessão se ainda não foi iniciada, para garantir acesso às variáveis de sessão
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica se o utilizador logado tem perfil de Administrador.
 * Utiliza a constante PERM_ADMIN definida em config.php.
 *
 * @return bool Retorna true se o utilizador for Admin, caso contrário, false.
 */
function isAdmin() {
    // Verifica se a variável de sessão 'user_perfil' existe e se o seu valor é 'Admin'
    return isset($_SESSION['user_perfil']) && $_SESSION['user_perfil'] === PERM_ADMIN;
}

/**
 * Formata uma string de CNPJ para o formato 00.000.000/0001-00.
 *
 * @param string $cnpj O CNPJ sem formatação.
 * @return string O CNPJ formatado ou o valor original se for inválido.
 */
function formatarCNPJ($cnpj) {
    // Remove qualquer caractere que não seja número
    $cnpj_limpo = preg_replace('/[^0-9]/', '', (string) $cnpj);

    // Se o CNPJ não tiver 14 dígitos, retorna o valor original
    if (strlen($cnpj_limpo) != 14) {
        return $cnpj;
    }

    // Aplica a máscara
    return vsprintf('%s%s.%s%s%s.%s%s%s/%s%s%s%s-%s%s', str_split($cnpj_limpo));
}

/**
 * Converte uma data/hora do fuso horário UTC para o fuso horário local (America/Sao_Paulo).
 *
 * @param string $utc_datetime_str A data/hora em formato de string vinda do banco de dados (UTC).
 * @param string $format O formato de saída desejado.
 * @return string A data/hora formatada no fuso horário local.
 */
function converterTimestampParaLocal($utc_datetime_str, $format = 'd/m/Y H:i') {
    if (empty($utc_datetime_str)) {
        return 'N/D';
    }
    try {
        // Cria um objeto DateTime com a data do banco, especificando que ela está em UTC
        $utc_date = new DateTime($utc_datetime_str, new DateTimeZone('UTC'));
        // Altera o fuso horário para o de São Paulo
        $utc_date->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        // Retorna a data formatada
        return $utc_date->format($format);
    } catch (Exception $e) {
        // Em caso de erro, retorna a data original para não quebrar a página
        return date($format, strtotime($utc_datetime_str));
    }
}

?>
