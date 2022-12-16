<?php
/**
 * abraflexi-send-unsent-with-attachments
 *
 * @copyright (c) 2018-2020, Vítězslav Dvořák
 */

namespace AbraFlexi\Mailer;

use AbraFlexi\FakturaVydana;
use Ease\Functions;
use Ease\Html\PTag;
use Ease\Shared;

define('APP_NAME', 'SentUnsentWithAttachments');
require_once '../vendor/autoload.php';
if (file_exists('../.env')) {
    (new Shared())->loadConfig('../.env', true);
}
$cfgKeys = ['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY',
    'MAIL_FROM'];
$configured = true;
foreach ($cfgKeys as $cfgKey) {
    if (empty(\Ease\Functions::cfg($cfgKey))) {
        fwrite(STDERR, 'Requied configuration '.$cfgKey." is not set.".PHP_EOL);
        $configured = false;
    }
}
if ($configured === false) {
    exit(1);
}

new \Ease\Locale(Functions::cfg('LC_ALL','cs_CZ'));

$invoicer = new FakturaVydana();

if (Functions::cfg('APP_DEBUG') == 'True') {
    $invoicer->logBanner(Shared::appName());
}
$unsent = $invoicer->getColumnsFromAbraFlexi(
    ['firma', 'kontaktEmail', 'popis', 'poznam'],
    ['stavMailK' => 'stavMail.odeslat'], 'kod'
);

if (empty($unsent)) {
    $invoicer->addStatusMessage(_('all sent'), 'success');
} else {
    foreach ($unsent as $unsentData) {
        $invoicer->setData($unsentData);
        $invoicer->updateApiURL();

        $mailer = new Mailer($invoicer);

        preg_match_all(
            '/cc:[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/i',
            $unsentData['poznam'], $ccs
        );
        if (!empty($ccs[0])) {
            $mailer->setMailHeaders(['Cc' => str_replace(
                    'cc:', '', implode(',', $ccs[0])
            )]);
        }

        $mailer->addItem(new PTag($invoicer->getDataValue('popis')));
        $mailer->addAttachments();

        if (Functions::cfg('ADD_QRCODE')) {
            $mailer->addQrCode();
        }

        $result = ($mailer->send() && $invoicer->sync(['id' => $invoicer->getRecordIdent(),
                'stavMailK' => 'stavMail.odeslano']));

        $invoicer->addStatusMessage(
            $unsentData['kod']."\t".$unsentData['firma']."\t".$invoicer->getEmail()."\t".$unsentData['poznam'],
            $result ? 'success' : 'error'
        );
    }
    $invoicer->addStatusMessage(count($unsent).' '._('total'), 'warning');
}
