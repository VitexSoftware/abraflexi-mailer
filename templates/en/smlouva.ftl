<!DOCTYPE html>
<html>
    <head>
        <title>${nazevFirmy} Contract ${object.kod}</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body>
        <div>
            <p>Hello,</p>
            <p>
                please find attached the contract <strong>${object.kod}</strong> (${object.nazev}).
            </p>
            <p>
                The services under the contract are invoiced regularly. You can change or end the
                contract, as a whole or item by item, at any time in your customer zone.
                We confirm every change with another e-mail.
            </p>
            <p>If you have any questions, write to us at ${company.email}.</p>
        </div>
        <footer>
            <hr>
            <address>
                Kind regards,<br>
                <strong>${nazevFirmy}</strong>
                <div>Company ID: ${company.ic}</div>
            </address>
        </footer>
    </body>
</html>
