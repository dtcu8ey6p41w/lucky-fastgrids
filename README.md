# Lucky_FastGrids

Snelle, lichte admin-grids voor **orders**, **klanten** en **producten** in Magento 2 Open Source 2.4.x (PHP 8.1+).

- **Sales > Orders Fast**, **Customers > Customers Fast**, **Catalog > Products Fast**: eigen pagina's.
- **Switch-knop** op de standaardpagina's Sales > Orders, Customers > All Customers en Catalog > Products
  ("Orders Fast" / "Customers Fast" / "Products Fast" ↔ "Standaard grid"). In de snelle stand wordt het
  Magento UI-grid niet geladen; de knoppen bovenin (Create New Order, Add New Customer, Add Product en
  knoppen van andere modules) blijven. De keuze wordt per browser in een cookie onthouden.
- **Kolommen instelbaar** via Stores > Configuration > Lucky > Fast Grids.

## Waarom snel

De grids lezen rechtstreeks uit `sales_order_grid`, `customer_grid_flat` en `catalog_product_entity`
(met één LEFT JOIN per gekozen productattribuut) met één query voor de rijen en één voor de telling.
Er worden geen Magento-collections, UI-componenten of grid-plugins gebruikt, dus modules die per rij
extra data laden spelen geen rol. Alleen de gekozen kolommen worden opgehaald. De pagina zelf is kale
HTML met één klein script (geen RequireJS/Knockout).

## Kolommen instellen

Stores > Configuration > Lucky > Fast Grids, per grid:

1. **Kolommen ophalen**: leest alle kolommen van het standaard Magento-grid (ook die van andere modules)
   en gebruikt hun labels in de lijst.
2. **Kolommen**: vink aan wat getoond moet worden en klik op **Save Config**. Het snelle grid laadt
   voortaan precies die kolommen, in die volgorde. Alles uitvinken = terug naar de standaardset.

Kolommen met een ⚠ worden in het standaardgrid door een kolomklasse van een (andere) module **per rij
berekend** (bijvoorbeeld ordergewicht, tracking, allocated sources, salable quantity, Mailchimp). Ze zijn
gewoon kiesbaar: het snelle grid laat dan exact die kolomklasse over de geladen rijen draaien, met
dezelfde uitkomst als in het standaardgrid. Bij "Kolommen ophalen" wordt per zo'n kolom gemeten hoeveel
milliseconden hij kost op 20 echte rijen; dat staat als opmerking bij het vinkje. Die tijd komt bij elke
pagina bovenop de laadtijd en groeit mee met het aantal rijen per pagina. Alles wat niet gemarkeerd is
komt rechtstreeks uit de gridtabel of via een goedkope join: alle velden van de gridtabellen, de
Amasty-bezorgdatum/-tijd/-opmerking, de verzendmethode-code, klantattributen van de klant bij de order
(`customer_<code>`), alle klantattributen met "Add to Column Options", productvoorraad, websites en alle
productattributen (kernset + "Add to Column Options").

Groep **Algemeen**:
- **Standaardinstellingen opslaan**: slaat de vinkjes hieronder op én legt ze vast als nieuwe standaard.
- **Terug naar standaard**: zet de vinkjes van alle drie de grids terug naar die standaard (of, als er
  nooit een standaard is opgeslagen, naar de ingebouwde set hieronder). De opgehaalde kolomlijsten en
  metingen blijven bewaard.

Ingebouwde standaardset:
- Orders: ID, Besteldatum, Status, Verzendnaam, Verzendadres, Klantgroep, Bezorgdatum, Klant informatie.
- Klanten: ID, Groep, Naam, E-mail, Telefoon, Postcode, Klant sinds, Aangemaakt in, Geboortedatum,
  Verzendadres, Email CC, Acties, Klant informatie, Inloggen als klant, Klant type.
- Producten: ID, Afbeelding, Naam, Type, Attributenset, SKU, Prijs, Aantal, Zichtbaarheid, Status, Websites, Acties.

