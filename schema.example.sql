CREATE DATABASE IF NOT EXISTS results_db;
USE results_db;

CREATE TABLE students (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    reg_number   VARCHAR(20)  NOT NULL UNIQUE,
    full_name    VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20),
    email        VARCHAR(100),
    waec_result  JSON,
    utme_result  JSON,
    aggregate    DECIMAL(5,2)
);
