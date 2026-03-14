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
 * Confirmation of receipt of invoice.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class PotvrzeniPrijetiFaktury extends \AbraFlexi\Mailer\HtmlMailer
{
    /**
     * Send invoice receipt confirmation.
     */
    public function __construct(\AbraFlexi\FakturaPrijata $invoice)
    {
        $adresar = new \AbraFlexi\Adresar($invoice->getDataValue('firma'));
        $to = $adresar->getNotificationEmailAddress();

        $customerName = $invoice->getDataValue('firma@showAs');

        if (empty($customerName)) {
            $customerName = \AbraFlexi\Functions::uncode((string) $invoice->getDataValue('firma'));
        }

        $subject = sprintf(
            _('Confirmation of receipt your invoice %s'),
            \AbraFlexi\Functions::uncode($invoice->getRecordIdent()),
        );

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
            _('we confirm receipt of invoice %s as %s '),
            $invoice->getDataValue('cisDosle'),
            $invoice->getDataValue('kod'),
        )));
        $this->addItem(new \Ease\Html\PTag(''));

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
