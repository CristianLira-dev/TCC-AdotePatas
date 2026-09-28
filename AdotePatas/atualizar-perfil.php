<?php
require_once __DIR__ . '/app/security.php';
appStartSession();
include_once 'conexao.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

// 1. Validações de Segurança Iniciais
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método de requisição inválido.';
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION['user_id'], $_SESSION['user_tipo'])) {
    $response['message'] = 'Sessão inválida. Faça login novamente.';
    echo json_encode($response);
    exit;
}

// 2. Coleta e Limpeza dos Dados
$user_id = $_SESSION['user_id'];
$user_tipo = $_SESSION['user_tipo'];
$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$documento = preg_replace('/[^0-9]/', '', trim($_POST['documento'] ?? ''));

// --- CAMPOS DE ENDEREÇO DO FORMULÁRIO ---
$cep = trim($_POST['cep'] ?? '');
$numero = trim($_POST['numero'] ?? '');
$complemento = trim($_POST['complemento'] ?? '');

// --- CAMPOS QUE SERÃO BUSCADOS PELA API ---
$logradouro = '';
$bairro = '';
$cidade = '';
$estado = '';

// --- Funções de Validação ---
function validarCPF($cpf) {
    if (empty($cpf) || strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    return true;
}

function validarCNPJ($cnpj) {
    if (empty($cnpj) || strlen($cnpj) != 14 || preg_match('/(\d)\1{13}/', $cnpj)) {
        return false;
    }
    
    // Valida DVs
    for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++) {
        $soma += $cnpj[$i] * $j;
        $j = ($j == 2) ? 9 : $j - 1;
    }
    $resto = $soma % 11;
    if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto)) {
        return false;
    }
    
    for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++) {
        $soma += $cnpj[$i] * $j;
        $j = ($j == 2) ? 9 : $j - 1;
    }
    $resto = $soma % 11;
    return $cnpj[13] == ($resto < 2 ? 0 : 11 - $resto);
}

function buscarViaCEP($cep) {
    $cep_limpo = preg_replace('/[^0-9]/', '', $cep);
    if (strlen($cep_limpo) !== 8) {
        return null;
    }

    $url = "https://viacep.com.br/ws/{$cep_limpo}/json/";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout de 5 segundos
    
    $result_json = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || $result_json === false) {
        return null; // Falha na API
    }

    $result_data = json_decode($result_json, true);

    if (isset($result_data['erro']) && $result_data['erro'] === true) {
        return null; // CEP não encontrado
    }

    return $result_data;
}

// --- 3. Execução da Validação Back-end ---
$erros = [];

// Valida campos obrigatórios
if (empty($nome)) {
    $erros[] = "O campo nome é obrigatório.";
}
if (empty($email)) {
    $erros[] = "O campo e-mail é obrigatório.";
}
if (empty($documento)) {
    $erros[] = "O campo documento é obrigatório.";
}
if (empty($cep)) {
    $erros[] = "O campo CEP é obrigatório.";
}
if (empty($numero)) {
    $erros[] = "O campo número é obrigatório.";
}

// Se não há erros de campos obrigatórios, valida o formato
if (empty($erros)) {
    if ($user_tipo == 'usuario') {
        if (strpos($nome, ' ') === false) {
            $erros[] = "Por favor, digite seu nome completo.";
        }
    }
    // Protetor pode ter nome com uma única palavra - não faz validação adicional
    
    // Valida email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = "O formato do e-mail é inválido.";
    }
    
    // Valida documento (CPF ou CNPJ)
    if ($user_tipo == 'usuario') {
        if (strlen($documento) !== 11) {
            $erros[] = "CPF deve conter 11 dígitos.";
        } elseif (!validarCPF($documento)) {
            $erros[] = "O CPF informado é inválido.";
        }
    } elseif ($user_tipo == 'ong') {
        if (strlen($documento) !== 14) {
            $erros[] = "CNPJ deve conter 14 dígitos.";
        } elseif (!validarCNPJ($documento)) {
            $erros[] = "O CNPJ informado é inválido.";
        }
    }
}


// BUSCA VIACEP (NOVO)
if (empty($erros)) {
    $dados_cep = buscarViaCEP($cep);
    
    if ($dados_cep === null) {
        $erros[] = "CEP inválido ou não encontrado. Verifique o CEP digitado.";
    } else {
        // Popula as variáveis com os dados da API
        $logradouro = $dados_cep['logradouro'] ?? '';
        $bairro = $dados_cep['bairro'] ?? '';
        $cidade = $dados_cep['localidade'] ?? '';
        $estado = $dados_cep['uf'] ?? '';

        // Validação extra: Se a API não retornar um campo essencial (exceto bairro)
        if (empty($logradouro) || empty($cidade) || empty($estado)) {
             $erros[] = "CEP incompleto. A API não retornou todos os dados. (Ex: CEP geral da cidade)";
        }
    }
}

