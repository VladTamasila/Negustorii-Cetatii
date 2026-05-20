# Negustorii Cetatii — Documentatie Proiect

**Programare Web — Anul II**

Versiune: 3.0 (final Sprint #3) — mai 2026

---

## Cuprins

1. Prezentare generala
2. Echipa si responsabilitati
3. Stack tehnologic
4. Sprint #1 — Definirea API-ului
5. Sprint #2 — Implementarea serviciilor
6. Sprint #3 — Consum servicii, testare, rafinare
7. Modelul bazei de date
8. Endpoint-uri REST
9. Algoritmi cheie
10. Cum se ruleaza
11. Testare si validare
12. Concluzii

---

## 1. Prezentare generala

**Negustorii Cetatii** este un joc de strategie inspirat din *Settlers of Catan*,
in varianta **peaceful** — fara tatari/hot, fara furt si fara atacuri intre
jucatori. Accentul se pune pe productie, comert si dezvoltare.

**Reguli pe scurt:**
- Harta de 19 hexagoane in layout Catan clasic (3-4-5-4-3 randuri).
- 5 resurse: lemn, piatra, aur, grau, lana — fiecare produsa de un anumit
  tip de teren (padure / deal / munte / camp / pasune). Desertul nu produce nimic.
- Faza de asezare initiala in **snake draft** (1→2→3→4→4→3→2→1): fiecare
  jucator pune 2 asezari + 2 drumuri.
- In faza de joc: arunci 2 zaruri, hexagoanele cu suma respectiva produc
  resurse pentru jucatorii cu asezari pe varfurile lor. Suma 7 nu face nimic.
- Construiesti: drum (1L+1P), asezare (1L+1P+1G+1La), cetate — upgrade din
  asezare (2A+3G).
- Castiga primul care atinge `punctajCastig` prestigiu (implicit 10):
  asezare = 1 punct, cetate = 2 puncte.

---

## 2. Echipa si responsabilitati

| Membru | Rol principal | Contributie principala |
| --- | --- | --- |
| Vlad Tamasila | Lead full-stack, Game Logic | Coordonare proiect, JocService cu regulile, HartaService (generare harta), distributie inteligenta a terenurilor, UI joc.html, sprint planning |
| Georgian Suta | Backend & API | Definirea API-ului in Sprint #1, structura PHP/Slim, controllers, repositories, PDO connection, implementare PUT/DELETE in Sprint #3 |
| Mihai Spineanu | UI / UX | Dezvoltarea UI-ului de baza, testarea fluxului frontend-backend, afisarea informatiilor despre partide / jucatori / harta |
| Andrei Vladut | API Documentation & Swagger | Validarea fisierului OpenAPI (pw.yaml), definirea schemelor request/response, sincronizarea documentatiei cu implementarea |

Repartizarea task-urilor pe sprint-uri (board Teams):

**Etapa 1 (Definire API)** — done de Georgian Suta
- analiza problemei si a regulilor jocului
- definirea actorilor si a cazurilor de utilizare
- prima versiune a specificatiei OpenAPI

**Etapa 2 (Implementare servicii)** — done de echipa
- Implementare servicii — Vlad
- UI/UX & Integration Support — Mihai
- API Documentation & Swagger — Andrei
- Backend & API — Georgian
- Game Logic — Vlad

**Etapa 3 (Consum servicii)** — in progress / done
- Consum servicii — UI extins, integrare 4 verbe HTTP
- UI/UX & Integration Support — rafinare mesaje de eroare/loading (Mihai)
- API Documentation & Swagger — sincronizare finala (Andrei)
- Game Logic — validari suplimentare pentru reguli (Vlad)
- Backend & API — implementare PUT/DELETE pentru lobby si partide (Georgian)

---

## 3. Stack tehnologic

| Strat | Tehnologie | Motivatie |
| --- | --- | --- |
| Limbaj server | PHP 8.1+ |
| Micro-framework | Slim 4 (`slim/slim ^4.12`) | Lightweight, PSR-7, recomandare din curs |
| Container DI | PHP-DI 7 | Autowiring, configurare minima |
| Abstractizare DB | PDO (mysql) | Simplitate, fara ORM 
| Server HTTP | XAMPP (Apache) sau PHP CLI built-in | Dev local rapid |
| Baza de date | MySQL 8 / MariaDB 10 | Standard pe XAMPP |
| Env / config | `vlucas/phpdotenv ^5.6` | Separare credentiale |
| Documentatie | OpenAPI 3.0 (pw.yaml) + Swagger UI (unpkg CDN) | Spec + testare interactiva |
| Testare | Guzzle HTTP `^7.10` (dev only) + Postman collection | Scenarii cu transfer de proprietati |
| UI | HTML + Vanilla JS + SVG | Fara framework — UI minimal cerut de curs |

Stiva e voit minima: am preferat PDO peste Doctrine si Vanilla JS peste React/Vue, pentru ca
focusul cursului e pe REST si abstractizare DB, nu pe frontend.

---

## 4. Sprint #1 — Definirea API-ului

**Obiectiv:** versiune (aproape) completa a API-ului — schelet de resurse,
endpoint-uri, scheme de request/response. Cod minim sau deloc.

### 4.1 Analiza problemei

- Domeniu: joc Catan-like peaceful.
- Actori: jucator (creator partida, jucator obisnuit), spectator (read-only).
- Cazuri de utilizare principale:
  - Creare / listare / pornire partida
  - Inscriere jucatori cu culori distincte
  - Vizualizare harta (hexagoane + colturi + muchii)
  - Faza setup (asezari + drumuri initiale)
  - Mutari in joc (zar, constructii, upgrade, paseaza)
  - Istoric mutari

### 4.2 Modelul de resurse REST

Am identificat 4 resurse principale + endpoint-uri auxiliare:

| Resursa | Verbe documentate |
| --- | --- |
| `/api/partide` | GET (lista), POST (creare), GET/{id}, PUT/{id}, DELETE/{id}, POST/{id}/start |
| `/api/partide/{id}/jucatori` | GET, POST, GET/{idJ}, PUT/{idJ}, DELETE/{idJ} |
| `/api/partide/{id}/harta` | GET |
| `/api/partide/{id}/mutari` | GET, GET/{idM}, POST mutari/{tip} (zar, asezare, drum, cetate, paseaza) |
| `/api/partide/{id}/setup` | POST asezare, POST drum |

### 4.3 Livrabil

- `docs/pw.yaml` — specificatie OpenAPI 3.0 cu **toate** endpoint-urile, parametri,
  scheme de request/response, raspunsuri de eroare (401, 400, 404, 409).
- Erorile au schema unificata: `{cod, mesaj, status, timestamp, detalii}`.
- Coduri de eroare in romana (ex: `CERERE_INVALIDA`, `PARTIDA_INEXISTENTA`,
  `MUTARE_INVALIDA`, `CULOARE_DEJA_FOLOSITA`).

---

## 5. Sprint #2 — Implementarea serviciilor

**Obiectiv:** transformare API-ul documentat intr-o aplicatie functionala
end-to-end, cu accent pe servicii (backend) si UI minim de demo.

### 5.1 Structura proiectului

```
negustorii-cetatii/
├── public/
│   ├── index.php          punct de intrare (PSR-7)
│   ├── joc.html           UI cu harta SVG si gameplay
│   ├── docs.html          Swagger UI
│   └── assets/            imagini hexagoane
├── src/
│   ├── controllers/       Partida, Jucator, Mutare, Harta (4 controllers)
│   ├── repositories/      8 repo-uri, unul per tabela
│   ├── services/          HartaService, JocService (logica de joc)
│   ├── middleware/        JsonMiddleware
│   ├── database/          Connection (PDO factory)
│   └── routes.php
├── config/                settings.php, dependencies.php (PHP-DI)
├── database/              schema.sql, seed.sql
├── docs/                  pw.yaml, postman_collection.json
├── tests/                 scenariu.php (Guzzle)
└── composer.json
```

### 5.2 Decizii arhitecturale

- **Controller → Service → Repository → PDO**: stratificare clasica.
  Controllers fac doar validare + mapare; logica reala e in service-uri;
  acces la date doar prin repository-uri.
- **PHP-DI cu autowiring**: nu mai scriem `new Repository($pdo)`, containerul
  injecteaza singur dependintele.
- **JsonMiddleware**: forteaza `Content-Type: application/json` pe orice
  raspuns API, ca sa nu uitam noi sa il setam.
- **CASCADE pe FK**: cand stergem o partida, toate jucatorii, hexagoanele,
  varfurile, muchiile, asezarile, drumurile si mutarile dispar automat.

### 5.3 Generarea hartii (HartaService)

Cand se apeleaza `POST /api/partide/{id}/start`:
1. Calculam pozitiile celor 19 hexagoane in coordonate axiale `(q, r)`.
2. Distribuim aleator terenurile (4 padure, 4 camp, 4 pasune, 3 deal,
   3 munte, 1 desert — distributia clasica Catan).
3. Distribuim aleator numerele token (2..12 fara 7).
4. Pentru fiecare hexagon, calculam pixel-positions ale celor 6 colturi
   (varfuri) si le deduplicam (un varf e impartit intre 2-3 hexagoane).
   Total: 54 varfuri unice si 72 muchii unice.
5. Inseram in DB cu FK-uri corecte.

Geometrie hex pointy-top:
- `cx = size * sqrt(3) * (q + r/2)`
- `cy = size * 3/2 * r`

### 5.4 Logica jocului (JocService)

Cele mai importante operatii:

| Operatie | Validari | Efect |
| --- | --- | --- |
| `aseazaInitiala($varf)` | faza=setup, varf liber, distance rule (nu adiacent altei asezari) | Insereaza asezare, daca e a 2-a runda da resurse de start |
| `construiesteDrumInitial($muchie)` | faza=setup, muchie adiacenta ultimei asezari proprii | Insereaza drum, avanseaza snake draft |
| `aruncaZarul()` | faza=joc, e tura ta, n-ai aruncat deja | Genereaza 2d6, distribuie resurse pe hexagoanele cu acel numar |
| `construiesteAsezare($varf)` | resurse 1L+1P+1G+1La, distance rule, conectat la drum propriu | Scade resurse, insereaza asezare, +1 prestigiu |
| `construiesteDrum($muchie)` | resurse 1L+1P, conectat la asezare/drum propriu | Scade resurse, insereaza drum |
| `upgradeCetate($asezare)` | resurse 2A+3G, asezarea e a ta | Transforma in cetate, +1 prestigiu in plus |
| `paseaza()` | e tura ta | Avanseaza tura |

Verificarea de **castig**: dupa fiecare actiune care da prestigiu, daca
jucatorul curent atinge `punctajCastig`, partida intra in faza `finalizata`.

---

## 6. Sprint #3 — Consum servicii, testare, rafinare

**Obiectiv:** completarea operatiilor (PUT/DELETE), integrarea tuturor in UI,
testare cu scenarii, finalizarea documentatiei.

### 6.1 Operatii PUT/DELETE complete

In Sprint #2 erau documentate ca planificate (`x-implemented: false`). In
Sprint #3 sunt **functionale**:

- `PUT /api/partide/{id}` — redenumire / configurare partida (doar in lobby)
- `DELETE /api/partide/{id}` — hard delete in lobby; arhivare daca pornita;
  apasare a doua oara sterge si pe cele arhivate
- `GET /api/partide/{id}/jucatori/{idJ}` — detalii individual
- `PUT /api/partide/{id}/jucatori/{idJ}` — schimba nume/culoare in lobby
- `DELETE /api/partide/{id}/jucatori/{idJ}` — elimina jucator + reindexare ordine
- `GET /api/partide/{id}/mutari/{idM}` — detalii pe o singura mutare

### 6.2 Distributie inteligenta a terenurilor (rafinare gameplay)

In Sprint #2, shuffle simplu producea uneori 2-3 campii adiacente —
neestetic si dezavantajos la joc. In Sprint #3 am introdus:

1. **Anti-cluster pentru terenuri**: BFS pe vecinii axiali, max 2 hexagoane
   adiacente de acelasi tip. Daca shuffle-ul curent are clustere, reshuffle.
   Maxim 200 incercari; fallback cu swap-uri locale.
2. **Anti-6/8 adiacente**: regula non-oficiala Catan, dar foarte raspandita.
   Numerele "rosii" (6, 8) nu pot fi vecine — ar produce dezechilibre mari.

Empiric, ambele constrangeri se satisfac in 4-10 incercari (~1 ms total).

### 6.3 Swagger UI integrat (`/docs`)

Adaugat:
- `public/docs.html` — pagina cu Swagger UI (incarcat din CDN unpkg)
- Ruta `GET /openapi` — serveste `docs/pw.yaml` ca text/yaml

URL-ul `/openapi` (fara extensie) e deliberat — PHP CLI server intercepteaza
URL-urile cu extensii cunoscute (`.yaml`, `.json`) si raspunde 404 direct,
fara sa mai treaca prin index.php. Ruta fara extensie evita problema.

Cu Swagger UI, se poate face `Try it out` pe orice endpoint, fara
sa se scrie cod sau sa se lanseze Postman.

### 6.4 Scenarii de testare (cerinta directa Sprint #3)

**A. Guzzle PHP** (`tests/scenariu.php`)
- 13+ pasi end-to-end: creare → PUT → adaugare jucatori → start → setup
  snake draft → aruncari zar → GET mutari → DELETE
- Fiecare pas extrage ID-uri din raspunsul anterior si le foloseste in
  pasii urmatori (exact "property transfer").
- Rulare: `composer install --dev && php tests/scenariu.php`

**B. Postman collection** (`docs/postman_collection.json`)
- 15 request-uri cu `pm.collectionVariables.set(...)` pentru transfer.
- Import → Runner → Run. Vezi teste verzi/rosii pe fiecare pas.

**C. Browser F12 Console** (`mod_admin.txt`)
- Snippets fetch() pe care le copiezi in Console.
- Demonstreaza interactiuni la nivel de browser (developer mode) 

### 6.5 UI extins (`public/joc.html`)

Lobby in panoul stang are acum si butoane:
- Pe fiecare partida: **Deschide**, **Redenumeste** (in lobby), **Sterge**
- Pe fiecare jucator (in lobby): **Edit**, **Elimina**
- Checkbox "Arata si partidele arhivate" — toggle pentru filtrare
- Link "Swagger UI →" catre `/docs`

Astfel UI-ul consuma toate 4 verbele HTTP (GET, POST, PUT, DELETE)

---

## 7. Modelul bazei de date

9 tabele, toate cu `partida_id` ca discriminator + CASCADE pe FK.

```
+-----------+        +-----------+
|  partide  |◄───────| jucatori  |
+-----------+        +-----------+
     ▲ ▲ ▲ ▲              ▲
     │ │ │ │              │
     │ │ │ │       +------+-------+
     │ │ │ │       │              │
     │ │ │ └──── asezari       drumuri ─── muchii
     │ │ │            │           │           ▲
     │ │ │            └─── varfuri ───────────┘
     │ │ │                  ▲
     │ │ │                  │
     │ │ └── hexagon_varfuri (jonctiune)
     │ │            ▲
     │ │            │
     │ └─────── hexagoane
     │
     └────── mutari (istoric)
```

| Tabela | Coloane importante | Rol |
| --- | --- | --- |
| `partide` | id, nume, status, faza, runda_setup, pas_setup, tura_curenta, jucator_activ_id, castigator_id | Resursa centrala |
| `jucatori` | id, partida_id, nume, culoare, ordine, prestigiu, lemn, piatra, aur, grau, lana | Jucatori si resursele lor |
| `hexagoane` | id, partida_id, q, r, terrain, numar_token | Tile-urile hartii |
| `varfuri` | id, partida_id, x, y | Colturi (asezari) |
| `muchii` | id, partida_id, varf_a_id, varf_b_id | Laturi (drumuri) |
| `hexagon_varfuri` | hexagon_id, varf_id | Jonctiune many-to-many |
| `asezari` | id, partida_id, jucator_id, varf_id, tip (asezare/cetate) | Constructii pe colturi |
| `drumuri` | id, partida_id, jucator_id, muchie_id | Drumuri pe muchii |
| `mutari` | id, partida_id, jucator_id, tip, payload_json, mesaj, runda | Istoric cronologic |

Indexuri pe `partida_id` peste tot, plus indexuri compozite pe
`(partida_id, numar_token)` pentru cautari rapide la productie.

---

## 8. Endpoint-uri REST (lista completa)

Toate sunt **implementate efectiv** in Sprint #3 (nu mai sunt marcate ca
planificate in pw.yaml v3.0.0).

