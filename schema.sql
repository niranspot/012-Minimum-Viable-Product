CREATE DATABASE IF NOT EXISTS 012_mvp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE 012_mvp;

-- Tenants (Master DB metadata)
CREATE TABLE tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(100) UNIQUE NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    subdomain VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    plan ENUM('basic','pro','enterprise') DEFAULT 'basic',
    db_name VARCHAR(255) DEFAULT NULL,
    theme_settings TEXT DEFAULT NULL,
    status ENUM('active','inactive','suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert a default tenant to test with
-- The password hash here is the bcrypt of '123456'
-- INSERT INTO tenants (tenant_id, company_name, subdomain, email, password, plan, db_name, theme_settings, status) 
-- VALUES (
--     'DEFAULT001', 
--     'Default Hospital', 
--     'default', 
--     'admin@test.com', 
--     '$2y$10$pY47v8Tbe4x9h/6gGkFzNuDkW2Z3J3Rj6tV.c1Kj9tNfK08JvD2m.', 
--     'basic', 
--     'tenant_default_db',
--     '{"primaryColor": "#4A90E2", "darkTheme": false}',
--     'active'
-- );