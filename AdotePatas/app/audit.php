<?php

declare(strict_types=1);

function appAudit(PDO $connection, string $action, string $entityType, ?int $entityId = null, array $metadata = []): void
{
    try {
        $statement = $connection->prepare(
            'INSERT INTO audit_log (actor_id, actor_type, action_name, entity_type, entity_id, metadata_json, ip_address, created_at)'
            . ' VALUES (:actor_id, :actor_type, :action_name, :entity_type, :entity_id, :metadata_json, :ip_address, NOW())'
        );
        $statement->execute([
            ':actor_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
            ':actor_type' => $_SESSION['user_tipo'] ?? 'system',
            ':action_name' => $action,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':ip_address' => appClientIp(),
        ]);
    } catch (Throwable $error) {
        error_log('Falha ao registrar auditoria: ' . $error->getMessage());
    }
}
