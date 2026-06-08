# Ghid: pune jocul online (accesibil din toata lumea) cu ngrok

Acest ghid arata cum sa faci ca proiectul tau sa fie accesibil de oriunde
din lume, fara sa platesti gazduire. Folosim **ngrok**, care creeaza un
"tunel": ia serverul tau local (`localhost:8080`) si ii da o adresa
publica `https://....ngrok-free.app` pe care o poate deschide oricine.

```
  Browserul prietenului  --->  https://abcd.ngrok-free.app  (ngrok)
                                        |
                                        v  tunel securizat
                          calculatorul tau: localhost:8080  (php -S)
                                        |
                                        v
                                  XAMPP MySQL (local)
```

Important: cat timp tii tunelul deschis, **calculatorul tau este serverul**.
Daca inchizi laptopul sau opresti scriptul, linkul nu mai merge. E perfect
pentru demonstratie la profesor sau pentru testat cu colegii.

---

## Pas 0. Pre-requisite (le ai deja)

- XAMPP pornit cu **MySQL** (din XAMPP Control Panel apesi Start la MySQL).
- Baza de date `negustorii_cetatii` creata si populata (vezi README).

---

## Pas 1. Instaleaza ngrok (o singura data)

1. Intra pe https://ngrok.com/download si descarca versiunea Windows.
2. Dezarhiveaza `ngrok.exe`. Cel mai simplu: pune-l **chiar in folderul
   proiectului** (langa `pornire-ngrok.bat`).
3. Fa-ti cont gratuit pe https://dashboard.ngrok.com (e gratis).
4. Din dashboard, sectiunea **"Your Authtoken"**, copiaza tokenul.
5. Deschide un Command Prompt in folderul proiectului si ruleaza o data:

   ```
   ngrok config add-authtoken TOKENUL_COPIAT_AICI
   ```

   (Asta leaga ngrok de contul tau. Se face o singura data pe calculator.)

---

## Pas 2. Porneste serverul local

Dublu-click pe **`pornire-server.bat`**.

Se deschide o fereastra neagra care ramane deschisa. Verifica in browser ca
merge: deschide http://localhost:8080/ - ar trebui sa vezi jocul.

> Lasa aceasta fereastra deschisa. Daca o inchizi, se opreste serverul.

---

## Pas 3. Porneste tunelul ngrok

Dublu-click pe **`pornire-ngrok.bat`** (sau ruleaza `ngrok http 8080`).

Se deschide o a doua fereastra. Cauta linia **Forwarding**:

```
Forwarding   https://abcd-12-34-56-78.ngrok-free.app -> http://localhost:8080
```

Acel link `https://....ngrok-free.app` este adresa ta publica.
**Trimite-l oricui** - profesor, colegi - si pot deschide jocul din browserul
lor, de oriunde din lume.

---

## Pas 4. Verifica

Deschide chiar tu linkul ngrok intr-un telefon pe date mobile (nu pe wifi-ul
de acasa) ca sa confirmi ca merge din afara retelei tale:

- `https://....ngrok-free.app/` -> jocul
- `https://....ngrok-free.app/docs` -> Swagger UI
- `https://....ngrok-free.app/api/ping` -> `{"ok":true,...}`

---

## Intrebari frecvente / probleme

**"Apare o pagina galbena ngrok cu un buton 'Visit Site'."**
E normal la planul gratuit, doar la prima deschidere a paginii. Apesi
"Visit Site" si intri. Apelurile API din joc (fetch) NU mai sunt afectate -
am adaugat header-ul `ngrok-skip-browser-warning` in cod special pentru asta.

**"Linkul se schimba de fiecare data cand pornesc ngrok."**
Da, la planul gratuit primesti un subdomeniu random la fiecare pornire. E ok
pentru demo. (Daca vrei link fix, ngrok ofera un domeniu static gratuit per
cont - il poti rezerva din dashboard la "Domains".)

**"Merge la mine local dar prin ngrok da eroare la baza de date."**
Verifica ca MySQL din XAMPP e pornit. Baza ramane locala pe calculatorul tau;
ngrok tuneleaza doar serverul web, nu si MySQL - dar nici nu e nevoie, fiindca
PHP-ul tau (local) vorbeste cu MySQL-ul tau (local).

**"Vreau sa il las pornit si sa plec."**
Nu se poate - calculatorul tau e serverul. Trebuie sa ramana pornit, cu cele
doua ferestre deschise (server + ngrok).

---

## Alternativa: Cloudflare Tunnel (fara cont)

Daca nu vrei cont ngrok, poti folosi `cloudflared`:

```
cloudflared tunnel --url http://localhost:8080
```

Iti da un link `https://....trycloudflare.com` fara pagina de avertisment.
Restul (serverul local) ramane la fel.
