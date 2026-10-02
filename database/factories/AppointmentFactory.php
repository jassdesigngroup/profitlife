<?php

namespace Database\Factories;

use App\Domain\Appointments\Enums\AppointmentSource;
use App\Domain\Appointments\Enums\AppointmentStatus;
use App\Domain\Appointments\Models\Appointment;
use App\Domain\Appointments\Models\Service;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Models\Member;
use App\Domain\Staff\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $start = now()->addDay()->setTime(10, 0);

        return [
            'member_id' => Member::factory(),
            'staff_id' => Staff::factory(),
            'service_id' => Service::factory(),
            'location_id' => Location::factory(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'status' => AppointmentStatus::Confirmed,
            'source' => AppointmentSource::Admin,
        ];
    }
}
