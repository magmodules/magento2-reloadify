# Probleemoplossing

Problemen met [Reloadify](https://www.magmodules.nl/magento2-reloadify.html)? Deze pagina behandelt de meest voorkomende problemen en hoe ze op te lossen. Begin met de snelle diagnostiek om te bepalen wat er mis is, en vind dan uw specifieke probleem hieronder.

## Snelle Diagnostiek

Voer deze controles eerst uit:

1. **Module ingeschakeld?**
   Check: Winkels > Configuratie > Reloadify > General > Enabled = Yes

2. **Token gegenereerd?**
   Check: General > Access Token toont een token waarde
   Indien leeg: Voer `bin/magento reloadify:integration` uit

3. **Voer selftest uit:**
   ```bash
   bin/magento reloadify:selftest
   ```
   Alle tests zouden moeten slagen. Noteer eventuele fouten.

4. **Controleer error logs:**
   ```bash
   tail -100 var/log/reloadify/error.log
   ```

5. **Cache legen:**
   ```bash
   bin/magento cache:flush
   ```

---

## Veelvoorkomende Problemen

### Probleem: Geen toegangstoken zichtbaar in admin

**Symptomen:**
- Access Token veld is leeg in configuratie
- Reloadify meldt "invalid token" of verbindingsfouten

**Oplossing:**
1. Genereer het integratie token via CLI:
   ```bash
   bin/magento reloadify:integration
   ```
2. Kopieer het getoonde token naar Reloadify
3. Ververs de admin pagina - token zou nu moeten verschijnen

**Als token nog steeds niet verschijnt:**
- Controleer of de Magento integratie is aangemaakt: Systeem > Integraties
- Zoek naar "Reloadify" integratie - zou Actief moeten zijn

**Preventie:**
Token wordt automatisch gegenereerd tijdens module installatie via setup patch. Als dit niet is uitgevoerd, maakt het CLI commando het handmatig aan.

---

### Probleem: Reloadify kan niet verbinden / API geeft 401

**Symptomen:**
- Reloadify dashboard toont "Connection failed"
- API requests geven 401 Unauthorized
- Sync start niet

**Oplossing:**
1. Verifieer dat het token in Reloadify overeenkomt met het token in Magento admin
2. Controleer of de integratie actief is: Systeem > Integraties > Reloadify > Status = Actief
3. Bij token mismatch, regenereer:
   ```bash
   bin/magento reloadify:integration --update=1
   ```
4. Werk het nieuwe token direct bij in Reloadify

**Als het nog steeds faalt:**
- Controleer of uw server externe API requests blokkeert
- Verifieer dat de winkel URL in Reloadify correct is (inclusief https://)
- Controleer op Web Application Firewall (WAF) regels die requests blokkeren

**Preventie:**
Regenereer tokens niet onnodig. Als u het doet, werk Reloadify direct bij.

---

### Probleem: Producten synchroniseren niet / ontbrekende producten

**Symptomen:**
- Reloadify toont minder producten dan verwacht
- Specifieke producten verschijnen niet in Reloadify
- Productdata is verouderd

**Oplossing:**
1. Schakel debug mode in: Winkels > Configuratie > Reloadify > Debug & Logging > Debug Mode = Yes
2. Trigger een sync vanuit Reloadify
3. Controleer debug logs:
   ```bash
   tail -200 var/log/reloadify/debug.log
   ```
4. Zoek naar product filtering of errors

**Veelvoorkomende oorzaken:**
- Product is uitgeschakeld of niet zichtbaar
- Product is niet op voorraad (afhankelijk van Reloadify instellingen)
- Product is in een niet-gesynchroniseerde store view

**Controleer product zichtbaarheid:**
- Catalogus > Producten > [Product] > Zichtbaarheid = "Catalogus, Zoeken" of "Catalogus"
- Status = Ingeschakeld

**Preventie:**
Reloadify synchroniseert standaard alleen zichtbare, ingeschakelde producten. Dit is meestal wat u wilt.

---

### Probleem: Winkelwagen herstel links werken niet

**Symptomen:**
- Klikken op verlaten winkelwagen e-mail links toont error
- Klant komt op homepage terecht in plaats van winkelwagen
- "Quote does not exist" melding

**Oplossing:**
1. Controleer of quote nog bestaat in database (quotes verlopen)
2. Verifieer dat het link formaat overeenkomt met uw setup:
   - Standaard Magento: `/reloadify/cart/restore?id={encrypted}`
   - PWA: Uw aangepaste URL met `?id={encrypted}`

3. Test met een verse winkelwagen:
   - Voeg items toe aan winkelwagen
   - Haal de versleutelde winkelwagen ID op van Reloadify
   - Test de herstel link handmatig

**Als quote verlopen is:**
Magento verwijdert oude quotes op basis van uw configuratie. Controleer:
Winkels > Configuratie > Sales > Checkout > Quote Lifetime (dagen)

**PWA setup problemen:**
- Verifieer dat PWA URL correct is geconfigureerd
- Controleer dat uw PWA de `id` parameter afhandelt
- Zorg dat PWA het restore API endpoint aanroept

**Preventie:**
Stel Quote Lifetime hoog genoeg in zodat verlaten winkelwagen e-mails aankomen voordat quotes verlopen (meestal 30+ dagen).

---

### Probleem: Winkelwagen al omgezet naar bestelling

**Symptomen:**
- Winkelwagen herstel toont "An order has already been placed for this quote"
- Klant klikte op link na het voltooien van aankoop

**Oplossing:**
Dit is verwacht gedrag - de klant heeft al gekocht. De module voorkomt correct het herstellen van een voltooide bestelling's winkelwagen.

**Preventie:**
Configureer Reloadify om verlaten winkelwagen e-mails te stoppen zodra een bestelling is geplaatst.

---

### Probleem: Selftest faalt - "Extension Disabled"

**Symptomen:**
- Selftest toont extension disabled
- Maar Enabled = Yes in configuratie

**Oplossing:**
1. Leeg alle caches:
   ```bash
   bin/magento cache:flush
   ```
2. Controleer correcte scope - instelling kan uitgeschakeld zijn op website/store niveau
3. Verifieer dat configuratie is opgeslagen (klik Save Config)

---

### Probleem: Selftest faalt - "Integration Token Invalid"

**Symptomen:**
- Selftest toont token invalid
- Token verschijnt in admin maar werkt niet

**Oplossing:**
1. Regenereer het token:
   ```bash
   bin/magento reloadify:integration --update=1
   ```
2. Werk het nieuwe token bij in Reloadify
3. Voer selftest opnieuw uit

**Als het blijft falen:**
- Controleer Systeem > Integraties voor dubbele Reloadify entries
- Verwijder oude integraties, houd alleen één actief

---

### Probleem: Grote debug log bestanden

**Symptomen:**
- `var/log/reloadify/debug.log` is gigabytes groot
- Schijfruimte raakt op

**Oplossing:**
1. Schakel debug mode uit:
   Winkels > Configuratie > Reloadify > Debug & Logging > Debug Mode = No
2. Verwijder oude logs:
   ```bash
   rm var/log/reloadify/debug.log
   ```

**Preventie:**
Schakel debug mode alleen in bij actief troubleshooten. Schakel uit wanneer klaar.

---

## Debug Mode

### Debug Mode Inschakelen

Navigeer naar: Winkels > Configuratie > Reloadify > General > Debug & Logging

Zet **Debug Mode** op **Yes** en sla op.

### Log Locaties

| Log | Locatie | Bevat |
|-----|---------|-------|
| Debug | `var/log/reloadify/debug.log` | API requests, responses, verwerkingsdetails |
| Error | `var/log/reloadify/error.log` | Errors en exceptions (altijd gelogd) |

### Logs Bekijken

Via CLI:
```bash
# Recente debug entries
tail -100 var/log/reloadify/debug.log

# Recente errors
tail -100 var/log/reloadify/error.log

# Volg logs in real-time
tail -f var/log/reloadify/debug.log
```

Via Admin:
Gebruik de "Show Debug Log" en "Show Error Log" knoppen in de Debug & Logging sectie.

### Waar Op Te Letten

- **API request URLs** - verifieer dat correcte endpoints worden aangeroepen
- **Response codes** - 200 = success, 401 = auth probleem, 500 = server error
- **Product/order IDs** - vind specifieke items in de sync
- **Error messages** - directe indicatie van wat er mis is

---

## Meer Hulp Nodig?

**Documentatie:**
- [Alle Help Artikelen](https://www.magmodules.nl/help/magento2-reloadify.html) - Compleet documentatie overzicht

**Support:**
- [Contact Opnemen](https://www.magmodules.nl/support/) - Hulp van ons team
