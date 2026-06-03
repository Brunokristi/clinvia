<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class BranchServicesPdfController extends Controller
{
    public function show(Branch $branch): Response
    {
        $branch->load([
            'services',
        ]);

        $pdf = $this->makePdf($branch);

        return $pdf->stream($this->fileName($branch));
    }

    public function download(Branch $branch): Response
    {
        $branch->load([
            'services',
        ]);

        $pdf = $this->makePdf($branch);

        return $pdf->download($this->fileName($branch));
    }

    private function makePdf(Branch $branch)
    {
        return Pdf::loadView('pdf.branch-services', [
            'branch' => $branch,
            'services' => $branch->services
                ->sortBy('sort_order')
                ->values(),
        ])->setPaper('a4');
    }

    private function fileName(Branch $branch): string
    {
        return Str::slug($branch->name ?: 'pobocka') . '-sluzby.pdf';
    }
}