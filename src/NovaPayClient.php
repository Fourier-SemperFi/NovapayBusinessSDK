<?php
declare(strict_types=1);

namespace src;

use NovaPay\DTO;
use RuntimeException;
use SoapClient;

/**
 * NovaPay Business SOAP API client.
 *
 * Token/session persistence is the caller's responsibility.
 *
 * Usage:
 *   $client  = new NovaPayClient();
 *   $preAuth = $client->preAuthenticate('login', 'password');
 *   $auth    = $client->authenticate($preAuth->tempPrincipal, $preAuth->codeOperationOtp, $otp);
 *   $accounts = $client->getAccounts($auth->principal, $clientId);
 *   $extract  = $client->getExtract($auth->principal, $accounts[0]->id, '01.01.2025', '31.01.2025');
 */
class NovaPayClient
{
    private const DEFAULT_WSDL     = 'https://business.novapay.ua/Services/ClientAPIService.svc?wsdl';
    private const DEFAULT_ENDPOINT = 'https://business.novapay.ua/Services/ClientAPIService.svc';

    private SoapClient $soap;

    /**
     * @param string|null $wsdl        Custom WSDL URL (null = production)
     * @param string|null $endpoint    Custom endpoint URL (null = production)
     * @param array       $soapOptions Extra options passed to SoapClient
     */
    public function __construct(
        ?string $wsdl = null,
        ?string $endpoint = null,
        array $soapOptions = []
    ) {
        $this->soap = new SoapClient(
            $wsdl ?? self::DEFAULT_WSDL,
            array_merge([
                'location'   => $endpoint ?? self::DEFAULT_ENDPOINT,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ], $soapOptions)
        );
    }

    /** Create an instance with an injected SoapClient (useful for testing). */
    public static function withSoapClient(SoapClient $soap): self
    {
        $instance = new self();
        $instance->soap = $soap;
        return $instance;
    }

    // Authentication

    /**
     * Step 1: Pre-authenticate. Triggers an OTP SMS to the user's phone.
     *
     * @throws RuntimeException If the API does not return a temp_principal.
     */
    public function preAuthenticate(string $login, string $password): DTO\PreAuthResult
    {
        $r = $this->call('PreUserAuthentication', [
            'login'    => $login,
            'password' => $password,
        ]);

        if (empty($r->temp_principal)) {
            throw new RuntimeException('PreAuthentication failed: no temp_principal returned');
        }

        return new DTO\PreAuthResult(
            (string)$r->temp_principal,
            (string)$r->code_operation_otp
        );
    }

    /**
     * Step 2: Confirm the OTP and obtain a session principal.
     *
     * @throws RuntimeException If the API does not return a principal.
     */
    public function authenticate(
        string $tempPrincipal,
        string $codeOperationOtp,
        string $otpPassword
    ): \src\DTO\AuthResult {
        $r = $this->call('UserAuthentication', [
            'temp_principal'     => $tempPrincipal,
            'code_operation_otp' => $codeOperationOtp,
            'otp_password'       => $otpPassword,
        ]);

        if (empty($r->principal)) {
            throw new RuntimeException('Authentication failed: no principal returned');
        }

        return new \src\DTO\AuthResult(
            (string)$r->principal,
            (string)$r->expiration
        );
    }

    /**
     * Refresh (extend) the current session.
     *
     * @return \src\DTO\AuthResult|null New credentials, or null if refresh was not needed.
     */
    public function refreshSession(string $principal): ?\src\DTO\AuthResult
    {
        $r = $this->call('RefreshUserAuthentication', [
            'principal' => $principal,
        ]);

        if (empty($r->new_principal)) {
            return null;
        }

        return new \src\DTO\AuthResult(
            (string)$r->new_principal,
            (string)$r->expiration
        );
    }

    /**
     * Check whether a session token is still valid (static helper).
     */
    public static function isSessionValid(?string $principal, ?string $expiration): bool
    {
        return !empty($principal)
            && !empty($expiration)
            && time() <= strtotime($expiration);
    }

    // Clients & Accounts

