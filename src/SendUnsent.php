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
$shared = new Shared();
if (file_exists('../client.json')) {
    $shared->loadConfig('../client.json', true);
}
if (file_exists('../.env')) {
    $shared->loadConfig('../.env', true);
}

$invoicer = new \AbraFlexi\FakturaVydana();

$invoicer->logBanner(Functions::cfg('EASE_APPNAME'));
$invoicer->addStatusMessage(_('Send unsent mails'), $invoicer->sendUnsent() == 202 ? 'success' : 'warning' );