Kolommen die in de doelomgeving niet bestaan worden stilzwijgend overgeslagen.

## Functies

- Zoeken (ordernummer/SKU/ID, e-mail exact, vrije tekst via de fulltext-index van de gridtabel met
  LIKE-fallback), filters, sorteren, paginering tot 500 rijen.
- Orders: de statusfilter toont alleen statussen die in orders voorkomen. De filter Betaalmethode
  verschijnt zodra de kolom Betaalmethode is aangevinkt in de instellingen (en toont alleen methoden
  die in orders voorkomen); uitvinken laat de filter weer verdwijnen.
- Kolommen aan/uit zetten en verslepen in het grid zelf; keuze, volgorde en filters worden per browser onthouden.
- Hele rij aanklikbaar (Ctrl/Cmd-klik of middelste muisknop opent een nieuw tabblad).
- Klanten: "Inloggen als klant" als link, acties Bewerken en Orders (springt naar Orders Fast met het
  e-mailadres als filter).

## Optionele integraties (automatisch gedetecteerd, geen afhankelijkheid)

| Als aanwezig | Dan |
|---|---|
| Amasty Delivery Date (`amasty_deliverydate_deliverydate_order`) | kolom + filter Bezorgdatum in Orders Fast |
| Mailchimp (ebizmarts) | kolom Mailchimp-status in Customers Fast |
| Magento_LoginAsCustomerAdminUi ingeschakeld + rechten | kolom "Inloggen als klant" |
| Klantattributen met "Add to Column Options" | kolommen in Customers Fast en (als `customer_<code>`) in Orders Fast |

## Installatie

**Optie A: als app/code-module**

1. Pak het archief uit naar `app/code/Lucky/FastGrids` (het mapje met `registration.php`).
2. Draai:

```
bin/magento module:enable Lucky_FastGrids
bin/magento setup:upgrade
bin/magento setup:di:compile        # alleen in production mode
bin/magento setup:static-content:deploy nl_NL en_US   # alleen in production mode
bin/magento cache:flush
```

**Optie B: via Composer vanaf GitHub**

```
composer config repositories.lucky-fastgrids vcs https://github.com/dtcu8ey6p41w/lucky-fastgrids
composer require lucky/module-fast-grids:dev-main
bin/magento module:enable Lucky_FastGrids && bin/magento setup:upgrade
```

**Optie C: via Composer (lokale map)**

```
composer config repositories.lucky-fastgrids path /pad/naar/lucky-fastgrids
composer require lucky/module-fast-grids:*
bin/magento module:enable Lucky_FastGrids && bin/magento setup:upgrade
```

Geen database-wijzigingen. Instellingen staan in `core_config_data` onder `lucky_fastgrids/*` (columns, defaults, labels, impact per grid).

## Rechten (ACL)

- `Lucky_FastGrids::orders` (onder Sales > Operations) voor Orders Fast
- `Lucky_FastGrids::customers` (onder Customers) voor Customers Fast
- `Lucky_FastGrids::products` (onder Catalog > Inventory) voor Products Fast
- `Lucky_FastGrids::config` (onder Stores > Configuration) voor de instellingen
- De switch-knoppen gebruiken de bestaande rechten van de standaardpagina's.

## Verwijderen

```
bin/magento module:disable Lucky_FastGrids
bin/magento setup:upgrade
```

en verwijder de map. Er blijft niets achter in de database.

## Opmerkingen

- Als de keyword-zoekfunctie niets vindt terwijl de klant bestaat, is de fulltext-index van
  `customer_grid_flat` leeg; `bin/magento indexer:reindex customer_grid` lost dat op.
- Massa-acties (Actions-menu) zijn bewust niet aanwezig in de snelle stand; schakel daarvoor
  terug naar het standaardgrid.
- Teksten zijn Nederlands en via `__()` vertaalbaar (i18n/*.csv).
