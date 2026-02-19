<?php
declare(strict_types=1);

namespace src\DTO;

/**
 * Authentication / session refresh result containing the session token.
 */
class AuthResult
{
    public string $principal;
    public string $expiration;

    public function __construct(string $principal, string $expiration)
    {
        $this->principal  = $principal;
        $this->expiration = $expiration;
    }

    /** Check whether this session token is still valid. */
    public function isValid(): bool
    {
        return !empty($this->principal)
            && !empty($this->expiration)
            && time() <= strtotime($this->expiration);
    }
}
