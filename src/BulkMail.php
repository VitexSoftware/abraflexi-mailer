<?php

/**
 * abraflexi-bulkmail
 *
 * @copyright (c) 2023, Vítězslav Dvořák
 */

use Ease\Functions;
use Ease\Shared;

define('APP_NAME', 'AbraFlexiBulkMail');
require_once '../vendor/autoload.php';
Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'MAIL_FROM'], '../.env');
new \Ease\Locale(Functions::cfg('LC_ALL', 'cs_CZ'));
$template = ($argv[1]);
$query = array_key_exists(2, $argv) ? $argv[2] : '';
if ($argc > 2) {
    if (file_exists($template)) {
        $templater = new \AbraFlexi\Mailer\Templater(file_get_contents($template));
        if (\Ease\Functions::cfg('APP_DEBUG') == 'True') {
            $templater->logBanner(\Ease\Shared::appName());
        }

        $document = new \AbraFlexi\Adresar(null, ['limit' => 0,'detail' => 'full']);
        $to = $document->getFlexiData('', $query);

        $document->addStatusMessage(sprintf(_('Query "%s" found %d recipients'), $query, count($to)), 'debug');

        foreach ($to as $recipient) {
            $document->setData($recipient, true);
            $document->updateApiURL();
            $mailAddress = $document->getNotificationEmailAddress();
            $document->addStatusMessage(sprintf(_('Sending to %s %s %s'), $document->getRecordCode(), $document->getDataValue('nazev'), $mailAddress));
            $templater->populate($document);
            $mailer = new \Ease\HtmlMailer($mailAddress, pathinfo($template, PATHINFO_FILENAME), $templater->getRendered(), ['from' => Functions::cfg('MAIL_FROM')]);
            $mailer->send();
        }
    } else {
        die(sprintf(_('Template file %s not found'), realpath($template)));
    }
} else {
    echo _('AbraFlexi BulkMail') . "\n";
    echo "abraflexi-bulkmail <template> [recipient query]\n";
    echo "abraflexi-bulkmail oznameni_vypadku.ftl \"ulice=Lomená AND mesto=Praha\" \n";
}
