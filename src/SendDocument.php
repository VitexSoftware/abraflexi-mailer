<?php

/**
 * abraflexi-send-document
 * 
 * @copyright (c) 2018-2021, Vítězslav Dvořák
 */
use AbraFlexi\FakturaVydana;
use AbraFlexi\RO;
use Ease\Functions;
use Ease\Shared;

define('APP_NAME', 'SentDocument');
define('EASE_LOGGER', 'syslog|console');
require_once '../vendor/autoload.php';
if (file_exists('../.env')) {
    (new Shared())->loadConfig('../.env', true);
}

$document = $argv[1];
$evidence = array_key_exists(2, $argv) ? $argv[2] : 'faktura-vydana';

if ($argc > 2) {

    $documentor = new FakturaVydana(RO::code($document), ['evidence' => $evidence, 'ignore404' => true]);
    if (\Ease\Functions::cfg('APP_DEBUG') == 'True') {
        $documentor->logBanner(\Ease\Shared::appName());
    }

    if ($documentor->lastResponseCode == 200) {

        $to = (array_key_exists(3, $argv) ? $argv[3] : $documentor->getEmail());
        $documentor->addStatusMessage(RO::uncode($documentor->getRecordCode()) . "\t" . RO::uncode($documentor->getDataValue('firma')) . "\t" . $to . "\t" . $documentor->getDataValue('poznam'), 'success');

        $mailer = new \AbraFlexi\Mailer\Mailer($documentor, $to);

        $documentor->addStatusMessage(_('Attaching') . ': ' . implode(',', $mailer->addAttachments()));

        if (array_key_exists('juhSum', $documentor->getColumnsInfo())) {
            if (Functions::cfg('ADD_QRCODE')) {
                $mailer->addQrCode();
            }
        }

        if (array_key_exists('stavMailK', $documentor->getColumnsInfo())) {
            $result = ($mailer->send() && $documentor->sync(['id' => $documentor->getRecordIdent(), 'stavMailK' => 'stavMail.odeslano']));
        } else {
            $result = $mailer->send();
        }
    } else {
        $documentor->addStatusMessage(sprintf(_('Cannot read %s %s'), $evidence, $document));
    }
} else {
    echo _('AbraFlexi Document Sender') . "\n";
    echo "abraflexi-send-document <DocID> [evidence-code] [recipent@email,another@recipient] \n";
    echo "abraflexi-send-document VF1-7326/2020 faktura-vydana \n";
}


