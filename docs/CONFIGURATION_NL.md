# Configuratie Gids

Hier vindt u alle instellingen voor [Reloadify](https://www.magmodules.nl/magento2-reloadify.html). Deze gids legt uit wat elke optie doet en wanneer u deze zou willen aanpassen. De module is ontworpen om direct te werken voor de meeste winkels, dus u hoeft waarschijnlijk de meeste instellingen niet aan te passen tenzij u specifieke vereisten hebt.

**Locatie:** Winkels > Configuratie > Reloadify > General

## General

Basis module-instellingen en verbindingsstatus.

### Enabled

Schakelt de Reloadify-integratie in of uit. Wanneer uitgeschakeld, geven alle API-endpoints lege responses en wordt geen data gedeeld met Reloadify.

**Wanneer uitschakelen:**
- Tijdelijk pauzeren van de integratie voor onderhoud
- Problemen testen door de module te isoleren
- Staging omgevingen waar u geen data-synchronisatie wilt

### Access Token

Toont uw integratie-token na het uitvoeren van `bin/magento reloadify:integration`. Dit is een alleen-lezen weergaveveld - u kunt het token hier niet bewerken.

**Waarvoor het is:** Kopieer dit token naar uw Reloadify dashboard om de verbinding te authenticeren. Reloadify gebruikt dit token in API-verzoeken om uw winkeldata op te halen.

**Als u een nieuw token nodig hebt:** Voer `bin/magento reloadify:integration --update=1` uit om het opnieuw te genereren. Vergeet niet het token daarna in Reloadify bij te werken.

---

## Attributes

Map uw Magento productattributen naar de verwachte velden van Reloadify. Dit bepaalt hoe productdata verschijnt in Reloadify voor segmentatie en e-mail personalisatie.

### EAN

De barcode/GTIN identifier voor producten.

**Standaard gedrag:** Gebruikt SKU indien niet geconfigureerd.

**Wanneer wijzigen:** Als u een specifiek EAN/GTIN attribuut hebt (zoals `ean`, `barcode`, of `gtin`), selecteer dit hier. Dit helpt Reloadify producten te matchen tussen systemen en verbetert productherkenning in e-mails.

**Tip:** Als uw producten geen EAN-codes hebben, is het prima om dit op SKU te laten staan.

### Name

De productnaam die naar Reloadify wordt gestuurd.

**Standaard:** Product Name (het standaard Magento naam attribuut)

**Wanneer wijzigen:** Alleen als u een alternatief naam attribuut hebt specifiek voor marketingdoeleinden. De meeste winkels kunnen dit op de standaardwaarde laten staan.

### SKU

De product-identifier gebruikt in Reloadify.

**Standaard:** SKU

**Wanneer wijzigen:** Zelden. Wijzig dit alleen als u een ander attribuut als primaire product-identifier gebruikt (ongebruikelijk).

### Brand

De fabrikant of merknaam voor producten.

**Wanneer configureren:** Als u merkdata beschikbaar wilt hebben in Reloadify voor segmentatie (bijv. "klanten die Nike producten kochten"). Selecteer uw merk/fabrikant attribuut.

**Veelvoorkomende attribuutnamen:** `manufacturer`, `brand`, `merk`

**Als u geen merkdata hebt:** Laat leeg - het is optioneel.

### Description

De productomschrijving die naar Reloadify wordt gestuurd voor gebruik in e-mail content.

**Standaard:** Description (standaard Magento omschrijving)

**Wanneer wijzigen:**
- Als u een korte omschrijving hebt die beter werkt voor e-mails, gebruik `short_description`
- Als u een marketing-specifiek omschrijving attribuut hebt, selecteer dat in plaats daarvan

**Tip:** Reloadify gebruikt dit meestal voor dynamische productblokken in e-mails. Kortere, pakkende omschrijvingen werken vaak beter dan volledige productpagina's.

### Main Image

Welke productafbeelding als primaire afbeelding te versturen.

**Opties:** Base Image, Small Image, Thumbnail, of specifieke afbeeldingsrollen die u hebt geconfigureerd.

**Aanbeveling:** Base Image werkt voor de meeste winkels. Dit is meestal de hoogste kwaliteit en wordt gebruikt in productlijsten.

### Extra Image

Extra productafbeelding om mee te sturen.

**Wanneer gebruiken:** Als u wilt dat Reloadify toegang heeft tot een secundaire afbeelding (zoals een lifestyle foto of alternatieve hoek). Optioneel voor de meeste winkels.

### Extra Fields

Voeg aangepaste attributen toe aan de productdata die naar Reloadify wordt gestuurd.

**Hoe het werkt:** Een dynamische tabel waar u extra Magento attributen kunt mappen naar aangepaste veldnamen in Reloadify.

**Wanneer gebruiken:**
- U wilt segmenteren op aangepaste attributen (kleur, maat, materiaal, seizoen)
- Reloadify campagnes hebben specifieke productdata nodig die niet gedekt wordt door standaard velden
- U hebt product flags (nieuw, sale, bestseller) nuttig voor e-mail targeting

**Voorbeeld setup:**
| Magento Attribuut | Reloadify Veld |
|-------------------|----------------|
| `color` | `color` |
| `is_new` | `new_arrival` |
| `season` | `season` |

### Image

Selecteer de afbeeldingsgrootte variant om te gebruiken.

**Opties variëren** op basis van de geconfigureerde afbeeldingstypen van uw thema.

**Aanbeveling:** Kies een grootte geschikt voor e-mail gebruik - meestal medium resolutie (300-600px). Te grote afbeeldingen vertragen het laden van e-mails.

---

## PWA Settings

Configuratie voor headless/PWA storefronts. Sla deze sectie over als u een traditionele Magento frontend gebruikt (Luma, Hyvä, etc.).

### Base URL

Bepaalt welk URL-formaat te gebruiken voor winkelwagen herstel links.

**Opties:**
- **Magento** - Gebruikt standaard Magento frontend URL (`/reloadify/cart/restore`)
- **PWA** - Gebruikt uw aangepaste PWA URL (configureer hieronder)

**Wanneer PWA gebruiken:** Als uw frontend een aparte applicatie is (React, Vue, etc.) die geen gebruik maakt van Magento's routing.

### PWA URL

Het winkelwagen herstel endpoint van uw PWA.

**Alleen zichtbaar wanneer:** Base URL is ingesteld op "PWA"

**Hoe het werkt:** Reloadify voegt de versleutelde winkelwagen ID toe als query parameter:
```
https://uw-pwa.com/restore-cart?id={encrypted_quote_id}
```

Uw PWA roept dan de Reloadify API aan om de winkelwagen sessie te herstellen.

**Store view configuratie:** Als u store codes in URL's gebruikt, configureer verschillende PWA URL's per store view.

---

## Debug & Logging

Tools voor troubleshooting en het monitoren van de integratie.

### Debug Mode

Wanneer ingeschakeld, logt gedetailleerde informatie over API-verzoeken en responses.

**Log locatie:** `var/log/reloadify/debug.log`

**Wanneer inschakelen:**
- Troubleshooten waarom specifieke producten niet synchroniseren
- Onderzoeken van API response problemen
- Support verzoeken (we kunnen om debug logs vragen)

**Let op:** Houd uitgeschakeld in productie tijdens normale werking - debug logs kunnen groot worden.

### Debug Log Button

Download het huidige debug log bestand voor review.

### Error Log Button

Download het error log (`var/log/reloadify/error.log`).

**Errors worden altijd gelogd** ongeacht debug mode. Controleer dit eerst wanneer iets niet werkt.

### Selftest Button

Voert een diagnostische controle uit op uw configuratie. Tests omvatten:
- Extensie ingeschakeld status
- Magento en PHP versie compatibiliteit
- Integratie token geldigheid

Hetzelfde als het uitvoeren van `bin/magento reloadify:selftest` maar toegankelijk vanuit admin.

---

## Meer Hulp Nodig?

**Documentatie:**
- [Alle Help Artikelen](https://www.magmodules.nl/help/magento2-reloadify.html) - Compleet documentatie overzicht

**Support:**
- [Contact Opnemen](https://www.magmodules.nl/support/) - Hulp van ons team
