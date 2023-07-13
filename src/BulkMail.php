<?php

/**
 * abraflexi-bulkmail
 *
 * @copyright (c) 2023, Vítězslav Dvořák
 */
use Ease\Functions;
use Ease\Shared;

define('APP_NAME', 'BulkMail');
require_once '../vendor/autoload.php';
Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'MAIL_FROM'], '../.env');
new \Ease\Locale(Functions::cfg('LC_ALL', 'cs_CZ'));
$template = ($argv[1]);
$query = array_key_exists(2, $argv) ? $argv[2] : '';
if ($argc > 1) {
    $document = new \AbraFlexi\Adresar();
    $templater = new \AbraFlexi\Mailer\Templater($document, $template);
    if (\Ease\Functions::cfg('APP_DEBUG') == 'True') {
        $templater->logBanner(\Ease\Shared::appName());
        $templater->addStatusMessage(sprintf(
                        _('Cannot read %s %s'), $evidence, $document
        ));
    }
} else {
    echo _('AbraFlexi BulkMail') . "\n";
    echo "abraflexi-bulkmail <template> [recipient query]\n";
    echo "abraflexi-bulkmail oznameni_vypadku.ftl \"ulice=Lomená AND mesto=Praha\" \n";
}
