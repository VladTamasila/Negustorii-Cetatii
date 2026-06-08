# language: ro
Functionalitate: Gestionarea partidelor
  Ca jucator vreau sa pot crea si configura partide prin API,
  ca sa pot incepe un joc cu prietenii.

  Context:
    Date fiind API-ul este pornit

  Scenariu: Creez o partida si adaug doi jucatori
    Cand creez o partida cu numele "Cetatea Gherkin"
    Atunci primesc codul de status 201
    Cand adaug jucatorul "Vlad" cu culoarea "albastru"
    Si adaug jucatorul "Bianca" cu culoarea "rosu"
    Atunci partida are 2 jucatori

  Scenariu: Pornesc o partida cu doi jucatori
    Cand creez o partida cu numele "Cetatea de start"
    Si adaug jucatorul "Ana" cu culoarea "verde"
    Si adaug jucatorul "Dan" cu culoarea "galben"
    Si pornesc partida
    Atunci faza partidei este "asezare_initiala"

  Scenariu: Nu pot crea o partida fara nume
    Cand creez o partida fara nume
    Atunci primesc codul de status 400
