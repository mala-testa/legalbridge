CREATE DATABASE IF NOT EXISTS legalbridge;
USE legalbridge;



-- USERS
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin','lawyer','client') NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- SPECIALTIES
CREATE TABLE specialties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    active TINYINT(1) DEFAULT 1
);

-- LAWYERS
CREATE TABLE lawyers (
    user_id INT PRIMARY KEY,
    specialty_id INT NOT NULL,
    hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    bio TEXT,
    active TINYINT(1) DEFAULT 1,

    CONSTRAINT fk_lawyer_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_lawyer_specialty
        FOREIGN KEY (specialty_id)
        REFERENCES specialties(id)
        ON DELETE RESTRICT
);

-- CLIENT REQUESTS
CREATE TABLE client_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    specialty_id INT NOT NULL,
    case_description TEXT NOT NULL,

    status ENUM(
        'open',
        'matched',
        'closed',
        'cancelled'
    ) DEFAULT 'open',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_request_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_request_specialty
        FOREIGN KEY (specialty_id)
        REFERENCES specialties(id)
        ON DELETE RESTRICT
);

-- MESSAGES

CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,

    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,

    message TEXT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_message_sender
        FOREIGN KEY (sender_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_message_recipient
        FOREIGN KEY (recipient_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);