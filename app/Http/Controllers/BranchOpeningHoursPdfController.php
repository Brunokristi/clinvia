<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class BranchOpeningHoursPdfController extends Controller
{
    public function show(Branch $branch): Response
    {
        $branch->load([
            'openingHours.intervals',
        ]);

        $pdf = $this->makePdf($branch);

        return $pdf->stream($this->fileName($branch));
    }

    public function download(Branch $branch): Response
    {
        $branch->load([
            'openingHours.intervals',
        ]);

        $pdf = $this->makePdf($branch);

        return $pdf->download($this->fileName($branch));
    }

    private function makePdf(Branch $branch)
    {
        return Pdf::loadView('pdf.branch-opening-hours', [
            'branch' => $branch,
            'openingHours' => $branch->openingHours
                ->sortBy('sort_order')
                ->values(),
        ])->setPaper('a4');
    }

    private function fileName(Branch $branch): string
    {
        return Str::slug($branch->name ?: 'pobocka') . '-otvaracie-hodiny.pdf';
    }
}