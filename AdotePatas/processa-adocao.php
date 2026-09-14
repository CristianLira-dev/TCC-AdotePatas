<?php
session_start();

require_once __DIR__ . '/route-bootstrap.php';
include_once __DIR__ . '/conexao.php';

$base_path = ADOTE_PATAS_BASE_URL;

// 1. Segurança: Verifica se o usuário está logado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_tipo'])) {
    header('Location: ' . $base_path . 'login/');
    exit;
}

// 3. Pega os dados do usuário e do pet
$id_usuario_adotante = $_SESSION['user_id'];
$id_pet = filter_input(INPUT_POST, 'id_pet', FILTER_SANITIZE_NUMBER_INT);

// 4. Pega todas as respostas do formulário
$dados_formulario = [
    'tem_criancas' => htmlspecialchars($_POST['tem_criancas'] ?? ''),
    'todos_apoiam' => htmlspecialchars($_POST['todos_apoiam'] ?? ''),
    'tipo_moradia' => htmlspecialchars($_POST['tipo_moradia'] ?? ''),
    'pet_sera_presente' => htmlspecialchars($_POST['pet_sera_presente'] ?? ''),
    'presente_responsavel' => htmlspecialchars($_POST['presente_responsavel'] ?? null),
    'teve_pets' => htmlspecialchars($_POST['teve_pets'] ?? ''),
    'autoriza_visita' => htmlspecialchars($_POST['autoriza_visita'] ?? ''),
    'ciente_devolucao' => htmlspecialchars($_POST['ciente_devolucao'] ?? ''),
    'ciente_termo_responsabilidade' => htmlspecialchars($_POST['ciente_termo_responsabilidade'] ?? ''),
];

// 5. Validação básica
if (empty($id_pet) || empty($dados_formulario['tem_criancas']) || empty($dados_formulario['todos_apoiam'])) {
    $_SESSION['form_error'] = 'Erro: Parece que alguns campos obrigatórios não foram preenchidos.';
    header('Location: ' . $base_path . 'formulario-adocao/?id=' . urlencode((string) $id_pet));
    exit;
}

// 6. Checa se o usuário JÁ aplicou para este pet
try {
    $sql_check = "SELECT c.id_conversa
                  FROM solicitacao s
                  JOIN conversa c ON s.id_solicitacao = c.id_solicitacao_fk
                  WHERE s.id_usuario = :id_usuario AND s.id_pet = :id_pet
                  LIMIT 1";

    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([
        ':id_usuario' => $id_usuario_adotante,
        ':id_pet' => $id_pet
    ]);

    $conversa_existente = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($conversa_existente) {
        header('Location: ' . $base_path . 'chat/?id=' . urlencode((string) $conversa_existente['id_conversa']));
        exit;
    }
} catch (Exception $e) {
    error_log('Erro ao checar solicitação duplicada: ' . $e->getMessage());
    $_SESSION['form_error'] = 'Ops! Ocorreu um erro ao verificar sua solicitação. Tente novamente.';
    header('Location: ' . $base_path . 'formulario-adocao/?id=' . urlencode((string) $id_pet));
    exit;
}

