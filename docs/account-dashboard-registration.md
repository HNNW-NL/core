# AC01-A/B en AU03-B

Voor deze taken heb ik het accountdashboard verder uitgewerkt en de registratie gekoppeld aan het bestaande Symfony Security-systeem. Hierbij heb ik de technische roadmap van 3 november 2026 gebruikt (pagina 6).

## Accountdashboard

Op `/account` kan een ingelogde gebruiker nu zijn accountgegevens bekijken. Hier worden de weergavenaam, het e-mailadres en de verificatiestatus getoond. Daarnaast zijn er overzichten toegevoegd voor lopende aanmeldingen, actieve projecten en ongelezen meldingen.

Bij een nieuw account staan deze aantallen standaard op 0. Er wordt ook een korte uitleg getoond met links naar het profiel en de beschikbare projecten. Als een profiel ontbreekt of verwijderd is, blijven de aantallen op 0 staan.

De `AccountsController` gebruikt `ROLE_USER` en haalt de ingelogde gebruiker op via Symfony Security. Hierdoor kan iemand niet via een account-ID of profiel-ID in de URL de gegevens van een andere gebruiker bekijken. Dit is ook toegepast op de bestaande profiel- en meldingsinstellingen.

Voor het ophalen van de gegevens heb ik `AccountDashboardService` gebruikt. De tellers en bijbehorende lijsten gebruiken dezelfde filters.

| Onderdeel | Wat wordt opgehaald? |
| --- | --- |
| Lopende aanmeldingen | Eigen aanmeldingen met de status `send`, `pending` of `in behandeling` |
| Actieve projecten | Projecten waaraan de gebruiker actief deelneemt en die niet verwijderd zijn |
| Ongelezen meldingen | Meldingen van het eigen account die nog niet gelezen of verwijderd zijn |

De statussen worden bij het vergelijken omgezet naar kleine letters, zodat verschillen in hoofdletters geen problemen veroorzaken.

De lijsten tonen maximaal 20 resultaten per pagina en worden gesorteerd op aanmaakdatum en ID. Projecten worden maar één keer meegeteld.

Bij het openen van projecten wordt nog steeds gecontroleerd of de gebruiker hiervoor rechten heeft via `PROJECT_VIEW`. Als iemand geen toegang heeft, wordt dit aangegeven.

Meldingen worden niet automatisch als gelezen gemarkeerd wanneer de pagina wordt geopend. Ook blijft het mogelijk om een eigen lopende aanmelding te annuleren, waarbij de bestaande CSRF-beveiliging wordt gebruikt.

Verder heb ik links toegevoegd naar het eigen profiel, profiel bewerken, aanmeldingen, projecten en accountinstellingen. De instellingenpagina verwijst naar de bestaande profiel- en meldingsvoorkeuren.

## Registratie

Voor de registratie heb ik `RegistrationService` aangepast. De bestaande velden worden nu ook op de server gecontroleerd. Het gaat om de naam, gebruikersnaam, e-mailadres, wachtwoord, wachtwoordherhaling en het akkoord met de voorwaarden.

De volgende controles zijn toegevoegd of behouden:

- Naam: maximaal 100 tekens.
- Gebruikersnaam: minimaal 3 en maximaal 30 tekens.
- E-mailadres: maximaal 254 tekens.
- Wachtwoord: minimaal 8 tekens en maximaal 4096 bytes.

Het registratieformulier gebruikt een eigen CSRF-token (`register`). Alleen de velden die bij de registratie horen worden verwerkt. Als iemand bijvoorbeeld zelf rollen of een verificatiestatus meestuurt, worden deze genegeerd.

Wanneer een gebruiker iets verkeerd invult, krijgt die een melding bij het betreffende veld. De andere ingevulde gegevens blijven staan, behalve de wachtwoorden. Bij validatiefouten wordt HTTP 422 teruggegeven.

E-mailadressen worden automatisch zonder spaties aan het begin of einde en in kleine letters opgeslagen. Ook wordt gecontroleerd of het e-mailadres of de gebruikersnaam al bestaat. Hiervoor worden de bestaande unieke database-indexen gebruikt, waardoor er geen extra migratie nodig is.

Bij een succesvolle registratie worden het account, het profiel en de verificatietoken samen aangemaakt binnen één databasetransactie.

Het wachtwoord wordt gehasht met `UserPasswordHasherInterface`. Nieuwe accounts zijn standaard nog niet bevestigd en krijgen alleen de normale gebruikersrol.

