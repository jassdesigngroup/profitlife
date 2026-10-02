<?php

namespace App\Http\Admin\Controllers;

use App\Domain\Members\Models\Member;
use App\Domain\Physiotherapy\Actions\SignClinicalNote;
use App\Domain\Physiotherapy\Enums\ClinicalAction;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use App\Domain\Physiotherapy\Services\ClinicalAccess;
use App\Domain\Settings\Services\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exporta la historia clínica completa en PDF. Exige el permiso de
 * exportación y acceso al contenido; queda en la bitácora clínica.
 */
class ClinicalRecordPdfController
{
    public function __invoke(Request $request, Member $member, ClinicalAccess $access, Settings $settings): Response
    {
        $record = PhysiotherapyRecord::query()->where('member_id', $member->id)->firstOrFail();
        Gate::authorize('export', $record);

        $access->log($request->user(), $member->id, $record, ClinicalAction::Export);

        $record->load([
            'primaryStaff', 'activeTeam.staff',
            'plans' => fn ($q) => $q->with('staff')->oldest('starts_on'),
            'sessions' => fn ($q) => $q->reorder()->oldest('performed_at')->with([
                'staff', 'location', 'plan',
                'notes' => fn ($n) => $n->whereNull('parent_note_id')->with(['signer', 'author', 'addenda.author']),
            ]),
        ]);

        $pdf = Pdf::loadView('admin.clinical.pdf', [
            'record' => $record,
            'member' => $member,
            'brand' => $settings->brandName(),
            'tz' => $settings->displayTimezone(),
            'hasConsent' => SignClinicalNote::hasClinicalConsent($member->id),
            'exportedBy' => $request->user()->name,
        ])->setPaper('letter');

        return $pdf->download('historia-clinica-'.$member->member_number.'.pdf')
            ->setPrivate()
            ->setMaxAge(0);
    }
}
