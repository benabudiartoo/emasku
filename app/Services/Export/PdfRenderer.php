<?php

namespace App\Services\Export;

use Dompdf\Dompdf;
use Dompdf\Options;

/** Draws a Report as an A4 PDF from the `exports.report` view, using dompdf's built-in fonts. */
class PdfRenderer
{
    public const MIME = 'application/pdf';

    public function render(Report $report): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false); // nothing is ever fetched while rendering
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('exports.report', ['report' => $report, 'printed' => now()->format('d-m-Y H:i')])->render());
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return (string) $pdf->output();
    }
}
