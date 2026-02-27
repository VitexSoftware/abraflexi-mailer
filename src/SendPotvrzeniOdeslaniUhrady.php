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

use AbraFlexi\FakturaPrijata;
use AbraFlexi\RO;
use Ease\Shared;

\define('APP_NAME', 'AbraFlexiPotvrzeniOdeslaniUhrady');

require_once '../vendor/autoload.php';
Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'MAIL_FROM'], '../.env');
new \Ease\Locale(Shared::cfg('LOCALIZE', 'cs_CZ'), '../i18n', 'abraflexi-mailer');

$options = getopt('', ['docid:']);
$docId = $options['docid'] ?? Shared::cfg('DOCID', '');

if (empty($docId)) {
    fwrite(\STDERR, _('Confirmation of sending of invoice payment')."\n");
    fwrite(\STDERR, "abraflexi-potvrzeni-odeslani-uhrady --docid=<DocID>\n");
    fwrite(\STDERR, "DOCID=<DocID> abraflexi-potvrzeni-odeslani-uhrady\n");

    exit(1);
}

$document = (is_numeric($docId) ? (int) $docId : RO::code(RO::uncode($docId)));
$invoice = new FakturaPrijata($document, ['ignore404' => true]);

if (Shared::cfg('APP_DEBUG') === 'True') {
    $invoice->logBanner(Shared::appName().' v'.Shared::appVersion());
}

$report = [
    'producer' => \constant('APP_NAME'),
    'status' => 'error',
    'timestamp' => date('c'),
];

if ($invoice->lastResponseCode === 200) {
    $mailer = new \AbraFlexi\Mailer\PotvrzeniOdeslaniUhrady($invoice);

    if ($mailer->send()) {
        $message = sprintf(_('Payment sent confirmation sent for %s'), RO::uncode($invoice->getRecordCode()));
        $invoice->addStatusMessage($message, 'success');
        $report['status'] = 'success';
        $report['message'] = $message;
    } else {
        $message = sprintf(_('Failed to send payment sent confirmation for %s'), RO::uncode($invoice->getRecordCode()));
        $invoice->addStatusMessage($message, 'error');
        $report['message'] = $message;
        echo json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE) . "\n";

        exit(2);
    }
} else {
    $message = sprintf(_('Cannot read document %s from evidence %s'), $docId, 'faktura-prijata');
    $invoice->addStatusMessage($message, 'error');
    $report['message'] = $message;
    echo json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE) . "\n";

    exit(3);
}

echo json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE) . "\n";
