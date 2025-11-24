-- haahoowaa base schema (MySQL)
-- Core language + translation tables
CREATE TABLE IF NOT EXISTS languages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(5) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lang_code VARCHAR(5) NOT NULL,
    key_name VARCHAR(191) NOT NULL,
    value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_lang_key (lang_code, key_name),
    CONSTRAINT fk_trans_lang FOREIGN KEY (lang_code) REFERENCES languages (code)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pages module
CREATE TABLE IF NOT EXISTS pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(191) NOT NULL UNIQUE,
    module VARCHAR(50) NOT NULL DEFAULT 'pages',
    template VARCHAR(191) NOT NULL DEFAULT 'home',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages_i18n (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    lang VARCHAR(5) NOT NULL,
    title VARCHAR(191) NOT NULL,
    content MEDIUMTEXT,
    meta_title VARCHAR(191),
    meta_description VARCHAR(191),
    CONSTRAINT fk_pages_i18n_page FOREIGN KEY (page_id) REFERENCES pages (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_pages_i18n_lang FOREIGN KEY (lang) REFERENCES languages (code)
        ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY idx_page_lang (page_id, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products module
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(191) NOT NULL UNIQUE,
    sku VARCHAR(100) DEFAULT NULL,
    module VARCHAR(50) NOT NULL DEFAULT 'products',
    template VARCHAR(191) NOT NULL DEFAULT 'detail',
    price DECIMAL(12,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products_i18n (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    lang VARCHAR(5) NOT NULL,
    title VARCHAR(191) NOT NULL,
    description MEDIUMTEXT,
    meta_title VARCHAR(191),
    meta_description VARCHAR(191),
    CONSTRAINT fk_products_i18n_product FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_products_i18n_lang FOREIGN KEY (lang) REFERENCES languages (code)
        ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY idx_product_lang (product_id, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shops module
CREATE TABLE IF NOT EXISTS shops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(191) NOT NULL UNIQUE,
    module VARCHAR(50) NOT NULL DEFAULT 'shops',
    template VARCHAR(191) NOT NULL DEFAULT 'shop_detail',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shops_i18n (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL,
    lang VARCHAR(5) NOT NULL,
    name VARCHAR(191) NOT NULL,
    description MEDIUMTEXT,
    meta_title VARCHAR(191),
    meta_description VARCHAR(191),
    CONSTRAINT fk_shops_i18n_shop FOREIGN KEY (shop_id) REFERENCES shops (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_shops_i18n_lang FOREIGN KEY (lang) REFERENCES languages (code)
        ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY idx_shop_lang (shop_id, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed defaults
INSERT IGNORE INTO languages (code, name, is_default) VALUES
('es', 'Español', 1),
('en', 'English', 0),
('fr', 'Français', 0);
