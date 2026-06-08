# language: ro
Functionalitate: Autentificare si control acces (ACL)
  Vreau ca zona de administrare sa fie accesibila doar utilizatorilor
  cu rol de administrator, autentificati cu token.

  Context:
    Date fiind API-ul este pornit

  Scenariu: Login reusit ca administrator
    Cand ma loghez cu utilizatorul "admin" si parola "admin123"
    Atunci primesc codul de status 200
    Si primesc un token de autentificare

  Scenariu: Login esuat cu parola gresita
    Cand ma loghez cu utilizatorul "admin" si parola "gresita"
    Atunci primesc codul de status 401

  Scenariu: Zona de admin e blocata fara token
    Cand accesez lista de utilizatori fara autentificare
    Atunci primesc codul de status 401

  Scenariu: Adminul vede statisticile
    Cand ma loghez cu utilizatorul "admin" si parola "admin123"
    Si cer statisticile cu token-ul curent
    Atunci primesc codul de status 200
    Si raspunsul contine campul "partide"
