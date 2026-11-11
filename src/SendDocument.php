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

use AbraFlexi\FakturaVydana;
use AbraFlexi\RO;
use Ease\Shared;

\define('APP_NAME', 'AbraFlexiDocumentSender');

require_once '../vendor/autoload.php';
Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'MAIL_FROM'], '../.env');
new \Ease\Locale(Shared::cfg('LOCALIZE', 'cs_CZ'), '../i18n', 'abraflexi-mailer');
$document = (is_numeric($argv[1]) ? (int) ($argv[1]) : RO::code(RO::uncode($argv[1])));
$evidence = \array_key_exists(2, $argv) ? $argv[2] : 'faktura-vydana';

if ($argc > 1) {
    $documentor = new FakturaVydana($document, ['evidence' => $evidence, 'ignore404' => true]);

    if (\AbraFlexi\Mailer\Config::bool('APP_DEBUG')) {
        $documentor->logBanner(\Ease\Shared::appName().' v'.\Ease\Shared::appVersion());
    }

    $result = false;

    if ($documentor->lastResponseCode === 200) {
        $to = (\array_key_exists(3, $argv) ? $argv[3] : $documentor->getEmail());
        $documentor->addStatusMessage(
            RO::uncode($documentor->getRecordCode())."\t".RO::uncode($documentor->getDataValue('firma'))."\t".$to."\t".$documentor->getDataValue('poznam'),
            'success',
        );

        try {
            $mailer = new \AbraFlexi\Mailer\DocumentMailer($documentor, $to);
            $documentor->addStatusMessage(_('Attaching').': '.implode(
                ',',
                $mailer->addAttachments(),
            ));

            if (\array_key_exists('juhSum', $documentor->getColumnsInfo())) {
                if (\AbraFlexi\Mailer\Config::bool('ADD_QRCODE')) {
                    $mailer->addQrCode();
                }
            }

            $result = $mailer->send();

            if ($result && !$mailer->isMuted() && \array_key_exists('stavMailK', $documentor->getColumnsInfo())) {
                $result = (bool) $documentor->sync(['id' => $documentor->getRecordIdent(), 'stavMailK' => 'stavMail.odeslano']);
            }
        } catch (\AbraFlexi\Mailer\DocumentIncompleteException $exc) {
            $documentor->addStatusMessage(sprintf(_('Document not sent: %s'), $exc->getMessage()), 'error');
        }
    } else {
        $documentor->addStatusMessage(sprintf(
            _('Cannot read %s %s'),
            $evidence,
            $document,
        ));
    }

    exit($result ? 0 : 1);
} else {
    echo _('AbraFlexi Document Sender')."\n";
    echo "abraflexi-send-document <DocID> [evidence-code] [recipent@email,another@recipient] \n";
    echo "abraflexi-send-document VF1-7326/2020 faktura-vydana \n";
}
