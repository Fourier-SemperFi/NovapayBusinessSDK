<?php
declare(strict_types=1);

namespace NovaPay\DTO;

/** Parsed account extract containing IBAN and a list of transactions. */
class Extract
{
    public string $iban;

    /** @var Transaction[] */
    public array $transactions;

    /** @param Transaction[] $transactions */
    public function __construct(string $iban, array $transactions)
    {
        $this->iban         = $iban;
        $this->transactions = $transactions;
    }

    /** Filter to incoming transactions only. */
    public function incoming(): array
    {
        return array_values(array_filter(
            $this->transactions,
            fn(Transaction $tx) => $tx->isIncoming($this->iban)
        ));
    }

    /** Filter to outgoing transactions only. */
    public function outgoing(): array
    {
        return array_values(array_filter(
            $this->transactions,
            fn(Transaction $tx) => $tx->isOutgoing($this->iban)
        ));
    }

    /** Filter transactions by credit-side EDRPOU code. */
    public function byCreditEdrpou(string $edrpou): array
    {
        return array_values(array_filter(
            $this->transactions,
            fn(Transaction $tx) => $tx->creditStateCode === $edrpou
        ));
    }

    /** Filter transactions by debit-side EDRPOU code. */
    public function byDebitEdrpou(string $edrpou): array
    {
        return array_values(array_filter(
            $this->transactions,
            fn(Transaction $tx) => $tx->debitStateCode === $edrpou
        ));
    }

    /** Filter transactions matching a code pattern (e.g. /^BO\d+$/). */
    public function byCodePattern(string $regex): array
    {
        return array_values(array_filter(
            $this->transactions,
            fn(Transaction $tx) => (bool)preg_match($regex, $tx->code)
        ));
    }
}
