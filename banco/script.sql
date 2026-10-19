CREATE DATABASE IF NOT EXISTS sa_ferrorama_fundos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sa_ferrorama_fundos;

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

-- Exemplos para instalação nova. As verificações permitem repetir a importação.
INSERT INTO sensores (id_trem, codigo, tipo, localizacao, segmento)
SELECT t.id_trem, amostra.codigo, amostra.tipo, amostra.localizacao, amostra.segmento
FROM (
    SELECT 'TR-204' AS prefixo, 'S-TEMP-001' AS codigo, 'temperatura' AS tipo, 'Motor' AS localizacao, 'Trecho Norte' AS segmento
    UNION ALL SELECT 'TR-318', 'S-VELO-002', 'velocidade', 'Cabine', 'Trecho Central'
    UNION ALL SELECT 'TR-427', 'S-ENER-003', 'energia', 'Gerador', 'Trecho Sul'
) amostra INNER JOIN trens t ON t.prefixo=amostra.prefixo
WHERE NOT EXISTS (SELECT 1 FROM sensores s WHERE s.codigo=amostra.codigo);

INSERT INTO rotas (id_trem, origem, destino, data_viagem, hora_partida, hora_chegada, status)
SELECT t.id_trem, amostra.origem, amostra.destino, amostra.data_viagem, amostra.hora_partida, amostra.hora_chegada, amostra.status
FROM (
    SELECT 'TR-204' AS prefixo, 'Joinville' AS origem, 'Blumenau' AS destino, '2026-10-20' AS data_viagem, '08:00:00' AS hora_partida, '11:00:00' AS hora_chegada, 'programada' AS status
    UNION ALL SELECT 'TR-318', 'Blumenau', 'Itajaí', '2026-10-21', '09:00:00', '12:00:00', 'programada'
    UNION ALL SELECT 'TR-427', 'Itajaí', 'Joinville', '2026-10-22', '14:00:00', '17:00:00', 'programada'
) amostra INNER JOIN trens t ON t.prefixo=amostra.prefixo
WHERE NOT EXISTS (SELECT 1 FROM rotas r WHERE r.id_trem=t.id_trem AND r.data_viagem=amostra.data_viagem AND r.hora_partida=amostra.hora_partida);

INSERT INTO leituras (id_sensor, data_hora, valor, unidade)
SELECT s.id_sensor, amostra.data_hora, amostra.valor, amostra.unidade
FROM (
    SELECT 'S-TEMP-001' AS codigo, '2026-10-19 09:00:00' AS data_hora, 92.00 AS valor, '°C' AS unidade
    UNION ALL SELECT 'S-TEMP-001', '2026-10-19 09:05:00', 102.00, '°C'
    UNION ALL SELECT 'S-VELO-002', '2026-10-19 09:10:00', 124.00, 'km/h'
    UNION ALL SELECT 'S-ENER-003', '2026-10-19 09:15:00', 560.00, 'kWh'
) amostra INNER JOIN sensores s ON s.codigo=amostra.codigo
WHERE NOT EXISTS (SELECT 1 FROM leituras l WHERE l.id_sensor=s.id_sensor AND l.data_hora=amostra.data_hora);

INSERT INTO alertas (id_leitura, tipo, severidade, mensagem, data_hora)
SELECT l.id_leitura, s.tipo,
    CASE WHEN (s.tipo='temperatura' AND l.valor>99) OR (s.tipo='velocidade' AND l.valor>132) OR (s.tipo='energia' AND l.valor>550) THEN 'critico' ELSE 'atencao' END,
    CONCAT(s.codigo, ': valor de exemplo acima do limite.'), l.data_hora
FROM leituras l INNER JOIN sensores s ON s.id_sensor=l.id_sensor
WHERE s.codigo IN ('S-TEMP-001', 'S-VELO-002', 'S-ENER-003')
  AND l.data_hora IN ('2026-10-19 09:00:00', '2026-10-19 09:05:00', '2026-10-19 09:10:00', '2026-10-19 09:15:00')
  AND NOT EXISTS (SELECT 1 FROM alertas a WHERE a.id_leitura=l.id_leitura);
