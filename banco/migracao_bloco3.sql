-- Execute somente no banco exclusivo sa_ferrorama_fundos, após o Bloco 2.
CREATE DATABASE IF NOT EXISTS sa_ferrorama_fundos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sa_ferrorama_fundos;

CREATE TABLE IF NOT EXISTS rotas (
    id_rota INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_trem INT UNSIGNED NOT NULL,
    origem VARCHAR(80) NOT NULL,
    destino VARCHAR(80) NOT NULL,
    data_viagem DATE NOT NULL,
    hora_partida TIME NOT NULL,
    hora_chegada TIME NULL,
    status ENUM('programada', 'em_andamento', 'concluida', 'cancelada') NOT NULL DEFAULT 'programada',
    INDEX idx_rotas_data (data_viagem),
    INDEX idx_rotas_trem_data (id_trem, data_viagem),
    CONSTRAINT fk_rotas_trens FOREIGN KEY (id_trem) REFERENCES trens (id_trem) ON DELETE RESTRICT,
    CONSTRAINT chk_rotas_horas CHECK (hora_chegada IS NULL OR hora_chegada > hora_partida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leituras (
    id_leitura INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_sensor INT UNSIGNED NOT NULL,
    data_hora DATETIME NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    unidade VARCHAR(10) NOT NULL,
    INDEX idx_leituras_sensor_data (id_sensor, data_hora, id_leitura),
    INDEX idx_leituras_data (data_hora),
    CONSTRAINT fk_leituras_sensores FOREIGN KEY (id_sensor) REFERENCES sensores (id_sensor) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alertas (
    id_alerta INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_leitura INT UNSIGNED NOT NULL UNIQUE,
    tipo VARCHAR(40) NOT NULL,
    severidade ENUM('atencao', 'critico') NOT NULL,
    mensagem VARCHAR(200) NOT NULL,
    data_hora DATETIME NOT NULL,
    INDEX idx_alertas_data (data_hora),
    INDEX idx_alertas_severidade_tipo (severidade, tipo),
    CONSTRAINT fk_alertas_leituras FOREIGN KEY (id_leitura) REFERENCES leituras (id_leitura) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