Voor de e-mailverificatie wordt een willekeurige token aangemaakt. Deze wordt alleen gehasht opgeslagen. Bij het bevestigen wordt gecontroleerd of de token geldig is, niet verlopen is en bij het juiste account en e-mailadres hoort.

De bevestigingsmail gebruikt de bestaande mailconfiguratie met `MAILER_FROM` en `DEFAULT_URI`.

Als het versturen van de e-mail mislukt, wordt de registratie teruggedraaid en krijgt de gebruiker een foutmelding. Als alles goed gaat, wordt de gebruiker doorgestuurd naar de loginpagina met de melding **Controleer je e-mail**.

De bestaande registratiepagina en opmaak zijn behouden. De tijdelijke `#`-link voor de voorwaarden en privacy is verwijderd. De juiste route moet nog vanuit AU03-A worden toegevoegd.

## Testen

Om te controleren of alles werkt, heb ik de volgende tests uitgevoerd:

```powershell
& "C:\xampp\php\php.exe" vendor/bin/phpunit tests/Module/Auth/RegistrationServiceTest.php tests/Module/AccountCentre/Controller/AccountAccessTest.php

$env:AUTH_TEST_DATABASE_URL = '<lokale PostgreSQL-DSN>'
& "C:\xampp\php\php.exe" vendor/bin/phpunit tests/Module/AccountCentre/AccountOnboardingPostgresTest.php

& "C:\xampp\php\php.exe" bin/console lint:twig templates/pages/account-centre templates/pages/auth templates/emails --env=test

& "C:\xampp\php\php.exe" bin/console lint:container --env=test
```

Voor de PostgreSQL-tests wordt per test een apart tijdelijk schema aangemaakt. Dit wordt na de test weer verwijderd, zodat de normale database niet wordt aangepast.

Ik heb onder andere de volgende situaties getest:

- Een nieuw account zonder aanmeldingen of projecten.
- Accounts met meerdere actieve projecten en aanmeldingen.
- Paginering bij meer dan 20 resultaten.
- Controleren dat gebruikers geen gegevens van andere accounts kunnen bekijken.
- Verwijderde projecten, aanmeldingen en gelezen meldingen.
- Registreren met een bestaand e-mailadres of gebruikersnaam.
- Ongeldige wachtwoorden en CSRF-tokens.
- Problemen tijdens het versturen van de bevestigingsmail.
- Het bevestigen van een account via een eenmalige verificatielink.

Ook zijn de bestaande AU01/AU04-tests meegenomen om te controleren of de eerdere functionaliteiten nog werken.

### Testresultaten

Op 13 november 2026 zijn de gerichte tests lokaal succesvol uitgevoerd.

**Resultaat: 60 tests geslaagd met 407 assertions.**

Hiervan zijn 27 bestaande tests voor AU01/AU04 en 33 nieuwe tests voor de registratie en het accountdashboard.

De Twig- en containercontroles zijn ook geslaagd.

Daarnaast heb ik de pagina's in Chromium gecontroleerd op verschillende schermgroottes: 360px, 640px met 200% schaal en 1280px. Hierbij waren er geen problemen met horizontaal scrollen. Het mobiele menu werkt ook met het toetsenbord.

## Handmatig controleren

Je kunt de functionaliteit zelf controleren door de website lokaal te starten en met een bestaand account naar `/account` te gaan.

Voor de registratie kun je via `/auth/register` een nieuw testaccount aanmaken. Vervolgens open je de bevestigingsmail en bevestig je het account via de link. Daarna kun je opnieuw inloggen en controleren of het dashboard goed werkt.

Om de bevestigingsmail lokaal te ontvangen, moet de mailvoorziening gestart zijn. De instructies hiervoor staan in [Wachtwoordherstel](password-reset.md).

## Wat moet nog gebeuren?

De oudere `AcountsControllerTest` bevat nog placeholders voor andere accounttaken. Deze heb ik niet aangepast, omdat ze buiten mijn huidige taken vallen. Daarom kan ik nog niet zeggen dat de volledige testsuite van het project foutvrij draait.

De implementatie en de gerichte tests zijn lokaal afgerond. De code moet nog gereviewd en gemerged worden via de PR. Daarna kan de functionaliteit in de gezamenlijke testomgeving worden gecontroleerd en gedemonstreerd.