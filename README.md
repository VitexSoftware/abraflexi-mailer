![Package Logo](project-logo.svg?raw=true "Project Logo")

Odesílač dokladů pro AbraFlexi
==============================



K dispozici jsou skripty pro odesílání dokladů:

[SendUnsent.php](src/SendUnsent.php) - hromadně odešle neodeslané.
[SendUnsentAttachments.php](src/SendUnsentAttachments.php) - najde neodeslané, připojí k nim přílohy a odešle

Doklady jsou odesílány na adresy dle následujícího klíče:

1. "kontaktEmail" z dokladu
2. email firmy
3. email primárního kontaktu
4. email kontaktu

Pokud je v poznámce dokladu nalezena adresa s prefixem cc např.: "cc:emailova@adresa.cz, cc:kopii@sem.com", odešle se kopie i na tyto maily.  


Debian/Ubuntu
-------------

Pro Linux jsou k dispozici .deb balíčky. Prosím použijte repo:

    echo "deb http://repo.vitexsoftware.cz $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/vitexsoftware.list
    sudo wget -O /etc/apt/trusted.gpg.d/vitexsoftware.gpg http://repo.vitexsoftware.cz/keyring.gpg
    sudo apt update
    sudo apt install abraflexi-mailer

Po instalaci balíku jsou v systému k dispozici tyto nové příkazy:

  * **abraflexi-send**                    - odešle doklad (TODO)
  * **abraflexi-send-unsent**             - odešle neodeslané
  * **abraflexi-send-attachments**        - odešle doklad s přílohami (TODO)
  * **abraflexi-send-unsent-attachments** - odešle neodeslané s přílohami
  * **abraflexi-show-unsent**             - vypíše neodeslané doklady 

Závislosti
----------

Tento nástroj ke svojí funkci využívá následující knihovny:

 * [**EasePHP Framework**](https://github.com/VitexSoftware/php-ease-core) - pomocné funkce např. logování
 * [**AbraFlexi**](https://github.com/Spoje-NET/AbraFlexi)        - komunikace s [AbraFlexi](https://flexibee.eu/)
 * [**AbraFlexi Bricks**](https://github.com/VitexSoftware/AbraFlexi-Bricks) - používají se třídy Zákazníka, Upomínky a Upomínače


Testování:
----------

K dispozici je základní test funkcionality spustitelný příkazem **make test** ve zdrojové složce projektu

Pouze testovací faktury a platby se vytvoří příkazem **make pretest**
![Prepare](https://raw.githubusercontent.com/VitexSoftware/php-abraflexi-mailer/master/doc/preparefortesting.png "Preparation")

Test sestavení balíčku + test instalace balíčku + test funkce balíčku obstarává [Vagrant](https://www.vagrantup.com/)

Konfigurace
-----------

S provádí uvedenín direktiv do .env souboru, jejich definicí jako konstant, nebo nastavením proměnných prostředí.


```
APP_NAME=AbraFlexiMailer                        - název aplikace v syslogu
APP_DEBUG=true                                  - zapnutí ladícího režimu
MUTE=true                                       - neodesílat zprávy příjemcům ale na

EASE_MAILTO=info@vitexsoftware.cz               - sem se posílají zprávy je-li mute aktivní

ABRAFLEXI_URL="https://demo.abraflexi.eu:5434"
ABRAFLEXI_LOGIN="winstrom"
ABRAFLEXI_PASSWORD="winstrom"
ABRAFLEXI_COMPANY="demo"
ABRAFLEXI_CUSTOMER="demo"

ADD_LOGO=true                                   - vkládat do mailu i logo firmy
ADD_QRCODE=true                                 - vkládat do mailu i Obrázek pro QR platbu
MAIL_CC=info@vitexsoftware.cz                   - všechny maily odesílat také v kopii na tuto adresu
MAIL_FROM=office@vitexsoftware.cz               - adresa odesilatele

EASE_LOGGER="console|syslog"                    - způsob logování
```



Další software pro AbraFlexi
---------------------------

 * [Pravidelné reporty z AbraFlexi](https://github.com/VitexSoftware/AbraFlexi-Digest)
 * [Odesílač upomínek](https://github.com/VitexSoftware/php-abraflexi-reminder)
 * [Klientská Zóna pro AbraFlexi](https://github.com/VitexSoftware/AbraFlexi-ClientZone)
 * [Nástroje pro testování a správu AbraFlexi](https://github.com/VitexSoftware/AbraFlexi-TestingTools)
 * [Monitoring funkce AbraFlexi serveru](https://github.com/VitexSoftware/monitoring-plugins-abraflexi)
 * [AbraFlexi server bez grafických závislostí](https://github.com/VitexSoftware/abraflexi-server-deb)

Poděkování
----------

Tento software by nevznikl pez podpory:

[ ![Spoje.Net](doc/spojenet.gif?raw=true "Spoje.Net s.r.o.") ](https://spoje.net/)

