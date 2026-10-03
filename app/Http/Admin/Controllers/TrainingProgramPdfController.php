<?php

namespace App\Http\Admin\Controllers;

use App\Domain\Training\Models\TrainingProgram;
use App\Domain\Training\Services\ProgramPdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Descarga el programa en PDF para imprimir o entregar al cliente.
 */
class TrainingProgramPdfController
{
    public function __invoke(TrainingProgram $program, ProgramPdf $pdf): Response
    {
        Gate::authorize('view', $program);

        return response($pdf->render($program), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf->filename($program).'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
