# CLI Commando's

Command line tools voor het beheren van de [Reloadify](https://www.magmodules.nl/magento2-reloadify.html) integratie. Deze commando's zijn handig voor de initiële setup en troubleshooting.

## Beschikbare Commando's

| Commando | Beschrijving |
|----------|--------------|
| `reloadify:integration` | Maak of update het integratie token |
| `reloadify:selftest` | Voer diagnostische tests uit op de module |

---

## reloadify:integration

Maakt de Magento integratie aan en genereert een toegangstoken voor Reloadify.

```bash
bin/magento reloadify:integration
```

**Output:**
```
Integration token: abc123xyz...
```

Kopieer dit token naar uw Reloadify dashboard om de verbinding te authenticeren.

### Opties

| Optie | Beschrijving |
|-------|--------------|
| `--update=1` | Regenereer het token (maakt oud token ongeldig) |

### Token Regenereren

Als u een nieuw token nodig hebt (security rotatie, gecompromitteerd token):

```bash
bin/magento reloadify:integration --update=1
```

**Belangrijk:** Na het regenereren, werk het token direct bij in Reloadify. Het oude token stopt onmiddellijk met werken.

### Wanneer Gebruiken

- **Initiële setup** - Genereer token na het installeren van de module
- **Token rotatie** - Periodiek regenereren voor beveiliging
- **Na restore** - Als database werd hersteld zonder de integratie

---

## reloadify:selftest

Voert diagnostische controles uit om te verifiëren dat de module correct is geconfigureerd.

```bash
bin/magento reloadify:selftest
```

**Output:**
```
Extension Status: success - Enabled
Extension Version: success - 1.15.0
Magento Version: success - 2.4.6
PHP Version: success - 8.2.0
```

### Wat Het Test

| Test | Controleert |
|------|-------------|
| Extension Status | Module is ingeschakeld in configuratie |
| Extension Version | Huidige geïnstalleerde versie |
| Magento Version | Magento versie compatibiliteit |
| PHP Version | PHP versie compatibiliteit |

### Resultaten Interpreteren

- **success** - Test geslaagd, geen actie nodig
- **failed** - Probleem gedetecteerd, controleer de melding voor details

### Wanneer Gebruiken

- **Na installatie** - Verifieer dat alles correct is opgezet
- **Troubleshooting** - Eerste stap wanneer iets niet werkt
- **Na upgrades** - Bevestig compatibiliteit na Magento of module updates

---

## Meer Hulp Nodig?

**Documentatie:**
- [Alle Help Artikelen](https://www.magmodules.nl/help/magento2-reloadify.html) - Compleet documentatie overzicht

**Support:**
- [Contact Opnemen](https://www.magmodules.nl/support/) - Hulp van ons team
