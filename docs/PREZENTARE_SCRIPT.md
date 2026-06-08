# Script prezentare — Sprint #4 (5 minute, 4 vorbitori)

Deck-ul: `docs/prezentare_sprint4.pptx` (9 slide-uri).
Idee de baza: **Sprint #3 era complet; Sprint #4 tinteste maximul pe barem.**
Fiecare functie e legata explicit de un criteriu — spune mereu criteriul cu voce tare.

Reguli rapide ca sa sune bine:
- Spune intai **ce face**, apoi **ce punct ia pe barem**. (ex: "notificari async — astea urca REST impl2 de la 2 la 6 puncte".)
- Nu citi slide-ul cuvant cu cuvant; slide-ul e suport, tu spui povestea.
- Tine fraza-cheie de la fiecare parte (e scrisa cu **bold** mai jos) — aia e ce vrei sa ramana.
- Total ~5 min: fiecare ~1 min 15 sec. Maxim 2 min/persoana.

---

## Vorbitor 1 — Vlad (Lead / Game Logic) · slide-urile 1–4 · ~1.5 min

**Slide 1 (titlu) + Slide 2 (overview):**
- "Salut, suntem echipa Negustorii Cetatii. In Sprint #3 aplicatia era deja completa: API REST, UI cu harta, Swagger, teste. In Sprint #4 ne-am uitat pe barem si am tintit fix criteriile unde mai puteam lua puncte."
- Arata grila de 6 directii: "ngrok, notificari async, auth si ACL, chat, SOAP si template, plus testare si documentatie. Estimat, vreo 28 de puncte in plus."

**Slide 3 (ngrok):**
- "Prima cerinta: sa mearga din toata lumea, nu doar pe localhost. Am folosit ngrok — un tunel care da serverului local o adresa publica HTTPS."
- "Important: zero modificari de cod, pentru ca UI-ul foloseste URL-uri relative. Un singur fix, un header pentru pagina de avertisment ngrok."
- **Fraza-cheie: "Totul trece exclusiv printr-un server HTTP — exact ce cere baremul la expunerea operatiilor."**

**Slide 4 (notificari asincrone):**
- "Pana acum, comunicarea era doar sincrona: ceri, primesti. Am adaugat un feed de evenimente: cand un jucator face o mutare, ceilalti primesc o notificare live, prin polling, fara reload."
- **Fraza-cheie: "Asta e schimb asincron de mesaje — urca criteriul REST impl2 de la 2 la 6 puncte."**

---

## Vorbitor 2 — Georgian (Backend & API) · slide-urile 5 + 7 · ~2 min

**Slide 5 (autentificare + ACL + admin):**
- "Am adaugat autentificare cu token Bearar si roluri: admin si jucator. Parolele sunt stocate ca hash bcrypt, niciodata in clar."
- "Controlul accesului se face cu doua middleware: unul verifica cine esti (token valid sau 401), altul verifica daca ai voie — doar adminul intra pe zona /api/admin, altfel 403."
- "Adminul are un panou separat: statistici, management de utilizatori si partide."
- **Fraza-cheie: "Asta acopera doua criterii: componenta de administrare si ACL cu utilizatori multipli."**

**Slide 7 (SOAP):**
- "Pe langa REST, am expus aceleasi date si printr-un protocol alternativ — SOAP — cu operatii ca numarPartide sau listaPartide."
- **Fraza-cheie: "Acelasi domeniu, alt protocol — ceea ce arata ca logica e separata de transport."**

---

## Vorbitor 3 — Mihai (UI / UX & Integration) · slide 6 + demo live · ~1.5 min

**Slide 6 (chat social):**
- "Pe partea sociala am adaugat un chat live in fiecare partida — mesaje in timpul jocului, livrate prin acelasi mecanism de polling."
- "Detaliu important de securitate: autorul mesajului e luat de server din contul logat, nu din ce trimite clientul. Asa nimeni nu poate scrie sub numele altcuiva."
- "Si ca sa nu depindem de admin, jucatorii isi fac singuri cont — inregistrare si login direct din joc."
- **Fraza-cheie: "Componenta sociala, peste acelasi API REST."**

**Demo live (optional, 20–30 sec):**
- Deschide jocul prin linkul ngrok pe doua ferestre (sau pe telefon). Logheaza-te cu doua conturi, fa o mutare intr-una si arata notificarea + un mesaj de chat care apare in cealalta.

---

## Vorbitor 4 — Andrei (Docs & Testing) · slide-urile 8 + 9 · ~1.5 min

**Slide 8 (testare + documentatie):**
- "Pentru testare am scris scenarii in Gherkin, in romana — dat fiind / cand / atunci — si un runner propriu care le executa automat pe API-ul real. Ruleaza cu o comanda si da 8 din 8 verde."
- **Fraza-cheie: "Asta urca criteriul de testare de la 4 la 6 puncte, pentru ca folosim un limbaj de testare."**
- "Tot ce am adaugat e si documentat: OpenAPI actualizat, vizibil in Swagger la /docs, plus un ghid care leaga fiecare functie de barem."

**Slide 9 (bilant) + template-ing:**
- "Inca o functie: un mic motor de template-ing integrat in REST — aceeasi resursa partida care prin API vine ca JSON e randata si ca pagina HTML."
- Arata tabelul: "Pe scurt: async 2 la 6, admin si ACL plus 8, testare 4 la 6, social plus protocol plus template inca 10. Vreo 28 de puncte, totul doar in PHP."
- **Inchidere: "Acelasi joc, acum accesibil din toata lumea, securizat, social si testat automat. Multumim!"**

---

## Intrebari probabile + raspunsuri scurte

**De ce polling si nu WebSocket / SSE?**
Serverul PHP de dezvoltare e single-thread; o conexiune SSE deschisa ar bloca restul cererilor. Polling-ul e robust si suficient. Ideea de async se pastreaza: clientul afla actiunile altora fara sa ceara acea actiune anume.

**Cum garantati ca un jucator nu scrie sub numele altuia in chat?**
Autorul vine din token, pe server — ruta de trimitere e protejata de middleware-ul de autentificare. Clientul nu poate falsifica numele.

**Ce inseamna concret ACL aici?**
Control acces pe baza de rol. Middleware-ul de admin lasa pe /api/admin doar conturile cu rol admin; un jucator obisnuit primeste 403.

**De ce SOAP, daca aveti deja REST?**
E pentru criteriul de protocol alternativ. Demonstreaza ca aceeasi logica de domeniu poate fi expusa prin doua protocoale diferite.

**In ce sens e template-ing-ul "integrat in REST"?**
Aceeasi resursa — partida — e servita ca JSON prin API si ca pagina HTML prin template, la /partide/{id}/rezumat.

**E sigur sa expuneti aplicatia prin ngrok?**
E un tunel temporar pentru demo. Serverul si baza de date raman locale; pentru productie s-ar folosi o gazduire reala. Pentru prezentare, e exact ce trebuie.

**De ce nu un framework de auth gata facut (Laravel etc.)?**
Cerinta stack-ului e PHP cu Slim. Am implementat token plus bcrypt direct — simplu, transparent si usor de explicat.

**Cum rulati testele?**
`php tests/gherkin.php` cu serverul pornit. Scenariile Gherkin se executa automat pe API si dau cod de iesire (0 = tot verde), deci se pot baga si intr-un pipeline.
