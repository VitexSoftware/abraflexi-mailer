<?php

/**
 * abraflexi-send-unsent-with-attachments
 * 
 * @copyright (c) 2018-2020, Vítězslav Dvořák
 */

namespace AbraFlexi\Mailer;

use Ease\Functions;
use Ease\Shared;

define('APP_NAME', 'SentUnsentWithAttachments');
define('EASE_LOGGER', 'syslog|console');
require_once '../vendor/autoload.php';
$shared = new Shared();
$shared->loadConfig('../.env', true);

$invoicer = new \AbraFlexi\FakturaVydana();

$invoicer->logBanner(Functions::cfg('APP_NAME'));

$unsent = $invoicer->getColumnsFromAbraFlexi(['firma', 'kontaktEmail', 'popis', 'poznam'], ['stavMailK' => 'stavMail.odeslat'], 'kod');

if (empty($unsent)) {
    $invoicer->addStatusMessage(_('all sent'), 'success');
} else {
    foreach ($unsent as $unsentData) {
        $invoicer->setData($unsentData);
        $invoicer->updateApiURL();

        $mailer = new Mailer($invoicer);

        preg_match_all('/cc:[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/i', $unsentData['poznam'], $ccs);
        if (!empty($ccs[0])) {
            $mailer->setMailHeaders(['Cc' => str_replace('cc:', '', implode(',', $ccs[0]))]);
        }

        $mailer->addItem(new \Ease\Html\PTag($invoicer->getDataValue('popis')));
        $mailer->addAttachments();

        if (\Ease\Functions::cfg('ADD_QRCODE')) {
            $mailer->addQrCode();
        }

        $result = ($mailer->send() && $invoicer->sync(['id' => $invoicer->getRecordIdent(), 'stavMailK' => 'stavMail.odeslano']));

        $invoicer->addStatusMessage($unsentData['kod'] . "\t" . $unsentData['firma'] . "\t" . $invoicer->getEmail() . "\t" . $unsentData['poznam'], $result ? 'success' : 'error');
    }
    $invoicer->addStatusMessage(count($unsent) . ' ' . _('total'), 'warning');
}
