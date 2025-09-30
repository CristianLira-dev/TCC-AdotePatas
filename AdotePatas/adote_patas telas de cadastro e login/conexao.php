<?php
/**
 * conexao.php - Configuração da Conexão PDO com o Banco de Dados
 * * Configura as credenciais e tenta estabelecer uma conexão usando PDO,
 * que é crucial para segurança (Prepared Statements) e portabilidade.
 * Certifique-se de que o banco de dados 'adote_patas' exista.
 */

$servername = "localhost:3306";
$username = "root"; // Substitua pelo seu usuário
$password = ""; // Substitua pela sua senha
$dbname = "adote_patas";

try {
    // Cria a conexão PDO
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    
    // Define o modo de erro do PDO para lançar exceções
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Opcional: Define o fetch mode padrão
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch(PDOException $e){
    // Em um ambiente de produção, esta linha não deve ser exibida publicamente
    //echo "Falha na conexão: " . $e->getMessage();
    
    // Mensagem amigável em caso de falha de conexão
    die("<div style='text-align: center; padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px;'>
            <strong>Erro Crítico:</strong> Não foi possível conectar ao banco de dados.
            <br>Verifique as configurações em 'conexao.php'.
          </div>");
}

// O objeto $conn está disponível para uso nos outros arquivos.
?>
