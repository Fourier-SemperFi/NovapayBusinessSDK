<?php
declare(strict_types=1);

namespace NovaPay\DTO;

/** Account balance snapshot. */
class Balance
{
    public int    $accountId;
    public float  $confirmed;
    public float  $available;
    public float  $projected;
    public object $raw;

    public function __construct(int $accountId, float $confirmed, float $available, float $projected, object $raw)
    {
        $this->accountId = $accountId;
        $this->confirmed = $confirmed;
        $this->available = $available;
        $this->projected = $projected;
        $this->raw       = $raw;
    }
}
