-- Run `php artisan migrate` first. The Laravel migration creates bcrypt hashes
-- because MySQL cannot safely generate Laravel-compatible bcrypt hashes itself.
--
-- This script verifies the backfill and removes the legacy plaintext column
-- when it still exists. The application table name is `users`, not `user`.

START TRANSACTION;

SET @invalid_password_hashes := (
    SELECT COUNT(*)
    FROM `users`
    WHERE `password` IS NULL
       OR `password` NOT REGEXP '^\\$2[ayb]\\$[0-9]{2}\\$.{53}$'
);

SET @password_guard_sql := IF(
    @invalid_password_hashes = 0,
    'SELECT ''Password hash verification passed'' AS result',
    'SELECT * FROM `ERROR_run_php_artisan_migrate_before_removing_password_plain`'
);

PREPARE password_guard_statement FROM @password_guard_sql;
EXECUTE password_guard_statement;
DEALLOCATE PREPARE password_guard_statement;

SET @has_password_plain := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'password_plain'
);

SET @drop_password_plain_sql := IF(
    @has_password_plain > 0,
    'ALTER TABLE `users` DROP COLUMN `password_plain`',
    'SELECT ''password_plain already removed'' AS result'
);

PREPARE drop_password_plain_statement FROM @drop_password_plain_sql;
EXECUTE drop_password_plain_statement;
DEALLOCATE PREPARE drop_password_plain_statement;

COMMIT;
