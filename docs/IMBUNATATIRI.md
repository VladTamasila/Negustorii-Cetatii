# Imbunatatiri (Sprint #4) - ghid pentru prezentare

Acest document rezuma ce s-a adaugat fata de Sprint #3 si cum se mapeaza fiecare
lucru pe baremul de notare. Pentru fiecare functie ai: ce e, cum o testezi si
cum o explici la prezentare.

> **Inainte de toate**, ruleaza cele 3 migrari noi pe baza ta existenta
> (phpMyAdmin -> baza `negustorii_cetatii` -> tab SQL -> paste -> Go):
>
> 1. `database/migratie_notificari.sql`
> 2. `database/migratie_auth.sql`
> 3. `database/migratie_social.sql`
>
> (Pe o baza noua nu e nevoie - sunt deja in `database/schema.sql`.)

---

## 1. Server accesibil din toata lumea (ngrok)

**Ce e:** aplicatia poate fi expusa public printr-un tunel ngrok, fara gazduire.

**Cum testezi:** vezi `docs/GHID_SERVER_NGROK.md`. Pe scurt: pornesti
`pornire-server.bat`, apoi `pornire-ngrok.bat`, si dai linkul `...ngrok-free.app`.

**Cum explici:** "Serverul HTTP local e expus printr-un tunel securizat; oricine
din lume poate accesa jocul, util pentru demo si testare multi-jucator."

Barem: sustine **Expunere operatii (HTTP)** si demonstreaza ca totul merge
exclusiv printr-un server HTTP.

---

## 2. Notificari asincrone  → criteriul "REST impl2" (de la 2p la 6p)

**Ce e:** un feed de evenimente pe care clientii il interogheaza prin polling.
Cand un jucator face o mutare / se alatura / porneste partida / castiga, ceilalti
sunt anuntati automat prin toast-uri, fara reload.

**Endpoint:** `GET /api/partide/{id}/notificari?dupa={ultimulId}`

**Cum testezi:** deschide aceeasi partida in 2 ferestre; o actiune intr-una
apare ca notificare in cealalta in cateva secunde.

**Cum explici:** "Comunicarea nu mai e doar request-raspuns sincron; clientii
primesc notificari despre actiunile altora printr-un feed de evenimente, deci
e schimb asincron de mesaje (modelat prin polling)."

---

## 3. Autentificare + ACL si panou admin → criteriile "Componente admin" si "Componente ACL"

**Ce e:** login cu token (Bearer), roluri `admin` / `jucator`, si o zona de
administrare protejata. Pagina reala de admin la `/admin`.

**Endpoint-uri:** `POST /api/auth/login`, `POST /api/auth/logout`,
`GET /api/auth/eu`, `GET /api/admin/statistici`, `GET/POST /api/admin/utilizatori`,
`DELETE /api/admin/utilizatori/{id}`.

**Cont implicit:** `admin` / `admin123`.

**Cum testezi:** deschide `/admin`, logheaza-te, vezi statistici si gestioneaza
utilizatori/partide. Pentru ACL: in consola, `fetch('/api/admin/utilizatori')`
fara token -> **401**; cu un cont de rol "jucator" -> **403**.

**Cum explici:** "Autentificare cu token si control al accesului pe baza de rol:
adminul are o zona separata cu drepturi pe care un jucator obisnuit nu le are.
Doua middleware-uri: unul verifica token-ul, altul verifica rolul."

---

## 4. Teste Gherkin → criteriul "Testare" (de la 4p la 6p)

**Ce e:** scenarii `.feature` scrise in Gherkin (romana) + un runner care le
ruleaza automat pe API.

**Fisiere:** `features/*.feature`, runner `tests/gherkin.php`.

**Cum testezi:** cu serverul pornit, ruleaza `php tests/gherkin.php`
(sau `composer gherkin`). Vezi "X / 8 scenarii au trecut".

**Cum explici:** "Comportamentul API-ului e descris in Gherkin, limbaj natural
structurat, si verificat automat. Acelasi format poate fi rulat si cu Behat."

---

## 5. Componente sociale, SOAP si template-ing → criteriile "Others (social)", "Others (protocol)", "Plus! (template)"

### 5a. Chat (social)
**Ce e:** chat live intre jucatorii dintr-o partida.
**Endpoint:** `GET/POST /api/partide/{id}/chat`. Panou de chat in joc.
**Cum testezi:** deschide partida in 2 ferestre, scrie in chat intr-una.
**Cum explici:** "Componenta sociala: jucatorii comunica in timp real in cadrul
partidei."

### 5b. SOAP (protocol alternativ)
**Ce e:** un endpoint SOAP peste aceleasi date, pe langa REST.
**Endpoint:** `/soap` (info la GET, apeluri prin POST). Operatii:
`numarPartide`, `listaPartide`, `detaliiPartida`.
**Cum testezi:** deschide `/soap`; ruleaza `php tests/soap_client.php`.
(Necesita extensia `php-soap`; de obicei activa in XAMPP.)
**Cum explici:** "Acelasi domeniu expus si printr-un protocol alternativ (SOAP),
nu doar REST."

### 5c. Template-ing integrat in REST
**Ce e:** un motor mic de template-ing (stil Handlebars: `{{ var }}` si
`{{#each}}`) care randeaza o resursa REST ca pagina HTML.
**Endpoint:** `GET /partide/{id}/rezumat` (reprezentarea HTML a partidei).
**Fisiere:** `src/services/Template.php`, `templates/rezumat_partida.html`.
**Cum testezi:** deschide `http://localhost:8080/partide/1/rezumat`.
**Cum explici:** "Aceeasi resursa care prin API vine ca JSON e randata si ca
pagina HTML printr-un sistem de template-ing, integrat in operatiile REST."

---

## Recapitulare endpoint-uri noi

| Metoda | Ruta | Rol |
| --- | --- | --- |
| GET | `/api/partide/{id}/notificari` | feed notificari (polling) |
| GET/POST | `/api/partide/{id}/chat` | chat (social) |
| POST | `/api/auth/login` | login -> token |
| POST | `/api/auth/logout` | logout |
| GET | `/api/auth/eu` | utilizatorul curent |
| GET | `/api/admin/statistici` | statistici (admin) |
| GET/POST | `/api/admin/utilizatori` | utilizatori (admin) |
| DELETE | `/api/admin/utilizatori/{id}` | sterge utilizator (admin) |
| GET/POST | `/soap` | protocol alternativ SOAP |
| GET | `/partide/{id}/rezumat` | reprezentare HTML (template-ing) |
| GET | `/admin` | panou de administrare |

Toate endpoint-urile JSON noi sunt si in `docs/pw.yaml` (Swagger UI la `/docs`).
