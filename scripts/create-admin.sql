-- Run after the Shield migrations, against the application database.
-- Existing accounts retain their password; repeated runs do not duplicate roles.
START TRANSACTION;

SET @admin_email = 'matthias.zillig@adlix.de';
SET @admin_id = (
    SELECT user_id FROM auth_identities
    WHERE type = 'email_password' AND secret = @admin_email
    LIMIT 1
);

INSERT INTO users (active, created_at, updated_at)
SELECT 1, NOW(), NOW()
WHERE @admin_id IS NULL;

SET @admin_id = COALESCE(@admin_id, LAST_INSERT_ID());

INSERT INTO auth_identities (user_id, type, secret, secret2, created_at, updated_at)
SELECT @admin_id, 'email_password', @admin_email,
       '$2y$12$mbkZEeskRIiBWDJq7e2jF.9IWbVj6uj0O.5l50oO1pPhzaNCR0wv.', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM auth_identities
    WHERE type = 'email_password' AND secret = @admin_email
);

INSERT INTO auth_groups_users (user_id, `group`, created_at)
SELECT @admin_id, 'admin', NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM auth_groups_users
    WHERE user_id = @admin_id AND `group` = 'admin'
);

COMMIT;
