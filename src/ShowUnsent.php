<?php

/**
 * abraflexi-show-unsent
 * 
 * @copyright (c) 2018-2020, Vítězslav Dvořák
 */
use Ease\Functions;
use Ease\Shared;

define('APP_NAME', 'ShowUnsent');
define('EASE_LOGGER', 'syslog|console');
require_once '../vendor/autoload.php';
$shared = new Shared();
$shared->loadConfig('../.env', true);

$invoicer = new \AbraFlexi\FakturaVydana();

$invoicer->logBanner(Functions::cfg('APP_NAME'));

$unsent = $invoicer->getColumnsFromAbraFlexi(['firma', 'kontaktEmail', 'poznam'], ['stavMailK' => 'stavMail.odeslat'], 'kod');

if (empty($unsent)) {
    $invoicer->addStatusMessage(_('all sent'), 'success');
} else {
    foreach ($unsent as $unsentData) {
        $invoicer->setData($unsentData);
        $invoicer->addStatusMessage($unsentData['kod'] . "\t" . $unsentData['firma'] . "\t" . $invoicer->getEmail() . "\t" . $unsentData['poznam']);
    }
    $invoicer->addStatusMessage(count($unsent) . ' ' . _('total'), 'warning');
}

