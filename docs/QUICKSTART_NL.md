# Aan de Slag

Dit is de snelstartgids voor [Reloadify](https://www.magmodules.nl/magento2-reloadify.html). We verbinden u in ongeveer 5 minuten met het Reloadify platform. Dit behandelt het inschakelen van de module, het genereren van uw toegangstoken en het verifiëren dat de verbinding werkt. Zodra dit klaar is, kan Reloadify automatisch uw winkeldata synchroniseren.

## Vereisten

- Module geïnstalleerd en cache geleegd
- SSH-toegang om CLI-commando's uit te voeren
- Een Reloadify account (registreer op reloadify.com)

## Stap 1: Module Inschakelen

Navigeer naar: **Winkels > Configuratie > Reloadify > General**

Zet **Enabled** op **Yes** en sla op.

## Stap 2: Integratie Token Genereren

Voer dit commando uit via SSH:

```bash
bin/magento reloadify:integration
```

Dit maakt een Magento-integratie aan en toont een toegangstoken. Kopieer dit token - u hebt het nodig voor Reloadify.

**Tip:** Het token is ook zichtbaar in het admin panel onder **General > Access Token** na het genereren.

## Stap 3: Reloadify Verbinden

1. Log in op uw Reloadify dashboard
2. Ga naar uw winkelverbinding instellingen
3. Plak het toegangstoken uit Stap 2
4. Sla de verbinding op

Reloadify gebruikt dit token nu om API-verzoeken naar uw winkel te authenticeren.

## Stap 4: Attribuut Mapping Configureren (Optioneel)

Als uw productdata aangepaste attributen gebruikt, configureer de mapping:

Navigeer naar: **Winkels > Configuratie > Reloadify > General > Attributes**

Map uw attributen:
- **EAN** → Uw barcode/GTIN attribuut (of SKU)
- **Brand** → Uw fabrikant/merk attribuut
- **Description** → Meestal werkt de standaard omschrijving

De meeste winkels kunnen dit overslaan - de standaardwaarden werken voor standaard Magento-setups.

## Stap 5: Verbinding Verifiëren

Voer de selftest uit om te bevestigen dat alles werkt:

```bash
bin/magento reloadify:selftest
```

U zou alle tests moeten zien slagen. Als er tests falen, bekijk de Probleemoplossing gids.

U kunt de selftest ook uitvoeren vanuit admin: **Winkels > Configuratie > Reloadify > General > Debug & Logging > Run Selftest**

## Wat Gebeurt Er Nu

Zodra verbonden, synchroniseert Reloadify automatisch:
- Producten en varianten
- Categorieën
- Klantprofielen
- Nieuwsbrief abonnees
- Bestellingen
- Verlaten winkelwagens

De synchronisatie draait vanaf Reloadify's servers via de API - geen cron jobs of prestatie-impact op uw winkel.

---

## Meer Hulp Nodig?

**Documentatie:**
- [Alle Help Artikelen](https://www.magmodules.nl/help/magento2-reloadify.html) - Compleet documentatie overzicht

**Support:**
- [Contact Opnemen](https://www.magmodules.nl/support/) - Hulp van ons team
