<?php

namespace Database\Seeders;

use App\Domain\Appointments\Actions\BookAppointment;
use App\Domain\Appointments\Enums\ServiceCategory;
use App\Domain\Appointments\Models\Service;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\CheckIns\Actions\RegisterCheckIn;
use App\Domain\CheckIns\Enums\CheckInMethod;
use App\Domain\CheckIns\Models\KioskDevice;
use App\Domain\Consents\Enums\ConsentMethod;
use App\Domain\Consents\Enums\ConsentType;
use App\Domain\Consents\Models\Consent;
use App\Domain\Consents\Models\ConsentTemplate;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Enums\DayOfWeek;
use App\Domain\Locations\Enums\RoomType;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Enums\MemberStatus;
use App\Domain\Members\Models\EmergencyContact;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Services\MemberNumber;
use App\Domain\Memberships\Actions\SellMembership;
use App\Domain\Memberships\Enums\AccessScope;
use App\Domain\Memberships\Enums\DurationUnit;
use App\Domain\Memberships\Enums\SessionPeriod;
use App\Domain\Memberships\Enums\VisitLimitPeriod;
use App\Domain\Memberships\Models\MembershipPlan;
use App\Domain\Physiotherapy\Actions\OpenPhysiotherapyRecord;
use App\Domain\Physiotherapy\Actions\RecordPhysiotherapySession;
use App\Domain\Physiotherapy\Actions\SaveTreatmentPlan;
use App\Domain\Physiotherapy\Enums\PlanStatus;
use App\Domain\Physiotherapy\Enums\SessionType;
use App\Domain\Shared\Enums\DocumentType;
use App\Domain\Staff\Enums\StaffStatus;
use App\Domain\Staff\Models\Staff;
use App\Domain\Staff\Models\StaffSchedule;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
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

        // Cliente con cuenta (el portal llega en una fase posterior).
        $memberUser = User::query()->firstOrCreate(['email' => 'cliente@profitlife.test'], [
            'name' => 'Mateo Gómez',
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
        ]);
        $memberUser->syncRoles([RoleName::Member->value]);

        $this->members($cabecera, $provenza, $memberUser);
        $this->consentTemplates();
        $this->plansAndMemberships($cabecera, $provenza);
        $this->checkIns($cabecera, $provenza);
        $this->appointments($cabecera, $provenza);

        Activity::enableLogging();
    }

    private function members(Location $cabecera, Location $provenza, User $memberUser): void
    {
        if (Member::query()->withoutGlobalScopes()->exists()) {
            return;
        }

        $numbers = app(MemberNumber::class);
        $create = function (array $attributes) use ($numbers): Member {
            $member = Member::factory()->create($attributes);
            $member->forceFill(['member_number' => $numbers->for($member->id)])->saveQuietly();

            return $member;
        };

        $mateo = $create([
            'home_location_id' => $cabecera->id,
            'user_id' => $memberUser->id,
            'first_name' => 'Mateo',
            'last_name' => 'Gómez',
            'email' => $memberUser->email,
        ]);
        EmergencyContact::factory()->for($mateo)->create(['name' => 'Lucía Gómez', 'relationship' => 'Madre', 'is_primary' => true]);

        foreach (range(1, 18) as $i) {
            $member = $create(['home_location_id' => $i % 3 === 0 ? $provenza->id : $cabecera->id]);
            EmergencyContact::factory()->for($member)->create(['is_primary' => true]);
        }

        $create(['home_location_id' => $cabecera->id, 'status' => MemberStatus::Inactive]);
        $create(['home_location_id' => $provenza->id, 'status' => MemberStatus::Blocked]);
    }

    private function plansAndMemberships(Location $cabecera, Location $provenza): void
    {
        if (MembershipPlan::query()->exists()) {
            return;
        }

        $plan = fn (array $attributes) => MembershipPlan::query()->create($attributes + [
            'billing_unit' => $attributes['duration_unit'],
            'billing_count' => $attributes['duration_count'],
            'currency' => config('profitlife.currency'),
            'tax_rate_bps' => 0,
            'access_scope' => AccessScope::AllLocations,
            'is_active' => true,
        ]);

        $monthly = $plan(['name' => 'Mensual', 'slug' => 'mensual', 'duration_unit' => DurationUnit::Month, 'duration_count' => 1, 'price_cents' => 15000000, 'enrollment_fee_cents' => 5000000, 'max_freeze_days' => 7, 'auto_renews' => true, 'sort_order' => 1, 'benefits' => ['Acceso libre al área de entrenamiento', 'Valoración inicial']]);
        $plan(['name' => 'Trimestral', 'slug' => 'trimestral', 'duration_unit' => DurationUnit::Month, 'duration_count' => 3, 'price_cents' => 40000000, 'enrollment_fee_cents' => 5000000, 'max_freeze_days' => 15, 'sort_order' => 2]);
        $plan(['name' => 'Anual', 'slug' => 'anual', 'duration_unit' => DurationUnit::Year, 'duration_count' => 1, 'price_cents' => 140000000, 'max_freeze_days' => 30, 'sort_order' => 3]);
        $visits = $plan(['name' => '12 ingresos al mes', 'slug' => '12-ingresos', 'duration_unit' => DurationUnit::Month, 'duration_count' => 1, 'price_cents' => 11000000, 'visit_limit_count' => 12, 'visit_limit_period' => VisitLimitPeriod::Month, 'access_scope' => AccessScope::SelectedLocations, 'sort_order' => 4]);
        $visits->locations()->sync([$cabecera->id]);

        $seller = User::query()->where('email', 'superadmin@profitlife.test')->firstOrFail();
        $sell = app(SellMembership::class);

        Member::query()->withoutGlobalScopes()->where('status', MemberStatus::Active)->orderBy('id')->limit(12)->get()
            ->each(function (Member $member, int $i) use ($sell, $monthly, $visits, $seller, $cabecera, $provenza) {
                $location = $member->home_location_id === $provenza->id ? $provenza : $cabecera;
                $chosen = $i % 4 === 3 && $location->is($cabecera) ? $visits : $monthly;
                $start = BusinessDate::today()->subDays(($i * 3) % 25);
                $paid = $i % 5 !== 4;

                $sell->execute($member, $chosen, $location, $start, $seller, payment: $paid
                    ? ['amount_cents' => $chosen->price_cents + ($chosen->enrollment_fee_cents ?? 0), 'method' => PaymentMethod::Cash, 'reference' => null]
                    : null);
            });
    }

    /**
     * Servicios, disponibilidad de los profesionales, un plan con sesiones,
     * un paquete sin gimnasio y citas de ejemplo para hoy y mañana.
     */
    private function appointments(Location $cabecera, Location $provenza): void
    {
        $service = fn (array $attributes) => Service::query()->create($attributes + [
            'currency' => 'COP', 'tax_rate_bps' => 0, 'buffer_minutes' => 0, 'is_active' => true,
        ]);

        $physio = $service(['name' => 'Fisioterapia', 'slug' => 'fisioterapia', 'category' => ServiceCategory::Physiotherapy, 'duration_minutes' => 60, 'buffer_minutes' => 10, 'price_cents' => 9000000, 'requires_room' => true, 'is_clinical' => true, 'color' => '#FD540D']);
        $training = $service(['name' => 'Entrenamiento personal', 'slug' => 'entrenamiento-personal', 'category' => ServiceCategory::PersonalTraining, 'duration_minutes' => 60, 'price_cents' => 7000000, 'color' => '#1D4ED8']);
        $assessment = $service(['name' => 'Valoración física', 'slug' => 'valoracion-fisica', 'category' => ServiceCategory::Assessment, 'duration_minutes' => 45, 'price_cents' => 5000000, 'color' => '#15803D']);

        foreach ([$physio, $training, $assessment] as $s) {
            $s->locations()->sync([$cabecera->id => ['is_active' => true], $provenza->id => ['is_active' => true]]);
        }
        $physio->locations()->updateExistingPivot($provenza->id, ['price_cents' => 8500000]);

        $fisio = Staff::query()->withoutGlobalScopes()->whereHas('user', fn ($q) => $q->where('email', 'fisio@profitlife.test'))->firstOrFail();
        $trainer = Staff::query()->withoutGlobalScopes()->whereHas('user', fn ($q) => $q->where('email', 'entrenador@profitlife.test'))->firstOrFail();
        $fisio->services()->sync([$physio->id, $assessment->id]);
        $trainer->services()->sync([$training->id, $assessment->id]);

        foreach (range(1, 5) as $day) {
            StaffSchedule::query()->create(['staff_id' => $fisio->id, 'location_id' => $cabecera->id, 'day_of_week' => $day, 'starts_at' => '07:00:00', 'ends_at' => '12:00:00']);
            StaffSchedule::query()->create(['staff_id' => $fisio->id, 'location_id' => $provenza->id, 'day_of_week' => $day, 'starts_at' => '14:00:00', 'ends_at' => '19:00:00']);
            StaffSchedule::query()->create(['staff_id' => $trainer->id, 'location_id' => $provenza->id, 'day_of_week' => $day, 'starts_at' => '06:00:00', 'ends_at' => '13:00:00']);
        }
        StaffSchedule::query()->create(['staff_id' => $trainer->id, 'location_id' => $provenza->id, 'day_of_week' => 6, 'starts_at' => '07:00:00', 'ends_at' => '11:00:00']);

        $withSessions = MembershipPlan::query()->create([
            'name' => 'Mensual + 4 fisioterapias', 'slug' => 'mensual-fisioterapias', 'duration_unit' => DurationUnit::Month, 'duration_count' => 1,
            'billing_unit' => DurationUnit::Month, 'billing_count' => 1, 'price_cents' => 42000000, 'currency' => 'COP',
            'access_scope' => AccessScope::AllLocations, 'max_freeze_days' => 7, 'sort_order' => 5, 'is_active' => true,
        ]);
        $withSessions->planServices()->create(['service_id' => $physio->id, 'sessions_included' => 4, 'period' => SessionPeriod::PerBillingPeriod]);

        $package = MembershipPlan::query()->create([
            'name' => 'Paquete 10 fisioterapias', 'slug' => 'paquete-10-fisioterapias', 'duration_unit' => DurationUnit::Month, 'duration_count' => 3,
            'billing_unit' => DurationUnit::Month, 'billing_count' => 3, 'price_cents' => 75000000, 'currency' => 'COP',
            'access_scope' => AccessScope::AllLocations, 'includes_gym_access' => false, 'sort_order' => 6, 'is_active' => true,
        ]);
        $package->planServices()->create(['service_id' => $physio->id, 'sessions_included' => 10, 'period' => SessionPeriod::PerTerm]);

        $seller = User::query()->where('email', 'superadmin@profitlife.test')->firstOrFail();
        $members = Member::query()->withoutGlobalScopes()->where('status', MemberStatus::Active)->orderBy('id')->skip(12)->limit(3)->get();
        if ($members->count() < 2) {
            return;
        }

        app(SellMembership::class)->execute($members[0], $package, $cabecera, BusinessDate::today(), $seller, payment: ['amount_cents' => $package->price_cents, 'method' => PaymentMethod::Transfer, 'reference' => null]);

        // Citas en el próximo día hábil (para que la agenda de ejemplo no quede vacía).
        $day = BusinessDate::today()->addDay();
        while ((int) $day->format('N') > 5) {
            $day = $day->addDay();
        }
        $at = fn (string $time) => CarbonImmutable::parse($day->toDateString().' '.$time, $cabecera->timezone);
        $book = app(BookAppointment::class);
        $book->execute($members[0], $physio->fresh('locations'), $cabecera, $fisio, $at('08:00')->utc(), $seller, notify: false);
        $book->execute($members[1], $physio->fresh('locations'), $cabecera, $fisio, $at('10:00')->utc(), $seller, notify: false);
        $book->execute($members[1], $training->fresh('locations'), $provenza, $trainer, CarbonImmutable::parse($day->toDateString().' 07:00', $provenza->timezone)->utc(), $seller, notify: false);

        $this->clinicalRecord($members[0], $fisio, $cabecera);
    }

    /**
     * Historia clínica de ejemplo: consentimiento, evaluación firmada, plan y
     * dos sesiones (la última sin firmar).
     */
    private function clinicalRecord(Member $member, Staff $fisio, Location $location): void
    {
        $user = $fisio->user;
        $template = ConsentTemplate::query()->where('type', ConsentType::ClinicalTreatment)->where('is_active', true)->first();
        if ($template !== null) {
            Consent::query()->create([
                'member_id' => $member->id, 'consent_template_id' => $template->id, 'method' => ConsentMethod::Paper,
                'signed_name' => $member->full_name, 'accepted_at' => now()->subDays(10), 'captured_by' => $user->id,
            ]);
        }

        $record = app(OpenPhysiotherapyRecord::class)->execute($member, $user, [
            'reason_for_consultation' => 'Dolor en rodilla derecha al correr desde hace 3 semanas.',
            'medical_history' => 'Sin cirugías. Esguince de tobillo derecho en 2023.',
            'medications' => 'Ninguno',
            'allergies' => 'Ninguna conocida',
        ]);

        $plan = app(SaveTreatmentPlan::class)->execute($record, null, $user, [
            'title' => 'Rehabilitación de rodilla derecha', 'diagnosis' => 'Síndrome de dolor patelofemoral derecho.',
            'goals' => 'Disminuir el dolor a 2/10 y volver a correr 5 km en 6 semanas.', 'planned_sessions' => 10,
            'starts_on' => BusinessDate::today()->subDays(7), 'ends_on' => null, 'status' => PlanStatus::Active, 'is_visible_to_member' => true,
        ]);

        $session = fn (SessionType $type, int $daysAgo, int $pain, array $sections) => app(RecordPhysiotherapySession::class)->execute($record, $user, [
            'session_type' => $type, 'performed_at' => CarbonImmutable::now()->subDays($daysAgo), 'pain_scale' => $pain,
            'treatment_plan_id' => $plan->id, 'appointment_id' => null, 'location_id' => $location->id,
            'summary_for_member' => 'Continúe con los ejercicios en casa dos veces al día.', 'sections' => $sections,
        ]);

        $evaluation = $session(SessionType::InitialEvaluation, 7, 7, [
            'subjective' => 'Dolor anterior de rodilla al subir escaleras y correr.',
            'objective' => 'Dolor a la palpación del borde lateral de la rótula. Debilidad de glúteo medio.',
            'assessment' => 'Compatible con síndrome patelofemoral.',
            'plan' => 'Fortalecimiento de cadera y cuádriceps, terapia manual, control de carga.',
        ]);
        $evaluation->forceFill(['signed_at' => now()->subDays(7), 'signed_by' => $fisio->id])->save();

        $treatment = $session(SessionType::Treatment, 3, 5, ['subjective' => 'Menos dolor en escaleras.', 'plan' => 'Progresar ejercicios excéntricos.']);
        $treatment->forceFill(['signed_at' => now()->subDays(3), 'signed_by' => $fisio->id])->save();

        $session(SessionType::Treatment, 0, 4, ['subjective' => 'Corrió 2 km sin dolor.', 'objective' => 'Mejor control de valgo dinámico.']);
    }

    /**
     * Un kiosco por sede (sin vincular) e ingresos de ejemplo de hoy por recepción.
     */
    private function checkIns(Location $cabecera, Location $provenza): void
    {
        KioskDevice::query()->firstOrCreate(['location_id' => $cabecera->id, 'name' => 'Tablet entrada']);
        KioskDevice::query()->firstOrCreate(['location_id' => $provenza->id, 'name' => 'Tablet entrada']);

        $reception = User::query()->where('email', 'recepcion@profitlife.test')->firstOrFail();
        $register = app(RegisterCheckIn::class);

        Member::query()->withoutGlobalScopes()->orderBy('id')->limit(14)->get()
            ->each(function (Member $member) use ($register, $reception, $cabecera, $provenza) {
                $location = $member->home_location_id === $provenza->id ? $provenza : $cabecera;
                $register->execute($location, CheckInMethod::Manual, $member, null, $reception);
            });
    }

    /**
     * Textos de ejemplo para desarrollo. En producción el texto legal lo
     * redacta y publica el centro desde el panel.
     */
    private function consentTemplates(): void
    {
        $templates = [
            [ConsentType::DataProcessing, 'Autorización de tratamiento de datos personales', 'BORRADOR DE EJEMPLO. Autorizo al centro a recolectar, almacenar y usar mis datos personales para la prestación de sus servicios, conforme a la Ley 1581 de 2012 y su política de tratamiento de datos. Conozco mis derechos a conocer, actualizar, rectificar y suprimir mis datos y a revocar esta autorización.'],
            [ConsentType::ClinicalTreatment, 'Consentimiento informado de fisioterapia', 'BORRADOR DE EJEMPLO. Declaro que me explicaron el objetivo, los beneficios y los posibles riesgos de la evaluación y el tratamiento de fisioterapia, y que acepto recibirlos.'],
            [ConsentType::ImageUse, 'Autorización de uso de imagen', 'BORRADOR DE EJEMPLO. Autorizo el uso de fotografías y videos en los que aparezca, tomados en las instalaciones, para fines de comunicación del centro.'],
            [ConsentType::Liability, 'Declaración de aptitud y exoneración', 'BORRADOR DE EJEMPLO. Declaro que me encuentro en condiciones de realizar actividad física y que informaré al personal sobre cualquier condición de salud relevante.'],
        ];

        foreach ($templates as [$type, $title, $body]) {
            ConsentTemplate::query()->firstOrCreate(['type' => $type, 'version' => 1], ['title' => $title, 'body' => $body, 'is_active' => true]);
        }
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