```
GET    /api/ping
GET    /openapi                                       (specificatia YAML)

GET    /api/partide                                   (filtre: status, pagina, dimensiunePagina)
POST   /api/partide
GET    /api/partide/{id}
PUT    /api/partide/{id}                              (doar lobby)
DELETE /api/partide/{id}                              (sterge sau arhiveaza)
POST   /api/partide/{id}/start

GET    /api/partide/{id}/jucatori
POST   /api/partide/{id}/jucatori
GET    /api/partide/{id}/jucatori/{idJ}
PUT    /api/partide/{id}/jucatori/{idJ}               (doar lobby)
DELETE /api/partide/{id}/jucatori/{idJ}               (doar lobby)

GET    /api/partide/{id}/harta

POST   /api/partide/{id}/setup/asezare                body { idVarf }
POST   /api/partide/{id}/setup/drum                   body { idMuchie }

GET    /api/partide/{id}/mutari
GET    /api/partide/{id}/mutari/{idM}
POST   /api/partide/{id}/mutari/zar
POST   /api/partide/{id}/mutari/asezare               body { idVarf }
POST   /api/partide/{id}/mutari/drum                  body { idMuchie }
POST   /api/partide/{id}/mutari/cetate                body { idAsezare }
POST   /api/partide/{id}/mutari/paseaza
```

