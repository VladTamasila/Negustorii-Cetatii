-- =====================================================================
--  Migrare: autentificare + ACL (Pas 3)
--  Ruleaza acest fisier O SINGURA DATA pe baza ta existenta
--  (phpMyAdmin -> baza negustorii_cetatii -> tab SQL -> paste -> Go).
--
--  Adauga:
--   - tabelul `utilizatori` (cont cu rol: admin sau jucator)
--   - tabelul `sesiuni`     (token de login, cu expirare)
--   - un cont de admin implicit:  utilizator = admin / parola = admin123
--     (parola e stocata ca hash bcrypt, niciodata in clar)
-- =====================================================================

DROP TABLE IF EXISTS `sesiuni`;
DROP TABLE IF EXISTS `utilizatori`;

-- utilizatori - conturile care se pot loga (ACL pe baza de rol)
CREATE TABLE `utilizatori` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `utilizator`   VARCHAR(40)  NOT NULL,                 -- numele de login (unic)
    `parola_hash`  VARCHAR(255) NOT NULL,                 -- hash bcrypt, nu parola in clar
    `rol`          ENUM('admin', 'jucator') NOT NULL DEFAULT 'jucator',
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_utilizator` (`utilizator`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sesiuni - token-ele active de autentificare (Bearer token)
CREATE TABLE `sesiuni` (
    `token`         CHAR(64)     NOT NULL,                -- token aleator (hex de 32 octeti)
    `utilizator_id` INT UNSIGNED NOT NULL,
    `expira_la`     DATETIME     NOT NULL,                -- dupa aceasta data token-ul nu mai e valid
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`token`),
    KEY `idx_sesiuni_utilizator` (`utilizator_id`),
    CONSTRAINT `fk_sesiuni_utilizator`
        FOREIGN KEY (`utilizator_id`) REFERENCES `utilizatori`(`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cont de admin implicit (parola: admin123). Schimba parola dupa primul login!
INSERT INTO `utilizatori` (`utilizator`, `parola_hash`, `rol`) VALUES
    ('admin', '$2y$10$XFZ6tllzi5l7wzrSH2X/l.XsvkWqatWo3UCl5sMrq6vvuxnEeH.ri', 'admin');
