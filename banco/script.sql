CREATE DATABASE IF NOT EXISTS frota_ferroviaria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE frota_ferroviaria;

CREATE TABLE IF NOT EXISTS trens (
    id_trem INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    prefixo VARCHAR(6) NOT NULL UNIQUE,
    modelo VARCHAR(120) NOT NULL,
    ano SMALLINT UNSIGNED NOT NULL,
    status ENUM('normal', 'atencao', 'critico') NOT NULL DEFAULT 'normal',
    capacidade_toneladas DECIMAL(8,2) NOT NULL,
    ultima_inspecao DATE NULL,
    CONSTRAINT chk_trens_ano CHECK (ano BETWEEN 1900 AND 2100),
    CONSTRAINT chk_trens_capacidade CHECK (capacidade_toneladas > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    login VARCHAR(80) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    papel VARCHAR(20) NOT NULL,
    trem_atribuido_id INT UNSIGNED NULL,
    CONSTRAINT fk_usuarios_trens FOREIGN KEY (trem_atribuido_id) REFERENCES trens (id_trem) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sensores (
    id_sensor INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_trem INT UNSIGNED NOT NULL,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    tipo ENUM('temperatura', 'velocidade', 'energia', 'localizacao') NOT NULL,
    localizacao VARCHAR(80) NOT NULL,
    segmento VARCHAR(80) NOT NULL,
    ultima_leitura ENUM('normal', 'atencao', 'critico') NOT NULL DEFAULT 'normal',
    INDEX idx_sensores_tipo (tipo),
    INDEX idx_sensores_trem_tipo (id_trem, tipo),
    CONSTRAINT chk_sensores_codigo CHECK (codigo REGEXP '^S-(TEMP|VELO|ENER|LOCA)-[0-9]{3}$'),
    CONSTRAINT fk_sensores_trens FOREIGN KEY (id_trem) REFERENCES trens (id_trem) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO trens (prefixo, modelo, ano, status, capacidade_toneladas, ultima_inspecao)
VALUES
    ('TR-204', 'GE ES43BBi', 2015, 'normal', 128.50, '2026-08-15'),
    ('TR-318', 'EMD SD70ACe', 2018, 'atencao', 132.00, '2026-07-22'),
    ('TR-427', 'GE AC44i', 2012, 'critico', 126.75, NULL)
ON DUPLICATE KEY UPDATE prefixo = prefixo;
