# MS12-D — Test- en reviewbewijs

Gecontroleerd op 20 november 2026 op branch team-3/sprint-2, na MS12-C (f2fba42).
Route /projects/{slug}/work-packages.

## Dekking

De taakomschrijving vormt de basis voor deze matrix. De volledige P/T/A-criteria en
de teksten van B01/B02 en PO-02/03 waren niet beschikbaar. Volledige dekking van die
documenten moet bij de PR-review worden bevestigd.

| Scenario | Gecontroleerd resultaat |
| --- | --- |
| Actieve deelnemer met kijkrecht | HTTP 200 en inhoud van het actuele project |
| Actieve deelnemer zonder eigenaarschap | HTTP 200 |
| Anonieme bezoeker | Redirect naar /auth/login, geen werkpakketinhoud |
| Ingelogde buitenstaander | HTTP 403 |
| Projecteigenaar zonder deelnemerschap | HTTP 403 |
| Wachtend of beëindigd deelnemerschap | HTTP 403, geen projectinhoud |
| Deelnemer zonder rol of zonder kijkrecht | HTTP 403, ook met alleen wijzigingsrecht |
| Wisselen naar slug van ander project | HTTP 403, geen inhoud van het andere project |
| Onbekende slug, lege slug of gecodeerde ongeldige tekens | HTTP 404 |
| Verwijderd project | HTTP 404 |
| Project zonder actieve werkpakketten | HTTP 200 en lege melding |
| Werkpakket zonder actieve taken | HTTP 200, lege melding en 0% voortgang |
| Verwijderde werkpakketten en taken | Uitgesloten van de lijst en voortgang |
| Voortgang | 0%, 50% en 100% gecontroleerd in de gerenderde pagina |
| Taak van huidige gebruiker | Eigen inschrijving zichtbaar |
| Taak van andere gebruiker | Toewijzing zichtbaar, geen inschrijfformulier |
| Vrije taak | Formulier verwijst naar de bestaande enrollment-route |
| HTML in taaktitel | Geëscapete tekst, geen script-element |
| B02-scope | Organisatierechten ontsluiten geen andere organisatie of platformrechten |

De tests controleren responsinhoud en formulieren. De enrollment-POST zelf valt
buiten deze MS12-overzichtstest.

## Uitvoering en resultaat

Testomgeving met de bestaande migraties en alleen de benodigde fixtures
ProjectParticipantFixtures en WorkPackageFixtures, inclusief hun afhankelijkheden.
Gebruikte aparte database hnnw-core_test_ms12c_20261120.

De volledige fixtureloader heeft een bestaande fout in ProfileSkillFixtures.
Die geeft een string door waar SkillProficiencyLevel wordt verwacht.

Met de lokale testdatabaseconfiguratie actief is het testcommando

~~~powershell
php vendor/bin/phpunit --do-not-fail-on-deprecation --order-by=random --random-order-seed=20261120 tests/Module/Main/Controller/WorkPackageAccessTest.php tests/Security/Voter/B02ScopeTest.php --testdox
~~~

Resultaat 15 tests, 100 assertions, geen failures of errors. Daarvan zijn 13
HTTP-tests voor MS12 en twee unit-tests voor B02.

PHPUnit meldt één bestaande Doctrine-deprecatie over Table::$uniqueConstraints.
De optie --do-not-fail-on-deprecation laat de deprecatie zichtbaar en voorkomt dat
deze bestaande melding de exitcode bepaalt. De standaard PHPUnit-configuratie
behandelt de melding als een mislukte run.

De bestaande DAMA PHPUnit-extensie draait de databasewijzigingen na elke test terug.
Een leesquery na afloop bevestigde nul overgebleven ms12-projecten en taken en
nul actieve deelnemerschappen in de oorspronkelijke fixtures. De route-tests
slaagden ook in de normale volgorde.

## Review

- De bestaande route controleert projectrechten vóór het renderen.
- De MS12-C-handler vereist daarnaast actief deelnemerschap en ProjectPermission::VIEW.
- De repository filtert op het gecontroleerde project en actieve werkpakketten.
- De gedeelde organisatie-mapper sluit verwijderde taken uit en berekent de voortgang.
- MS12-D wijzigt uitsluitend tests en dit bewijsdocument.
- De B02-testdubbel is aangepast aan de bestaande findBy-aanroep van de voter.

## Afronding in de PR

Dit document kan als testbewijs aan de PR worden toegevoegd. Voor volledige
taakafronding zijn nog bevestiging van de oorspronkelijke P/T/A-criteria,
PR-goedkeuring en de merge nodig.
