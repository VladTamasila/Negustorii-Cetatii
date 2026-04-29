# Negustorii Cetatii - Sprint #2 (Catan-like)

Joc tip Catan **peaceful** (fara hot, fara furt, fara atacuri).

Stack: PHP 8.1+ / Slim 4 / PHP-DI / MySQL (XAMPP).

---

## 1. Reguli pe scurt

- 19 hexagoane in stil Catan (3-4-5-4-3 randuri).
- 5 resurse: **lemn**, **piatra**, **aur**, **grau**, **lana**.
- Terenuri: padure -> lemn, deal -> piatra, munte -> aur, camp -> grau, pasune -> lana, desert -> nimic.
- Numere "token" pe hexagoane: 2,3,4,5,6,8,9,10,11,12 (fara 7).

**Faza de asezare initiala (snake draft):**
- Fiecare jucator pune 2 asezari + 2 drumuri, in ordine 1->2->3->4 apoi 4->3->2->1.
- A doua asezare aduce resurse de start - cate 1 din fiecare teren adiacent.

**Faza de joc normala:**
- Jucatorul activ arunca 2 zaruri.
- Toate hexagoanele cu suma respectiva produc - jucatorii cu asezari pe varfurile lor primesc 1 resursa (cetatea = 2 resurse).
- Suma 7 nu produce nimic (fara hot in varianta noastra).
- Jucatorul poate construi: drum (1 lemn + 1 piatra), asezare (1 lemn + 1 piatra + 1 grau + 1 lana), upgrade cetate (2 aur + 3 grau).
- Apoi paseaza tura urmatorului.

**Castig:** primul care atinge `punctaj_castig` prestigiu (implicit 10).
- Asezare = 1 prestigiu, cetate = 2 prestigiu.

---

## 2. Pornire rapida

```bash
cd "C:\PW(negustorii cetatii)"
composer install
# Ruleaza schema.sql + seed.sql in DataGrip pe baza `negustorii_cetatii`
php -S localhost:8080 -t public
```

Apoi deschide `http://localhost:8080/` in browser.

---

## 3. Structura proiectului

```
negustorii-cetatii/
+- public/
|  +- index.php          punctul de intrare
|  +- joc.html           UI cu harta SVG
|  +- .htaccess
+- src/
|  +- controllers/       PartidaController, JucatorController, MutareController, HartaController
|  +- repositories/      Partida, Jucator, Hexagon, Varf, Muchie, Asezare, Drum, Mutare
|  +- services/          HartaService (genereaza harta), JocService (regulile)
|  +- middleware/        JsonMiddleware
|  +- database/          Connection (PDO)
|  +- routes.php
+- config/               settings.php, dependencies.php (PHP-DI)
+- database/             schema.sql, seed.sql
+- docs/pw.yaml          OpenAPI 3.0
+- composer.json
+- .env
+- API_TEST.http         teste rapide cu REST Client
```

---

## 4. Endpoint-uri

### Citire
- `GET /api/ping`
- `GET /api/partide` (filtre: `status`, `pagina`, `dimensiunePagina`)
- `GET /api/partide/{id}`
- `GET /api/partide/{id}/jucatori`
- `GET /api/partide/{id}/harta` (hexagoane, varfuri, muchii, asezari, drumuri)
- `GET /api/partide/{id}/mutari`

### Setup partida
- `POST /api/partide` - creeaza partida
- `POST /api/partide/{id}/jucatori` - adauga jucator
- `POST /api/partide/{id}/start` - genereaza harta + intra in faza setup

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

## 5. Schema bazei de date

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

Cand creezi o partida si o pornesti, harta (hexagoane + varfuri + muchii) se genereaza
automat de aplicatie cu distribuie aleatoare a terenurilor si numerelor.

---

## 6. UI

`public/joc.html` - SPA simpla cu vanilla JS:
- click pe varf liber = pune asezare (in faza potrivita)
- click pe muchie libera = pune drum
- click pe asezare proprie = upgrade in cetate (cand e selectat butonul "Cetate")
- butoane: "Arunca zarul", "Paseaza", + buton modal pentru fiecare tip de constructie
