-- Uitvoeren op de server:  sudo mysql < deploy/mysql-setup.sql
-- Vervang JOUW_GEBRUIKER en JOUW_WACHTWOORD, en vul dezelfde waarden in je .env in.
CREATE DATABASE IF NOT EXISTS campusconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'JOUW_GEBRUIKER'@'localhost' IDENTIFIED BY 'JOUW_WACHTWOORD';
GRANT ALL PRIVILEGES ON campusconnect.* TO 'JOUW_GEBRUIKER'@'localhost';
FLUSH PRIVILEGES;