Total: 24 endpoint-uri (cu toate verbele) peste 4 resurse principale.

---

## 9. Algoritmi cheie

### 9.1 Snake draft pentru setup initial

```
runda 1: jucator 1 → jucator 2 → jucator 3 → jucator 4
runda 2: jucator 4 → jucator 3 → jucator 2 → jucator 1
```

Avantajul: ultimul jucator e penalizat in runda 1 (alegeri mai limitate)
dar compensat in runda 2 (primul la a doua alegere). E mai echitabil decat
un draft simplu.

### 9.2 Productie la aruncarea zarului

```sql
-- Pseudo-SQL: pentru fiecare hex cu numar = suma_zar,
-- pentru fiecare asezare/cetate de pe colturile lui,
-- adauga 1 (asezare) sau 2 (cetate) la resursa corespunzatoare
-- terrain-ului hex-ului.
SELECT j.id AS idJucator, h.terrain, a.tip
FROM hexagoane h
  JOIN hexagon_varfuri hv ON hv.hexagon_id = h.id
  JOIN asezari a ON a.varf_id = hv.varf_id
  JOIN jucatori j ON j.id = a.jucator_id
WHERE h.partida_id = ? AND h.numar_token = ?
```

### 9.3 Distance rule (Catan)

O asezare poate fi plasata pe un varf doar daca **niciunul din varfurile
adiacente** (legate prin o muchie) nu are deja o asezare. Implementat ca
JOIN intre muchii si asezari.