    /**
     * List business clients (enterprises) available to the authenticated user.
     *
     * @return DTO\Client[]
     */
    public function getClients(string $principal): array
    {
        $r    = $this->call('GetClientsList', ['principal' => $principal]);
        $list = $r->clients->Clients ?? [];
        $list = is_array($list) ? $list : [$list];

        return array_map(
            fn($c) => new DTO\Client((int)$c->id, (string)($c->name ?? '')),
            $list
        );
    }

    /**
     * List bank accounts for a given client.
     *
     * @return \src\DTO\Account[]
     */
    public function getAccounts(string $principal, int $clientId): array
    {
        $r    = $this->call('GetAccountsList', [
            'principal' => $principal,
            'client_id' => $clientId,
        ]);
        $list = $r->accounts->Accounts ?? [];
        $list = is_array($list) ? $list : [$list];

        return array_map(
            fn($a) => new \src\DTO\Account(
                (int)$a->id,
                (string)($a->IBAN ?? ''),
                (string)($a->Currency ?? ''),
                (string)($a->Balance ?? '0')
            ),
            $list
        );
    }

    // Balance

    /**
     * Get the current balance for a single account.
     *
     * @throws RuntimeException If the API returns a non-ok result.
     */
    public function getBalance(string $principal, int $accountId): DTO\Balance
    {
        $r = $this->call('GetAccountRest', [
            'principal'  => $principal,
            'account_id' => $accountId,
        ]);

        if (($r->result ?? '') !== 'ok') {
            throw new RuntimeException(
                "GetAccountRest failed for account {$accountId}: " . ($r->result ?? 'unknown')
            );
        }

        return new DTO\Balance(
            $accountId,
            (float)($r->confirmed_balance ?? 0),
            (float)($r->available_balance ?? 0),
            (float)($r->projected_balance ?? 0),
            $r
        );
    }

    /**
     * Get balances for all accounts of a client.
     *
     * Silently skips accounts that return errors (logs to $onError if provided).
     *
     * @param callable|null $onError  fn(int $accountId, \Throwable $e): void
     * @return DTO\Balance[]
     */
    public function getBalances(string $principal, int $clientId, ?callable $onError = null): array
    {
        $accounts = $this->getAccounts($principal, $clientId);
        $balances = [];

        foreach ($accounts as $acct) {
            try {
                $balances[] = $this->getBalance($principal, $acct->id);
            } catch (\Throwable $e) {
                if ($onError) {
                    $onError($acct->id, $e);
                }
            }
        }

        return $balances;
    }

    // Extract (transactions)

    /**
     * Get a parsed account extract (statement) for a date range.
     *
     * @param string $dateFrom  Format: dd.mm.YYYY
     * @param string $dateTo    Format: dd.mm.YYYY
     * @throws RuntimeException
     */
    public function getExtract(
        string $principal,
        int $accountId,
        string $dateFrom,
        string $dateTo
    ): DTO\Extract {
        $r = $this->call('GetAccountExtract', [
            'principal'  => $principal,
            'account_id' => $accountId,
            'date_from'  => $dateFrom,
            'date_to'    => $dateTo,
        ]);

        if (($r->result ?? '') !== 'ok') {
            throw new RuntimeException(
                "GetAccountExtract failed: " . ($r->result ?? 'unknown')
            );
        }

        $xml = @simplexml_load_string((string)$r->extract);
        if (!$xml) {
            throw new RuntimeException('Failed to parse extract XML');
        }

        return ExtractParser::parse($xml);
    }

    /**
     * Get the raw XML string of an account extract (for custom parsing).
     *
     * @return string|null  Raw XML or null on failure.
     */
    public function getExtractRaw(
        string $principal,
        int $accountId,
        string $dateFrom,
        string $dateTo
    ): ?string {
        $r = $this->call('GetAccountExtract', [
            'principal'  => $principal,
            'account_id' => $accountId,
            'date_from'  => $dateFrom,
            'date_to'    => $dateTo,
        ]);

        return ($r->result ?? '') === 'ok' ? (string)$r->extract : null;
    }

    // SOAP transport

    private function call(string $method, array $params): object
    {
        $resp = $this->soap->__soapCall($method, [['request' => $params]]);
        return $resp->{$method . 'Result'};
    }
}
