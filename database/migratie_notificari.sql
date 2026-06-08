-- =====================================================================
--  Migrare: notificari asincrone (Pas 2)
--  Ruleaza acest fisier O SINGURA DATA pe baza ta existenta
--  (phpMyAdmin -> selecteaza baza negustorii_cetatii -> tab SQL -> paste -> Go).
--
--  Adauga in ENUM-ul coloanei `mutari.tip` cele 3 tipuri noi de eveniment
--  folosite pentru notificari: alaturare (jucator nou), start (partida pornita)
--  si castig (cineva a castigat). Fara asta, insert-urile noi ar esua.
-- =====================================================================

ALTER TABLE `mutari`
    MODIFY `tip` ENUM('zar', 'asezare', 'drum', 'cetate', 'paseaza', 'tribut',
                      'alaturare', 'start', 'castig') NOT NULL;
