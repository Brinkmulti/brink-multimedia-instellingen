# Brink Multimedia Instellingen

Centrale WordPress-plugin voor Brink Multimedia om diverse instellingen aan te passen, ondergebracht in tabbladen (categorieën). Geïnspireerd op **Admin and Site Enhancements (ASE)**.

## Tabbladen

1. **Log in/uit | Registreer** — pas de standaard `/wp-login.php`-URL aan naar een eigen, geheime login-URL.
2. **Dashboard Layout** — breedte van het beheermenu, darkmodus, volgorde van het beheermenu, ruimte tussen menu-items, en dashboardwidgets per rol verbergen (ook widgets van andere plugins).
3. **Inhoudsbeheer** — pagina's/berichten met 1 klik dupliceren, SVG-uploads aan/uit, AVIF-uploads aan/uit.
4. **Rollen & Rechten** — per rol welke menu-onderdelen zichtbaar zijn, welke plugin-functies uitgesloten zijn, en welke rollen niets mogen verwijderen (alleen toevoegen en aanpassen).

## Installatie

1. Zip de map `brink-multimedia-instellingen` (of gebruik de meegeleverde zip).
2. Upload via **Plugins → Nieuwe plugin → Plugin uploaden** in WordPress.
3. Activeer de plugin.
4. Ga naar **Brink Instellingen** in het WordPress-menu.

## Belangrijk bij de login-URL

Bewaar de nieuwe login-URL goed nadat je deze hebt ingesteld. Als je hem vergeet, kun je tijdelijk het bestand
`includes/class-bmi-login.php` hernoemen via (S)FTP om weer bij `/wp-login.php` te kunnen, de instelling leegmaken, en het bestand terugzetten.

## Updates via GitHub

1. Hoog het versienummer op (header + `BMI_VERSION`) en werk `CHANGELOG.md` bij.
2. Zet de **pluginbestanden zelf** in de repository (niet als zip), zodat ook oudere versies (1.4.2/1.5.0) automatisch kunnen updaten.
3. Maak een release met een tag als `v1.6.0` (kleine letter v).
4. Hang `brink-multimedia-instellingen.zip` (met daarin de map `brink-multimedia-instellingen/`) als bijlage aan de release. Vanaf 1.6.0 gebruikt de updater die zip.

Zit de plugin niet in het pakket, dan breekt de updater af en blijft de huidige versie actief.

## Versiebeheer

Bij elke update wordt:
- het versienummer in `brink-multimedia-instellingen.php` (header + `BMI_VERSION`) opgehoogd, en
- een Engelstalige samenvatting toegevoegd aan `CHANGELOG.md`.

## Licentie

GPL v2 of later.
