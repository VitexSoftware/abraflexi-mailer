<?php

declare(strict_types=1);

/**
 * This file is part of the AbraFlexi Mailer package
 *
 * https://github.com/VitexSoftware/abraflexi-mailer
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AbraFlexi\Mailer;

use AbraFlexi\FakturaVydana;
use Ease\Html\PTag;
use Ease\Shared;

\define('APP_NAME', 'AbraFlexiSentUnsentWithAttachments');

require_once '../vendor/autoload.php';
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'MAIL_FROM', 'LANG'], \array_key_exists(1, $argv) ? $argv[1] : '../.env');
new \Ease\Locale(Shared::cfg('LOCALIZE', 'cs_CZ'), '../i18n', 'abraflexi-mailer');
$invoicer = new FakturaVydana();

if (Shared::cfg('APP_DEBUG', false)) {
    $invoicer->logBanner();
}

$unsent = $invoicer->getColumnsFromAbraFlexi(
    'full',
    ['stavMailK' => 'stavMail.odeslat', 'limit' => 0, 'storno' => false,
        // 'lastUpdate gt "'. \AbraFlexi\RO::dateToFlexiDateTime( new \DateTime('-1 hour') ).'"'
    ],
    'kod',
);

if (empty($unsent)) {
    $invoicer->addStatusMessage(_('all sent'), 'success');
} else {
    foreach ($unsent as $unsentId => $unsentData) {
        $invoicer->setData($unsentData);
        $invoicer->updateApiURL();
        $mailer = new DocumentMailer($invoicer);
        preg_match_all(
            '/cc:[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/i',
            $unsentData['poznam'],
            $ccs,
        );

        if (!empty($ccs[0])) {
            $mailer->setMailHeaders(['Cc' => str_replace(
                'cc:',
                '',
                implode(',', $ccs[0]),
            )]);
        }

        $mailer->addItem(new PTag($invoicer->getDataValue('popis')));
        $mailer->addAttachments();

        if (Shared::cfg('ADD_QRCODE')) {
            $mailer->addQrCode();
        }

        $lock = false;

        if ($invoicer->getDataValue('zamekK') !== 'zamek.otevreno') {
            if (\Ease\Shared::cfg('SEND_LOCKED', false) === 'True') {
                $unlock = $invoicer->performAction('unlock', 'int');

                if ($unlock['success'] === 'false') {
                    $invoicer->addStatusMessage(sprintf(_('Invoice %s cannot unlock: skipping process'), $invoicer->getRecordCode()), 'warning');

                    continue;
                }
            } else {
                $invoicer->addStatusMessage(sprintf(_('Invoice %s locked: skipping process'), $invoicer->getRecordCode()), 'info');

                continue;
            }
        }

        $result = false;

        if (strtolower(\Ease\Shared::cfg('DRY_RUN', '')) !== 'true') {
            try {
                $sendResult = $mailer->send();

                if ($sendResult) {
                    $invoiceUpdate = $invoicer->sync(['id' => $invoicer->getRecordIdent(), 'stavMailK' => 'stavMail.odeslano']);
                } else {
                    $invoiceUpdate = false;
                }

                $invoicer->addStatusMessage(sprintf(_('Updating Mail State of %s to "sent"'), $invoicer), $invoiceUpdate ? 'success' : 'event');
                $result = ($sendResult && $invoiceUpdate);
            } catch (\Exception $exc) {
                $mailer->addStatusMessage('Problem sending document '.$invoicer->getRecordCode(), 'error');
            }
        } else {
            $result = false;
        }

        if ($result === true) {
            unset($unsent[$unsentId]);
        }

        $invoicer->addStatusMessage(
            $unsentData['kod']."\t".$unsentData['firma']."\t".$invoicer->getEmail()."\t".$unsentData['poznam'],
            $result ? 'success' : 'error',
        );

        if ($lock === true) {
            $lock = $invoicer->performAction('lock', 'int');

            if ($lock['success'] === 'true') {
                $this->addStatusMessage(sprintf(_('Invoice %s locked again'), $invoicer), 'success');
            } else {
                $this->addStatusMessage(sprintf(_('Invoice %s locking failed'), $invoicer), 'warning');
            }
        }
    }

    $invoicer->addStatusMessage(\count($unsent).' '._('total'), 'info');
}
