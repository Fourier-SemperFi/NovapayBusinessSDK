<?php
declare(strict_types=1);

namespace NovaPay\DTO;

/** A single transaction from an account extract. */
class Transaction
{
    public string $code;
    public string $date;
    public string $dateRaw;
    public string $amount;
    public string $currency;
    public string $purpose;
    public string $paymentType;

    // Debit side (sender)
    public string $debitName;
    public string $debitIban;
    public string $debitStateCode;

    // Credit side (receiver)
    public string $creditName;
    public string $creditIban;
    public string $creditStateCode;

    public function __construct(
        string $code,
        string $date,
        string $dateRaw,
        string $amount,
        string $currency,
        string $purpose,
        string $paymentType,
        string $debitName,
        string $debitIban,
        string $debitStateCode,
        string $creditName,
        string $creditIban,
        string $creditStateCode
    ) {
        $this->code            = $code;
        $this->date            = $date;
        $this->dateRaw         = $dateRaw;
        $this->amount          = $amount;
        $this->currency        = $currency;
        $this->purpose         = $purpose;
        $this->paymentType     = $paymentType;
        $this->debitName       = $debitName;
        $this->debitIban       = $debitIban;
        $this->debitStateCode  = $debitStateCode;
        $this->creditName      = $creditName;
        $this->creditIban      = $creditIban;
        $this->creditStateCode = $creditStateCode;
    }

    /** Check if this is an incoming (credit) transaction for the given IBAN. */
    public function isIncoming(string $ourIban): bool
    {
        return $this->creditIban === $ourIban;
    }

    /** Check if this is an outgoing (debit) transaction for the given IBAN. */
    public function isOutgoing(string $ourIban): bool
    {
        return $this->debitIban === $ourIban;
    }
}
