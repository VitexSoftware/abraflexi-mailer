<?php

/**
 * flexibee-send-unsent
 *
 * @copyright (c) 2018-2020, Vítězslav Dvořák
 */
use Ease\Functions;
use Ease\Shared;

define('EASE_APPNAME', 'OdeslatNeodeslane');
require_once '../vendor/autoload.php';
if (file_exists('../.env')) {
    (new Shared())->loadConfig('../.env', true);
}

$cfgKeys = ['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY',
    'MAIL_FROM'];
$configured = true;
foreach ($cfgKeys as $cfgKey) {
    if (empty(\Ease\Functions::cfg($cfgKey))) {
        fwrite(STDERR,
            'Requied configuration '.$cfgKey." is not set.".PHP_EOL);
        $configured = false;
    }
}
if ($configured === false) {
    exit(1);
}




$invoicer = new \AbraFlexi\FakturaVydana();
if (Functions::cfg('APP_DEBUG') == 'True') {
    $invoicer->logBanner(Shared::appName());
}
$invoicer->addStatusMessage(
    _('Send unsent mails'),
    $invoicer->sendUnsent() == 202 ? 'success' : 'warning'
);
