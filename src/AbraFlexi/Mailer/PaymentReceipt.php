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
 * Decides whether a received payment is to be confirmed to the customer, and keeps the book of it.
 *
 * Money that comes to the bank or to the cash desk is confirmed once, at the moment the payer is known:
 * the bank record carries the company (firma) from then on - when the payment is paired with an invoice
 * or with the deduction of an advance, or when the payer is known from the start.
 * The payment is marked with a label when it is confirmed, so a later change of the record
 * (and every pairing is one) does not send the confirmation again.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
final class PaymentReceipt
{
    /**
     * Evidences of received money.
     */
    public const EVIDENCES = ['banka', 'pokladni-pohyb'];

    /**
     * Label of a payment that was confirmed already.
     */
    public const LABEL = 'UHRADA_POTVRZENA';

    private function __construct()
    {
    }

    /**
     * The evidence if it is one of the received money, null otherwise.
     */
    public static function evidence(?string $evidence): ?string
    {
        $evidence = trim((string) $evidence);

        return \in_array($evidence, self::EVIDENCES, true) ? $evidence : null;
    }

    /**
     * Why the payment is not to be confirmed, null when it is.
     *
     * @param array<string, mixed> $payment columns of the payment (typPohybuK, firma, storno, stitky, sumCelkem)
     */
    public static function skipReason(array $payment): ?string
    {
        if (self::truthy($payment['storno'] ?? false)) {
            return 'cancelled';
        }

        if ((string) ($payment['typPohybuK'] ?? '') !== 'typPohybu.prijem') {
            return 'not an incoming payment';
        }

        if ((float) ($payment['sumCelkem'] ?? 0) <= 0.0) {
            return 'no amount';
        }

        if (self::company($payment['firma'] ?? null) === '') {
            return 'payer unknown';
        }

        if (self::hasLabel($payment['stitky'] ?? null)) {
            return 'already confirmed';
        }

        return null;
    }

    /**
     * Code of the company without "code:" (a relation of the AbraFlexi library or plain text), empty when unknown.
     */
    public static function company(mixed $firma): string
    {
        if (\is_array($firma)) {
            $firma = $firma['value'] ?? '';
        }

        return trim((string) preg_replace('/^code:/i', '', trim((string) $firma)));
    }

    /**
     * Is the label among the labels of the record?
     *
     * @param array<int, string>|string|null $labels "A, B", "code:A,code:B" or a list
     */
    public static function hasLabel(array|string|null $labels): bool
    {
        return \in_array(self::LABEL, self::labelCodes($labels), true);
    }

    /**
     * Labels of the record with the label added ("A, B" form AbraFlexi takes).
     *
     * @param array<int, string>|string|null $labels
     */
    public static function withLabel(array|string|null $labels): string
    {
        $codes = self::labelCodes($labels);

        if (!\in_array(self::LABEL, $codes, true)) {
            $codes[] = self::LABEL;
        }

        return implode(', ', $codes);
    }

    /**
     * Labels of the record without the label.
     *
     * @param array<int, string>|string|null $labels
     */
    public static function withoutLabel(array|string|null $labels): string
    {
        return implode(', ', array_values(array_filter(
            self::labelCodes($labels),
            static fn (string $code): bool => $code !== self::LABEL,
        )));
    }

    /**
     * Codes of the issued invoices the payment is linked to.
     *
     * @param array<int, array<string, mixed>> $links "vazby" of the payment
     *
     * @return array<int, string>
     */
    public static function invoices(array $links): array
    {
        $codes = [];

        foreach ($links as $link) {
            foreach (['a', 'b'] as $end) {
                $ref = (string) ($link[$end.'@ref'] ?? '');

                if (preg_match('#/faktura-vydana/\d+(?:\.json)?$#', $ref) === 1) {
                    $code = self::company($link[$end.'@showAs'] ?? $link[$end] ?? '');

                    if ($code !== '' && !\in_array($code, $codes, true)) {
                        $codes[] = $code;
                    }
                }
            }
        }

        return $codes;
    }

    /**
     * @param array<int, string>|string|null $labels
     *
     * @return array<int, string> codes without "code:"
     */
    private static function labelCodes(array|string|null $labels): array
    {
        $parts = \is_array($labels) ? $labels : explode(',', (string) $labels);

        return array_values(array_filter(array_map(
            static fn ($part): string => self::company($part),
            $parts,
        ), static fn (string $code): bool => $code !== ''));
    }

    private static function truthy(mixed $value): bool
    {
        return $value === true || (\is_string($value) && strtolower($value) === 'true');
    }
}
