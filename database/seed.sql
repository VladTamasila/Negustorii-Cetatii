-- =====================================================================
-- Negustorii Cetatii - seed pentru testare rapida
-- Dupa schema 2.0, harta se genereaza automat de aplicatie cand creezi
-- o partida noua, deci aici nu mai populam manual hexagoane/varfuri/muchii.
-- =====================================================================

-- O partida demo, in asteptare, cu 2 jucatori inscrisi.
INSERT INTO `partide`
    (`id`, `nume`, `status`, `faza`, `jucatori_maxim`, `punctaj_castig`)
VALUES
    (1, 'Cetatea de Lemn', 'in_asteptare', 'in_asteptare', 4, 10);

INSERT INTO `jucatori`
    (`id`, `partida_id`, `nume`, `culoare`, `ordine`)
VALUES
    (1, 1, 'Andrei',  'albastru', 1),
    (2, 1, 'Bianca',  'rosu',     2);
