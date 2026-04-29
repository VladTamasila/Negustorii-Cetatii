-- =====================================================================
-- Negustorii Cetatii - Schema baza de date (Catan-like)
-- Versiune: 2.0 - harta hexagonala cu asezari pe colturi si drumuri pe muchii
-- Compatibil: MySQL 8.x / MariaDB 10.x (XAMPP)
-- =====================================================================
-- Cum se ruleaza in DataGrip:
--   1. Conecteaza-te la MySQL local
--   2. Selecteaza baza `negustorii_cetatii` (sau creeaza-o daca nu exista)
--   3. Deschide acest fisier si apasa "Run" (sau Ctrl+Enter pe tot)
--   4. Apoi ruleaza seed.sql pentru date demo (optional)
-- =====================================================================

-- Stergem tabelele in ordine inversa fata de cheile straine.
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `drumuri`;
DROP TABLE IF EXISTS `asezari`;
DROP TABLE IF EXISTS `hexagon_varfuri`;
DROP TABLE IF EXISTS `muchii`;
DROP TABLE IF EXISTS `varfuri`;
DROP TABLE IF EXISTS `hexagoane`;
DROP TABLE IF EXISTS `mutari`;
DROP TABLE IF EXISTS `jucatori`;
DROP TABLE IF EXISTS `partide`;
-- Tabelele vechi care nu mai sunt folosite:
DROP TABLE IF EXISTS `cladiri`;
DROP TABLE IF EXISTS `cladiri_catalog`;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- partide - resursa centrala. Acum are si o "faza" a jocului.
-- =====================================================================
CREATE TABLE `partide` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nume`              VARCHAR(80)  NOT NULL,
    `status`            ENUM('in_asteptare', 'activa', 'finalizata', 'arhivata')
                                     NOT NULL DEFAULT 'in_asteptare',
    -- Faza interna a jocului - mai detaliata decat "status".
    -- in_asteptare    = jucatorii inca se inscriu
    -- asezare_initiala = jucatorii pun asezari + drumuri de start (snake draft)
    -- joc             = gameplay normal (zar, build, trade)
    -- finalizata      = cineva a atins punctajul de castig
    `faza`              ENUM('in_asteptare', 'asezare_initiala', 'joc', 'finalizata')
                                     NOT NULL DEFAULT 'in_asteptare',
    `runda_setup`       TINYINT UNSIGNED NOT NULL DEFAULT 1, -- 1 sau 2 in faza setup
    `pas_setup`         ENUM('asezare', 'drum') NOT NULL DEFAULT 'asezare',
    `jucatori_maxim`    TINYINT UNSIGNED NOT NULL DEFAULT 4,
    `punctaj_castig`    SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    `tura_curenta`      INT UNSIGNED NOT NULL DEFAULT 0,
    `jucator_activ_id`  INT UNSIGNED NULL,
    `castigator_id`     INT UNSIGNED NULL,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                     ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_partide_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- jucatori - acum cu 5 resurse Catan: lemn, piatra, aur, grau, lana
-- =====================================================================
CREATE TABLE `jucatori` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `partida_id`    INT UNSIGNED NOT NULL,
    `nume`          VARCHAR(40)  NOT NULL,
    `culoare`       VARCHAR(20)  NULL,
    `ordine`        TINYINT UNSIGNED NOT NULL DEFAULT 0, -- 1, 2, 3, 4 - ordinea in setup
    `prestigiu`     INT UNSIGNED NOT NULL DEFAULT 0,
    `lemn`          INT UNSIGNED NOT NULL DEFAULT 0,
    `piatra`        INT UNSIGNED NOT NULL DEFAULT 0,
    `aur`           INT UNSIGNED NOT NULL DEFAULT 0,
    `grau`          INT UNSIGNED NOT NULL DEFAULT 0,
    `lana`          INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_jucatori_partida` (`partida_id`),
    CONSTRAINT `fk_jucatori_partida`
        FOREIGN KEY (`partida_id`) REFERENCES `partide`(`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- hexagoane - tile-urile hartii pentru o partida
-- Coordonate axiale (q, r) - sistem standard pentru hex grids.
-- =====================================================================
CREATE TABLE `hexagoane` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `partida_id`    INT UNSIGNED NOT NULL,
    `q`             TINYINT      NOT NULL,
    `r`             TINYINT      NOT NULL,
    -- Tipul de teren determina ce resursa produce hexagonul:
    --   padure -> lemn,  deal -> piatra, munte -> aur,
    --   camp   -> grau,  pasune -> lana, desert -> nimic
    `terrain`       ENUM('padure', 'deal', 'munte', 'camp', 'pasune', 'desert')
                                 NOT NULL,
    -- Numarul "token" 2..12 (fara 7). NULL pentru desert.
    `numar_token`   TINYINT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_hexagoane_pozitie` (`partida_id`, `q`, `r`),
    KEY `idx_hexagoane_partida` (`partida_id`),
    KEY `idx_hexagoane_numar` (`partida_id`, `numar_token`),
    CONSTRAINT `fk_hexagoane_partida`
        FOREIGN KEY (`partida_id`) REFERENCES `partide`(`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- varfuri - colturile hexagoanelor (pe ele se construiesc asezari)
-- 19 hexagoane => 54 varfuri unice (multe se impart intre 2-3 hexagoane).
-- Stocam coordonate pixel (x, y) pentru afisaj direct in SVG.
-- =====================================================================
CREATE TABLE `varfuri` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `partida_id`    INT UNSIGNED NOT NULL,
    `x`             DECIMAL(8, 2) NOT NULL, -- coordonata pixel pentru render
    `y`             DECIMAL(8, 2) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_varfuri_pozitie` (`partida_id`, `x`, `y`),
    KEY `idx_varfuri_partida` (`partida_id`),
    CONSTRAINT `fk_varfuri_partida`
        FOREIGN KEY (`partida_id`) REFERENCES `partide`(`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- hexagon_varfuri - tabela de jonctiune
-- Fiecare hexagon are 6 varfuri. Fiecare varf apartine de 1-3 hexagoane.
-- Asa stim ce resurse trebuie distribuite cand se arunca zarul.
-- =====================================================================
CREATE TABLE `hexagon_varfuri` (
    `hexagon_id`    INT UNSIGNED NOT NULL,
    `varf_id`       INT UNSIGNED NOT NULL,
    PRIMARY KEY (`hexagon_id`, `varf_id`),
    KEY `idx_hv_varf` (`varf_id`),
    CONSTRAINT `fk_hv_hexagon`
        FOREIGN KEY (`hexagon_id`) REFERENCES `hexagoane`(`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_hv_varf`
        FOREIGN KEY (`varf_id`) REFERENCES `varfuri`(`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- muchii - laturile hexagoanelor (pe ele se construiesc drumuri)
-- O muchie conecteaza intotdeauna 2 varfuri.
-- =====================================================================
CREATE TABLE `muchii` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `partida_id`    INT UNSIGNED NOT NULL,
    `varf_a_id`     INT UNSIGNED NOT NULL,
    `varf_b_id`     INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_muchii_varfuri` (`partida_id`, `varf_a_id`, `varf_b_id`),
    KEY `idx_muchii_a` (`varf_a_id`),
    KEY `idx_muchii_b` (`varf_b_id`),
    CONSTRAINT `fk_muchii_partida`
        FOREIGN KEY (`partida_id`) REFERENCES `partide`(`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_muchii_varf_a`
        FOREIGN KEY (`varf_a_id`) REFERENCES `varfuri`(`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_muchii_varf_b`
        FOREIGN KEY (`varf_b_id`) REFERENCES `varfuri`(`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- asezari - cladirile efective ale jucatorilor pe varfuri
-- tip: 'asezare' (1 prestigiu, 1 resursa la productie)
--      'cetate'  (2 prestigiu, 2 resurse la productie - upgrade dintr-o asezare)
-- =====================================================================
CREATE TABLE `asezari` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `partida_id`    INT UNSIGNED NOT NULL,
    `jucator_id`    INT UNSIGNED NOT NULL,
    `varf_id`       INT UNSIGNED NOT NULL,
    `tip`           ENUM('asezare', 'cetate') NOT NULL DEFAULT 'asezare',
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_asezari_varf` (`partida_id`, `varf_id`),
    KEY `idx_asezari_partida` (`partida_id`),
    KEY `idx_asezari_jucator` (`jucator_id`),
    CONSTRAINT `fk_asezari_partida`
        FOREIGN KEY (`partida_id`) REFERENCES `partide`(`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_asezari_jucator`
        FOREIGN KEY (`jucator_id`) REFERENCES `jucatori`(`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_asezari_varf`
        FOREIGN KEY (`varf_id`) REFERENCES `varfuri`(`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- drumuri - drumurile jucatorilor pe muchii
-- =====================================================================
CREATE TABLE `drumuri` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `partida_id`    INT UNSIGNED NOT NULL,
    `jucator_id`    INT UNSIGNED NOT NULL,
    `muchie_id`     INT UNSIGNED NOT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_drumuri_muchie` (`partida_id`, `muchie_id`),
    KEY `idx_drumuri_partida` (`partida_id`),
    KEY `idx_drumuri_jucator` (`jucator_id`),
    CONSTRAINT `fk_drumuri_partida`
        FOREIGN KEY (`partida_id`) REFERENCES `partide`(`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_drumuri_jucator`
        FOREIGN KEY (`jucator_id`) REFERENCES `jucatori`(`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_drumuri_muchie`
        FOREIGN KEY (`muchie_id`) REFERENCES `muchii`(`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- mutari - istoric cronologic al actiunilor
-- =====================================================================
CREATE TABLE `mutari` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `partida_id`    INT UNSIGNED NOT NULL,
    `jucator_id`    INT UNSIGNED NOT NULL,
    `tip`           ENUM('zar', 'asezare', 'drum', 'cetate', 'paseaza', 'tribut')
                                 NOT NULL,
    `payload_json`  JSON         NULL,
    `mesaj`         VARCHAR(255) NULL,
    `runda`         INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_mutari_partida` (`partida_id`),
    KEY `idx_mutari_jucator` (`jucator_id`),
    CONSTRAINT `fk_mutari_partida`
        FOREIGN KEY (`partida_id`) REFERENCES `partide`(`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_mutari_jucator`
        FOREIGN KEY (`jucator_id`) REFERENCES `jucatori`(`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
