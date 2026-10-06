-- Execute no banco existente do Bloco 1. Repetir não apaga contas.
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

SET @renomear = IF(EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'usuario') AND NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'usuarios'), 'RENAME TABLE usuario TO usuarios', 'SELECT 1');
PREPARE etapa FROM @renomear; EXECUTE etapa; DEALLOCATE PREPARE etapa;

SET @nome = IF(EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'nome'), 'SELECT 1', 'ALTER TABLE usuarios ADD COLUMN nome VARCHAR(120) NULL AFTER id');
PREPARE etapa FROM @nome; EXECUTE etapa; DEALLOCATE PREPARE etapa;
UPDATE usuarios SET nome = login WHERE nome IS NULL OR nome = '';
ALTER TABLE usuarios MODIFY nome VARCHAR(120) NOT NULL;

SET @vinculo = IF(EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'trem_atribuido_id'), 'SELECT 1', 'ALTER TABLE usuarios ADD COLUMN trem_atribuido_id INT UNSIGNED NULL');
PREPARE etapa FROM @vinculo; EXECUTE etapa; DEALLOCATE PREPARE etapa;
SET @fk_usuario = IF(EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND constraint_name = 'fk_usuarios_trens'), 'SELECT 1', 'ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_trens FOREIGN KEY (trem_atribuido_id) REFERENCES trens (id_trem) ON DELETE RESTRICT');
PREPARE etapa FROM @fk_usuario; EXECUTE etapa; DEALLOCATE PREPARE etapa;

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

SET @indice_tipo = IF(EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'sensores' AND index_name = 'idx_sensores_tipo'), 'SELECT 1', 'ALTER TABLE sensores ADD INDEX idx_sensores_tipo (tipo)');
PREPARE etapa FROM @indice_tipo; EXECUTE etapa; DEALLOCATE PREPARE etapa;
SET @indice_trem_tipo = IF(EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'sensores' AND index_name = 'idx_sensores_trem_tipo'), 'SELECT 1', 'ALTER TABLE sensores ADD INDEX idx_sensores_trem_tipo (id_trem, tipo)');
PREPARE etapa FROM @indice_trem_tipo; EXECUTE etapa; DEALLOCATE PREPARE etapa;
SET @check_codigo = IF(EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = 'sensores' AND constraint_name = 'chk_sensores_codigo'), 'SELECT 1', 'ALTER TABLE sensores ADD CONSTRAINT chk_sensores_codigo CHECK (codigo REGEXP ''^S-(TEMP|VELO|ENER|LOCA)-[0-9]{3}$'')');
PREPARE etapa FROM @check_codigo; EXECUTE etapa; DEALLOCATE PREPARE etapa;
