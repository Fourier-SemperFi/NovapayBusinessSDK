<?php
declare(strict_types=1);

namespace NovaPay\DTO;

/**
 * Result of the pre-authentication step (OTP is sent to the user's phone).
 */
class PreAuthResult
{
    public string $tempPrincipal;
    public string $codeOperationOtp;

    public function __construct(string $tempPrincipal, string $codeOperationOtp)
    {
        $this->tempPrincipal    = $tempPrincipal;
        $this->codeOperationOtp = $codeOperationOtp;
    }
}
