-- =====================================================================
--  Migrare: componenta sociala - chat pe partida (Pas 5A)
--  Ruleaza acest fisier O SINGURA DATA pe baza ta existenta.
--
--  Adauga tabelul `mesaje_chat`: mesajele scrise de jucatori in timpul
--  unei partide (componenta sociala). Fiecare mesaj apartine unei partide.
-- =====================================================================

DROP TABLE IF EXISTS `mesaje_chat`;

CREATE TABLE `mesaje_chat` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `partida_id` INT UNSIGNED NOT NULL,
    `autor`      VARCHAR(40)  NOT NULL,
    `text`       VARCHAR(500) NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_chat_partida` (`partida_id`),
    CONSTRAINT `fk_chat_partida`
        FOREIGN KEY (`partida_id`) REFERENCES `partide`(`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
