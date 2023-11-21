<?php

/**
 * flexibee-send-unsent
 *
 * @copyright (c) 2018-2020, Vítězslav Dvořák
 */

use Ease\Shared;

define('EASE_APPNAME', 'AbraFlexiOdeslatNeodeslane');
require_once '../vendor/autoload.php';
Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'MAIL_FROM'], '../.env');
new \Ease\Locale(Shared::cfg('LOCALIZE', 'cs_CZ'), '../i18n', 'abraflexi-mailer');
$invoicer = new \AbraFlexi\FakturaVydana();
if (Shared::cfg('APP_DEBUG') == 'True') {
    $invoicer->logBanner(Shared::appName());
}
$invoicer->addStatusMessage(
    _('Send unsent mails'),
    $invoicer->sendUnsent() == 202 ? 'success' : 'warning'
);
