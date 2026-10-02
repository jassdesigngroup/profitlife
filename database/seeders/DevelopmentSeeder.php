<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Enums\DayOfWeek;
use App\Domain\Locations\Enums\RoomType;
use App\Domain\Locations\Models\Location;
use App\Domain\Shared\Enums\DocumentType;
use App\Domain\Staff\Enums\StaffStatus;
use App\Domain\Staff\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Facades\Activity;

/**
 * Datos de ejemplo para desarrollo local. Idempotente (busca por email,
 * slug o nombre antes de crear). Nunca se ejecuta en producción.
 *
 * Contraseña de todos los usuarios: Password123
 */
class DevelopmentSeeder extends Seeder
{
    public const PASSWORD = 'Password123';

    public function run(): void
    {
        Activity::disableLogging();

        $cabecera = $this->location([
            'name' => 'Sede Cabecera',
            'slug' => 'cabecera',
            'code' => 'CAB',
            'address_line' => 'Carrera 35 # 48-20',
            'city' => 'Bucaramanga',
            'department' => 'Santander',
            'phone' => '6076431020',
            'email' => 'cabecera@example.com',
        ], rooms: [
            ['name' => 'Consultorio 1', 'type' => RoomType::ConsultingRoom, 'capacity' => 1],
            ['name' => 'Consultorio 2', 'type' => RoomType::ConsultingRoom, 'capacity' => 1],
            ['name' => 'Sala funcional', 'type' => RoomType::Room, 'capacity' => 12],
            ['name' => 'Zona de pesas', 'type' => RoomType::Zone, 'capacity' => 30],
        ]);

        $provenza = $this->location([
            'name' => 'Sede Provenza',
            'slug' => 'provenza',
            'code' => 'PRO',
            'address_line' => 'Calle 105 # 22-15',
            'city' => 'Bucaramanga',
            'department' => 'Santander',
            'phone' => '6076358840',
            'email' => 'provenza@example.com',
        ], rooms: [
            ['name' => 'Consultorio 1', 'type' => RoomType::ConsultingRoom, 'capacity' => 1],
            ['name' => 'Sala de rehabilitación', 'type' => RoomType::Room, 'capacity' => 6],
            ['name' => 'Zona cardio', 'type' => RoomType::Zone, 'capacity' => 20],
        ]);

        $both = [$cabecera, $provenza];

        $this->staff('superadmin@profitlife.test', 'Sofía', 'Rangel', RoleName::SuperAdmin, $both, 'Dirección general');
        $this->staff('admin@profitlife.test', 'Andrés', 'Díaz', RoleName::Admin, $both, 'Administrador');
        $this->staff('gerente.cabecera@profitlife.test', 'Laura', 'Méndez', RoleName::LocationManager, [$cabecera], 'Gerente de sede');
        $this->staff('gerente.provenza@profitlife.test', 'Camilo', 'Ortiz', RoleName::LocationManager, [$provenza], 'Gerente de sede');
        $this->staff('recepcion@profitlife.test', 'Valentina', 'Suárez', RoleName::Reception, [$cabecera], 'Recepcionista');
        $this->staff('fisio@profitlife.test', 'Daniel', 'Pinzón', RoleName::Physiotherapist, [$cabecera, $provenza], 'Fisioterapeuta', bookable: true, license: 'TP-123456');
        $this->staff('entrenador@profitlife.test', 'Juliana', 'Rueda', RoleName::Trainer, [$provenza], 'Entrenadora', bookable: true);

        // Cliente: solo usuario; el perfil `members` llega en la Fase 3.
        $member = User::query()->firstOrCreate(['email' => 'cliente@profitlife.test'], [
            'name' => 'Mateo Gómez',
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
        ]);
        $member->syncRoles([RoleName::Member->value]);

        Activity::enableLogging();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $rooms
     */
    private function location(array $attributes, array $rooms): Location
    {
        $location = Location::query()->firstOrCreate(['slug' => $attributes['slug']], $attributes + [
            'timezone' => config('profitlife.display_timezone'),
        ]);

        if (! $location->hours()->exists()) {
            foreach ([DayOfWeek::Monday, DayOfWeek::Tuesday, DayOfWeek::Wednesday, DayOfWeek::Thursday, DayOfWeek::Friday] as $day) {
                // Jornada partida entre semana.
                $location->hours()->create(['day_of_week' => $day, 'opens_at' => '05:00', 'closes_at' => '12:00']);
                $location->hours()->create(['day_of_week' => $day, 'opens_at' => '14:00', 'closes_at' => '21:30']);
            }
            $location->hours()->create(['day_of_week' => DayOfWeek::Saturday, 'opens_at' => '07:00', 'closes_at' => '13:00']);
        }

        foreach ($rooms as $room) {
            $location->rooms()->firstOrCreate(['name' => $room['name']], $room);
        }

        return $location;
    }

    /**
     * @param  list<Location>  $locations
     */
    private function staff(
        string $email,
        string $firstName,
        string $lastName,
        RoleName $role,
        array $locations,
        string $jobTitle,
        bool $bookable = false,
        ?string $license = null,
    ): Staff {
        $user = User::query()->firstOrCreate(['email' => $email], [
            'name' => "{$firstName} {$lastName}",
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
        ]);
        $user->syncRoles([$role->value]);

        $staff = Staff::query()->withoutGlobalScopes()->firstOrCreate(['user_id' => $user->id], [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'document_type' => DocumentType::CitizenshipCard,
            'document_number' => (string) random_int(1_000_000_000, 1_099_999_999),
            'phone' => '3'.random_int(100_000_000, 199_999_999),
            'job_title' => $jobTitle,
            'professional_license' => $license,
            'calendar_color' => sprintf('#%06X', random_int(0, 0xFFFFFF)),
            'is_bookable' => $bookable,
            'status' => StaffStatus::Active,
            'hired_on' => now()->subYear()->toDateString(),
        ]);

        $sync = [];
        foreach ($locations as $i => $location) {
            $sync[$location->id] = ['is_primary' => $i === 0];
        }
        $staff->locations()->sync($sync);

        return $staff;
    }
}
