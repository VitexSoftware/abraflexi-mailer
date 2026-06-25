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

use AbraFlexi\Banka;
use AbraFlexi\RO;
use Ease\Shared;

\define('APP_NAME', 'AbraFlexiPotvrzeniPrijetiBankovniPlatby');

require_once '../vendor/autoload.php';
Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'MAIL_FROM'], '../.env');
new \Ease\Locale(Shared::cfg('LOCALIZE', 'cs_CZ'), '../i18n', 'abraflexi-mailer');

$options = getopt('', ['docid:']);
$docId = $options['docid'] ?? Shared::cfg('DOCID', '');

if (empty($docId)) {
    fwrite(\STDERR, _('Confirmation of receipt of bank payment (unmatched)')."\n");
    fwrite(\STDERR, "abraflexi-potvrzeni-prijeti-bankovni-platby --docid=<BankaID>\n");
    fwrite(\STDERR, "DOCID=<BankaID> abraflexi-potvrzeni-prijeti-bankovni-platby\n");

    exit(1);
}

$document = (is_numeric($docId) ? (int) $docId : RO::code(RO::uncode($docId)));
$bankRecord = new Banka($document, ['ignore404' => true]);

if (Shared::cfg('APP_DEBUG') === 'True') {
    $bankRecord->logBanner(Shared::appName().' v'.Shared::appVersion());
}

$report = [
    'producer' => \constant('APP_NAME'),
    'status' => 'error',
    'timestamp' => date('c'),
];

if ($bankRecord->lastResponseCode === 200) {
    $mailer = new \AbraFlexi\Mailer\PotvrzeniPrijetiBankovniPlatby($bankRecord);

    if ($mailer->send()) {
        $message = sprintf(_('Bank payment receipt confirmation sent for %s'), RO::uncode($bankRecord->getRecordCode()));
        $bankRecord->addStatusMessage($message, 'success');
        $report['status'] = 'success';
        $report['message'] = $message;
    } else {
        $message = sprintf(_('Failed to send bank payment receipt confirmation for %s'), RO::uncode($bankRecord->getRecordCode()));
        $bankRecord->addStatusMessage($message, 'error');
        $report['message'] = $message;
        echo json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE)."\n";

        exit(2);
    }
} else {
    $message = sprintf(_('Cannot read document %s from evidence %s'), $docId, 'banka');
    $bankRecord->addStatusMessage($message, 'error');
    $report['message'] = $message;
    echo json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE)."\n";

    exit(3);
}

echo json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE)."\n";
