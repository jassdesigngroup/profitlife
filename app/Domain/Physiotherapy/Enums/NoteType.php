<?php

namespace App\Domain\Physiotherapy\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum NoteType: string
{
    use HasLabel;

    case Evaluation = 'evaluation';
    case Progress = 'progress';
    case Soap = 'soap';
    case Addendum = 'addendum';

    public function label(): string
    {
        return match ($this) {
            self::Evaluation => 'Evaluación inicial',
            self::Progress => 'Evolución',
            self::Soap => 'Nota SOAP',
            self::Addendum => 'Adenda',
        };
    }

    /**
     * Secciones de la plantilla: clave => [título, ayuda].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public function sections(): array
    {
        return match ($this) {
            self::Evaluation => [
                'subjective' => ['Anamnesis', 'Relato del paciente, inicio y evolución de los síntomas.'],
                'objective' => ['Examen físico', 'Inspección, palpación, rangos de movimiento, fuerza, pruebas especiales.'],
                'assessment' => ['Diagnóstico fisioterapéutico', 'Impresión diagnóstica y deficiencias encontradas.'],
                'plan' => ['Plan', 'Objetivos, intervenciones y frecuencia propuestas.'],
            ],
            self::Addendum => [
                'text' => ['Adenda', 'Aclaración o corrección de la nota firmada.'],
            ],
            default => [
                'subjective' => ['S · Subjetivo', 'Lo que refiere el paciente.'],
                'objective' => ['O · Objetivo', 'Lo observado y medido en la sesión.'],
                'assessment' => ['A · Análisis', 'Interpretación y evolución.'],
                'plan' => ['P · Plan', 'Intervenciones realizadas y próximos pasos.'],
            ],
        };
    }
}