// Inicia o processo de salvar no banco
try {
    // 6. Encontrar o protetor (dono) do pet E o nome do pet
    $sql_pet = "SELECT nome, id_usuario_fk, id_ong_fk FROM pet WHERE id_pet = :id_pet LIMIT 1";
    $stmt_pet = $conn->prepare($sql_pet);
    $stmt_pet->execute([':id_pet' => $id_pet]);
    $pet = $stmt_pet->fetch(PDO::FETCH_ASSOC);

    if (!$pet) {
        throw new Exception('Pet não encontrado.');
    }

    $nome_pet = $pet['nome'];
    $id_protetor_usuario = $pet['id_usuario_fk'];
    $id_protetor_ong = $pet['id_ong_fk'];

    $id_protetor_final = $id_protetor_usuario ?? $id_protetor_ong;
    $tipo_protetor_final = !empty($id_protetor_usuario) ? 'usuario' : 'ong';

    // 7. Inicia a Transação
    $conn->beginTransaction();

    // 8. Passo A: Inserir na tabela solicitacao
    $sql_solicitacao = "INSERT INTO solicitacao
                            (id_usuario, id_pet, id_protetor_usuario_fk, id_protetor_ong_fk, status_solicitacao)
                        VALUES
                            (:id_usuario, :id_pet, :id_protetor_usuario, :id_protetor_ong, 'pendente')";

    $stmt_solicitacao = $conn->prepare($sql_solicitacao);
    $stmt_solicitacao->execute([
        ':id_usuario' => $id_usuario_adotante,
        ':id_pet' => $id_pet,
        ':id_protetor_usuario' => $id_protetor_usuario,
        ':id_protetor_ong' => $id_protetor_ong
    ]);

    // 9. Passo B: Pegar o ID da solicitação criada
    $id_solicitacao_criada = $conn->lastInsertId();

    // 10. Passo C: Inserir as respostas na tabela formulario_adocao
    $sql_formulario = "INSERT INTO formulario_adocao
        (id_solicitacao_fk, id_usuario_fk, id_pet_fk, tem_criancas, todos_apoiam, tipo_moradia, pet_sera_presente, presente_responsavel, teve_pets, autoriza_visita, ciente_devolucao, ciente_termo_responsabilidade)
        VALUES
        (:id_solicitacao, :id_usuario, :id_pet, :tem_criancas, :todos_apoiam, :tipo_moradia, :pet_sera_presente, :presente_responsavel, :teve_pets, :autoriza_visita, :ciente_devolucao, :ciente_termo_responsabilidade)";

    $stmt_formulario = $conn->prepare($sql_formulario);
    $stmt_formulario->execute([
        ':id_solicitacao' => $id_solicitacao_criada,
        ':id_usuario' => $id_usuario_adotante,
        ':id_pet' => $id_pet,
        ':tem_criancas' => $dados_formulario['tem_criancas'],
        ':todos_apoiam' => $dados_formulario['todos_apoiam'],
        ':tipo_moradia' => $dados_formulario['tipo_moradia'],
        ':pet_sera_presente' => $dados_formulario['pet_sera_presente'],
        ':presente_responsavel' => $dados_formulario['presente_responsavel'],
        ':teve_pets' => $dados_formulario['teve_pets'],
        ':autoriza_visita' => $dados_formulario['autoriza_visita'],
        ':ciente_devolucao' => $dados_formulario['ciente_devolucao'],
        ':ciente_termo_responsabilidade' => $dados_formulario['ciente_termo_responsabilidade']
    ]);

    // 11. Passo D: Criar a conversa
    $sql_conversa = "INSERT INTO conversa
                        (id_solicitacao_fk, id_adotante_fk, id_protetor_fk, tipo_protetor)
                     VALUES
                        (:id_solicitacao, :id_adotante, :id_protetor, :tipo_protetor)";
    $stmt_conversa = $conn->prepare($sql_conversa);
    $stmt_conversa->execute([
        ':id_solicitacao' => $id_solicitacao_criada,
        ':id_adotante' => $id_usuario_adotante,
        ':id_protetor' => $id_protetor_final,
        ':tipo_protetor' => $tipo_protetor_final
    ]);

    // 12. Passo E: Pegar o ID da conversa criada
    $id_conversa_criada = $conn->lastInsertId();

    // 13. Passo F: Enviar a primeira mensagem automática
    $mensagem_inicial = 'Olá! Tenho interesse em adotar o(a) ' . htmlspecialchars($nome_pet) . '.';

    $sql_mensagem = "INSERT INTO mensagem
                        (id_conversa_fk, id_remetente_fk, tipo_remetente, conteudo)
                     VALUES
                        (:id_conversa, :id_remetente, :tipo_remetente, :conteudo)";
    $stmt_mensagem = $conn->prepare($sql_mensagem);
    $stmt_mensagem->execute([
        ':id_conversa' => $id_conversa_criada,
        ':id_remetente' => $id_usuario_adotante,
        ':tipo_remetente' => 'usuario',
        ':conteudo' => $mensagem_inicial
    ]);

    // 14. Confirma a transação
    $conn->commit();

    // 15. Redireciona para a conversa criada usando a rota por diretório
    header('Location: ' . $base_path . 'chat/?id=' . urlencode((string) $id_conversa_criada));
    exit;
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Erro ao processar adoção: ' . $e->getMessage());
    $_SESSION['form_error'] = 'Ops! Ocorreu um erro ao enviar sua solicitação. Por favor, tente novamente.';
    header('Location: ' . $base_path . 'formulario-adocao/?id=' . urlencode((string) $id_pet));
    exit;
}
