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
use Ease\Shared;

\define('APP_NAME', 'AbraFlexiShowUnsentInvoices');

require_once '../vendor/autoload.php';

$options = getopt('o::e::', ['output::environment::']);
Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'], \array_key_exists('environment', $options) ? $options['environment'] : '../.env');
new \Ease\Locale(Shared::cfg('LOCALIZE', 'cs_CZ'), '../i18n', 'abraflexi-mailer');
$destination = \array_key_exists('output', $options) ? $options['output'] : \Ease\Shared::cfg('RESULT_FILE', 'php://stdout');

$invoicer = new FakturaVydana(null, ['limit' => 0]);

if (Shared::cfg('APP_DEBUG') === 'True') {
    $invoicer->logBanner();
}

$unsent = $invoicer->getColumnsFromAbraFlexi(
    ['firma', 'kontaktEmail', 'poznam'],
    ['stavMailK' => 'stavMail.odeslat', 'limit' => 0],
    'kod',
);

// Prepare report structure according to multiflexi.report.schema.json
$report = [
    'status' => 'success',
    'timestamp' => date('c'), // ISO8601 format
    'message' => '',
    'artifacts' => [
        'unsent_invoices' => [],
    ],
    'metrics' => [
        'total_unsent' => 0,
        'companies_affected' => 0,
    ],
];

if (empty($unsent)) {
    $invoicer->addStatusMessage(_('all sent'), 'success');
    $report['message'] = _('All invoices have been sent successfully');
    $report['status'] = 'success';
} else {
    $companies = [];

    foreach ($unsent as $unsentData) {
        $invoicer->setData($unsentData);
        $unsent[$unsentData['kod']]['email'] = $invoicer->getEmail();
        $unsent[$unsentData['kod']]['recipients'] = $invoicer->getRecipients();
        $companies[$unsentData['firma']] = true;
        $invoicer->addStatusMessage(
            $unsentData['kod']."\t".$unsentData['firma']."\t".$unsent[$unsentData['kod']]['email'].' '.$unsent[$unsentData['kod']]['recipients']."\t".$unsentData['poznam'],
            'warning',
        );
    }

    $report['artifacts']['unsent_invoices'] = array_values($unsent);
    $report['metrics']['total_unsent'] = \count($unsent);
    $report['metrics']['companies_affected'] = \count($companies);
    $report['message'] = sprintf(_('%d unsent invoices found affecting %d companies'), \count($unsent), \count($companies));
    $report['status'] = 'warning';

    $invoicer->addStatusMessage(\count($unsent).' '._('total'), 'warning');
}

$written = file_put_contents($destination, json_encode($report, \Ease\Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT : 0));
$invoicer->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');

exit($written ? 0 : 1);
