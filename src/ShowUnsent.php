<?php

/**
 * abraflexi-show-unsent
 *
 * @copyright (c) 2018-2023, Vítězslav Dvořák
 */
use AbraFlexi\FakturaVydana;
use Ease\Functions;
use Ease\Shared;

define('APP_NAME', 'ShowUnsent');

require_once '../vendor/autoload.php';

Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'], '../.env');

$invoicer = new FakturaVydana(null, ['limit' => 0]);
if (Functions::cfg('APP_DEBUG') == 'True') {
    $invoicer->logBanner(Shared::appName());
}
$unsent = $invoicer->getColumnsFromAbraFlexi(
    ['firma', 'kontaktEmail', 'poznam'], ['stavMailK' => 'stavMail.odeslat','limit'=>0],
    'kod'
);

if (empty($unsent)) {
    $invoicer->addStatusMessage(_('all sent'), 'success');
} else {
    foreach ($unsent as $unsentData) {
        $invoicer->setData($unsentData);
        $invoicer->addStatusMessage(
            $unsentData['kod']."\t".$unsentData['firma']."\t".$invoicer->getEmail()."\t".$unsentData['poznam'],
            'warning'
        );
    }
    $invoicer->addStatusMessage(count($unsent).' '._('total'), 'warning');
}
