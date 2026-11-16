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

namespace AbraFlexi\Mailer;

/**
 * Confirms received payments (bank, cash desk) to the customers who paid.
 *
 * One payment (an event of MultiFlexi: DOCID of the changed record) or all payments changed
 * lately that were not confirmed yet (a sweep run now and then: an event can be lost, and a payment
 * is confirmed only when the payer is known, which may happen long after the payment came).
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class PaymentConfirmer
{
    public const COLUMNS = ['id', 'kod', 'typPohybuK', 'firma', 'storno', 'stitky', 'sumCelkem', 'mena', 'datVyst', 'varSym', 'popis'];

    /**
     * Confirm one payment if it is to be confirmed.
     *
     * @return array{status: string, message: string, exit: int} status: success, skipped or error; exit: 0, 2 (not sent), 3 (not read), 4 (not marked)
     */
    public function confirm(string $evidence, int $id): array
    {
        $payment = new \AbraFlexi\RW($id, ['evidence' => $evidence, 'ignore404' => true]);
        $payment->throwException = false;
        $rows = $payment->getColumnsFromAbraFlexi(self::COLUMNS, ['id' => $id]);
        $row = \is_array($rows) && $payment->lastResponseCode === 200 ? (array) ($rows[0] ?? []) : [];

        if ($row === []) {
            return $this->result($payment, 'error', sprintf(_('Cannot read document %s from evidence %s'), (string) $id, $evidence), 3);
        }

        // Not every change of a payment is a reason to write: most are not, and that is no error
        $reason = PaymentReceipt::skipReason($row);

        if ($reason !== null) {
            return $this->result($payment, 'skipped', sprintf(_('No confirmation of %s %s: %s'), $evidence, (string) $id, $reason), 0);
        }

        $this->ensureLabel();

        // Mark first, send after: a second change of the payment that comes meanwhile finds the mark and sends nothing
        $marker = new \AbraFlexi\RW($id, ['evidence' => $evidence]);
        $marker->throwException = false;
        $marker->insertToAbraFlexi(['id' => $id, 'stitky' => PaymentReceipt::withLabel($row['stitky'] ?? null)]);

        if (!\in_array($marker->lastResponseCode, [200, 201], true)) {
            return $this->result($payment, 'error', sprintf(_('The payment %s could not be marked as confirmed: nothing was sent'), (string) $id), 4);
        }

        $linkRows = $payment->getColumnsFromAbraFlexi(['id'], ['id' => $id, 'relations' => 'vazby']);
        $links = \is_array($linkRows) && isset($linkRows[0]['vazby']) && \is_array($linkRows[0]['vazby']) ? $linkRows[0]['vazby'] : [];

        $payment->setData($row);
        $mailer = new PotvrzeniPrijetiPlatby($payment, PaymentReceipt::invoices($links));
        $code = (string) ($row['kod'] ?? $id);

        if ($mailer->send()) {
            return $this->result($payment, 'success', sprintf(_('Payment receipt confirmation sent for %s'), $code), 0);
        }

        // Not sent: take the mark away, the next change of the payment (or the next sweep) tries again
        $marker->insertToAbraFlexi(['id' => $id, 'stitky' => PaymentReceipt::withoutLabel($row['stitky'] ?? null)]);

        return $this->result($payment, 'error', sprintf(_('Failed to send payment receipt confirmation for %s'), $code), 2);
    }

    /**
     * Ids of the payments of the evidence that changed in the last days and are to be confirmed.
     *
     * @return array<int, int>
     */
    public function unconfirmed(string $evidence, int $daysBack): array
    {
        $payments = new \AbraFlexi\RO(null, ['evidence' => $evidence, 'ignore404' => true]);
        $payments->throwException = false;
        $since = (new \DateTimeImmutable('today'))->modify('-'.max(1, $daysBack).' days')->format('Y-m-d');
        $rows = $payments->getColumnsFromAbraFlexi(['id', 'typPohybuK', 'firma', 'storno', 'stitky', 'sumCelkem'], [
            "lastUpdate >= '".$since."'",
            'limit' => 0,
        ]);
        $ids = [];

        foreach (\is_array($rows) && $payments->lastResponseCode === 200 ? $rows : [] as $row) {
            if (\is_array($row) && PaymentReceipt::skipReason($row) === null) {
                $ids[] = (int) $row['id'];
            }
        }

        return $ids;
    }

    /**
     * Confirm all payments of the bank and the cash desk changed in the last days that were not confirmed yet.
     *
     * @return array{sent: int, failed: int, checked: array<string, int>, messages: array<int, string>}
     */
    public function sweep(int $daysBack): array
    {
        $report = ['sent' => 0, 'failed' => 0, 'checked' => [], 'messages' => []];

        foreach (PaymentReceipt::EVIDENCES as $evidence) {
            $ids = $this->unconfirmed($evidence, $daysBack);
            $report['checked'][$evidence] = \count($ids);

            foreach ($ids as $id) {
                $result = $this->confirm($evidence, $id);
                $report['messages'][] = $result['message'];

                if ($result['status'] === 'success') {
                    ++$report['sent'];
                } elseif ($result['status'] === 'error') {
                    ++$report['failed'];
                }
            }
        }

        return $report;
    }

    /**
     * The label of a confirmed payment must exist in AbraFlexi (for the bank and the cash desk).
     */
    private function ensureLabel(): void
    {
        $labels = new \AbraFlexi\RW(null, ['evidence' => 'stitek', 'ignore404' => true]);
        $labels->throwException = false;
        $known = $labels->getColumnsFromAbraFlexi(['id'], ['kod' => PaymentReceipt::LABEL]);

        if (!\is_array($known) || $known === []) {
            $labels->insertToAbraFlexi([
                'kod' => PaymentReceipt::LABEL,
                'nazev' => 'Úhrada potvrzena',
                'poznam' => 'abraflexi-mailer: potvrzení o přijetí úhrady bylo odesláno zákazníkovi',
                'vsbBan' => true,
                'vsbPok' => true,
            ]);
        }
    }

    /**
     * @return array{status: string, message: string, exit: int}
     */
    private function result(\AbraFlexi\RO $logger, string $status, string $message, int $exit): array
    {
        $logger->addStatusMessage($message, $status === 'error' ? 'error' : ($status === 'success' ? 'success' : 'info'));

        return ['status' => $status, 'message' => $message, 'exit' => $exit];
    }
}
