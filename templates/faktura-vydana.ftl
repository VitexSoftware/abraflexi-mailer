<!DOCTYPE html>
<html>
    <head>
        <title>${nazevFirmy} Faktura ${doklad.kod}</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body>
        <div>
            Dobrý den, zasílám Vám fakturu ${doklad.kod}.
        </div>
        <footer>
            <hr>
            <div>
                S pozdravem<br> 
                ${uzivatelJmeno} ${uzivatelPrijmeni}<br> 
                ${user.mobil}
            </div>
            <address>
                <strong>${nazevFirmy}</strong>
                <div>IČO: ${company.ic}</div>
            </address>
        </footer>
    </body>
</html>
