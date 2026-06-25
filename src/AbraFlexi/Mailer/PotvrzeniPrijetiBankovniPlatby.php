<?php

declare(strict_types=1);

/**
 * This file is part of the Mailer for AbraFlexi package
 *
 * https://github.com/VitexSoftware/abraflexi-mailer
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AbraFlexi\Mailer;

use Ease\Shared;

/**
 * Notification of received bank payment that could not be matched to an invoice.
 *
 * Used when abraflexi-matcher fails to pair the payment (unknown variable symbol,
 * underpayment, overpayment, or AbraFlexi unavailability). Sends the customer
 * a "we received your payment" message referencing the bank record.
 */
class PotvrzeniPrijetiBankovniPlatby extends \AbraFlexi\Mailer\HtmlMailer
{
    public function __construct(\AbraFlexi\Banka $bankRecord)
    {
        $firmaRef = $bankRecord->getDataValue('firma');
        $adresar = new \AbraFlexi\Adresar($firmaRef ?: null);
        $to = $adresar->getNotificationEmailAddress();

        $customerName = $bankRecord->getDataValue('firma@showAs');

        if (empty($customerName)) {
            $customerName = \AbraFlexi\Functions::uncode((string) $bankRecord->getDataValue('firma'));
        }

        if (empty($customerName)) {
            $customerName = $bankRecord->getDataValue('popis', '');
        }

        $amount = $bankRecord->getDataValue('sumCastka', '');
        $currency = \AbraFlexi\Functions::uncode((string) $bankRecord->getDataValue('mena', 'CZK'));
        $date = $bankRecord->getDataValue('datVyst', '');
        $varSym = $bankRecord->getDataValue('varSym', '');

        $subject = sprintf(_('Confirmation of receipt of payment %s'), $varSym ?: $bankRecord->getDataValue('kod', ''));

        parent::__construct($to, $subject);
        $this->fromEmailAddress = Shared::cfg('MAIL_FROM', '');

        $this->addItem(new CompanyLogo(['align' => 'right', 'id' => 'companylogo',
            'height' => '50', 'title' => _('Company logo')]));

        $prober = new \AbraFlexi\Company();
        $infoRaw = $prober->getFlexiData();

        if (\count($infoRaw) && !\array_key_exists('success', $infoRaw)) {
            $info = \Ease\Functions::reindexArrayBy($infoRaw, 'dbNazev');
            $myCompany = $prober->getCompany();

            if (\array_key_exists($myCompany, $info)) {
                $this->addItem(new \Ease\Html\H2Tag($info[$myCompany]['nazev']));
            }
        }

        $this->addItem(new \Ease\Html\DivTag(sprintf(
            _('Dear customer %s,'),
            $customerName,
        )));
        $this->addItem(new \Ease\Html\PTag(''));

        $this->addItem(new \Ease\Html\DivTag(sprintf(
            _('we confirm receipt of your payment of %s %s on %s.'),
            $amount,
            $currency,
            $date,
        )));
        $this->addItem(new \Ease\Html\PTag(''));

        $this->addItem(new \Ease\Html\DivTag(
            _('Your payment could not be automatically matched to an invoice. Our team will process it shortly. If you have any questions, please contact us with the payment details.'),
        ));
        $this->addItem(new \Ease\Html\PTag(''));

        if (!empty($varSym)) {
            $this->addItem(new \Ease\Html\DivTag(sprintf(
                _('Variable symbol: %s'),
                $varSym,
            )));
            $this->addItem(new \Ease\Html\PTag(''));
        }

        $this->addItem(new \Ease\Html\DivTag(_('With greetings')));
        $this->addItem(new \Ease\Html\PTag(''));

        $signature = Shared::cfg('MAIL_SIGNATURE', '');

        if (!empty($signature)) {
            $this->addItem(nl2br($signature));
        }

        $sendInfoTo = Shared::cfg('SEND_INFO_TO', '');

        if (!empty($sendInfoTo)) {
            $this->setMailHeaders(['Cc' => $sendInfoTo]);
        }
    }
}
