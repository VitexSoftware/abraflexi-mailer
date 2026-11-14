<!DOCTYPE html>
<html>
    <head>
        <title>${nazevFirmy} Smlouva ${object.kod}</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body>
        <div>
            <p>Dobrý den,</p>
            <p>
                zasíláme Vám smlouvu <strong>${object.kod}</strong> (${object.nazev}).
                Smlouva je v příloze tohoto e-mailu.
            </p>
            <p>
                Služby podle smlouvy Vám budeme fakturovat pravidelně. Změnu nebo ukončení smlouvy,
                celé nebo po jednotlivých položkách, můžete kdykoli provést ve své zákaznické zóně.
                Každou změnu Vám potvrdíme dalším e-mailem.
            </p>
            <p>V případě dotazů nám napište na ${company.email}.</p>
        </div>
        <footer>
            <hr>
            <address>
                S pozdravem<br>
                <strong>${nazevFirmy}</strong>
                <div>IČO: ${company.ic}</div>
            </address>
        </footer>
    </body>
</html>
