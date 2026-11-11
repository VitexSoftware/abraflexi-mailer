<?php

declare(strict_types=1);

/**
 * This file is part of the Mailer for AbraFlexi package
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

\define('APP_NAME', 'AbraFlexi SentWith📎');

require_once '../vendor/autoload.php';
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'MAIL_FROM', 'LANG'], \array_key_exists(1, $argv) ? $argv[1] : '../.env');
new \Ease\Locale(Shared::cfg('LOCALIZE', 'cs_CZ'), '../i18n', 'abraflexi-mailer');
$invoicer = new FakturaVydana();

if (Config::bool('APP_DEBUG')) {
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
    $dryRun = Config::bool('DRY_RUN');
    $failed = 0;

    foreach ($unsent as $unsentId => $unsentData) {
        // External IDs may be malformed (e.g. "ext:XYZ" without system part) and unresolvable
        // by AbraFlexi; address the record by its code instead.
        unset($unsentData['external-ids']);
        $invoicer->setData($unsentData, true); // reset: nothing may leak from the previous invoice
        $invoicer->updateApiURL();

        $code = $unsentData['kod'];
        $summary = $code."\t".$unsentData['firma']."\t".$invoicer->getEmail()."\t".$unsentData['poznam'];
        $mailer = null;
        $relock = false;

        try {
            if ($invoicer->getDataValue('zamekK') !== 'zamek.otevreno') {
                if (Config::bool('SEND_LOCKED') === false) {
                    $invoicer->addStatusMessage(sprintf(_('Invoice %s locked: skipping process'), $code), 'info');

                    continue;
                }

                if ($dryRun === false) {
                    if ($invoicer->performAction('unlock', 'int') === false) {
                        ++$failed;
                        $invoicer->addStatusMessage(sprintf(_('Invoice %s cannot unlock: skipping process'), $code), 'error');

                        continue;
                    }

                    $relock = true;
                }
            }

            // Never send a document nobody would receive (only Bcc copies would go out)
            if (trim((string) $invoicer->getRecipients()) === '') {
                ++$failed;
                $invoicer->addStatusMessage(sprintf(_('Invoice %s not sent: customer has no email address'), $summary), 'error');

                continue;
            }

            $mailer = new DocumentMailer($invoicer);
            preg_match_all(
                '/cc:[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/i',
                (string) $unsentData['poznam'],
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

            if (Config::bool('ADD_QRCODE')) {
                $mailer->addQrCode();
            }

            if ($dryRun) {
                $invoicer->addStatusMessage(sprintf(_('DRY_RUN: %s prepared, not sent'), $summary), 'info');

                continue;
            }

            if ($mailer->send() === false) {
                ++$failed;
                $invoicer->addStatusMessage(sprintf(_('Invoice %s was not sent'), $summary), 'error');

                continue;
            }

            if ($mailer->isMuted()) {
                $invoicer->addStatusMessage(sprintf(_('MUTE: %s sent to %s only, mail state not changed'), $code, $mailer->emailAddress), 'warning');

                continue;
            }

            if ($invoicer->sync(['id' => $invoicer->getRecordIdent(), 'stavMailK' => 'stavMail.odeslano'])) {
                unset($unsent[$unsentId]);
                $invoicer->addStatusMessage(sprintf(_('Updating Mail State of %s to "sent"'), $code), 'success');
                $invoicer->addStatusMessage($summary, 'success');
            } else {
                ++$failed;
                $invoicer->addStatusMessage(sprintf(_('Invoice %s was sent, but its mail state could not be updated'), $summary), 'error');
            }
        } catch (DocumentIncompleteException $exc) {
            ++$failed;
            $invoicer->addStatusMessage(sprintf(_('Invoice %s not sent: %s'), $code, $exc->getMessage()), 'error');
        } catch (\Exception $exc) {
            ++$failed;
            $invoicer->addStatusMessage(sprintf(_('Processing of %s failed: %s'), $code, $exc->getMessage()), 'error');
        } finally {
            if ($mailer instanceof DocumentMailer) {
                $mailer->cleanup();
            }

            if ($relock) {
                if ($invoicer->performAction('lock', 'int')) {
                    $invoicer->addStatusMessage(sprintf(_('Invoice %s locked again'), $code), 'success');
                } else {
                    ++$failed;
                    $invoicer->addStatusMessage(sprintf(_('Invoice %s locking failed'), $code), 'error');
                }
            }
        }
    }

    $invoicer->addStatusMessage(\count($unsent).' '._('total'), 'info');

    if ($failed) {
        exit(1);
    }
}
