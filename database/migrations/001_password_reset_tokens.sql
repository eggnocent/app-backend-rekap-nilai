CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id          UUID        DEFAULT gen_random_uuid()           NOT NULL,
    user_id     UUID                                  NOT NULL,
    token_hash  VARCHAR(64)                           NOT NULL,
    expires_at  TIMESTAMPTZ                           NOT NULL,
    used_at     TIMESTAMPTZ,
    created_at  TIMESTAMPTZ DEFAULT NOW()             NOT NULL,
    created_by  UUID                                  NOT NULL,
    updated_at  TIMESTAMPTZ,
    updated_by  UUID,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users (id),
    FOREIGN KEY (created_by) REFERENCES users (id),
    FOREIGN KEY (updated_by) REFERENCES users (id)
);
