<?php
declare(strict_types=1);

namespace src\DTO;

/** A bank account belonging to a client. */
class Account
{
    public int    $id;
    public string $iban;
    public string $currency;
    public string $balance;

    public function __construct(int $id, string $iban, string $currency, string $balance)
    {
        $this->id       = $id;
        $this->iban     = $iban;
        $this->currency = $currency;
        $this->balance  = $balance;
    }
}
