<?php
declare(strict_types=1);

namespace NovaPay\DTO;

/** A NovaPay business client (enterprise). */
class Client
{
    public int    $id;
    public string $name;

    public function __construct(int $id, string $name)
    {
        $this->id   = $id;
        $this->name = $name;
    }
}
