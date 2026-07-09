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
