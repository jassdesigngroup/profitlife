<?php

namespace App\Domain\Physiotherapy\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Physiotherapy\Enums\ClinicalAction;
use App\Domain\Physiotherapy\Enums\PlanStatus;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use App\Domain\Physiotherapy\Models\TreatmentPlan;
use App\Domain\Physiotherapy\Services\ClinicalAccess;
use Carbon\CarbonImmutable;

class SaveTreatmentPlan
{
    public function __construct(private readonly ClinicalAccess $access) {}

    /**
     * @param  array{title: string, diagnosis: ?string, goals: ?string, planned_sessions: ?int, starts_on: CarbonImmutable, ends_on: ?CarbonImmutable, status: PlanStatus, is_visible_to_member: bool}  $data
     */
    public function execute(PhysiotherapyRecord $record, ?TreatmentPlan $plan, User $actor, array $data): TreatmentPlan
    {
        $attributes = [
            'title' => $data['title'],
            'diagnosis' => $data['diagnosis'] ?: null,
            'goals' => $data['goals'] ?: null,
            'planned_sessions' => $data['planned_sessions'],
            'starts_on' => $data['starts_on']->toDateString(),
            'ends_on' => $data['ends_on']?->toDateString(),
            'status' => $data['status'],
            'is_visible_to_member' => $data['is_visible_to_member'],
        ];

        if ($plan === null) {
            $plan = TreatmentPlan::query()->create($attributes + [
                'physiotherapy_record_id' => $record->id,
                'staff_id' => $actor->staff->id,
            ]);
            $this->access->log($actor, $record->member_id, $plan, ClinicalAction::Create);
        } else {
            $plan->update($attributes);
            $this->access->log($actor, $record->member_id, $plan, ClinicalAction::Update);
        }

        return $plan;
    }
}
