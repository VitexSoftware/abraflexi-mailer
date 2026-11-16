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

use AbraFlexi\Mailer\PaymentReceipt;
use Ease\Shared;

\define('APP_NAME', 'AbraFlexiPotvrzeniPrijetiPlatby');

require_once '../vendor/autoload.php';
Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'MAIL_FROM'], '../.env');
new \Ease\Locale(Shared::cfg('LOCALIZE', 'cs_CZ'), '../i18n', 'abraflexi-mailer');

$options = getopt('', ['docid:', 'evidence:']);
$docId = $options['docid'] ?? Shared::cfg('DOCID', '');
$evidence = PaymentReceipt::evidence($options['evidence'] ?? Shared::cfg('EVIDENCE', 'banka'));

if (empty($docId) || $evidence === null || !ctype_digit((string) $docId)) {
    fwrite(\STDERR, _('Confirmation of receipt of a payment (bank or cash desk)')."\n");
    fwrite(\STDERR, "abraflexi-potvrzeni-prijeti-platby --docid=<ID> [--evidence=banka|pokladni-pohyb]\n");
    fwrite(\STDERR, "DOCID=<ID> EVIDENCE=banka abraflexi-potvrzeni-prijeti-platby\n");

    exit(1);
}

$report = [
    'producer' => \constant('APP_NAME'),
    'status' => 'error',
    'timestamp' => date('c'),
];

$columns = ['id', 'kod', 'typPohybuK', 'firma', 'storno', 'stitky', 'sumCelkem', 'mena', 'datVyst', 'varSym', 'popis'];
$payment = new \AbraFlexi\RW((int) $docId, ['evidence' => $evidence, 'ignore404' => true]);
$payment->throwException = false;
$rows = $payment->getColumnsFromAbraFlexi($columns, ['id' => (int) $docId]);
$row = \is_array($rows) && $payment->lastResponseCode === 200 ? (array) ($rows[0] ?? []) : [];

if ($row === []) {
    $message = sprintf(_('Cannot read document %s from evidence %s'), $docId, $evidence);
    $payment->addStatusMessage($message, 'error');
    $report['message'] = $message;
    echo json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE)."\n";

    exit(3);
}

// Not every change of a payment is a reason to write: most are not, and that is no error
$reason = PaymentReceipt::skipReason($row);

if ($reason !== null) {
    $report['status'] = 'skipped';
    $report['message'] = sprintf(_('No confirmation of %s %s: %s'), $evidence, $docId, $reason);
    $payment->addStatusMessage($report['message'], 'info');
    echo json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE)."\n";

    exit(0);
}

// The label of a confirmed payment must exist in AbraFlexi (for the bank and the cash desk)
$labels = new \AbraFlexi\RW(null, ['evidence' => 'stitek', 'ignore404' => true]);
$labels->throwException = false;
$known = $labels->getColumnsFromAbraFlexi(['id'], ['kod' => PaymentReceipt::LABEL]);

if (!\is_array($known) || $known === []) {
    $labels->insertToAbraFlexi([
        'kod' => PaymentReceipt::LABEL,
        'nazev' => 'Úhrada potvrzena',
        'poznam' => 'abraflexi-mailer: potvrzení o přijetí úhrady bylo odesláno zákazníkovi',
        'vsbBan' => true,
        'vsbPok' => true,
    ]);
}

// Mark first, send after: a second change of the payment that comes meanwhile finds the mark and sends nothing
$marker = new \AbraFlexi\RW((int) $docId, ['evidence' => $evidence]);
$marker->throwException = false;
$marker->insertToAbraFlexi(['id' => (int) $docId, 'stitky' => PaymentReceipt::withLabel($row['stitky'] ?? null)]);

if (!\in_array($marker->lastResponseCode, [200, 201], true)) {
    $message = sprintf(_('The payment %s could not be marked as confirmed: nothing was sent'), $docId);
    $payment->addStatusMessage($message, 'error');
    $report['message'] = $message;
    echo json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE)."\n";

    exit(4);
}

$linkRows = $payment->getColumnsFromAbraFlexi(['id'], ['id' => (int) $docId, 'relations' => 'vazby']);
$links = \is_array($linkRows) && isset($linkRows[0]['vazby']) && \is_array($linkRows[0]['vazby']) ? $linkRows[0]['vazby'] : [];

$payment->setData($row);
$mailer = new \AbraFlexi\Mailer\PotvrzeniPrijetiPlatby($payment, PaymentReceipt::invoices($links));

if ($mailer->send()) {
    $message = sprintf(_('Payment receipt confirmation sent for %s'), (string) ($row['kod'] ?? $docId));
    $payment->addStatusMessage($message, 'success');
    $report['status'] = 'success';
    $report['message'] = $message;
} else {
    // Not sent: take the mark away, the next change of the payment tries again
    $marker->insertToAbraFlexi(['id' => (int) $docId, 'stitky' => PaymentReceipt::withoutLabel($row['stitky'] ?? null)]);
    $message = sprintf(_('Failed to send payment receipt confirmation for %s'), (string) ($row['kod'] ?? $docId));
    $payment->addStatusMessage($message, 'error');
    $report['message'] = $message;
    echo json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE)."\n";

    exit(2);
}

echo json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE)."\n";
