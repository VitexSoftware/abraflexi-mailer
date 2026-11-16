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
 * Confirmation of a received payment (bank or cash desk) to the customer who paid.
 *
 * The mail is in the language of the customer (label LANG_xx of the company, set by ClientZone).
 */
class PotvrzeniPrijetiPlatby extends HtmlMailer
{
    /**
     * @param \AbraFlexi\RO                    $payment  the payment record (banka or pokladni-pohyb) with its columns
     * @param array<int, string>               $invoices codes of the invoices the payment was paired with
     */
    public function __construct(\AbraFlexi\RO $payment, array $invoices = [])
    {
        $this->useCustomerLanguage($payment);

        $firma = PaymentReceipt::company($payment->getDataValue('firma'));
        $adresar = new \AbraFlexi\Adresar(\AbraFlexi\Functions::code($firma));
        $to = $adresar->getNotificationEmailAddress();

        $customerName = (string) ($payment->getDataValue('firma@showAs') ?: ($adresar->getDataValue('nazev') ?: $firma));
        $amount = number_format((float) $payment->getDataValue('sumCelkem'), 2, ',', ' ');
        $currency = \AbraFlexi\Functions::uncode((string) ($payment->getDataValue('mena') ?: 'code:CZK'));
        $date = $this->formatDate($payment->getDataValue('datVyst'));
        $varSym = (string) $payment->getDataValue('varSym');

        parent::__construct($to, sprintf(_('Confirmation of receipt of payment %s'), $varSym !== '' ? $varSym : \AbraFlexi\Functions::uncode((string) $payment->getDataValue('kod'))));
        $this->fromEmailAddress = Shared::cfg('MAIL_FROM', '');

        $this->addItem(new CompanyLogo(['align' => 'right', 'id' => 'companylogo', 'height' => '50', 'title' => _('Company logo')]));
        $this->addItem(new \Ease\Html\DivTag(htmlspecialchars(sprintf(_('Dear customer %s,'), $customerName))));
        $this->addItem(new \Ease\Html\PTag(''));
        $this->addItem(new \Ease\Html\DivTag(htmlspecialchars(sprintf(_('we confirm receipt of your payment of %s %s on %s.'), $amount, $currency, $date))));
        $this->addItem(new \Ease\Html\PTag(''));

        if ($varSym !== '') {
            $this->addItem(new \Ease\Html\DivTag(htmlspecialchars(sprintf(_('Variable symbol: %s'), $varSym))));
        }

        if ($invoices !== []) {
            $this->addItem(new \Ease\Html\DivTag(htmlspecialchars(sprintf(_('The payment was assigned to: %s'), implode(', ', $invoices)))));
        }

        $this->addItem(new \Ease\Html\PTag(''));
        $this->addItem(new \Ease\Html\DivTag(_('Thank you for your payment.')));
        $this->addItem(new \Ease\Html\PTag(''));
        $this->addItem(new \Ease\Html\DivTag(_('With greetings')));
        $this->addItem(new \Ease\Html\PTag(''));

        $signature = Shared::cfg('MAIL_SIGNATURE', '');

        if (!empty($signature)) {
            $this->addItem(nl2br(htmlspecialchars((string) $signature)));
        }

        $sendInfoTo = Shared::cfg('SEND_INFO_TO', '');

        if (!empty($sendInfoTo)) {
            $this->setMailHeaders(['Cc' => $sendInfoTo]);
        }
    }

    /**
     * The mail is written in the language of the customer, the configured one (LOCALIZE) otherwise.
     */
    private function useCustomerLanguage(\AbraFlexi\RO $payment): void
    {
        $locale = CustomerLanguage::localeFor(CustomerLanguage::forDocument($payment), (string) Shared::cfg('LOCALIZE', 'cs_CZ'));

        if ($locale !== \Ease\Locale::$localeUsed) {
            \Ease\Locale::useLocale($locale);
        }
    }

    private function formatDate(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('d.m.Y');
        }

        $timestamp = strtotime((string) $date);

        return $timestamp === false ? (string) $date : date('d.m.Y', $timestamp);
    }
}
