![Package Logo](social-preview.svg?raw=true "Project Logo")

Document Sender for AbraFlexi
=============================

Scripts for sending documents are available:

[SendUnsent.php](src/SendUnsent.php) - sends unsent documents in bulk directly from AbraFlexi in the standard way

[SendUnsentAttachments.php](src/SendUnsentAttachments.php) - finds unsent documents, attaches attachments to them, and sends them via the default PHP mailer or SMTP

Documents are sent to addresses according to the following key:

1. "contactEmail" from the document
2. company email
3. primary contact email
4. contact email

If an address with the prefix cc is found in the document note, e.g., "cc:email@example.com, cc:copy@example.com", a copy will also be sent to these emails.

Configuration
-------------

Configuration is done by entering directives into the .env file, defining them as constants, or setting environment variables.
The Debian package expects the configuration file in the /etc/abraflexi-mailer folder, where a sample file .env.template is available.

```env
APP_NAME=AbraFlexiMailer                        - application name in syslog
APP_DEBUG=true                                  - enable debug mode
MUTE=true                                       - do not send messages to recipients but to

EASE_MAILTO=info@vitexsoftware.cz               - messages are sent here if mute is active

ABRAFLEXI_URL="https://demo.abraflexi.eu:5434"
ABRAFLEXI_LOGIN="winstrom"
ABRAFLEXI_PASSWORD="winstrom"
ABRAFLEXI_COMPANY="demo"
ABRAFLEXI_CUSTOMER="demo"

ADD_LOGO=true                                   - include company logo in the email
ADD_QRCODE=true                                 - include QR payment image in the email
MAIL_CC=info@vitexsoftware.cz                   - send all emails also in copy to this address
MAIL_FROM=office@vitexsoftware.cz               - sender address

EASE_LOGGER="console|syslog"                    - logging method
SEND_LOCKED=False                               - try to temporarily unlock a locked document
DRY_RUN=False                                   - if enabled, does not write the date and sending status to documents
```

Templates
---------

It is assumed that the template is named according to the record type, e.g., **invoice-issued.ftl**
and is stored in the "templates" folder ( /usr/share/abraflexi-mailer/templates in Debian )

The following variables can be used in the templates:

* ${application} – Application name, i.e., "AbraFlexi BulkMail"
* ${user} – User object, which can be further worked with
* ${company} – Company settings
* ${uzivatelJmeno} – Your first name
* ${uzivatelPrijmeni} – Your last name
* ${titulJmenoPrijmeni} – Your full name, including achieved titles
* ${nazevFirmy} – Company name
* ${doklad} – Document to be sent

Bulk Mailer
-----------

If we want to send emails to all clients from Nerudova Street in Prague:

```shell
abraflexi-bulkmail templates/template.ftl "(city='Prague' AND street='Nerudova')"
```

When used in a [template](tests/test.ftl), the variables for each sent message
are filled from https://demo.flexibee.eu/c/demo_de/addressbook/properties

Unsent mail reporter
--------------------

Script which produces reports of unsent invoices in MultiFlexi-compliant format.

Since version 1.3.8, reports conform to the [MultiFlexi report schema](https://raw.githubusercontent.com/VitexSoftware/php-vitexsoftware-multiflexi-core/refs/heads/main/multiflexi.report.schema.json):

```json
{
  "status": "warning",
  "timestamp": "2025-10-04T01:00:00+00:00",
  "message": "2 unsent invoices found affecting 1 companies",
  "artifacts": {
    "unsent_invoices": [
      {
        "id": 1131,
        "firma": {
          "value": "code:CUSTOMER",
          "target": "adresar",
          "ref": "/c/vitex_software/adresar/827.odeslat')",
          "showAs": "CUSTOMER: CUSTOMER l.t.d."
        },
        "kontaktEmail": "info@customer.com",
        "poznam": "",
        "kod": "VF1-0077/2024",
        "email": "info@customer.com",
        "recipients": "info@customer.com"
      }
    ]
  },
  "metrics": {
    "total_unsent": 2,
    "companies_affected": 1
  }
}
```



Dependencies
------------

This tool uses the following libraries for its functionality:

* [**EasePHP Framework**](https://github.com/VitexSoftware/php-ease-core)   - helper functions, e.g., logging
* [**AbraFlexi**](https://github.com/Spoje-NET/php-abraflexi)               - communication with [AbraFlexi](https://flexibee.eu/)
* [**AbraFlexi Bricks**](https://github.com/VitexSoftware/AbraFlexi-Bricks) - classes for Customer, Reminder, and ReminderSender are used

Acknowledgements
----------------

This software would not have been created without the support of:

[ ![Spoje.Net](doc/spojenet.gif?raw=true "Spoje.Net s.r.o.") ](https://spoje.net/)

Other software for AbraFlexi
----------------------------

* [Regular reports from AbraFlexi](https://github.com/VitexSoftware/AbraFlexi-Digest)
* [Reminder sender](https://github.com/VitexSoftware/php-abraflexi-reminder)
* [Client Zone for AbraFlexi](https://github.com/VitexSoftware/AbraFlexi-ClientZone)
* [Tools for testing and managing AbraFlexi](https://github.com/VitexSoftware/AbraFlexi-TestingTools)
* [Monitoring the functionality of the AbraFlexi server](https://github.com/VitexSoftware/monitoring-plugins-abraflexi)
* [AbraFlexi server without graphical dependencies](https://github.com/VitexSoftware/abraflexi-server-deb)

MultiFlexi
----------

AbraFlexi Mailer is ready to run as a [MultiFlexi](https://multiflexi.eu) application.
See the full list of ready-to-run applications within the MultiFlexi platform on the [application list page](https://www.multiflexi.eu/apps.php).

Since version 1.3.8, all produced reports conform to the [MultiFlexi report schema](https://raw.githubusercontent.com/VitexSoftware/php-vitexsoftware-multiflexi-core/refs/heads/main/multiflexi.report.schema.json), ensuring proper integration and monitoring capabilities within the MultiFlexi ecosystem.

[![MultiFlexi App](https://github.com/VitexSoftware/MultiFlexi/blob/main/doc/multiflexi-app.svg)](https://www.multiflexi.eu/apps.php)

Debian/Ubuntu
-------------

For Linux, .deb packages are available. Please use the repo:

```shell
    echo "deb http://repo.vitexsoftware.com $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/vitexsoftware.list
    sudo wget -O /etc/apt/trusted.gpg.d/vitexsoftware.gpg http://repo.vitexsoftware.cz/keyring.gpg
    sudo apt update
    sudo apt install abraflexi-mailer
```

After installing the package, the following new commands are available in the system:

* **abraflexi-send**                    - sends a document
* **abraflexi-send-unsent**             - sends unsent documents
* **abraflexi-send-attachments**        - sends a document with attachments (TODO)
* **abraflexi-send-unsent-attachments** - sends unsent documents with attachments
* **abraflexi-show-unsent**             - lists unsent documents
* **abraflexi-bulkmail**                - sends emails to contacts from the address book in bulk

