<?php

/**
 * abraflexi-show-unsent
 *
 * @copyright (c) 2018-2024, Vítězslav Dvořák
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
        $unsent[$unsentData['kod']]['email'] = $invoicer->getEmail();
        $unsent[$unsentData['kod']]['recipients'] = $invoicer->getRecipients();
        $invoicer->addStatusMessage(
            $unsentData['kod'] . "\t" . $unsentData['firma'] . "\t" . $unsent[$unsentData['kod']]['email'] . ' ' . $unsent[$unsentData['kod']]['recipients'] . "\t" . $unsentData['poznam'],
            'warning'
        );
    }
    $invoicer->addStatusMessage(count($unsent) . ' ' . _('total'), 'warning');
}

echo json_encode($unsent, \Ease\Shared::cfg('DEBUG') ? JSON_PRETTY_PRINT : 0);
