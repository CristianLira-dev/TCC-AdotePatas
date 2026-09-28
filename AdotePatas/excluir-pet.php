<?php
// 1. Iniciar a sessão e carregar a conexão
include_once 'conexao.php';
include_once 'session.php';

// 2. Garantir que o usuário está logado
requerer_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

// 3. Pegar IDs da sessão e da URL
$user_id = $_SESSION['user_id'];
$user_tipo = $_SESSION['user_tipo'];
$id_pet_para_excluir = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (empty($id_pet_para_excluir)) {
    $_SESSION['mensagem_status'] = "ID do pet não fornecido.";
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: perfil.php?page=meus-pets');
    exit;
}

try {
    // 4. VERIFICAÇÃO DE PROPRIEDADE
    // (Não precisamos mais da foto aqui, só dos donos)
    $sql_check = "SELECT id_usuario_fk, id_ong_fk FROM pet WHERE id_pet = :id_pet";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([':id_pet' => $id_pet_para_excluir]);
    $pet = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$pet) {
        throw new Exception("Pet não encontrado.");
    }

    $tem_permissao = false;
    if ($user_tipo == 'usuario' && $pet['id_usuario_fk'] == $user_id) $tem_permissao = true;
    elseif ($user_tipo == 'ong' && $pet['id_ong_fk'] == $user_id) $tem_permissao = true;

    if (!$tem_permissao) {
        throw new Exception("Você não tem permissão para excluir este pet.");
    }

    // 5. BUSCAR TODAS AS FOTOS ANTES DE EXCLUIR
    $sql_fotos = "SELECT caminho_foto FROM pet_fotos WHERE id_pet_fk = :id_pet";
    $stmt_fotos = $conn->prepare($sql_fotos);
    $stmt_fotos->execute([':id_pet' => $id_pet_para_excluir]);
    $fotos_para_apagar = $stmt_fotos->fetchAll(PDO::FETCH_COLUMN, 0); // Pega só a coluna 'caminho_foto'

    // 6. DELETAR O PET DO BANCO
    // Graças ao "ON DELETE CASCADE" que definimos no SQL,
    // o banco vai apagar automaticamente os registros da 'pet_fotos'.
    $sql_delete = "DELETE FROM pet WHERE id_pet = :id_pet";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->execute([':id_pet' => $id_pet_para_excluir]);
    appAudit($conn, 'delete', 'pet', (int) $id_pet_para_excluir, ['owner_type' => $user_tipo]);

    // 8. APAGAR OS ARQUIVOS FÍSICOS DO SERVIDOR (SÓ APÓS O COMMIT)
    foreach ($fotos_para_apagar as $caminho) {
        // $caminho é um caminho relativo, ex: 'uploads/pets/foto.jpg'
        // Criamos um caminho absoluto baseado no local deste script (__DIR__)
        // __DIR__ é o diretório do arquivo atual (ex: C:\xampp\htdocs\seu_projeto)
        $caminho_absoluto = __DIR__ . DIRECTORY_SEPARATOR . $caminho;
        
        // Verificamos e apagamos usando o caminho absoluto
        if (!empty($caminho) && file_exists($caminho_absoluto)) {
            @unlink($caminho_absoluto);
        }
    }

 // ... código existente ...

// 8. Redireciona de volta com sucesso (Padrão PRG)
$_SESSION['toast_message'] = "Pet excluído com sucesso!";
$_SESSION['toast_type'] = 'success';
header('Location: perfil.php?page=meus-pets');
exit;

// ... código existente ...

} catch (Exception $e) {
    // 9. Se der qualquer erro, redireciona com a mensagem de falha
    error_log('Erro ao excluir pet: ' . $e->getMessage());
    $_SESSION['toast_message'] = 'Não foi possível excluir o pet.';
    $_SESSION['toast_type'] = 'danger';
    header('Location: perfil.php?page=meus-pets');
    exit;
}
?>
