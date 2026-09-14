<?php
session_start();

require_once __DIR__ . '/route-bootstrap.php';
include_once __DIR__ . '/conexao.php';

$base_path = ADOTE_PATAS_BASE_URL;

function redirectToAdoptionForm(string $basePath, $petId): void
{
    header('Location: ' . $basePath . 'formulario-adocao/?id=' . urlencode((string) $petId));
    exit;
}

function redirectToChat(string $basePath, $conversationId): void
{
    header('Location: ' . $basePath . 'chat/?id=' . urlencode((string) $conversationId));
    exit;
}

// Apenas adotantes autenticados podem enviar uma solicitação de adoção.
if (!isset($_SESSION['user_id'], $_SESSION['user_tipo'])) {
    header('Location: ' . $base_path . 'login/');
    exit;
}

if ($_SESSION['user_tipo'] !== 'usuario') {
    $_SESSION['form_error'] = 'Apenas contas de adotante podem enviar solicitações de adoção.';
    header('Location: ' . $base_path . 'pets/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $base_path . 'pets/');
    exit;
}

$id_usuario_adotante = (int) $_SESSION['user_id'];
$id_pet = filter_input(INPUT_POST, 'id_pet', FILTER_VALIDATE_INT);

$dados_formulario = [
    'tem_criancas' => trim((string) ($_POST['tem_criancas'] ?? '')),
    'todos_apoiam' => trim((string) ($_POST['todos_apoiam'] ?? '')),
    'tipo_moradia' => trim((string) ($_POST['tipo_moradia'] ?? '')),
    'pet_sera_presente' => trim((string) ($_POST['pet_sera_presente'] ?? '')),
    'presente_responsavel' => trim((string) ($_POST['presente_responsavel'] ?? '')),
    'teve_pets' => trim((string) ($_POST['teve_pets'] ?? '')),
    'autoriza_visita' => trim((string) ($_POST['autoriza_visita'] ?? '')),
    'ciente_devolucao' => trim((string) ($_POST['ciente_devolucao'] ?? '')),
    'ciente_termo_responsabilidade' => trim((string) ($_POST['ciente_termo_responsabilidade'] ?? '')),
];

if (!$id_pet || $dados_formulario['tem_criancas'] === '' || $dados_formulario['todos_apoiam'] === '') {
    $_SESSION['form_error'] = 'Parece que alguns campos obrigatórios não foram preenchidos.';
    redirectToAdoptionForm($base_path, $id_pet ?: '');
}

try {
    // Descobre o responsável real pelo pet antes de criar a solicitação/conversa.
    $stmt_pet = $conn->prepare(
        "SELECT id_pet, nome, id_usuario_fk, id_ong_fk
         FROM pet
         WHERE id_pet = :id_pet
         LIMIT 1"
    );
    $stmt_pet->execute([':id_pet' => $id_pet]);
    $pet = $stmt_pet->fetch(PDO::FETCH_ASSOC);

    if (!$pet) {
        $_SESSION['form_error'] = 'O pet informado não foi encontrado.';
        header('Location: ' . $base_path . 'pets/');
        exit;
    }

    $id_protetor_usuario = !empty($pet['id_usuario_fk']) ? (int) $pet['id_usuario_fk'] : null;
    $id_protetor_ong = !empty($pet['id_ong_fk']) ? (int) $pet['id_ong_fk'] : null;

    // O pet deve pertencer exatamente a um responsável válido.
    if ($id_protetor_usuario !== null) {
        $id_protetor_final = $id_protetor_usuario;
        $tipo_protetor_final = 'usuario';
    } elseif ($id_protetor_ong !== null) {
        $id_protetor_final = $id_protetor_ong;
        $tipo_protetor_final = 'ong';
    } else {
        throw new RuntimeException('Pet sem responsável vinculado.');
    }

    // Um usuário não pode iniciar um processo de adoção do próprio pet.
    if ($tipo_protetor_final === 'usuario' && $id_protetor_final === $id_usuario_adotante) {
        $_SESSION['form_error'] = 'Você não pode enviar uma solicitação de adoção para o seu próprio pet.';
        redirectToAdoptionForm($base_path, $id_pet);
    }

    // Procura uma solicitação anterior, inclusive as que ficaram sem conversa.
    $stmt_existente = $conn->prepare(
        "SELECT s.id_solicitacao, c.id_conversa
         FROM solicitacao s
         LEFT JOIN conversa c ON c.id_solicitacao_fk = s.id_solicitacao
         WHERE s.id_usuario = :id_usuario
           AND s.id_pet = :id_pet
         ORDER BY s.id_solicitacao DESC
         LIMIT 1"
    );
    $stmt_existente->execute([
        ':id_usuario' => $id_usuario_adotante,
        ':id_pet' => $id_pet,
    ]);
    $solicitacao_existente = $stmt_existente->fetch(PDO::FETCH_ASSOC);

    if ($solicitacao_existente && !empty($solicitacao_existente['id_conversa'])) {
        redirectToChat($base_path, $solicitacao_existente['id_conversa']);
    }

    // Se já existe solicitação mas faltou a conversa, repara o fluxo sem duplicar a solicitação.
    if ($solicitacao_existente) {
        $conn->beginTransaction();

        $stmt_conversa = $conn->prepare(
            "INSERT INTO conversa
                (id_solicitacao_fk, id_adotante_fk, id_protetor_fk, tipo_protetor)
             VALUES
                (:id_solicitacao, :id_adotante, :id_protetor, :tipo_protetor)"
        );
        $stmt_conversa->execute([
            ':id_solicitacao' => $solicitacao_existente['id_solicitacao'],
            ':id_adotante' => $id_usuario_adotante,
            ':id_protetor' => $id_protetor_final,
            ':tipo_protetor' => $tipo_protetor_final,
        ]);

        $id_conversa = $conn->lastInsertId();

        $stmt_mensagem = $conn->prepare(
            "INSERT INTO mensagem
                (id_conversa_fk, id_remetente_fk, tipo_remetente, conteudo)
             VALUES
                (:id_conversa, :id_remetente, 'usuario', :conteudo)"
        );
        $stmt_mensagem->execute([
            ':id_conversa' => $id_conversa,
            ':id_remetente' => $id_usuario_adotante,
            ':conteudo' => 'Olá! Tenho interesse em adotar o(a) ' . $pet['nome'] . '.',
        ]);

        $conn->commit();
        redirectToChat($base_path, $id_conversa);
    }

    // Nova solicitação: formulário, conversa e primeira mensagem são criados na mesma transação.
    $conn->beginTransaction();

    $stmt_solicitacao = $conn->prepare(
        "INSERT INTO solicitacao
            (id_usuario, id_pet, id_protetor_usuario_fk, id_protetor_ong_fk, status_solicitacao)
         VALUES
            (:id_usuario, :id_pet, :id_protetor_usuario, :id_protetor_ong, 'pendente')"
    );
    $stmt_solicitacao->execute([
        ':id_usuario' => $id_usuario_adotante,
        ':id_pet' => $id_pet,
        ':id_protetor_usuario' => $id_protetor_usuario,
        ':id_protetor_ong' => $id_protetor_ong,
    ]);

    $id_solicitacao = $conn->lastInsertId();

    $stmt_formulario = $conn->prepare(
        "INSERT INTO formulario_adocao
            (id_solicitacao_fk, id_usuario_fk, id_pet_fk, tem_criancas, todos_apoiam, tipo_moradia, pet_sera_presente, presente_responsavel, teve_pets, autoriza_visita, ciente_devolucao, ciente_termo_responsabilidade)
         VALUES
            (:id_solicitacao, :id_usuario, :id_pet, :tem_criancas, :todos_apoiam, :tipo_moradia, :pet_sera_presente, :presente_responsavel, :teve_pets, :autoriza_visita, :ciente_devolucao, :ciente_termo_responsabilidade)"
    );
    $stmt_formulario->execute([
        ':id_solicitacao' => $id_solicitacao,
        ':id_usuario' => $id_usuario_adotante,
        ':id_pet' => $id_pet,
        ':tem_criancas' => $dados_formulario['tem_criancas'],
        ':todos_apoiam' => $dados_formulario['todos_apoiam'],
        ':tipo_moradia' => $dados_formulario['tipo_moradia'],
        ':pet_sera_presente' => $dados_formulario['pet_sera_presente'],
        ':presente_responsavel' => $dados_formulario['presente_responsavel'] !== '' ? $dados_formulario['presente_responsavel'] : null,
        ':teve_pets' => $dados_formulario['teve_pets'],
        ':autoriza_visita' => $dados_formulario['autoriza_visita'],
        ':ciente_devolucao' => $dados_formulario['ciente_devolucao'],
        ':ciente_termo_responsabilidade' => $dados_formulario['ciente_termo_responsabilidade'],
    ]);

    $stmt_conversa = $conn->prepare(
        "INSERT INTO conversa
            (id_solicitacao_fk, id_adotante_fk, id_protetor_fk, tipo_protetor)
         VALUES
            (:id_solicitacao, :id_adotante, :id_protetor, :tipo_protetor)"
    );
    $stmt_conversa->execute([
        ':id_solicitacao' => $id_solicitacao,
        ':id_adotante' => $id_usuario_adotante,
        ':id_protetor' => $id_protetor_final,
        ':tipo_protetor' => $tipo_protetor_final,
    ]);

    $id_conversa = $conn->lastInsertId();

    $stmt_mensagem = $conn->prepare(
        "INSERT INTO mensagem
            (id_conversa_fk, id_remetente_fk, tipo_remetente, conteudo)
         VALUES
            (:id_conversa, :id_remetente, 'usuario', :conteudo)"
    );
    $stmt_mensagem->execute([
        ':id_conversa' => $id_conversa,
        ':id_remetente' => $id_usuario_adotante,
        ':conteudo' => 'Olá! Tenho interesse em adotar o(a) ' . $pet['nome'] . '.',
    ]);

    $conn->commit();

    $_SESSION['mensagem_status'] = 'Solicitação enviada com sucesso. A conversa com o responsável pelo pet foi iniciada.';
    $_SESSION['tipo_mensagem'] = 'success';

    redirectToChat($base_path, $id_conversa);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Erro ao processar adoção: ' . $e->getMessage());
    $_SESSION['form_error'] = 'Não foi possível concluir a solicitação de adoção. Tente novamente.';
    redirectToAdoptionForm($base_path, $id_pet);
}
