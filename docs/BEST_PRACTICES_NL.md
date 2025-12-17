# Best Practices

Dit zijn de aanbevolen manieren om [Reloadify](https://www.magmodules.nl/magento2-reloadify.html) te configureren op basis van wat we goed hebben zien werken. De module is eenvoudig, maar een paar aanpassingen kunnen uw marketing automation resultaten verbeteren. We hebben ook veelvoorkomende fouten toegevoegd om te vermijden.

## Algemene Richtlijnen

### Do's

✅ Voer `reloadify:selftest` uit na de eerste setup om de verbinding te verifiëren

✅ Gebruik een specifiek EAN/barcode attribuut als u er een hebt - verbetert product matching

✅ Map uw merk attribuut - maakt merk-gebaseerde segmentatie mogelijk in Reloadify

✅ Houd debug mode uit in productie om grote logbestanden te vermijden

✅ Test winkelwagen herstel links voordat u verlaten winkelwagen campagnes lanceert

### Don'ts

❌ Deel uw toegangstoken niet publiekelijk - het geeft API-toegang tot uw winkeldata

❌ Schakel de module niet uit terwijl Reloadify campagnes actief zijn - breekt e-mail links

❌ Map niet hetzelfde Magento attribuut naar meerdere Reloadify velden

❌ Gebruik geen volledige productafbeeldingen - medium resolutie (300-600px) laadt sneller in e-mails

---

## Veelvoorkomende Scenario's

### Scenario 1: Standaard Winkel Setup

**Gebruik:** Eentalige winkel met standaard Magento attributen

**Configuratie:**
General:
- Enabled: Yes
- Genereer token via CLI

Attributes:
- Laat alle standaardwaarden (SKU, Product Name, Description)
- Map Brand als u een fabrikant attribuut hebt

PWA Settings:
- Base URL: Magento (standaard)

**Resultaat:** Reloadify synchroniseert uw volledige catalogus, klantdata en bestellingen met standaard Magento data.

---

### Scenario 2: Multi-Brand Winkel

**Gebruik:** Winkel met meerdere merken, wilt e-mails segmenteren per merk

**Configuratie:**
Attributes:
- Brand: Selecteer uw fabrikant/merk attribuut
- Extra Fields: Voeg merk-gerelateerde attributen toe zoals `brand_collection` of `brand_tier`

**Resultaat:** Reloadify kan segmenten maken zoals "Nike klanten" of "Premium merk kopers" voor gerichte campagnes.

---

### Scenario 3: PWA/Headless Frontend

**Gebruik:** React/Vue frontend, Magento als headless backend

**Configuratie:**
PWA Settings:
- Base URL: PWA
- PWA URL: `https://uw-pwa.com/cart/restore`

**Uw PWA moet:**
1. De `?id={encrypted_quote_id}` parameter accepteren
2. Het Reloadify winkelwagen herstel API endpoint aanroepen
3. De winkelwagen sessie herbouwen in uw frontend

**Resultaat:** Verlaten winkelwagen e-mails linken naar uw PWA in plaats van Magento frontend.

---

### Scenario 4: Rijke Productdata voor Segmentatie

**Gebruik:** Wilt dat Reloadify segmenteert op product attributen (kleur, maat, seizoen)

**Configuratie:**
Attributes > Extra Fields:
| Magento Attribuut | Reloadify Veld |
|-------------------|----------------|
| `color` | `color` |
| `size` | `size` |
| `season` | `season` |
| `is_sale` | `on_sale` |

**Resultaat:** Reloadify campagnes kunnen targeten op "klanten die rode producten kochten" of "wintercollectie kopers."

---

## Prestatie Tips

### Afbeelding Optimalisatie

Selecteer passend formaat afbeeldingen in de configuratie:
- Te groot = trage e-mail laden, mogelijke bezorgproblemen
- Te klein = slechte kwaliteit in high-DPI e-mail clients
- Sweet spot: 400-600px breedte werkt voor de meeste e-mail templates

### API Request Afhandeling

Reloadify haalt data op van uw winkel via API. De module is hiervoor geoptimaliseerd, maar:
- Grote catalogi (50k+ producten) kunnen langer duren om initieel te synchroniseren
- Delta sync (`/products-delta`) haalt alleen recent gewijzigde producten op
- Syncs draaien vanaf Reloadify's servers, niet via Magento cron

### Log Beheer

Als u debug mode inschakelt voor troubleshooting:
- Debug logs groeien snel met API verkeer
- Schakel uit na het oplossen van problemen
- Logs staan in `var/log/reloadify/`

---

## Veelvoorkomende Fouten

### Fout: EAN attribuut niet mappen

**Waarom het uitmaakt:** EAN/barcode helpt Reloadify producten accuraat te matchen, vooral als u op meerdere kanalen verkoopt.

**Correcte aanpak:** Als u een barcode attribuut hebt (`ean`, `gtin`, `barcode`), map dit in Attributes > EAN. Als u geen barcodes hebt, werkt SKU prima als fallback.

---

### Fout: Debug mode permanent gebruiken in productie

**Waarom het fout is:** Debug logt elke API request. Met regelmatige Reloadify syncs creëert dit grote logbestanden die schijfruimte verspillen.

**Correcte aanpak:** Schakel debug mode alleen in bij het troubleshooten van specifieke problemen. Schakel uit na het oplossen.

---

### Fout: Token regenereren zonder Reloadify bij te werken

**Waarom het fout is:** Als u `reloadify:integration --update=1` uitvoert, wordt het oude token ongeldig. Reloadify kan niet meer synchroniseren.

**Correcte aanpak:** Na het regenereren van een token, werk het direct bij in uw Reloadify dashboard. Plan token rotaties tijdens rustige periodes.

---

### Fout: Description mappen naar een zeer lang attribuut

**Waarom het uitmaakt:** Productomschrijvingen in e-mails moeten bondig zijn. Een omschrijving van 2000 woorden ziet er slecht uit in een e-mail card.

**Correcte aanpak:** Als uw hoofdomschrijving zeer gedetailleerd is, overweeg `short_description` te gebruiken of maak een marketing-specifiek attribuut.

---

## Meer Hulp Nodig?

**Documentatie:**
- [Alle Help Artikelen](https://www.magmodules.nl/help/magento2-reloadify.html) - Compleet documentatie overzicht

**Support:**
- [Contact Opnemen](https://www.magmodules.nl/support/) - Hulp van ons team
