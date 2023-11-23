<?php

/**
 * abraflexi-send-unsent-with-attachments
 *
 * @copyright (c) 2018-2023, Vítězslav Dvořák
 */

namespace AbraFlexi\Mailer;

use AbraFlexi\FakturaVydana;
use Ease\Html\PTag;
use Ease\Shared;

define('APP_NAME', 'AbraFlexiSentUnsentWithAttachments');
require_once '../vendor/autoload.php';
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'MAIL_FROM', 'LANG'], array_key_exists(1, $argv) ? $argv[1] : '../.env');
new \Ease\Locale(Shared::cfg('LOCALIZE', 'cs_CZ'), '../i18n', 'abraflexi-mailer');
$invoicer = new FakturaVydana();
if (Shared::cfg('APP_DEBUG', false)) {
    $invoicer->logBanner();
}
$unsent = $invoicer->getColumnsFromAbraFlexi(
    'full',
    ['stavMailK' => 'stavMail.odeslat', 'limit' => 0,
        //'lastUpdate gt "'. \AbraFlexi\RO::dateToFlexiDateTime( new \DateTime('-1 hour') ).'"'
        ],
    'kod'
);
if (empty($unsent)) {
    $invoicer->addStatusMessage(_('all sent'), 'success');
} else {
    foreach ($unsent as $unsentData) {
        $invoicer->setData($unsentData);
        $invoicer->updateApiURL();
        $mailer = new DocumentMailer($invoicer);
        preg_match_all(
            '/cc:[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/i',
            $unsentData['poznam'],
            $ccs
        );
        if (!empty($ccs[0])) {
            $mailer->setMailHeaders(['Cc' => str_replace(
                'cc:',
                '',
                implode(',', $ccs[0])
            )]);
        }

        $mailer->addItem(new PTag($invoicer->getDataValue('popis')));
        $mailer->addAttachments();
        if (Shared::cfg('ADD_QRCODE')) {
            $mailer->addQrCode();
        }


        $lock = false;
        if ($invoicer->getDataValue('zamekK') == 'zamek.zamceno') {
            if (\Ease\Shared::cfg('SEND_LOCKED') == 'True') {
                $unlock = $invoicer->performAction('unlock', 'int');
                if ($unlock['success'] == 'false') {
                    $this->addStatusMessage(_('Invoice locked: skipping process'), 'warning');
                    $lock = true;
                }
            }
        }
        try {
            if (\Ease\Shared::cfg('DRY_RUN')) {
                $result = ($mailer->send() === true);
            } else {
                $result = (($mailer->send() === true) && $invoicer->sync(['id' => $invoicer->getRecordIdent(), 'stavMailK' => 'stavMail.odeslano']));
            }
        } catch (\AbraFlexi\Exception $exc) {
            $mailer->addStatusMessage('Problem sending document ' . $invoicer->getRecordIdent(), 'error');
        }

        $invoicer->addStatusMessage(
            $unsentData['kod'] . "\t" . $unsentData['firma'] . "\t" . $invoicer->getEmail() . "\t" . $unsentData['poznam'],
            $result ? 'success' : 'error'
        );
        if ($lock === true) {
            $lock = $invoicer->performAction('lock', 'int');
            if ($lock['success'] == 'true') {
                $this->addStatusMessage(sprintf(_('Invoice %s locked again'), $invoicer), 'success');
            } else {
                $this->addStatusMessage(sprintf(_('Invoice %s locking failed'), $invoicer), 'warning');
            }
        }
    }
    $invoicer->addStatusMessage(count($unsent) . ' ' . _('total'), 'warning');
}
