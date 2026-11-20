# AU01-A/B en AU04-A/B: wachtwoordherstel

De bestaande routes, `Account` en `ResetPasswordToken` zijn het uitgangspunt.
`AuthController` handelt formulieren en terugmeldingen af; `PasswordResetService`
verzorgt uitgifte, controle en verbruik van de tokens. De databasekolommen en
migraties blijven bruikbaar. De taakcriteria komen uit de technische roadmap
Definitief v1 van 3 november 2026, pagina 5 en 6.

## Gedrag

| Taak | Opgeleverd gedrag |
| --- | --- |
| AU01-A | E-mailformulier met knop **Herstellink aanvragen**, veldfouten, link naar login en dezelfde neutrale bevestiging voor bestaande en onbekende adressen. |
| AU01-B | Eén herstelmail voor een bestaand account, 256 bits willekeur, alleen de tokenhash in opslag, binding aan het account en twintig minuten geldigheid. |
| AU04-A | Nieuw wachtwoord plus herhaling, veldfouten zonder teruggetoonde wachtwoorden, begrijpelijke ongeldige-linkpagina en verwijzing naar een nieuwe aanvraag. Na succes volgt de loginpagina met bevestiging. |
| AU04-B | Controle van tokenformaat, hash, account, verval en gebruiksstatus. Wachtwoordhash en tokenverbruik worden in één transactie opgeslagen. |

Beide POST-formulieren gebruiken afzonderlijke Symfony-CSRF-tokens. Het
wachtwoordbeleid volgt de huidige applicatie: minimaal acht tekens. De bovengrens
is 4096 bytes, passend bij de bestaande Symfony-hasher. De bestaande twintig
minuten voor hersteltokens valt binnen het roadmapmaximum van zestig minuten.

Een nieuwe aanvraag vervangt eerdere herstellinks. Een geslaagde reset verbruikt
alle resterende hersteltokens van het account. Een vergrendeling op het account,
gevolgd door opnieuw inlezen van de gegevens, voorkomt dat parallelle verzoeken
dezelfde token tweemaal gebruiken. Vergrendelen en verversen zijn afzonderlijke
Doctrine-operaties vanwege de nullable relaties op `Account` in PostgreSQL.

GET-verzoeken wijzigen geen opgeslagen gegevens. Formulierfouten geven HTTP 422,
ongeldige herstellinks HTTP 400 en geslaagde POST-verzoeken een HTTP 303-redirect.
Mailtransportfouten houden dezelfde neutrale reactie; de transactie draait terug
en de applicatie logt een algemene fout zonder token, URL of exceptiontekst.
Het resetscherm en de succesredirect sturen `Referrer-Policy: no-referrer` en
`Cache-Control: no-store`.

## Lokale configuratie

Zet in `.env.local` de instellingen van de eigen omgeving, bijvoorbeeld:

```dotenv
DEFAULT_URI=http://127.0.0.1:8000
MAILER_DSN=smtp://127.0.0.1:1025
MAILER_FROM="HNNW <no-reply@hnnw.test>"
```

`DEFAULT_URI` is de vertrouwde publieke basis-URL, inclusief poort en eventueel
subpad. De herstellink gebruikt deze instelling, ook als een bezoeker een andere
Host-header meestuurt. Gebruik voor productie de werkelijke HTTPS-basis-URL,
een werkende SMTP-verbinding en een door die mailvoorziening toegestane afzender.

Mailpit staat al in `compose.override.yaml`. Gebruik de werkelijk toegewezen
SMTP-poort van de eigen installatie. Met `MAILER_DSN=null://null` worden berichten
alleen door Symfony verwerkt; er wordt geen e-mail in een inbox afgeleverd. In de
ontwikkelomgeving zijn de berichten via de Symfony-profiler te bekijken.

### Windows zonder Docker

