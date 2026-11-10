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
$template = \array_key_exists(1, $argv) ? $argv[1] : Shared::cfg('ABRAFLEXI_BULKMAIL_TEMPLATE', '');
$query = \array_key_exists(2, $argv) ? $argv[2] : Shared::cfg('ABRAFLEXI_BULKMAIL_QUERY', '');

if ($argc > 2) {
    Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'MAIL_FROM'], \array_key_exists(3, $argv) ? $argv[3] : '../.env');
    new \Ease\Locale(Shared::cfg('LOCALIZE', 'cs_CZ'), '../i18n', 'abraflexi-mailer');

    if (file_exists($template)) {
        $templater = new \AbraFlexi\Mailer\Templater(file_get_contents($template));

        if (Shared::cfg('APP_DEBUG') === 'True') {
            $templater->logBanner();
        }

        $evidence = Shared::cfg('ABRAFLEXI_BULKMAIL_EVIDENCE', \AbraFlexi\Mailer\RecipientSource::DEFAULT_EVIDENCE);

        if (!\AbraFlexi\Mailer\RecipientSource::isSupported($evidence)) {
            fwrite(\STDERR, sprintf(_('Unsupported recipients evidence %s'), $evidence).\PHP_EOL);

            exit(1);
        }

        $emailColumn = \AbraFlexi\Mailer\RecipientSource::getEmailColumn($evidence);
        $document = 'adresar' === $evidence
            ? new \AbraFlexi\Adresar(null, ['limit' => 0, 'detail' => 'full'])
            : new \AbraFlexi\RO(null, ['evidence' => $evidence, 'limit' => 0, 'detail' => 'full']);
        $sent = [];

        try {
            $to = $document->getFlexiData('', [$query, 'limit' => 0]);

            $document->addStatusMessage(sprintf(_('Query "%s" found %d recipients'), $query, \count($to)), 'debug');

            foreach ($to as $recipient) {
                unset($recipient['external-ids']);
                $document->setData($recipient, true);
                $document->updateApiURL();
                $mailAddress = 'adresar' === $evidence ? $document->getNotificationEmailAddress() : trim((string) $document->getDataValue($emailColumn));

                if ($mailAddress && isset($sent[$mailAddress])) {
                    $document->addStatusMessage(sprintf(_('Skipping %s: already sent to %s'), $document->getRecordCode(), $mailAddress), 'debug');

                    continue;
                }

                if ($mailAddress) {
                    $sent[$mailAddress] = true;
                    $document->addStatusMessage(sprintf(_('Sending to %s %s %s'), $document->getRecordCode(), (string) ($document->getDataValue('nazev') ?: $document->getDataValue('jmeno') ?: ''), $mailAddress));
                    $templater->populate($document);
                    $mailer = new \AbraFlexi\Mailer\HtmlMailer($mailAddress, Shared::cfg('MAIL_SUBJECT', pathinfo($template, \PATHINFO_FILENAME)), $templater->getRendered(), ['From' => Shared::cfg('MAIL_FROM')]);
                    $mailer->send();
                } else {
                    $document->addStatusMessage(sprintf(_('Record %s without email address'), $document), 'warning');
                }
            }
        } catch (\AbraFlexi\Exception $ex) {
            fwrite(\STDERR, $ex->getMessage().\PHP_EOL);

            exit(2);
        }
    } else {
        fwrite(\STDERR, sprintf(_('Template file %s not found'), realpath($template)).\PHP_EOL);

        exit(1);
    }
} else {
    echo _('AbraFlexi BulkMail')."\n";
    echo "abraflexi-bulkmail <template> [recipient query] [config/file.env]\n";
    echo _('Recipients evidence is selected by ABRAFLEXI_BULKMAIL_EVIDENCE (default adresar)')."\n";
    echo "abraflexi-bulkmail oznameni_vypadku.ftl \"ulice=Lomená AND mesto=Praha\" \n";
}
