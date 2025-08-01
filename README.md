# Gestione Accessi BluTrasimeno

Plugin WordPress per la gestione delle prenotazioni e comunicazioni automatiche con il servizio Alloggiati Web della Polizia di Stato.

**Versione:** 1.3.0  
**Autore:** Gianni Valeri  
**Compatibilità:** WordPress 5.0+, PHP 7.4+

## 📋 Descrizione

Questo plugin permette alle strutture ricettive di:
- Gestire prenotazioni e ospiti
- Raccogliere dati degli ospiti tramite form frontend
- Inviare automaticamente le schedine al servizio Alloggiati Web
- Monitorare lo stato delle comunicazioni
- Gestire cron job per invii automatici

## 🏗️ Architettura

Il plugin è stato completamente refactorizzato con una struttura modulare e maintainabile:

```
gestione-accessi-bt/
├── gestione-accessi-bt.php          # File principale del plugin
├── includes/
│   ├── class-plugin-core.php        # Classe principale singleton
│   ├── admin/                       # Componenti amministrazione
│   │   ├── class-admin-menu.php     # Gestione menu admin
│   │   ├── class-admin-pages.php    # Pagine amministrative
│   │   └── class-settings-manager.php # Gestione impostazioni
│   ├── ajax/
│   │   └── class-ajax-handlers.php  # Gestori AJAX
│   ├── cron/
│   │   └── class-cron-manager.php   # Gestione cron jobs
│   ├── database/
│   │   ├── class-database-manager.php # Gestione database
│   │   └── class-booking-repository.php # Repository prenotazioni
│   ├── frontend/
│   │   ├── class-frontend-handler.php # Gestione frontend
│   │   └── class-guest-forms.php    # Form ospiti
│   └── services/
│       ├── class-alloggiati-client.php # Client SOAP
│       ├── class-xml-parser.php     # Parser XML
│       └── class-schedule-formatter.php # Formattatore schedine
├── assets/
│   ├── css/
│   │   ├── admin.css               # Stili amministrazione
│   │   └── frontend.css            # Stili frontend
│   └── js/
│       ├── admin.js                # Script amministrazione
│       └── frontend.js             # Script frontend
└── templates/
    ├── admin/
    │   ├── dashboard.php           # Dashboard amministrativa
    │   ├── new-booking.php         # Form nuova prenotazione
    │   └── settings.php            # Pagina impostazioni
    └── frontend/
        ├── guest-form.php          # Form registrazione ospiti
        └── booking-status.php      # Stato prenotazione
```

## 🚀 Installazione

1. Carica la cartella del plugin in `/wp-content/plugins/`
2. Attiva il plugin dal pannello WordPress
3. Vai su **Gestione Accessi BT** > **Impostazioni**
4. Configura le credenziali del servizio Alloggiati Web
5. Imposta i dati della struttura ricettiva

## ⚙️ Configurazione

### Credenziali Alloggiati Web
- **Username**: Fornito dalla Polizia di Stato
- **Password**: Password associata all'username
- **WS Key**: Chiave del web service

### Impostazioni Struttura
- Nome e indirizzo della struttura
- Contatti (telefono, email)
- Dati amministrativi (comune, provincia)

### Configurazione Email
- Mittente per le email agli ospiti
- Template personalizzabili
- Notifiche amministrative

## 📱 Utilizzo

### Creazione Prenotazioni
1. Vai su **Gestione Accessi BT** > **Dashboard**
2. Clicca su **Nuova Prenotazione**
3. Inserisci i dati del soggiorno
4. Salva la prenotazione

### Registrazione Ospiti
Gli ospiti possono registrarsi tramite:
- URL diretto: `/registrazione-ospiti/[codice-prenotazione]`
- Shortcode: `[gabt_guest_form booking_code="CODICE"]`

### Stato Prenotazione
Verifica stato tramite:
- URL: `/stato-prenotazione/[codice-prenotazione]`
- Shortcode: `[gabt_booking_status]`

## 🔄 Funzionalità Automatiche

### Cron Jobs
- **Invio giornaliero**: Invia schedine automaticamente
- **Pulizia log**: Rimuove log vecchi
- **Notifiche**: Avvisa in caso di errori

### Sicurezza
- Nonce verification su tutte le richieste AJAX
- Sanitizzazione e validazione input
- Prepared statements per database
- Logging errori dettagliato

## 🛠️ Sviluppo

### Autoloader
Il plugin utilizza un autoloader personalizzato per le classi:
- Prefisso: `GABT_`
- Convenzione: `class-nome-classe.php`

### Hook WordPress
- `init`: Inizializzazione plugin
- `admin_menu`: Menu amministrazione
- `wp_enqueue_scripts`: Script frontend
- `admin_enqueue_scripts`: Script admin

### Database
Tabelle create automaticamente:
- `{prefix}_gabt_bookings`: Prenotazioni
- `{prefix}_gabt_guests`: Ospiti

## 🧪 Testing

### Test Connessione
Il plugin include strumenti per testare:
- Connessione al servizio Alloggiati Web
- Generazione token
- Download tabelle di sistema
- Invio schedine di test

### Debug Mode
Abilita la modalità debug per:
- Logging dettagliato
- Informazioni aggiuntive sui errori
- Tracciamento richieste SOAP

## 📄 Changelog

### 1.3.0
- Refactoring completo dell'architettura
- Separazione in moduli specializzati
- Miglioramento sicurezza e performance
- Nuova interfaccia utente responsive
- Sistema di template modulare

### Versioni Precedenti
- 1.2.x: Versione monolitica originale
- Funzionalità base per gestione prenotazioni

## 🤝 Supporto

Per supporto tecnico o segnalazione bug:
- Autore: Gianni Valeri
- Email: [inserire email di supporto]

## 📜 Licenza

Questo plugin è distribuito sotto licenza GPL v2 o successiva.

## 🔧 Requisiti Tecnici

- **WordPress**: 5.0 o superiore
- **PHP**: 7.4 o superiore
- **MySQL**: 5.6 o superiore
- **Estensioni PHP**: SOAP, XML, cURL
- **Connessione**: HTTPS per comunicazioni sicure

## 📚 Documentazione API

### Classi Principali

#### GABT_Plugin_Core
Classe singleton principale che coordina tutti i componenti.

#### GABT_Booking_Repository
Gestisce operazioni CRUD su prenotazioni e ospiti.

#### GABT_Alloggiati_Client
Client SOAP per comunicazioni con il servizio Alloggiati Web.

#### GABT_Ajax_Handlers
Gestisce tutte le richieste AJAX con validazione e sicurezza.

### Shortcodes Disponibili

- `[gabt_guest_form]`: Form registrazione ospiti
- `[gabt_booking_status]`: Stato prenotazione
- `[gabt_booking_search]`: Ricerca prenotazione

### Filtri WordPress

- `gabt_guest_form_fields`: Personalizza campi form ospiti
- `gabt_email_template`: Personalizza template email
- `gabt_booking_statuses`: Personalizza stati prenotazione
