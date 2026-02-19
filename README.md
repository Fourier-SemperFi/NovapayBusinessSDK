# NovaPay Business SDK

PHP SDK for the NovaPay Business SOAP API. No database dependencies — token persistence is the caller's responsibility.

## Requirements

- PHP 7.4+
- ext-soap
- ext-simplexml

## Installation

```bash
composer require novapay/business-sdk
```


## Quick Start

```php
use src\NovaPayClient;

$client = new NovaPayClient();

// Step 1: Pre-auth (sends OTP)
$preAuth = $client->preAuthenticate('login', 'password');

// Step 2: Confirm OTP
$auth = $client->authenticate($preAuth->tempPrincipal, $preAuth->codeOperationOtp, $otp);

// Persist $auth->principal and $auth->expiration yourself!

// Get data
$clients  = $client->getClients($auth->principal);
$accounts = $client->getAccounts($auth->principal, $clients[0]->id);
$balance  = $client->getBalance($auth->principal, $accounts[0]->id);
$extract  = $client->getExtract($auth->principal, $accounts[0]->id, '01.01.2025', '31.01.2025');
```

## API Reference

### Authentication

| Method | Returns | Description |
|--------|---------|-------------|
| `preAuthenticate($login, $pass)` | `PreAuthResult` | Sends OTP to user's phone |
| `authenticate($temp, $code, $otp)` | `AuthResult` | Confirms OTP, returns session token |
| `refreshSession($principal)` | `AuthResult\|null` | Extends session lifetime |
| `isSessionValid($principal, $exp)` | `bool` | Static helper to check token validity |

### Data

| Method | Returns | Description |
|--------|---------|-------------|
| `getClients($principal)` | `Client[]` | List business clients |
| `getAccounts($principal, $clientId)` | `Account[]` | List bank accounts |
| `getBalance($principal, $accountId)` | `Balance` | Single account balance |
| `getBalances($principal, $clientId)` | `Balance[]` | All account balances (with error callback) |
| `getExtract($principal, $acctId, $from, $to)` | `Extract` | Parsed statement |
| `getExtractRaw($principal, $acctId, $from, $to)` | `string\|null` | Raw XML for custom parsing |

### DTO Objects

**AuthResult** — `->principal`, `->expiration`, `->isValid()`

**Client** — `->id`, `->name`

**Account** — `->id`, `->iban`, `->currency`, `->balance`

**Balance** — `->accountId`, `->confirmed`, `->available`, `->projected`, `->raw`

**Transaction** — `->code`, `->date`, `->amount`, `->currency`, `->purpose`, `->paymentType`, `->debitName`, `->debitIban`, `->debitStateCode`, `->creditName`, `->creditIban`, `->creditStateCode`, `->isIncoming($iban)`, `->isOutgoing($iban)`

**Extract** — `->iban`, `->transactions`, `->incoming()`, `->outgoing()`, `->byCreditEdrpou($code)`, `->byDebitEdrpou($code)`, `->byCodePattern($regex)`

### Custom SOAP Options

```php
$client = new NovaPayClient('https://business.novapay.ua/Services/ClientAPIService.svc?wsdl', 'https://business.novapay.ua/Services/ClientAPIService.svc');

// Inject SoapClient for testing
$mock = $this->createMock(SoapClient::class);
$client = NovaPayClient::withSoapClient($mock);
```

## File Structure

```
src/
├── NovaPayClient.php      # Main API client
├── ExtractParser.php       # XML  DTO parser (usable standalone)
└── DTO/
    ├── Account.php
    ├── AuthResult.php
    ├── Balance.php
    ├── Client.php
    ├── Extract.php
    ├── PreAuthResult.php
    └── Transaction.php
```

