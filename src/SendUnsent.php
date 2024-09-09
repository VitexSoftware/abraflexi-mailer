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

use Ease\Shared;

\define('EASE_APPNAME', 'AbraFlexiOdeslatNeodeslane');

require_once '../vendor/autoload.php';
Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'MAIL_FROM'], '../.env');
new \Ease\Locale(Shared::cfg('LOCALIZE', 'cs_CZ'), '../i18n', 'abraflexi-mailer');
$invoicer = new \AbraFlexi\FakturaVydana();

if (strtolower(Shared::cfg('APP_DEBUG', 'false')) === 'true') {
    $invoicer->logBanner(Shared::appName().' v'.Shared::appVersion());
}

$invoicer->addStatusMessage(_('Send unsent mails'), $invoicer->sendUnsent() ? 'success' : 'warning');
