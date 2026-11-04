<?php

namespace App\Http\Controllers;

use App\Models\ZakatPeriod;
use App\Services\Export\CollectionReport;
use App\Services\Export\PdfRenderer;
use App\Services\Export\Report;
use App\Services\Export\SummaryReport;
use App\Services\Export\XlsxRenderer;
use App\Services\Export\ZakatListReport;
use App\Services\Export\ZakatPeriodReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloads of the owner's own data as Excel or PDF. The format is part of the URL (`.xlsx` or `.pdf`, enforced by
 * the route); a Report is built from the signed-in owner's data only and drawn by the matching renderer.
 */
class ExportController extends Controller
{
    public function collection(Request $request, string $format, CollectionReport $report): Response
    {
        return $this->download($report->build($request->user()->id, $request->only(['jenis', 'lokasi', 'data', 'toko'])), $format);
    }

    public function summary(Request $request, string $format, SummaryReport $report): Response
    {
        return $this->download($report->build($request->user()->id), $format);
    }

    public function zakatList(Request $request, string $format, ZakatListReport $report): Response
    {
        $periods = ZakatPeriod::where('user_id', $request->user()->id)
            ->orderByDesc('valuation_date')->orderByDesc('created_at')->get();

        return $this->download($report->build($periods), $format);
    }

    public function zakatPeriod(ZakatPeriod $period, string $format, ZakatPeriodReport $report): Response
    {
        Gate::authorize('view', $period);

        return $this->download($report->build($period), $format);
    }

    private function download(Report $report, string $format): Response
    {
        $xlsx = $format === 'xlsx';
        $body = $xlsx ? app(XlsxRenderer::class)->render($report) : app(PdfRenderer::class)->render($report);

        return response($body, 200, [
            'Content-Type' => $xlsx ? XlsxRenderer::MIME : PdfRenderer::MIME,
            'Content-Disposition' => 'attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/', '-', $report->filename).'.'.$format.'"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
