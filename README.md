# Negustorii Cetatii - Final Sprint (Catan-like)

Joc tip Catan **peaceful** (fara hot, fara furt, fara atacuri).

Stack: PHP 8.1+ / Slim 4 / PHP-DI / PDO / MySQL (XAMPP) + Guzzle (testare).

---

## Noutati (Final Sprint)

Fata de Sprint #3 s-au adaugat: server public prin **ngrok**, **notificari
asincrone**, **autentificare + ACL** cu panou de **admin**, **teste Gherkin**,
**chat** (social), endpoint **SOAP** si **template-ing** integrat in REST.

Detalii complete + cum se testeaza/prezinta fiecare: vezi
[`docs/IMBUNATATIRI.md`](docs/IMBUNATATIRI.md).

**Important:** ruleaza o data migrarile pe baza existenta (in phpMyAdmin):
`database/migratie_notificari.sql`, `database/migratie_auth.sql`,
`database/migratie_social.sql`. (Pe o baza noua nu e nevoie - sunt deja in
`schema.sql`.) Cont admin implicit: `admin` / `admin123` (la `/admin`).

---

## 1. Reguli pe scurt

- 19 hexagoane in stil Catan (3-4-5-4-3 randuri).
- 5 resurse: **lemn**, **piatra**, **aur**, **grau**, **lana**.
- Terenuri: padure -> lemn, deal -> piatra, munte -> aur, camp -> grau,
  pasune -> lana, desert -> nimic.
- Distributia terenurilor este **inteligent randomizata**: maxim 2
  hexagoane adiacente de acelasi tip (fara campii lipite intre ele) si
  numerele "rosii" (6/8) nu cad niciodata vecine.
- Numere "token" pe hexagoane: 2,3,4,5,6,8,9,10,11,12 (fara 7).

**Faza de asezare initiala (snake draft):**
- Fiecare jucator pune 2 asezari + 2 drumuri, in ordine 1-2-3-4 apoi 4-3-2-1.
- A doua asezare aduce resurse de start - cate 1 din fiecare teren adiacent.

**Faza de joc normala:**
- Jucatorul activ arunca 2 zaruri.
- Toate hexagoanele cu suma respectiva produc - jucatorii cu asezari pe
  varfurile lor primesc 1 resursa (cetatea = 2 resurse).
- Suma 7 nu produce nimic (fara hot in varianta noastra).
- Jucatorul poate construi: drum (1L+1P), asezare (1L+1P+1G+1La),
  upgrade cetate (2A+3G).
- Apoi paseaza tura urmatorului.

**Castig:** primul care atinge `punctajCastig` prestigiu (implicit 10).
- Asezare = 1 prestigiu, cetate = 2 prestigiu.

---

## 2. Instalare (pas cu pas)

### 2.1. Pre-requisite

