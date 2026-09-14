<?php

/**
 * Garante que a tabela de mensagens possua os campos necessários para
 * confirmação de leitura. A migração é idempotente e só é executada quando
 * os campos ainda não existem.
 */
function adotePatasEnsureMessageReadSchema(PDO $conn): bool
{
    static $resolved = null;

    if ($resolved !== null) {
        return $resolved;
    }

    try {
        $stmt = $conn->query("SHOW COLUMNS FROM mensagem LIKE 'lida'");
        $hasRead = (bool) $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$hasRead) {
            $conn->exec("ALTER TABLE mensagem ADD COLUMN lida TINYINT(1) NOT NULL DEFAULT 0 AFTER data_envio");
        }

        $stmt = $conn->query("SHOW COLUMNS FROM mensagem LIKE 'data_leitura'");
        $hasReadAt = (bool) $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$hasReadAt) {
            $conn->exec("ALTER TABLE mensagem ADD COLUMN data_leitura DATETIME NULL AFTER lida");
        }

        $resolved = true;
    } catch (Throwable $e) {
        error_log('Não foi possível preparar confirmação de leitura do chat: ' . $e->getMessage());
        $resolved = false;
    }

    return $resolved;
}
