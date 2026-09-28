<?php
include_once 'conexao.php';
appStartSession();

// Segurança: Só admin pode acessar
if (!isset($_SESSION['user_id']) || $_SESSION['user_tipo'] !== 'admin') {
    header("Location: login");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

$acao = (string) ($_POST['acao'] ?? '');
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$acoesPermitidas = [
    'excluir_usuario', 'excluir_ong', 'excluir_pet',
    'verificar_ong', 'rejeitar_ong',
    'aprovar_exclusao_conta', 'rejeitar_exclusao_conta',
];

if (!$id || !in_array($acao, $acoesPermitidas, true)) {
    $_SESSION['toast_message'] = "ID inválido.";
    $_SESSION['toast_type'] = "danger";
    header("Location: perfil?page=painel-admin");
    exit;
}

try {
    if ($acao == 'excluir_usuario') {
        // Excluir Usuário (PF)
        // O CASCADE do banco deve apagar pets, conversas e solicitações vinculadas
        $stmt = $conn->prepare("DELETE FROM usuario WHERE id_usuario = :id");
        $stmt->execute([':id' => $id]);
        appAudit($conn, 'delete', 'usuario', (int) $id);
        
        $_SESSION['toast_message'] = "Usuário excluído com sucesso.";
        $_SESSION['toast_type'] = "success";

    } elseif ($acao == 'excluir_ong') {
        // Excluir ONG (PJ)
        $stmt = $conn->prepare("DELETE FROM ong WHERE id_ong = :id");
        $stmt->execute([':id' => $id]);
        appAudit($conn, 'delete', 'ong', (int) $id);
        
        $_SESSION['toast_message'] = "ONG excluída com sucesso.";
        $_SESSION['toast_type'] = "success";

    } elseif ($acao == 'excluir_pet') {
        // Excluir Pet
        $stmt = $conn->prepare("DELETE FROM pet WHERE id_pet = :id");
        $stmt->execute([':id' => $id]);
        appAudit($conn, 'delete', 'pet', (int) $id);
        
        $_SESSION['toast_message'] = "Pet excluído com sucesso.";
        $_SESSION['toast_type'] = "success";
    } elseif ($acao === 'verificar_ong' || $acao === 'rejeitar_ong') {
        $status = $acao === 'verificar_ong' ? 'verified' : 'rejected';
        $verifiedAt = $status === 'verified' ? date('Y-m-d H:i:s') : null;
        $stmt = $conn->prepare(
            'UPDATE ong SET verification_status = :status, verified_at = :verified_at WHERE id_ong = :id'
        );
        $stmt->execute([':status' => $status, ':verified_at' => $verifiedAt, ':id' => $id]);
        appAudit($conn, 'verification_' . $status, 'ong', (int) $id);
        appNotify(
            $conn,
            (int) $id,
            'ong',
            'Verificação da ONG atualizada',
            $status === 'verified' ? 'Sua ONG foi verificada.' : 'A verificação da ONG precisa de revisão.',
            'perfil/'
        );
        $_SESSION['toast_message'] = $status === 'verified' ? 'ONG verificada com sucesso.' : 'Verificação da ONG recusada.';
        $_SESSION['toast_type'] = $status === 'verified' ? 'success' : 'warning';
    } elseif ($acao === 'aprovar_exclusao_conta' || $acao === 'rejeitar_exclusao_conta') {
        $conn->beginTransaction();
        $requestStatement = $conn->prepare(
            "SELECT account_id, account_type FROM account_deletion_request"
            . " WHERE id_request = :id AND status = 'pending' FOR UPDATE"
        );
        $requestStatement->execute([':id' => $id]);
        $request = $requestStatement->fetch(PDO::FETCH_ASSOC);
        if (!$request) {
            throw new RuntimeException('Solicitação de exclusão não encontrada.');
        }

        if ($acao === 'aprovar_exclusao_conta') {
            $accountTable = $request['account_type'] === 'ong' ? 'ong' : 'usuario';
            $accountColumn = $request['account_type'] === 'ong' ? 'id_ong' : 'id_usuario';
            $deleteAccount = $conn->prepare("DELETE FROM {$accountTable} WHERE {$accountColumn} = :id");
            $deleteAccount->execute([':id' => (int) $request['account_id']]);
            $newStatus = 'completed';
        } else {
            $newStatus = 'rejected';
        }

        $updateRequest = $conn->prepare(
            'UPDATE account_deletion_request SET status = :status, resolved_at = NOW() WHERE id_request = :id'
        );
        $updateRequest->execute([':status' => $newStatus, ':id' => $id]);
        appAudit($conn, 'account_deletion_' . $newStatus, 'account_deletion_request', (int) $id, [
            'account_type' => $request['account_type'],
            'account_id' => (int) $request['account_id'],
        ]);
        $conn->commit();

        if ($newStatus === 'rejected') {
            appNotify(
                $conn,
                (int) $request['account_id'],
                (string) $request['account_type'],
                'Solicitação de exclusão revisada',
                'Sua solicitação de exclusão foi revisada e não foi aprovada. Entre em contato com o suporte para mais informações.',
                'privacidade-conta/'
            );
        }
        $_SESSION['toast_message'] = $newStatus === 'completed'
            ? 'Conta e dados relacionados excluídos.'
            : 'Solicitação de exclusão rejeitada.';
        $_SESSION['toast_type'] = $newStatus === 'completed' ? 'success' : 'warning';
    }

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Erro administrativo ao excluir registro: ' . $e->getMessage());
    $_SESSION['toast_message'] = "Não foi possível excluir o registro.";
    $_SESSION['toast_type'] = "danger";
}

// Volta para o painel
header("Location: perfil?page=painel-admin");
exit;
?>
