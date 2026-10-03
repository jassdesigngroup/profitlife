<?php

namespace App\Domain\Training\Services;

use App\Domain\Settings\Services\Settings;
use App\Domain\Training\Models\TrainingProgram;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * PDF del programa para entregar al cliente.
 */
class ProgramPdf
{
    public function __construct(private readonly Settings $settings) {}

    public function render(TrainingProgram $program): string
    {
        $program->loadMissing(['member', 'staff', 'workouts.exercises.exercise', 'workouts.exercises.sets']);

        return Pdf::loadView('admin.training.pdf', [
            'program' => $program,
            'brand' => $this->settings->brandName(),
        ])->setPaper('letter')->output();
    }

    public function filename(TrainingProgram $program): string
    {
        return 'programa-'.str($program->name)->slug().'.pdf';
    }
}
