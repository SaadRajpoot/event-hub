-- EventHub MySQL Initialization Script
-- Creates databases for Main API and Payment Service

CREATE DATABASE IF NOT EXISTS eventhub_main CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS eventhub_payments CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant the eventhub user access to both databases
GRANT ALL PRIVILEGES ON eventhub_main.* TO 'eventhub'@'%';
GRANT ALL PRIVILEGES ON eventhub_payments.* TO 'eventhub'@'%';

FLUSH PRIVILEGES;
