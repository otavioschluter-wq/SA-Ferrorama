DROP DATABASE IF EXISTS sa_ferrorama_fundos;
CREATE DATABASE sa_ferrorama_fundos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sa_ferrorama_fundos;

CREATE TABLE trens (
    id_trem INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    prefixo VARCHAR(6) NOT NULL UNIQUE,
    modelo VARCHAR(120) NOT NULL,
    ano SMALLINT UNSIGNED NOT NULL,
    status ENUM('normal', 'atencao', 'critico') NOT NULL DEFAULT 'normal',
    capacidade_toneladas DECIMAL(8,2) NOT NULL,
    ultima_inspecao DATE NULL,
    CONSTRAINT chk_trens_ano CHECK (ano BETWEEN 1900 AND 2100),
    CONSTRAINT chk_trens_capacidade CHECK (capacidade_toneladas > 0)
) ENGINE=InnoDB;

CREATE TABLE usuarios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    login VARCHAR(80) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    papel ENUM('administrador', 'gestor', 'maquinista', 'usuario') NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    trem_atribuido_id INT UNSIGNED NULL,
    CONSTRAINT fk_usuarios_trens FOREIGN KEY (trem_atribuido_id) REFERENCES trens (id_trem) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE sensores (
    id_sensor INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_trem INT UNSIGNED NOT NULL,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    tipo ENUM('temperatura', 'velocidade', 'energia', 'localizacao') NOT NULL,
    localizacao VARCHAR(80) NOT NULL,
    segmento VARCHAR(80) NOT NULL,
    INDEX idx_sensores_tipo (tipo),
    INDEX idx_sensores_trem_tipo (id_trem, tipo),
    CONSTRAINT chk_sensores_codigo CHECK (codigo REGEXP '^S-(TEMP|VELO|ENER|LOCA)-[0-9]{3}$'),
    CONSTRAINT fk_sensores_trens FOREIGN KEY (id_trem) REFERENCES trens (id_trem) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE rotas (
    id_rota INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_trem INT UNSIGNED NOT NULL,
    origem VARCHAR(80) NOT NULL,
    destino VARCHAR(80) NOT NULL,
    data_viagem DATE NOT NULL,
    hora_partida TIME NOT NULL,
    hora_chegada TIME NULL,
    status ENUM('programada', 'em_andamento', 'concluida', 'cancelada', 'atrasada') NOT NULL DEFAULT 'programada',
    INDEX idx_rotas_data (data_viagem),
    INDEX idx_rotas_trem_data (id_trem, data_viagem),
    CONSTRAINT fk_rotas_trens FOREIGN KEY (id_trem) REFERENCES trens (id_trem) ON DELETE RESTRICT,
    CONSTRAINT chk_rotas_horas CHECK (hora_chegada IS NULL OR hora_chegada > hora_partida)
) ENGINE=InnoDB;

CREATE TABLE leituras (
    id_leitura INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_sensor INT UNSIGNED NOT NULL,
    data_hora DATETIME NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    unidade VARCHAR(10) NOT NULL,
    INDEX idx_leituras_sensor_data (id_sensor, data_hora, id_leitura),
    INDEX idx_leituras_data (data_hora),
    CONSTRAINT fk_leituras_sensores FOREIGN KEY (id_sensor) REFERENCES sensores (id_sensor) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE alertas (
    id_alerta INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_leitura INT UNSIGNED NOT NULL UNIQUE,
    tipo VARCHAR(40) NOT NULL,
    severidade ENUM('atencao', 'critico') NOT NULL,
    mensagem VARCHAR(200) NOT NULL,
    data_hora DATETIME NOT NULL,
    INDEX idx_alertas_data (data_hora),
    INDEX idx_alertas_severidade_tipo (severidade, tipo),
    CONSTRAINT fk_alertas_leituras FOREIGN KEY (id_leitura) REFERENCES leituras (id_leitura) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE relatorios (
    id_relatorio INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    periodo_inicio DATE NOT NULL,
    periodo_fim DATE NOT NULL,
    gerado_em DATETIME NOT NULL,
    CONSTRAINT fk_relatorios_usuarios FOREIGN KEY (id_usuario) REFERENCES usuarios (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO trens (prefixo, modelo, ano, status, capacidade_toneladas, ultima_inspecao) VALUES
    ('TR-204', 'GE ES43BBi', 2015, 'normal', 128.50, DATE_SUB(CURDATE(), INTERVAL 20 DAY)),
    ('TR-318', 'EMD SD70ACe', 2018, 'atencao', 132.00, DATE_SUB(CURDATE(), INTERVAL 45 DAY)),
    ('TR-427', 'GE AC44i', 2012, 'critico', 126.75, NULL),
    ('TR-512', 'GE ES44AC', 2020, 'normal', 130.00, DATE_SUB(CURDATE(), INTERVAL 10 DAY)),
    ('TR-631', 'EMD GP38', 2009, 'atencao', 98.40, DATE_SUB(CURDATE(), INTERVAL 90 DAY));

-- Senha de todos os usuarios de exemplo: Ferro@2026
INSERT INTO usuarios (nome, login, senha, papel, ativo, trem_atribuido_id) VALUES
    ('Administrador', 'admin', '$2y$12$oaC4FisAlmmvZEuIqwbCaOjHTwlKEubuQnuLjw0y1h6I3S974OVjK', 'administrador', 1, NULL),
    ('Gerente Operacional', 'gerente', '$2y$12$oaC4FisAlmmvZEuIqwbCaOjHTwlKEubuQnuLjw0y1h6I3S974OVjK', 'gestor', 1, NULL),
    ('Maquinista TR-204', 'maquinista', '$2y$12$oaC4FisAlmmvZEuIqwbCaOjHTwlKEubuQnuLjw0y1h6I3S974OVjK', 'maquinista', 1, 1),
    ('Maquinista TR-318', 'maquinista2', '$2y$12$oaC4FisAlmmvZEuIqwbCaOjHTwlKEubuQnuLjw0y1h6I3S974OVjK', 'maquinista', 1, 2),
    ('Usuario Comum', 'comum', '$2y$12$oaC4FisAlmmvZEuIqwbCaOjHTwlKEubuQnuLjw0y1h6I3S974OVjK', 'usuario', 1, NULL),
    ('Usuario Desativado', 'desativado', '$2y$12$oaC4FisAlmmvZEuIqwbCaOjHTwlKEubuQnuLjw0y1h6I3S974OVjK', 'usuario', 0, NULL);

INSERT INTO sensores (id_trem, codigo, tipo, localizacao, segmento) VALUES
    (1, 'S-VELO-001', 'velocidade', 'Cabine', 'Trecho Norte'),
    (1, 'S-ENER-001', 'energia', 'Gerador', 'Trecho Norte'),
    (1, 'S-TEMP-001', 'temperatura', 'Motor', 'Trecho Norte'),
    (2, 'S-VELO-002', 'velocidade', 'Cabine', 'Trecho Central'),
    (2, 'S-ENER-002', 'energia', 'Gerador', 'Trecho Central'),
    (2, 'S-TEMP-002', 'temperatura', 'Motor', 'Trecho Central'),
    (3, 'S-VELO-003', 'velocidade', 'Cabine', 'Trecho Sul'),
    (3, 'S-ENER-003', 'energia', 'Gerador', 'Trecho Sul'),
    (3, 'S-TEMP-003', 'temperatura', 'Motor', 'Trecho Sul'),
    (4, 'S-VELO-004', 'velocidade', 'Cabine', 'Trecho Norte'),
    (4, 'S-ENER-004', 'energia', 'Gerador', 'Trecho Norte'),
    (4, 'S-TEMP-004', 'temperatura', 'Motor', 'Trecho Norte'),
    (5, 'S-LOCA-005', 'localizacao', 'Cabine', 'Trecho Central'),
    (5, 'S-VELO-005', 'velocidade', 'Cabine', 'Trecho Central');

INSERT INTO rotas (id_trem, origem, destino, data_viagem, hora_partida, hora_chegada, status) VALUES
    (1, 'Joinville', 'Blumenau', CURDATE(), '06:00:00', '09:00:00', 'em_andamento'),
    (2, 'Blumenau', 'Itajaí', CURDATE(), '07:30:00', '10:30:00', 'em_andamento'),
    (1, 'Blumenau', 'Joinville', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:00:00', '11:00:00', 'concluida'),
    (2, 'Itajaí', 'Blumenau', DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:00:00', '12:00:00', 'concluida'),
    (3, 'Joinville', 'Itajaí', DATE_SUB(CURDATE(), INTERVAL 3 DAY), '10:00:00', '13:00:00', 'cancelada'),
    (4, 'Itajaí', 'Joinville', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '14:00:00', '17:30:00', 'atrasada'),
    (5, 'Joinville', 'Blumenau', DATE_SUB(CURDATE(), INTERVAL 5 DAY), '05:30:00', '08:30:00', 'concluida'),
    (3, 'Itajaí', 'Joinville', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00:00', '17:00:00', 'programada'),
    (4, 'Blumenau', 'Itajaí', DATE_ADD(CURDATE(), INTERVAL 2 DAY), '06:30:00', '09:30:00', 'programada'),
    (1, 'Joinville', 'Itajaí', DATE_SUB(CURDATE(), INTERVAL 10 DAY), '07:00:00', '10:00:00', 'concluida'),
    (2, 'Joinville', 'Blumenau', DATE_SUB(CURDATE(), INTERVAL 4 DAY), '11:00:00', '14:00:00', 'atrasada');

INSERT INTO leituras (id_sensor, data_hora, valor, unidade)
SELECT s.id_sensor, DATE_SUB(NOW(), INTERVAL n.i * 30 MINUTE),
    CASE s.tipo
        WHEN 'velocidade' THEN 60 + (s.id_sensor * 17 + n.i * 29) % 85
        WHEN 'energia' THEN 300 + (s.id_sensor * 37 + n.i * 53) % 300
        WHEN 'temperatura' THEN 60 + (s.id_sensor * 11 + n.i * 7) % 45
        ELSE 100 + (s.id_sensor * 97 + n.i * 61) % 850
    END,
    CASE s.tipo WHEN 'velocidade' THEN 'km/h' WHEN 'energia' THEN 'kWh' WHEN 'temperatura' THEN '°C' ELSE 'km' END
FROM sensores s
CROSS JOIN (SELECT 0 AS i UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
    UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7) n;

INSERT INTO alertas (id_leitura, tipo, severidade, mensagem, data_hora)
SELECT l.id_leitura, s.tipo, IF(l.valor > x.lim * 1.10, 'critico', 'atencao'),
    CONCAT(s.codigo, ': ', l.valor, ' ', l.unidade, ' acima do limite de ', x.lim, ' ', l.unidade, '.'), l.data_hora
FROM leituras l
INNER JOIN sensores s ON s.id_sensor = l.id_sensor
INNER JOIN (SELECT 'temperatura' AS tipo, 90 AS lim UNION ALL SELECT 'velocidade', 120
    UNION ALL SELECT 'energia', 500 UNION ALL SELECT 'localizacao', 1000) x ON x.tipo = s.tipo
WHERE l.valor > x.lim;

INSERT INTO relatorios (id_usuario, tipo, periodo_inicio, periodo_fim, gerado_em) VALUES
    (1, 'desempenho', DATE_SUB(CURDATE(), INTERVAL 30 DAY), CURDATE(), NOW()),
    (1, 'manutencao', DATE_SUB(CURDATE(), INTERVAL 30 DAY), CURDATE(), NOW()),
    (2, 'eficiencia', DATE_SUB(CURDATE(), INTERVAL 7 DAY), CURDATE(), NOW());