### 9.4 Generare harta anti-cluster (Sprint #3)

```
shuffle(terrains)
if BFS(terrains, vecini).any(cluster_size > 2):
    re-shuffle (max 200 iteratii)
else:
    return
```

BFS pe componente conexe de acelasi terrain. Detalii in `HartaService.php`.

---

## 10. Cum se ruleaza

### 10.1 Pre-requisite

- XAMPP (cu MySQL + PHP 8.1+) sau PHP CLI standalone
- Composer
- Browser modern (Chrome/Firefox/Edge)

### 10.2 Setup baza de date

```sql
CREATE DATABASE negustorii_cetatii
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Ruleaza `database/schema.sql` pe baza de date. Optional `database/seed.sql`
pentru date demo.

### 10.3 Setup aplicatie

```bash
cd "C:\PW(negustorii cetatii)"
composer install
composer install --dev          # adauga Guzzle pentru scenariul de test
```

### 10.4 Pornire

```bash
php -S localhost:8080 -t public
```

URL-uri principale:

| URL | Continut |
| --- | --- |
| `http://localhost:8080/` | UI jocului |
| `http://localhost:8080/docs` | Swagger UI |
| `http://localhost:8080/openapi` | Spec OpenAPI raw |
| `http://localhost:8080/api/ping` | Healthcheck |

