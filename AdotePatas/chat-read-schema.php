<?php

/**
 * Detecta o suporte a confirmação de leitura. Alterações de schema pertencem
 * às migrações versionadas em database/migrations, nunca ao ciclo da requisição.
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

        $stmt = $conn->query("SHOW COLUMNS FROM mensagem LIKE 'data_leitura'");
        $hasReadAt = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        $resolved = $hasRead && $hasReadAt;
    } catch (Throwable $e) {
        error_log('Não foi possível preparar confirmação de leitura do chat: ' . $e->getMessage());
        $resolved = false;
    }

    return $resolved;
}
