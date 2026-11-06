# AD01-A en AD01-B

Vóór de fix gaf een geautoriseerde `GET /admin` met Twig strict variables de fout `Variable "logs" does not exist`. De dashboardcontroller gaf geen gegevens mee voor de oude blokken voor logs, accounts en profielen.

Het dashboard toont nu de drie afgesproken platformtotalen. Het Twig-template haalt ze op via `admin_dashboard_totals()`. Die functie controleert `ROLE_ADMIN` en `ADMIN_VIEW_DASHBOARD` en telt via Doctrine accounts, organisaties en projecten die niet verwijderd zijn. De bestaande controller blijft het template renderen.

De links naar Accounts en Statussen openen de bestaande routes. Zolang die pagina’s geen beheerdata ontvangen, tonen ze een toelichting en een teruglink. Het accountbeheer valt onder AD02 en het statusbeheer onder AD11.

`AdminHomeTest` controleert dat een bevoegde platformbeheerder `/admin` kan openen, de verwachte totalen ziet en naar de twee bestemmingen kan navigeren. De test controleert ook dat een gewoon account geen toegang heeft.
