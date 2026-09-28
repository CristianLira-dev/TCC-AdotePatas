-- Execute em homologação antes de produção e faça backup do banco.

CREATE TABLE IF NOT EXISTS audit_log (
    id_audit BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id BIGINT NULL,
    actor_type VARCHAR(30) NOT NULL,
    action_name VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id BIGINT NULL,
    metadata_json JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_actor (actor_type, actor_id),
    INDEX idx_audit_created (created_at)
);

CREATE TABLE IF NOT EXISTS notificacao (
    id_notificacao BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_id BIGINT NOT NULL,
    recipient_type ENUM('usuario', 'ong', 'admin') NOT NULL,
    title VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    action_url VARCHAR(500) NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notification_recipient (recipient_type, recipient_id, read_at, created_at)
);

CREATE TABLE IF NOT EXISTS account_deletion_request (
    id_request BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT NOT NULL,
    account_type ENUM('usuario', 'ong') NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'pending',
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    UNIQUE INDEX uq_account_deletion (account_type, account_id),
    INDEX idx_deletion_request_status (status, requested_at)
);

ALTER TABLE ong
    ADD COLUMN verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    ADD COLUMN verified_at DATETIME NULL,
    ADD COLUMN verification_notes VARCHAR(500) NULL;

ALTER TABLE mensagem
    ADD COLUMN lida TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN data_leitura DATETIME NULL,
    ADD INDEX idx_message_polling (id_conversa_fk, id_mensagem),
    ADD INDEX idx_message_read (id_conversa_fk, lida, id_remetente_fk, tipo_remetente);

ALTER TABLE favorito
    ADD UNIQUE INDEX uq_favorite_user_pet (id_usuario, id_pet);

ALTER TABLE solicitacao
    MODIFY COLUMN status_solicitacao ENUM(
        'pendente', 'em_conversa', 'entrevista', 'aprovado',
        'recusado', 'concluido', 'cancelado'
    ) NOT NULL DEFAULT 'pendente',
    ADD UNIQUE INDEX uq_adoption_user_pet (id_usuario, id_pet),
    ADD INDEX idx_adoption_status (status_solicitacao);

ALTER TABLE pet
    ADD INDEX idx_pet_catalog (status_disponibilidade, especie, porte);

ALTER TABLE usuario
    ADD INDEX idx_user_location (cidade, estado);

ALTER TABLE ong
    ADD INDEX idx_ong_location (cidade, estado);