// 4. Verificação de Duplicidade
if (empty($erros)) {
    try {
        if ($user_tipo == 'usuario') {
            // Verifica email
            $sql = "SELECT id_usuario FROM usuario WHERE email = :email AND id_usuario != :id LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':email' => $email, ':id' => $user_id]);
            if ($stmt->fetch()) {
                $erros[] = "Este e-mail já está sendo usado por outra conta.";
            }
            
            // Verifica CPF
            $sql = "SELECT id_usuario FROM usuario WHERE cpf = :cpf AND id_usuario != :id LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':cpf' => $documento, ':id' => $user_id]);
            if ($stmt->fetch()) {
                $erros[] = "Este CPF já está sendo usado por outra conta.";
            }
        } elseif ($user_tipo == 'ong') {
            // Verifica email
            $sql = "SELECT id_ong FROM ong WHERE email = :email AND id_ong != :id LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':email' => $email, ':id' => $user_id]);
            if ($stmt->fetch()) {
                $erros[] = "Este e-mail já está sendo usado por outra conta.";
            }
            
            // Verifica CNPJ
            $sql = "SELECT id_ong FROM ong WHERE cnpj = :cnpj AND id_ong != :id LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':cnpj' => $documento, ':id' => $user_id]);
            if ($stmt->fetch()) {
                $erros[] = "Este CNPJ já está sendo usado por outra conta.";
            }
        }
    } catch (PDOException $e) {
        error_log("Erro ao verificar duplicidade: " . $e->getMessage());
        $erros[] = "Erro ao verificar dados. Tente novamente.";
    }
}

// 5. Se houver erros, retorna a primeira mensagem de erro
if (!empty($erros)) {
    $response['message'] = $erros[0];
    echo json_encode($response);
    exit;
}

// 6. ATUALIZAÇÃO NO BANCO (SQL é o mesmo da etapa anterior)
try {
    $params = [
        ':nome' => $nome,
        ':email' => $email,
        ':id' => $user_id,
        ':cep' => $cep,
        ':logradouro' => $logradouro,
        ':numero' => $numero,
        ':complemento' => $complemento,
        ':bairro' => $bairro,
        ':cidade' => $cidade,
        ':estado' => $estado
    ];

    if ($user_tipo == 'usuario') {
        $sql = "UPDATE usuario SET 
                    nome = :nome, email = :email, cpf = :cpf,
                    cep = :cep, logradouro = :logradouro, numero = :numero, 
                    complemento = :complemento, bairro = :bairro, 
                    cidade = :cidade, estado = :estado
                WHERE id_usuario = :id";
        $params[':cpf'] = $documento;
        
    } elseif ($user_tipo == 'ong') {
        $sql = "UPDATE ong SET 
                    nome = :nome, email = :email, cnpj = :cnpj,
                    cep = :cep, logradouro = :logradouro, numero = :numero, 
                    complemento = :complemento, bairro = :bairro, 
                    cidade = :cidade, estado = :estado
                WHERE id_ong = :id";
        $params[':cnpj'] = $documento;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    appAudit($conn, 'update', 'profile', (int) $user_id, ['account_type' => $user_tipo]);

    if ($stmt->rowCount() > 0) {
         $_SESSION['nome'] = $nome;
         $response['success'] = true;
         $response['message'] = 'Perfil atualizado com sucesso!';
    } else {
         $response['success'] = true;
         $response['message'] = 'Nenhuma alteração detectada.';
    }

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Erro no banco de dados ao atualizar perfil: " . $e->getMessage());
    // Verifica se é erro de chave duplicada (código 23000)
    if ($e->getCode() == 23000) {
         if (strpos($e->getMessage(), 'cpf') !== false) {
              $response['message'] = 'Erro: Este CPF já está em uso por outra conta.';
         } elseif (strpos($e->getMessage(), 'email') !== false) {
               $response['message'] = 'Erro: Este E-mail já está em uso por outra conta.';
         } elseif (strpos($e->getMessage(), 'cnpj') !== false) {
             $response['message'] = 'Erro: Este CNPJ já está em uso por outra conta.';
         } else {
              $response['message'] = 'Erro ao salvar: Violação de chave única.';
         }
    } else {
        $response['message'] = 'Erro no banco de dados ao salvar. Tente novamente.';
    }
    echo json_encode($response);
}
