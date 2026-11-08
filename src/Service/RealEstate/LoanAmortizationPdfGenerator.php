<?php

declare(strict_types=1);

namespace App\Service\RealEstate;

use App\Dto\RealEstate\LoanScheduleDto;
use App\Entity\Loan;
use App\Service\RealEstate\Contract\LoanAmortizationPdfGeneratorInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/** Remote resources stay off: the template is self-contained, nothing is fetched at render time. */
final class LoanAmortizationPdfGenerator implements LoanAmortizationPdfGeneratorInterface
{
    public function __construct(
        private readonly Environment $twig,
    ) {}

    public function generate(Loan $loan, LoanScheduleDto $schedule): string
    {
        $html = $this->twig->render('real_estate/loan/pdf/amortization.html.twig', [
            'loan' => $loan,
            'schedule' => $schedule,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
