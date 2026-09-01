# AAB LeaseFlow

Intern document- en leasingportaal voor Aerts Action Bike.

De applicatie beheert leasingdossiers van verkoop tot verwerking: klant koppelen, PDF-bestelbon uploaden, in de browser bekijken, digitaal laten ondertekenen, getekende PDF genereren, status opvolgen en verwerken via een centrale queue.

## MVP in `develop`

Beschikbaar:

- login met rollen (`seller`, `processor`, `manager`, `admin`)
- klanten en leasingdossiers
- standaard leasepartners
- PDF-upload met MIME-validatie en private opslag
- browserpreview van de bestelbon
- unieke, gehashte tekenlinks met vervaldatum
- handtekening via touchscreen/muis
- PDF-generatie via FPDI
- originele + ondertekende documentversie
- SHA-256 hashes voor documentintegriteit
- dossierstatussen en historiek
- auditlog
- centrale verwerkingsqueue
- endpoints voor starten, terugsturen en afronden van verwerking

## Vereisten

- PHP 8.2+
- MySQL 8 / MariaDB 10.6+
- Composer
- Apache met `mod_rewrite` of equivalente Nginx-routing
- HTTPS in productie

## Installatie

```bash
git clone https://github.com/warrevdm/document-portal.git
cd document-portal
git checkout develop
composer install --no-dev --optimize-autoloader
cp .env.example .env
```

Maak een database aan en importeer:

```text
database/schema.sql
```

Pas daarna `.env` aan met databasegegevens, `APP_URL` en een lange willekeurige `APP_KEY`.

Maak de eerste administrator aan:

```bash
php scripts/create-admin.php admin@voorbeeld.be "Administrator" "een-sterk-wachtwoord-van-minstens-12-tekens"
```

Configureer de webroot van het domein naar:

```text
/document-portal/public
```

De map `storage/private` moet schrijfbaar zijn voor PHP en mag nooit rechtstreeks via de webserver publiek toegankelijk zijn.

## Hoofdflow

```text
VERKOPER
  -> klant + leasingdossier
  -> bestelbon PDF uploaden
  -> tekenverzoek genereren

KLANT
  -> beveiligde link openen
  -> PDF bekijken
  -> akkoord bevestigen
  -> tekenen

SYSTEEM
  -> signature in PDF plaatsen
  -> nieuwe immutable documentversie
  -> SHA-256 opslaan
  -> status: klaar voor verwerking

VERWERKER
  -> centrale queue
  -> signed PDF bekijken/downloaden
  -> verwerken
  -> status afronden of terugsturen
```

## Beveiligingsprincipes

- signing tokens worden alleen gehasht opgeslagen
- bestanden staan buiten `public/`
- PDF-downloads lopen via geautoriseerde routes
- originele documenten worden niet overschreven
- elke ondertekende versie krijgt een eigen SHA-256
- CSRF-validatie op schrijfacties
- password hashing via PHP `password_hash`
- auditlogs voor relevante dossier- en documentacties

## Volgende iteraties

1. visuele drag-and-drop positionering van signature fields in de PDF-viewer
2. e-mail/SMS versturen van tekenlinks + reminders
3. gebruikersbeheer in admin
4. comments/aanvullingsflow in de UI
5. QR-signing aan de balie
6. PDF-tekstextractie en controle database ↔ bestelbon
7. Tilroy-integratie
8. partner-specifieke documenttemplates/checklists
9. optionele itsme/eIDAS signing provider
10. managementrapportering

## Belangrijk

De ingebouwde getekende canvas-handtekening is een technische elektronische ondertekenflow. Voor documenten waarvoor een advanced of qualified electronic signature vereist is, moet een geschikte externe trust/signing provider worden geïntegreerd.
