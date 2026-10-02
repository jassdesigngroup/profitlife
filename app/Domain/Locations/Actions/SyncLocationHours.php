<?php

namespace App\Domain\Locations\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\DTOs\HourSlot;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\LocationHour;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reemplaza el horario semanal de una sede. Admite varias franjas por día
 * (jornada partida) siempre que no se solapen.
 */
class SyncLocationHours
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<HourSlot>  $slots
     */
    public function execute(Location $location, array $slots, User $actor): void
    {
        $this->assertValid($slots);

        DB::transaction(function () use ($location, $slots, $actor) {
            $old = $this->snapshot($location);

            $location->hours()->delete();

            foreach ($slots as $slot) {
                $location->hours()->create([
                    'day_of_week' => $slot->day,
                    'opens_at' => $slot->opensAt,
                    'closes_at' => $slot->closesAt,
                ]);
            }

            $new = $this->snapshot($location);

            if ($old !== $new) {
                $this->audit->log('locations', AuditEvent::HoursUpdated, $location, $actor, [
                    'old' => $old,
                    'attributes' => $new,
                ]);
            }
        });
    }

    /**
     * @param  list<HourSlot>  $slots
     */
    private function assertValid(array $slots): void
    {
        $byDay = [];

        foreach ($slots as $i => $slot) {
            if ($slot->opensAt >= $slot->closesAt) {
                throw ValidationException::withMessages([
                    "slots.{$i}.closes_at" => 'La hora de cierre debe ser posterior a la de apertura.',
                ]);
            }

            $byDay[$slot->day->value][] = [$i, $slot];
        }

        foreach ($byDay as $daySlots) {
            usort($daySlots, fn ($a, $b) => strcmp($a[1]->opensAt, $b[1]->opensAt));

            for ($j = 1; $j < count($daySlots); $j++) {
                if ($daySlots[$j][1]->opensAt < $daySlots[$j - 1][1]->closesAt) {
                    throw ValidationException::withMessages([
                        "slots.{$daySlots[$j][0]}.opens_at" => "Las franjas del {$daySlots[$j][1]->day->label()} se solapan.",
                    ]);
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function snapshot(Location $location): array
    {
        return $location->hours()->get()
            ->map(fn (LocationHour $h) => $h->day_of_week->value.' '.substr($h->opens_at, 0, 5).'-'.substr($h->closes_at, 0, 5))
            ->all();
    }
}