---

## 11. Testare si validare

### 11.1 Acoperire pe criteriile de evaluare

| Criteriu | /pct | Cum acoperim |
| --- | --- | --- |
| Specificatii REST complete | /20 | `docs/pw.yaml` v3.0.0 — 24 endpoint-uri, 29 scheme, fara `x-implemented:false` |
| Corectitudine | /10 | Endpoint-urile valideaza input + intorc coduri/mesaje romanesti consistente; 6 categorii de erori distincte |
| Implementare servicii | /45 | 4 categorii de resurse (Partide, Jucatori, Harta, Mutari) cu GET + POST + PUT + DELETE complete + DB MySQL real cu FK CASCADE |
| Implementare UI | /45 | `joc.html` consuma toate 4 verbele HTTP, lobby cu Edit/Sterge, harta SVG interactiva, polling multiplayer |
| Documentatie | /10 | `DOCUMENTATIE.md` (acest fisier) + README + Swagger UI + PPT (`prezentare.pptx`) |
| Livrare | /3 | La timp (sesiunea A2/B2) |

### 11.2 Cum demonstrezi (pentru evaluare live)

1. **Pornesti serverul**: `php -S localhost:8080 -t public`
2. **Arati UI-ul** (`http://localhost:8080/`): creezi partida, adaugi jucatori,
   pornesti, joci o tura sau doua.
