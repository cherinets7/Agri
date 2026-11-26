<?php

namespace App\Http\Controllers;

use App\Enums\Level;
use App\Exports\AnnualReportExport;
use App\Models\Location;
use App\Services\AnnualReportService;
use App\Support\EthiopianDate;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;

class AnnualReportController extends Controller
{
    public function index(Request $request, AnnualReportService $service)
    {
        $user = $request->user();
        $defaultYear = EthiopianDate::fiscalYear(today());
        $year = $request->integer('year') ?: $defaultYear;
        $woredaId = $user->isWoreda() ? $user->location_id : ($user->isZone() ? ($request->integer('woreda') ?: null) : null);

        $data = $service->compile($user, $year, $woredaId);

        return view('annual.index', [
            ...$data,
            'years' => range($defaultYear - 2, $defaultYear + 1),
            'woredas' => $user->isZone() ? Location::query()->ofLevel(Level::Woreda)->orderBy('name_am')->get() : collect(),
            'woredaId' => $woredaId,
        ]);
    }

    public function excel(Request $request, AnnualReportService $service)
    {
        $user = $request->user();
        $year = $request->integer('year') ?: EthiopianDate::fiscalYear(today());
        $woredaId = $user->isWoreda() ? $user->location_id : ($user->isZone() ? ($request->integer('woreda') ?: null) : null);
        $data = $service->compile($user, $year, $woredaId);

        return Excel::download(new AnnualReportExport($data), "ዓመታዊ-ሪፖርት-{$year}.xlsx");
    }

    public function pdf(Request $request, AnnualReportService $service)
    {
        $user = $request->user();
        $year = $request->integer('year') ?: EthiopianDate::fiscalYear(today());
        $woredaId = $user->isWoreda() ? $user->location_id : ($user->isZone() ? ($request->integer('woreda') ?: null) : null);
        $data = $service->compile($user, $year, $woredaId);

        $html = view('annual.pdf', $data)->render();

        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'default_font' => 'abyssinicasil',
        ]);
        $mpdf->WriteHTML($html);
        $binary = $mpdf->Output("annual-report-{$year}.pdf", 'S');

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"ዓመታዊ-ሪፖርት-{$year}.pdf\"",
        ]);
    }
}
