# CORE-02 · B02-rechtenmatrix

Deze matrix beschrijft de bestaande autorisatie per object. De rechten van een rol volgen uit het permissiemasker. Een rolnaam dient als label.

| Object | Rol of relatie | Toegestaan | Geweigerd |
| --- | --- | --- | --- |
| Platform | Bezoeker, gewoon account of organisatiebeheerder zonder platformrol | Publieke routes volgens de firewall | Platformbeheer |
| Platform | Platformbeheerder | Dashboard, accounts, organisaties, logs, instellingen en social beheer voor zover het overeenkomstige `ADMIN_*`-bit aanwezig is | Acties met een ontbrekend bit wanneer de route dat bit vereist |
| Platform | Superadmin | Alle bestaande `ADMIN_*`-permissies | Onbekende permissies worden door de voter niet behandeld |
| Organisatie | `User` volgens de standaardseed | `ORG_VIEW` op de eigen organisatie | Bewerken, ledenbeheer, rollenbeheer, projectenbeheer en verwijderen |
| Organisatie | `Organisation Admin` volgens de standaardseed | Alle zes `ORG_*`-permissies op de eigen organisatie | Dezelfde acties op een andere organisatie zonder bevoegd lidmaatschap |
| Organisatie | Superadmin | Alle `ORG_*`-permissies via de centrale organisatievoter | Staff Management controleert daarnaast zijn eigen lidmaatschapsvereisten |
| Organisatiemedewerker | Bevoegd lid van de geselecteerde organisatie | Lezen met `ORG_VIEW`, toevoegen met leden- én rollenbeheer, rol wijzigen met rollenbeheer, verwijderen met ledenbeheer | Andere organisatie, rollen met hogere rechten en het wegnemen van de laatste effectieve beheerbevoegdheid |
| Project, werkpakket en taak | Eigenaar van het betreffende project | Bekijken, wijzigen en verwijderen via de centrale objectvoters | Het eigenaarschap geeft op zichzelf geen rechten op andere projecten |
| Project, werkpakket en taak | Actieve projectdeelnemer | Bekijken, wijzigen of verwijderen volgens het bijbehorende projectrolbit | Actie zonder bit, deelnemer zonder rol en toegang via een lidmaatschap op een ander project |
| Project, werkpakket en taak | Account zonder toepasselijke projectrelatie | Eventuele publieke weergave via afzonderlijke routes | De beschermde acties van de centrale objectvoters |

## Open beslissingen voor vaststelling

- Twee projectvoters behandelen `PROJECT_VIEW` en `PROJECT_EDIT`. De Org-voter gebruikt daarnaast bit 2 voor `PROJECT_INVITE`, terwijl de centrale projectrol hetzelfde bit voor verwijderen gebruikt. B02 moet vastleggen welke betekenis en voter leidend zijn voordat dit als definitief contract kan gelden.
- De centrale organisatievoter leest één lidmaatschap. Staff Management combineert de maskers van meerdere lidmaatschappen binnen de geselecteerde organisatie. Leg vast of samenvoegen ook voor de centrale voter hoort te gelden.
- De platformmatrix beschrijft voterrechten. Routebeveiliging moet afzonderlijk worden gecontroleerd. Meldingen en Events vereisen momenteel `ROLE_ADMIN` zonder afzonderlijk permissiebit.

## Negatieve tests

De tests onder `tests/Security/Voter` controleren onder meer ontbrekende rollen, ontbrekende rechten en de grens tussen organisaties. De Staff Management-tests dekken de organisatiecontext, roltoewijzing boven de eigen bevoegdheden en bescherming van de laatste beheerder.
