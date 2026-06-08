# Teste Gherkin - Negustorii Cetatii

Aici sunt scenariile de testare scrise in **Gherkin** (limbaj natural structurat,
in romana), care descriu comportamentul API-ului in stil "dat fiind / cand / atunci".

## Fisiere

- `partide.feature` - creare partida, adaugare jucatori, start, validari
- `autentificare.feature` - login, token, control acces (ACL) pe zona de admin
- `notificari.feature` - feed-ul de notificari asincrone dupa evenimente

## Cum rulez testele

1. Porneste serverul (si MySQL din XAMPP):

   ```
   php -S localhost:8080 -t public
   ```

2. Asigura-te ca exista contul de admin (ruleaza `database/migratie_auth.sql`
   daca nu l-ai rulat deja).

3. In alt terminal, ruleaza runner-ul:

   ```
   php tests/gherkin.php
   ```

   sau, prin Composer:

   ```
   composer gherkin
   ```

Runner-ul citeste toate fisierele `.feature` de aici, executa pasii lovind
API-ul real si afiseaza un rezumat. Codul de iesire e `0` daca toate scenariile
trec (util pentru testare automata / CI).

## De ce un runner propriu si nu Behat?

Ca sa nu adaugam dependinte noi - folosim doar Guzzle, pe care il avem deja
pentru testele de scenariu. Fisierele `.feature` sunt insa Gherkin standard,
deci aceleasi scenarii pot fi rulate si cu **Behat** daca se doreste
(`composer require --dev behat/behat` + un FeatureContext cu aceiasi pasi).
