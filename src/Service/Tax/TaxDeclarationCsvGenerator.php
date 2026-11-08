<?php

declare(strict_types=1);

namespace App\Service\Tax;

use App\Dto\RealEstate\LoanChargesDto;
use App\Dto\Tax\TaxBucketDto;
use App\Dto\Tax\TaxCategoryLineDto;
use App\Dto\Tax\TaxSummaryDto;
use App\Service\Tax\Contract\TaxDeclarationCsvGeneratorInterface;

/**
 * Semicolon-separated, BOM-prefixed so Excel opens accents correctly. Bases stay per bucket:
 * no global total is written, as on the screen.
 */
final class TaxDeclarationCsvGenerator implements TaxDeclarationCsvGeneratorInterface
{
    public function generate(TaxSummaryDto $summary, LoanChargesDto $loanCharges): string
    {
        $rows = [['Bloc', 'Catégorie fiscale', 'Rôle', 'Libellé', 'Montant (' . $summary->currency->value . ')', 'Mouvements']];

        foreach ($summary->buckets as $bucket) {
            array_push($rows, ...$this->bucketRows($bucket));
        }

        $outside = 'Sans rattachement fiscal complet';
        $rows[] = ['Hors périmètre', '', 'Revenu', $outside, $summary->outsideScopeIncome, ''];
        $rows[] = ['Hors périmètre', '', 'Charge', $outside, $summary->outsideScopeExpense, ''];
        $rows[] = ['Hors périmètre', '', 'Mouvements', $outside, '', $summary->outsideScopeCount];

        foreach ($loanCharges->perLoan as $loanYear) {
            $rows[] = [
                'Emprunts',
                '',
                'Charge déductible',
                $loanYear->loan->getBankName() . ' — ' . $loanYear->loan->getProperty()->getName(),
                $loanYear->deductiblePart(),
                $loanYear->instalmentCount,
            ];
        }
        $rows[] = ['Emprunts', '', 'Total', 'Intérêts + assurance', $loanCharges->deductibleTotal(), ''];

        $rows[] = ['Avertissement', '', '', 'Taux d\'abattement à vérifier chaque année. Plafonds d\'éligibilité non contrôlés. Document de travail, pas une déclaration officielle.', '', ''];

        return "\xEF\xBB\xBF" . $this->toCsv($rows);
    }

    /** @return list<list<string|int>> */
    private function bucketRows(TaxBucketDto $bucket): array
    {
        $category = $bucket->bucket->label();
        $rows = [];

        foreach ($bucket->revenus as $line) {
            $rows[] = $this->lineRow($category, 'Revenu', $line);
        }
        foreach ($bucket->charges as $line) {
            $rows[] = $this->lineRow($category, 'Charge', $line);
        }

        $rows[] = ['Assiette', $category, 'Total', 'Revenus', $bucket->revenusTotal, ''];
        $rows[] = ['Assiette', $category, 'Total', 'Charges', $bucket->chargesTotal, ''];
        $rows[] = ['Assiette', $category, 'Base', 'Au réel', $bucket->reelBase(), ''];

        if ($bucket->microBase() !== null) {
            $rows[] = ['Assiette', $category, 'Base', 'Micro (après abattement)', $bucket->microBase(), ''];
        }

        return $rows;
    }

    /** @return list<string|int> */
    private function lineRow(string $category, string $role, TaxCategoryLineDto $line): array
    {
        return ['Assiette', $category, $role, $line->categoryName, $line->total, $line->transactionCount];
    }

    /** @param list<list<string|int>> $rows */
    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, array_map(self::neutralise(...), $row), ';', '"', '\\');
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv . "\r\n";
    }

    /** Stops a user-typed category name from being read as a spreadsheet formula. */
    private static function neutralise(string|int $value): string|int
    {
        if (is_string($value) && $value !== '' && str_contains('=+-@', $value[0])) {
            return "'" . $value;
        }

        return $value;
    }
}
