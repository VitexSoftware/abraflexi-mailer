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

use AbraFlexi\Mailer\PaymentConfirmer;
use AbraFlexi\Mailer\PaymentReceipt;
use Ease\Shared;

\define('APP_NAME', 'AbraFlexiPotvrzeniPrijetiPlatby');

require_once '../vendor/autoload.php';
Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'MAIL_FROM'], '../.env');
new \Ease\Locale(Shared::cfg('LOCALIZE', 'cs_CZ'), '../i18n', 'abraflexi-mailer');

$options = getopt('', ['docid:', 'evidence:', 'days-back:']);
$docId = (string) ($options['docid'] ?? Shared::cfg('DOCID', ''));
$report = ['producer' => \constant('APP_NAME'), 'status' => 'error', 'timestamp' => date('c')];
$confirmer = new PaymentConfirmer();

if ($docId === '') {
    // Without a document: all payments changed lately that were not confirmed yet. An event of MultiFlexi can be lost
    // and the payer can become known long after the payment came, so this runs now and then besides the events.
    $daysBack = (int) ($options['days-back'] ?? Shared::cfg('CONFIRM_DAYS_BACK', '3'));
    $sweep = $confirmer->sweep(max(1, $daysBack));
    $report = array_merge($report, $sweep, [
        'status' => $sweep['failed'] === 0 ? 'success' : 'error',
        'message' => sprintf(_('Payments checked: %s, confirmations sent: %d, failed: %d'), json_encode($sweep['checked']), $sweep['sent'], $sweep['failed']),
    ]);
    echo json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE)."\n";

    exit($sweep['failed'] === 0 ? 0 : 2);
}

$evidence = PaymentReceipt::evidence((string) ($options['evidence'] ?? Shared::cfg('EVIDENCE', 'banka')));

if ($evidence === null || !ctype_digit($docId)) {
    fwrite(\STDERR, _('Confirmation of receipt of a payment (bank or cash desk)')."\n");
    fwrite(\STDERR, "abraflexi-potvrzeni-prijeti-platby --docid=<ID> [--evidence=banka|pokladni-pohyb]\n");
    fwrite(\STDERR, "DOCID=<ID> EVIDENCE=banka abraflexi-potvrzeni-prijeti-platby\n");
    fwrite(\STDERR, "abraflexi-potvrzeni-prijeti-platby [--days-back=3]   (without DOCID: all payments changed lately and not confirmed yet)\n");

    exit(1);
}

$result = $confirmer->confirm($evidence, (int) $docId);
$report['status'] = $result['status'];
$report['message'] = $result['message'];
echo json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE)."\n";

exit($result['exit']);
