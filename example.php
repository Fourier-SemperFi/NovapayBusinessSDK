<?php
declare(strict_types=1);

/**
 * Example: full workflow — auth, accounts, balances, extracts.
 *
 * Install:
 *   composer require novapay/business-sdk
 *
 * Or for a local path during development, add to your composer.json:
 *   "repositories": [{"type": "path", "url": "../novapay-sdk"}]
 */

require __DIR__ . '/vendor/autoload.php';

use src\NovaPayClient;

$client = new NovaPayClient();

//  Authentication (two-step with OTP)

$preAuth = $client->preAuthenticate('your_login', 'your_password');

echo "OTP sent. Enter code: ";
$otp = trim(fgets(STDIN));

$auth = $client->authenticate(
    $preAuth->tempPrincipal,
    $preAuth->codeOperationOtp,
    $otp
);

// IMPORTANT: persist $auth->principal and $auth->expiration
// in your storage (file, Redis, DB — your choice).
$principal = $auth->principal;
echo "Authenticated until: {$auth->expiration}\n";

// Refresh session before each use

if (!$auth->isValid()) {
    die("Session expired, re-authenticate.\n");
}

$refreshed = $client->refreshSession($principal);
if ($refreshed) {
    $principal = $refreshed->principal;
    echo "Session refreshed until: {$refreshed->expiration}\n";
}

// Clients & Accounts

$clients = $client->getClients($principal);
echo "Clients: " . count($clients) . "\n";

$clientId = $clients[0]->id;
$accounts = $client->getAccounts($principal, $clientId);

foreach ($accounts as $a) {
    echo "  Account #{$a->id}  {$a->iban}  {$a->currency}  balance={$a->balance}\n";
}

// Balances

// Single account
$bal = $client->getBalance($principal, $accounts[0]->id);
echo sprintf(
    "Balance: confirmed=%.2f  available=%.2f  projected=%.2f\n",
    $bal->confirmed, $bal->available, $bal->projected
);

// All accounts at once (errors are logged, not thrown)
$allBalances = $client->getBalances($principal, $clientId, function (int $id, Throwable $e) {
    echo "  Warning: account #{$id}: {$e->getMessage()}\n";
});

foreach ($allBalances as $b) {
    echo sprintf("  #%d: %.2f / %.2f / %.2f\n", $b->accountId, $b->confirmed, $b->available, $b->projected);
}

//  Extract (transactions)

$from = (new DateTime('today'))->modify('-2 weeks')->format('d.m.Y');
$to   = (new DateTime('today'))->format('d.m.Y');

foreach ($accounts as $acct) {
    $extract = $client->getExtract($principal, $acct->id, $from, $to);

    echo "\n--- Account #{$acct->id} ({$extract->iban}) ---\n";
    echo "Total transactions: " . count($extract->transactions) . "\n";

    foreach ($extract->transactions as $tx) {
        echo sprintf(
            "  %s  %s %s  %s → %s  [%s]\n",
            $tx->date,
            $tx->amount,
            $tx->currency,
            $tx->debitName ?: '—',
            $tx->creditName ?: '—',
            $tx->code
        );
    }

    // Filtering helpers

    // Incoming only
    $incoming = $extract->incoming();
    echo "Incoming: " . count($incoming) . "\n";

    // Outgoing only
    $outgoing = $extract->outgoing();
    echo "Outgoing: " . count($outgoing) . "\n";

    // By EDRPOU
    $byEdrpou = $extract->byCreditEdrpou('12345678');
    echo "By EDRPOU: " . count($byEdrpou) . "\n";

    // By code pattern (e.g. BO-codes)
    $boCodes = $extract->byCodePattern('/^BO\d+$/');
    echo "BO-codes: " . count($boCodes) . "\n";
}