- **XAMPP** instalat (cu MySQL si PHP 8.1+) sau PHP CLI standalone.
- **Composer** instalat (https://getcomposer.org/download/).

### 2.2. Setup baza de date

1. Porneste MySQL (din XAMPP Control Panel - Apasa Start la MySQL).
2. Deschide phpMyAdmin sau DataGrip si creeaza baza:
   ```sql
   CREATE DATABASE negustorii_cetatii
     DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Ruleaza `database/schema.sql` pe aceasta baza.
4. Optional: ruleaza `database/seed.sql` pentru o partida demo.

### 2.3. Setup aplicatie

```bash
cd "C:\PW(negustorii cetatii)"
composer install
# pentru scenariul de testare cu Guzzle:
composer install --dev
```

Daca primesti eroare "extension pdo_mysql not found", deschide `php.ini`
din XAMPP (Apache -> Config -> php.ini) si decomenteaza linia
`extension=pdo_mysql`.

### 2.4. Pornire server

```bash
php -S localhost:8080 -t public
```

Deschide in browser:

| URL | Ce face |
| --- | --- |
| `http://localhost:8080/` | UI-ul jocului (harta SVG, lobby, gameplay) |
| `http://localhost:8080/docs` | **Swagger UI** - documentatie interactiva, testare GET/POST/PUT/DELETE |
| `http://localhost:8080/api/ping` | Healthcheck JSON |
| `http://localhost:8080/openapi` | Specificatia OpenAPI raw (YAML; URL fara extensie ca PHP CLI server sa nu intercepteze) |

---

## 3. Structura proiectului

```
negustorii-cetatii/
├── public/
│   ├── index.php          punctul de intrare (PSR-7)
│   ├── joc.html           UI cu harta SVG si gameplay complet
│   ├── docs.html          Swagger UI (incarca pw.yaml de la server)
│   └── assets/            imagini hexagoane (lemn, piatra etc.)
├── src/
│   ├── controllers/       PartidaController, JucatorController, MutareController, HartaController
│   ├── repositories/      Partida, Jucator, Hexagon, Varf, Muchie, Asezare, Drum, Mutare
│   ├── services/          HartaService (genereaza harta random), JocService (regulile)
│   ├── middleware/        JsonMiddleware
│   ├── database/          Connection (PDO)
│   └── routes.php
├── config/                settings.php, dependencies.php (PHP-DI)
├── database/              schema.sql, seed.sql
├── docs/
│   ├── pw.yaml            OpenAPI 3.0 - documentatia completa a API-ului
│   └── postman_collection.json   collection Postman cu transfer automat de proprietati
├── tests/
│   └── scenariu.php       scenariu de testare end-to-end (Guzzle PHP)
├── composer.json
├── .env
└── API_TEST.http          teste rapide cu REST Client
```

---

## 4. Endpoint-uri API

Toate operatiile sunt **implementate** (nu mai sunt marcate ca planificate).

### Citire (GET)
- `GET /api/ping`
- `GET /api/partide` (filtre: `status`, `pagina`, `dimensiunePagina`)
- `GET /api/partide/{id}`
- `GET /api/partide/{id}/jucatori`
- `GET /api/partide/{id}/jucatori/{idJucator}`
- `GET /api/partide/{id}/harta`
- `GET /api/partide/{id}/mutari`
- `GET /api/partide/{id}/mutari/{idMutare}`

### Creare (POST)
- `POST /api/partide`
- `POST /api/partide/{id}/jucatori`
- `POST /api/partide/{id}/start`

### Modificare (PUT)
- `PUT /api/partide/{id}` - nume / jucatoriMaxim / punctajCastig (doar in lobby)
- `PUT /api/partide/{id}/jucatori/{idJucator}` - nume / culoare (doar in lobby)

### Stergere (DELETE)
- `DELETE /api/partide/{id}` - sterge daca e in lobby; arhiveaza daca e pornita
- `DELETE /api/partide/{id}/jucatori/{idJucator}` - elimina din lobby

### Faza asezare initiala
- `POST /api/partide/{id}/setup/asezare` body `{ idVarf }`
- `POST /api/partide/{id}/setup/drum`    body `{ idMuchie }`

### Faza de joc
- `POST /api/partide/{id}/mutari/zar`
- `POST /api/partide/{id}/mutari/asezare` body `{ idVarf }`
- `POST /api/partide/{id}/mutari/drum`    body `{ idMuchie }`
- `POST /api/partide/{id}/mutari/cetate`  body `{ idAsezare }`
- `POST /api/partide/{id}/mutari/paseaza`

---

## 5. Testare (Sprint #3)

Profesorul cere un **scenariu de testare cu transfer automat de proprietati
intre pasi**. Sunt 3 variante pregatite + optiunea browser dev mode.

### 5.1. Swagger UI (cea mai simpla)

1. Porneste serverul: `php -S localhost:8080 -t public`
2. Deschide `http://localhost:8080/docs`
3. Apasa "Try it out" pe orice endpoint, completeaza parametrii si apasa Execute.

### 5.2. Scenariu PHP cu Guzzle

```bash
# instaleaza Guzzle daca nu e deja:
composer install --dev

# in alt terminal (cu serverul deja pornit):
php tests/scenariu.php
# sau cu URL custom:
php tests/scenariu.php http://localhost:8080
```

Scriptul executa 13+ pasi: ping -> creare partida -> PUT partida ->
adaugare jucatori -> PUT jucator -> GET jucator -> start -> setup
snake draft -> aruncari de zar -> GET mutari -> GET mutare specifica
-> DELETE partida. Fiecare pas extrage ID-uri din raspunsul anterior
si le foloseste in pasii urmatori (asta e "property transfer"-ul cerut).

### 5.3. Collection Postman

1. In Postman: Import -> File -> `docs/postman_collection.json`
2. Click pe Runner -> selecteaza colectia -> Run.
3. Vezi rapoartele cu teste verzi/rosii pe fiecare pas.

Variabilele propagate intre pasi (`pm.collectionVariables.set(...)`):
`idPartida`, `idJucator1`, `idJucator2`, `idVarf1`, `idMuchie1`, `idMutare`.

### 5.4. Browser developer mode

Profesorul cere si demonstrarea interactiunilor "la nivel de browser
(developer mode)". Pasi simpli:

1. Deschide UI-ul (`http://localhost:8080/`)
2. Apasa **F12** -> tab-ul **Network**
3. Click prin UI (creeaza partida, adauga jucator, porneste, click pe varf
   etc.) si vezi cum apar request-urile in Network. Click pe fiecare ca
   sa vezi metoda (GET/POST/PUT/DELETE), URL-ul, body-ul JSON si raspunsul.
4. In tab-ul **Console** poti rula `fetch()` manual ca sa testezi orice
   endpoint.

---

## 6. Algoritmul nou de distributie a terenurilor

Pana acum aveam shuffle simplu, deci uneori apareau 2-3 campii lipite.
Acum `HartaService` foloseste:

1. Shuffle aleator al celor 19 terenuri (4 padure, 4 camp, 4 pasune,
   3 deal, 3 munte, 1 desert - exact ca in Catan original).
2. BFS pe vecinii axiali ca sa detecteze clustere de >2 hexagoane de
   acelasi tip. Daca exista cluster, re-shuffle.
3. Maxim 200 incercari; fallback cu swap-uri locale daca tot nu reuseste.
4. Acelasi mecanism pentru numere - nu permite 6/8 adiacente
   (regula Catan oficiala).

Empiric: in ~99% din cazuri reuseste din primele 5-10 incercari, deci
generarea hartii ramane practic instant.

---

## 7. UI (`public/joc.html`)

SPA simpla cu vanilla JS:
- click pe varf liber = pune asezare (in faza potrivita)
- click pe muchie libera = pune drum
- click pe asezare proprie cu mod "Cetate" = upgrade in cetate
- butoane: "Arunca zarul", "Paseaza", + butoane pentru fiecare tip de constructie
- in lobby: butoane **Redenumeste** / **Sterge** pe partide,
  **Edit** / **Elimina** pe jucatori
- link catre **Swagger UI** in coltul din dreapta-sus al panoului Partide

UI-ul consuma **toate** endpoint-urile API (GET, POST, PUT, DELETE).

---

## 8. Pachete noi adaugate in Sprint #3

| Pachet | Scop |
| --- | --- |
| `guzzlehttp/guzzle` (dev) | scriptul de scenariu `tests/scenariu.php` |

Comanda manuala:

```bash
composer require --dev guzzlehttp/guzzle
```

Swagger UI **NU** e dependinta PHP - se incarca direct din CDN (unpkg)
in `public/docs.html`, deci nu e nevoie de `swagger-php` (zircote).

---

## 9. Schema bazei de date

| Tabela | Rol |
| --- | --- |
| `partide` | resursa centrala, cu `faza` interna (in_asteptare / asezare_initiala / joc / finalizata) |
| `jucatori` | jucatorii unei partide cu cele 5 resurse + prestigiu + ordine |
| `hexagoane` | tile-urile hartii cu terrain si numar token, coordonate axiale (q, r) |
| `varfuri` | colturile hexagoanelor (pe ele se pun asezari) |
| `muchii` | laturile dintre 2 colturi (pe ele se pun drumuri) |
| `hexagon_varfuri` | jonctiune: ce varfuri are fiecare hexagon |
| `asezari` | constructiile jucatorilor pe varfuri (asezare sau cetate) |
| `drumuri` | drumurile jucatorilor pe muchii |
| `mutari` | istoric cronologic al actiunilor |

Cand creezi o partida si o pornesti, harta (hexagoane + varfuri + muchii)
se genereaza automat de aplicatie.