Voor deze lokale testomgeving is de officiële Windows-versie van
[Mailpit](https://mailpit.axllent.org/docs/install/) uitgepakt onder
`var/tools/mailpit-v1.33.1/`. De download is gecontroleerd met de SHA-256 uit de
officiële GitHub-release. De executable, inboxdatabase en runtimebestanden staan
onder het bestaande genegeerde `var/`.

De testinbox is bereikbaar op `http://127.0.0.1:8025`; SMTP gebruikt
`127.0.0.1:1025`. Beide luisteren alleen op de lokale computer. De website gebruikt
hiervoor `MAILER_DSN=smtp://127.0.0.1:1025` in `.env.local`.

Na een herstart van de computer kan Mailpit vanuit de projectmap worden gestart:

```powershell
.\var\tools\mailpit-v1.33.1\mailpit.exe --listen 127.0.0.1:8025 --smtp 127.0.0.1:1025 --database .\var\verification\runtime\mailpit.sqlite --disable-version-check --smtp-disable-rdns
```

Laat die terminal open. Start eventueel de website vanuit een tweede terminal:

```powershell
& "C:\xampp\php\php.exe" -S 127.0.0.1:8000 -t public
```

Vraag op `/auth/forgot-password` een link aan voor een bestaand lokaal testaccount.
Open daarna in Mailpit het nieuwste bericht met onderwerp **Stel je nieuwe
HNNW-wachtwoord in** en gebruik de link **Nieuw wachtwoord instellen**. Een nieuw
verzoek vervangt een eerdere link. De accountgegevens van het fictieve account
voor deze lokale controle staan in `var/verification/runtime/mail-test-account.json`.

De volledige SMTP-controle heeft via de werkelijke HTTP-routes een fictief account
geregistreerd en bevestigd, de herstelmail ontvangen en het wachtwoord gewijzigd.
Ook de fout bij ongelijke wachtwoorden, de gelijke bevestiging voor een onbekend
adres, afwijzing van het oude wachtwoord, login met het nieuwe wachtwoord en
afwijzing van de gebruikte link zijn gecontroleerd. Voor handmatig testen is een
nieuwe herstellink in de inbox achtergelaten. De resultaten staan lokaal in
`var/verification/runtime/smtp-end-to-end-result.json`.

## Testbewijs

```powershell
php vendor/bin/phpunit tests/Module/Auth/Controller/PasswordResetControllerTest.php
$env:PASSWORD_RESET_TEST_DATABASE_URL = '<DSN van een lokale PostgreSQL-testdatabase>'
php vendor/bin/phpunit tests/Module/Auth/PasswordResetPostgresTest.php
php bin/console lint:twig templates/pages/auth templates/emails --env=test
php bin/console lint:container --env=test
```

De controllerchecks gebruiken de echte Symfony-routing, CSRF, Twig,
e-mailverwerking en loginflow, met geïsoleerde repositories en een null-transport
dat de gerenderde mail voor assertions opvangt. Gecontroleerd zijn:

- Dezelfde bevestiging en status voor bekend en onbekend adres; precies één
  gerenderde mail voor het bestaande testaccount.
- Lege, ongeldige en te lange e-mail, veilige escaping en ontbrekende CSRF-tokens.
- Lege, te korte, te lange en ongelijke wachtwoorden; wachtwoorden blijven leeg
  bij een fout en het token blijft bruikbaar.
- Verlopen, gebruikte, aangepaste en verkeerd gekoppelde tokens, inclusief
  een token met extra tekens achter een geldig exemplaar.
- De herstelmail opent een bruikbaar formulier; het nieuwe wachtwoord kan
  inloggen, het oude wordt geweigerd en hergebruik van de link faalt.

De PostgreSQL-checks maken per test een uniek `password_reset_test_*`-schema en
ruimen dat in de teardown op. Zij controleren opslag na opnieuw laden, vervangen
van links, uitgesloten verwijderde accounts, rollback bij database- of mailfalen
en twee werkelijk gelijktijdige PHP-processen met dezelfde token. Precies één
van die processen slaagt. Deze tests zijn optioneel zonder de bovenstaande
omgevingsvariabele. Eén bekende Doctrine-deprecatie in de bestaande
`Status::uniqueConstraints`-mapping wordt gericht uitgesloten; overige fouten en
deprecaties blijven zichtbaar.

Visueel gecontroleerd in Chromium op 360 en 1280 CSS-pixels, plus een viewport
van 640 CSS-pixels op schaalfactor 2 als 200%-weergave. De aanvraagpagina, het
resetformulier en de ongeldige-linkpagina hebben geen horizontale overflow.

## Overdracht

Aanvraagbeperking en infrastructuurbeleid voor het filteren van gevoelige
request-URL's in webserverlogs vallen onder het vervolgwerk AU01-C/AU04-C.
De volledige B06-contracttekst staat niet in de aangeleverde roadmap; de huidige
wachtwoordlengte en geldigheidsduur zijn daarom behouden. Onafhankelijke review,
PR-merge en demonstratie in de gedeelde testomgeving behoren tot de algemene
Definition of Done en volgen op deze implementatie.
