# OR01-A en OR01-B — Opleveringsnotitie

## 1. Opdracht

**OR01-A — Functioneel**

Het organisatiedashboard op `/org` toont de organisatienaam, de eigen rol en toegestane beheerlinks. Bij meerdere organisaties is duidelijk welke organisatie actief is.

**OR01-B — Technisch**

De organisatiecontext wordt bepaald op basis van de ingelogde gebruiker en toegestane organisatierelaties, niet uitsluitend op basis van een organisatie-ID uit de URL. Bij het wisselen veranderen de gegevens en links mee.

## 2. Oorspronkelijk probleem en analyse

Tijdens de ontwikkeling waren er problemen met toegang tot `/org`. Eerder werd een HTTP 401-respons waargenomen. Na integratie met de centrale Symfony-authenticatie wordt een niet-ingelogde bezoeker doorgestuurd naar `/auth/login`.

Ook verscheen aanvankelijk de melding dat de gebruiker niet aan een organisatie gekoppeld was. Uit databasecontrole bleek dat de testorganisatie aan account `Jooster` gekoppeld was, terwijl op de website account `Bas123` was ingelogd. Na het toevoegen van het juiste lidmaatschap werd de organisatie zichtbaar.

De oorspronkelijke 401-fout is niet volledig met een stacktrace herleid. De bovenstaande punten zijn de tijdens het testen vastgestelde oorzaken en omstandigheden.

## 3. Technische oplossing

- Het dashboard haalt organisaties op via de lidmaatschappen van de ingelogde gebruiker.
- Verwijderde organisaties worden overgeslagen.
- De organisatierol moet bij de juiste organisatie horen.
- Met `ORG_VIEW` wordt gecontroleerd of de gebruiker de organisatie mag bekijken.
- Een organisatie-ID uit de URL wordt alleen geaccepteerd wanneer de gebruiker toegang heeft.
- Het dashboard toont de naam, rollen, projectteller en teller voor niet-beoordeelde aanvragen.
- De beheerlinks worden afhankelijk van de organisatierechten weergegeven.
- De gekozen organisatie wordt via de URL aan de projecten- en medewerkerspagina doorgegeven.
- De projectenservice filtert op `owner_org_id`, zodat projecten per organisatie worden opgehaald.

## 4. Uitgevoerde tests

De volgende onderdelen zijn gecontroleerd:

- Dashboard met één organisatie.
- Dashboard zonder organisatie.
- Dashboard met twee organisaties.
- Inloggen verplicht voor organisatiepagina's.
- Een ingelogde gebruiker zonder lidmaatschap kan geen willekeurige organisatie openen.
- Beveiliging van de projecten- en medewerkerspagina.
- Wisselen tussen twee toegestane organisaties, inclusief organisatienaam, projectaantallen, rollen en beheerlinks.

**Laatste testresultaat: 7 tests geslaagd, 48 assertions.**

Er is één bestaande Doctrine-deprecationmelding over de mapping van `Status.php`. Hiervoor zijn geen entities of migrations aangepast.

## 5. Grenzen en aandachtspunten

- De Events-functionaliteit is nog niet beschikbaar en wordt als zodanig getoond.
- De medewerkerspagina bevat momenteel voorbeeldinhoud.
- De overige project- en medewerkersroutes moeten bij verdere ontwikkeling hun eigen passende autorisatie behouden.
- De Projecten-link en de projectenpagina controleren beide ORG_MANAGE_PROJECTS, zodat de toegangsrechten overeenkomen.
- De afhankelijkheden B01/B02 en de definitieve vaststelling van de oorspronkelijke bronfout moeten bij de teamreview worden bevestigd.

## 6. Git en integratie

Het werk is ontwikkeld op `team-1/feature/OR01-integratie-test`, in combinatie met de wijzigingen van `team-1/develop`.

Er zijn geen entity- of migrationbestanden aangepast. De definitieve Pull Request wordt pas aangemaakt na de laatste controle.