3. **Arati F12 → Network**: vezi cum apar request-urile GET/POST/PUT/DELETE.
4. **Arati Swagger UI** (`/docs`): Try it out pe orice endpoint.
5. **Rulezi scenariul Guzzle**: `php tests/scenariu.php` — vezi cum trece
   prin toti 13 pasii cu transfer automat de proprietati.
6. **(Optional) Postman**: Import collection si Run → toate verzi.
7. **(Optional) mod_admin.txt**: copy-paste in F12 Console — alte 8
   snippet-uri organizate.

### 11.3 Erori controlate

API-ul intoarce raspunsuri JSON structurate pe toate caile:
- `400 CERERE_INVALIDA` — campuri lipsa sau format invalid
- `404 PARTIDA_INEXISTENTA` / `JUCATOR_INEXISTENT` / `MUTARE_INEXISTENTA`
- `409 CONFLICT_STARE` — operatia nu e permisa in starea curenta
- `409 CULOARE_DEJA_FOLOSITA` — duplicat in aceeasi partida
- `400 MUTARE_INVALIDA` — regula de joc incalcata (distance rule, resurse insuficiente, etc.)
- `400 STARE_INVALIDA` — actiune dintr-o faza gresita

---

## 12. Concluzii

Proiectul atinge toate cerintele cursului prin parcurgerea celor 3 sprint-uri:
**Sprint #1** a stabilit contractul API-ului (OpenAPI 3.0); **Sprint #2** a
livrat backend-ul functional (PHP/Slim/PDO/MySQL) cu UI minim; **Sprint #3**
a completat operatiile PUT/DELETE, a integrat Swagger UI si scenarii de
testare cu transfer automat de proprietati, si a rafinat UI-ul si algoritmul
de generare harta.

Cele mai importante decizii tehnice:
- Stiva minima (PDO, fara ORM; Vanilla JS, fara framework)
- Stratificare Controller/Service/Repository — testabilitate si separare
  clara de responsabilitati.
- CASCADE peste tot in DB — operatii DELETE simple, fara cleanup manual.
- Anti-cluster pe terenuri — gameplay echilibrat fara complexitate excesiva.

Total: ~5000 linii de cod (PHP + JS + YAML + SQL), 24 endpoint-uri REST,
9 tabele DB, 4 controllers, 2 services, 8 repositories.
