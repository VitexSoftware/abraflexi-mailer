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

if (empty($unsent)) {
    $invoicer->addStatusMessage(_('all sent'), 'success');
} else {
    foreach ($unsent as $unsentData) {
        $invoicer->setData($unsentData);
        $unsent[$unsentData['kod']]['email'] = $invoicer->getEmail();
        $unsent[$unsentData['kod']]['recipients'] = $invoicer->getRecipients();
        $invoicer->addStatusMessage(
            $unsentData['kod']."\t".$unsentData['firma']."\t".$unsent[$unsentData['kod']]['email'].' '.$unsent[$unsentData['kod']]['recipients']."\t".$unsentData['poznam'],
            'warning',
        );
    }

    $invoicer->addStatusMessage(\count($unsent).' '._('total'), 'warning');
}

$written = file_put_contents($destination, json_encode($unsent, \Ease\Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT : 0));
$invoicer->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');

exit($written ? 0 : 1);
