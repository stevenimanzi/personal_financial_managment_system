CREATE DATABASE IF NOT EXISTS imanzi_budget CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE imanzi_budget;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'RWF',
    emergency_months TINYINT UNSIGNED NOT NULL DEFAULT 3,
    savings_target_pct TINYINT UNSIGNED NOT NULL DEFAULT 20,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(190) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attempts_email (email, attempted_at),
    KEY idx_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS accounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    type ENUM('cash','bank','mobile','savings','other') NOT NULL DEFAULT 'cash',
    opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
    color CHAR(7) NOT NULL DEFAULT '#0d9488',
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_accounts_user (user_id),
    CONSTRAINT fk_accounts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    type ENUM('income','expense') NOT NULL,
    color CHAR(7) NOT NULL DEFAULT '#64748b',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_user_type_name (user_id, type, name),
    CONSTRAINT fk_categories_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transactions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    to_account_id INT UNSIGNED NULL,
    category_id INT UNSIGNED NULL,
    type ENUM('income','expense','transfer') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    txn_date DATE NOT NULL,
    party VARCHAR(120) NOT NULL DEFAULT '',
    reason VARCHAR(255) NOT NULL DEFAULT '',
    necessity ENUM('need','want','saving') NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_txn_user_date (user_id, txn_date),
    KEY idx_txn_user_type_date (user_id, type, txn_date),
    KEY idx_txn_category (category_id),
    KEY idx_txn_account (account_id),
    KEY idx_txn_to_account (to_account_id),
    CONSTRAINT fk_txn_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_txn_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE,
    CONSTRAINT fk_txn_to_account FOREIGN KEY (to_account_id) REFERENCES accounts (id) ON DELETE CASCADE,
    CONSTRAINT fk_txn_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS budgets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_budgets_user_category (user_id, category_id),
    CONSTRAINT fk_budgets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_budgets_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS goals (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    target_amount DECIMAL(15,2) NOT NULL,
    saved_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    target_date DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_goals_user (user_id),
    CONSTRAINT fk_goals_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS debts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    kind ENUM('owed_to_me','i_owe') NOT NULL,
    person VARCHAR(120) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    due_date DATE NULL,
    reason VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_debts_user (user_id, kind),
    CONSTRAINT fk_debts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS decisions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    item VARCHAR(160) NOT NULL,
    cost DECIMAL(15,2) NOT NULL,
    kind ENUM('need','want') NOT NULL DEFAULT 'want',
    verdict ENUM('go','wait','avoid') NOT NULL,
    summary VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_decisions_user (user_id, created_at),
    CONSTRAINT fk_decisions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;
