CREATE TABLE IF NOT EXISTS migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS home_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO home_messages (title, body)
SELECT 'Hello from MariaDB', 'This message was loaded through the Home model.'
WHERE NOT EXISTS (
    SELECT 1 FROM home_messages WHERE title = 'Hello from MariaDB'
);

INSERT INTO migrations (migration)
SELECT '2026_07_10_000001_create_home_messages_table'
WHERE NOT EXISTS (
    SELECT 1 FROM migrations WHERE migration = '2026_07_10_000001_create_home_messages_table'
);
