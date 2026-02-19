<?php
declare(strict_types=1);

namespace src;

use DateTime;
use NovaPay\DTO;
use SimpleXMLElement;

/**
 * Parses the raw XML returned by GetAccountExtract into DTO objects.
 *
 * Can also be used standalone if you obtained XML via getExtractRaw().
 */
class ExtractParser
{
    /** Parse a SimpleXMLElement extract into an Extract DTO. */
    public static function parse(SimpleXMLElement $xml): DTO\Extract
    {
        $first = $xml->ExtractHead->GetExtractForXML;
        $first = is_array($first) ? $first[0] : $first;
        $iban  = (string)$first->IBAN;

        $docs         = $xml->xpath('//GetExtractForXML/Docs') ?: [];
        $transactions = [];

        foreach ($docs as $d) {
            $rawDate = (string)$d->PayDate ?: (string)$d->OrgDate;
            $dt = DateTime::createFromFormat('d.m.Y', $rawDate);
            if (!$dt) {
                continue;
            }

            $transactions[] = new DTO\Transaction(
                trim((string)$d->Code),
                $dt->format('Y-m-d'),
                $rawDate,
                (string)$d['Amount'],
                (string)$d['CurrencyTag'],
                (string)$d->Purpose,
                (string)($d->PaymentType ?? ''),
                (string)$d->DebitName,
                (string)$d->DebitCodeIBAN,
                (string)$d->DebitStateCode,
                (string)$d->CreditName,
                (string)$d->CreditCodeIBAN,
                (string)$d->CreditStateCode
            );
        }

        return new DTO\Extract($iban, $transactions);
    }

    /** Parse a raw XML string into an Extract DTO. */
    public static function parseString(string $xmlString): DTO\Extract
    {
        $xml = simplexml_load_string($xmlString);
        if (!$xml) {
            throw new \RuntimeException('Failed to parse extract XML string');
        }
        return self::parse($xml);
    }
}
