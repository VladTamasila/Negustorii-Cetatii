# language: ro
Functionalitate: Notificari asincrone
  Dupa evenimente importante (alaturare, start), feed-ul de notificari
  trebuie sa le contina, ca jucatorii sa fie anuntati fara reload.

  Context:
    Date fiind API-ul este pornit

  Scenariu: Alaturarea jucatorilor si startul genereaza notificari
    Cand creez o partida cu numele "Cetatea cu notificari"
    Si adaug jucatorul "Mia" cu culoarea "albastru"
    Si adaug jucatorul "Rad" cu culoarea "rosu"
    Si pornesc partida
    Atunci feed-ul de notificari contine cel putin 3 evenimente
