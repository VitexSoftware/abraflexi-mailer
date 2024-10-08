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

use Ease\Shared;

\define('APP_NAME', 'AbraFlexiBulkMail');

require_once '../vendor/autoload.php';
$template = $argv[1];
$query = \array_key_exists(2, $argv) ? $argv[2] : '';

if ($argc > 2) {
    Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'MAIL_FROM'], \array_key_exists(3, $argv) ? $argv[3] : '../.env');
    new \Ease\Locale(Shared::cfg('LOCALIZE', 'cs_CZ'), '../i18n', 'abraflexi-mailer');

    if (file_exists($template)) {
        $templater = new \AbraFlexi\Mailer\Templater(file_get_contents($template));

        if (\Ease\Shared::cfg('APP_DEBUG') === 'True') {
            $templater->logBanner();
        }

        $document = new \AbraFlexi\Adresar(null, ['limit' => 0, 'detail' => 'full']);
        $to = $document->getFlexiData('', $query);

        $document->addStatusMessage(sprintf(_('Query "%s" found %d recipients'), $query, \count($to)), 'debug');

        foreach ($to as $recipient) {
            $document->setData($recipient, true);
            $document->updateApiURL();
            $mailAddress = $document->getNotificationEmailAddress();

            if ($mailAddress) {
                $document->addStatusMessage(sprintf(_('Sending to %s %s %s'), $document->getRecordCode(), $document->getDataValue('nazev'), $mailAddress));
                $templater->populate($document);
                $mailer = new \Ease\HtmlMailer($mailAddress, pathinfo($template, \PATHINFO_FILENAME), $templater->getRendered(), ['From' => Shared::cfg('MAIL_FROM')]);
                $mailer->send();
            } else {
                $document->addStatusMessage(sprintf(_('Address and contact %s without email address'), $document), 'warning');
            }
        }
    } else {
        exit(sprintf(_('Template file %s not found'), realpath($template)));
    }
} else {
    echo _('AbraFlexi BulkMail')."\n";
    echo "abraflexi-bulkmail <template> [recipient query] [config/file.env]\n";
    echo "abraflexi-bulkmail oznameni_vypadku.ftl \"ulice=Lomená AND mesto=Praha\" \n";
}
