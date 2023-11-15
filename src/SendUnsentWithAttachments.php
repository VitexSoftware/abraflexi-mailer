<?php

/**
 * abraflexi-send-unsent-with-attachments
 *
 * @copyright (c) 2018-2023, Vítězslav Dvořák
 */

namespace AbraFlexi\Mailer;

use AbraFlexi\FakturaVydana;
use Ease\Functions;
use Ease\Html\PTag;
use Ease\Shared;

define('APP_NAME', 'SentUnsentWithAttachments');
require_once '../vendor/autoload.php';
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'MAIL_FROM', 'LANG'], '../.env');
new \Ease\Locale();
$invoicer = new FakturaVydana();
if (Functions::cfg('APP_DEBUG') == 'True') {
    $invoicer->logBanner(Shared::appName());
}
$unsent = $invoicer->getColumnsFromAbraFlexi(
    ['firma', 'kontaktEmail', 'popis', 'poznam', 'typDokl'],
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
        if (Functions::cfg('ADD_QRCODE')) {
            $mailer->addQrCode();
        }


        $lock = false;
        if ($invoicer->getDataValue('zamekK') == 'zamek.zamceno') {
            if (\Ease\Functions::cfg('SEND_LOCKED') == 'True') {
                $unlock = $invoicer->performAction('unlock', 'int');
                if ($unlock['success'] == 'false') {
                    $this->addStatusMessage(_('Invoice locked: skipping process'), 'warning');
                    $lock = true;
                }
            }
        }
        try {
            if (\Ease\Functions::cfg('DRY_RUN')) {
                $result = ($mailer->send() === true);
            } else {
                $result = (($mailer->send() === true) && $invoicer->sync(['id' => $invoicer->getRecordIdent(), 'stavMailK' => 'stavMail.odeslano']));
            }
        } catch (\AbraFlexi\Exception $exc) {
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
