<?php

/**
 * abraflexi-show-unsent
 *
 * @copyright (c) 2018-2023, Vítězslav Dvořák
 */

use AbraFlexi\FakturaVydana;
use Ease\Shared;

define('APP_NAME', 'AbraFlexiShowUnsentInvoices');

require_once '../vendor/autoload.php';

Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'], '../.env');
new \Ease\Locale(Shared::cfg('LOCALIZE', 'cs_CZ'), '../i18n', 'abraflexi-mailer');

$invoicer = new FakturaVydana(null, ['limit' => 0]);
if (Shared::cfg('APP_DEBUG') == 'True') {
    $invoicer->logBanner();
}
$unsent = $invoicer->getColumnsFromAbraFlexi(
    ['firma', 'kontaktEmail', 'poznam'],
    ['stavMailK' => 'stavMail.odeslat', 'limit' => 0],
    'kod'
);

if (empty($unsent)) {
    $invoicer->addStatusMessage(_('all sent'), 'success');
} else {
    foreach ($unsent as $unsentData) {
        $invoicer->setData($unsentData);
        $invoicer->addStatusMessage(
            $unsentData['kod'] . "\t" . $unsentData['firma'] . "\t" . $invoicer->getEmail() . ' ' . $invoicer->getRecipients() . "\t" . $unsentData['poznam'],
            'warning'
        );
    }
    $invoicer->addStatusMessage(count($unsent) . ' ' . _('total'), 'warning');
}
