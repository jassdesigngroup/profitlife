<?php

namespace App\Domain\Identity\Enums;

use App\Domain\Shared\Enums\HasLabel;

/**
 * Catálogo de permisos con formato `recurso.accion`.
 *
 * Incluye los permisos de los módulos futuros para que la matriz de roles
 * quede completa desde la Fase 2. Añadir un caso aquí y volver a ejecutar
 * RolesAndPermissionsSeeder lo crea en la base de datos.
 */
enum Permission: string
{
    use HasLabel;

    // Panel
    case AdminAccess = 'admin.access';
    case DashboardView = 'dashboard.view';

    // Sedes
    case LocationsView = 'locations.view';
    case LocationsViewAll = 'locations.view-all';
    case LocationsCreate = 'locations.create';
    case LocationsUpdate = 'locations.update';
    case LocationsToggleStatus = 'locations.toggle-status';
    case LocationsManageHours = 'locations.manage-hours';
    case LocationsManageClosures = 'locations.manage-closures';

    // Salas
    case RoomsView = 'rooms.view';
    case RoomsCreate = 'rooms.create';
    case RoomsUpdate = 'rooms.update';
    case RoomsDelete = 'rooms.delete';

    // Staff
    case StaffView = 'staff.view';
    case StaffCreate = 'staff.create';
    case StaffUpdate = 'staff.update';
    case StaffToggleStatus = 'staff.toggle-status';
    case StaffInvite = 'staff.invite';

    // Usuarios, roles y permisos
    case UsersManageRoles = 'users.manage-roles';
    case UsersManagePermissions = 'users.manage-permissions';
    case RolesView = 'roles.view';

    // Auditoría y ajustes
    case AuditView = 'audit.view';
    case SettingsView = 'settings.view';
    case SettingsUpdate = 'settings.update';

    // Clientes (Fase 3)
    case MembersView = 'members.view';
    case MembersCreate = 'members.create';
    case MembersUpdate = 'members.update';
    case MembersDelete = 'members.delete';
    case MembersExport = 'members.export';
    case ConsentTemplatesManage = 'consent-templates.manage';

    // Membresías (Fase 4)
    case MembershipsView = 'memberships.view';
    case MembershipsCreate = 'memberships.create';
    case MembershipsUpdate = 'memberships.update';
    case MembershipsFreeze = 'memberships.freeze';
    case MembershipsCancel = 'memberships.cancel';
    case MembershipsManagePlans = 'memberships.manage-plans';

    // Check-in (Fase 5)
    case CheckInsView = 'check-ins.view';
    case CheckInsCreate = 'check-ins.create';
    case CheckInsOverride = 'check-ins.override';

    // Citas (Fase 6)
    case AppointmentsView = 'appointments.view';
    case AppointmentsViewAll = 'appointments.view-all';
    case AppointmentsCreate = 'appointments.create';
    case AppointmentsUpdate = 'appointments.update';
    case AppointmentsCancel = 'appointments.cancel';
    case ServicesManage = 'services.manage';
    case SessionCreditsAdjust = 'session-credits.adjust';

    // Notas clínicas (Fase 7) — permisos clínicos
    case ClinicalNotesView = 'clinical-notes.view';
    case ClinicalNotesCreate = 'clinical-notes.create';
    case ClinicalNotesUpdate = 'clinical-notes.update';
    case ClinicalNotesSign = 'clinical-notes.sign';
    case ClinicalNotesExport = 'clinical-notes.export';
    case ClinicalNotesEmergency = 'clinical-notes.emergency';

    // Entrenamiento (Fase 8)
    case TrainingView = 'training.view';
    case TrainingCreate = 'training.create';
    case TrainingUpdate = 'training.update';
    case TrainingDelete = 'training.delete';

    // Evaluaciones (Fase 9)
    case AssessmentsView = 'assessments.view';
    case AssessmentsCreate = 'assessments.create';
    case AssessmentsUpdate = 'assessments.update';
    case AssessmentsDelete = 'assessments.delete';

    // Pagos (Fases 4 y 10)
    case PaymentsView = 'payments.view';
    case PaymentsCreate = 'payments.create';
    case PaymentsRefund = 'payments.refund';
    case PaymentsVoid = 'payments.void';
    case PaymentsDiscount = 'payments.discount';

    // Reportes
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';

    public function resource(): string
    {
        return explode('.', $this->value, 2)[0];
    }

    public function isClinical(): bool
    {
        return $this->resource() === 'clinical-notes';
    }

    public static function resourceLabel(string $resource): string
    {
        return match ($resource) {
            'admin' => 'Panel',
            'dashboard' => 'Inicio',
            'locations' => 'Sedes',
            'rooms' => 'Salas',
            'staff' => 'Staff',
            'users' => 'Usuarios',
            'roles' => 'Roles',
            'audit' => 'Auditoría',
            'settings' => 'Ajustes',
            'members' => 'Clientes',
            'consent-templates' => 'Plantillas de consentimiento',
            'memberships' => 'Membresías',
            'check-ins' => 'Check-in',
            'appointments' => 'Citas',
            'services' => 'Servicios',
            'session-credits' => 'Sesiones incluidas',
            'clinical-notes' => 'Notas clínicas',
            'training' => 'Entrenamiento',
            'assessments' => 'Evaluaciones',
            'payments' => 'Pagos',
            'reports' => 'Reportes',
            default => $resource,
        };
    }

    /**
     * @return array<string, list<self>> recurso => permisos
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $case) {
            $groups[$case->resource()][] = $case;
        }

        return $groups;
    }

    /**
     * @return list<self>
     */
    public static function clinical(): array
    {
        return array_values(array_filter(self::cases(), fn (self $p) => $p->isClinical()));
    }

    public function label(): string
    {
        return match ($this) {
            self::AdminAccess => 'Acceder al panel administrativo',
            self::DashboardView => 'Ver el inicio',
            self::LocationsView => 'Ver sedes',
            self::LocationsViewAll => 'Ver todas las sedes',
            self::LocationsCreate => 'Crear sedes',
            self::LocationsUpdate => 'Editar sedes',
            self::LocationsToggleStatus => 'Activar o desactivar sedes',
            self::LocationsManageHours => 'Gestionar horarios',
            self::LocationsManageClosures => 'Gestionar cierres y festivos',
            self::RoomsView => 'Ver salas',
            self::RoomsCreate => 'Crear salas',
            self::RoomsUpdate => 'Editar salas',
            self::RoomsDelete => 'Eliminar salas',
            self::StaffView => 'Ver staff',
            self::StaffCreate => 'Crear staff',
            self::StaffUpdate => 'Editar staff',
            self::StaffToggleStatus => 'Activar o desactivar staff',
            self::StaffInvite => 'Reenviar invitaciones',
            self::UsersManageRoles => 'Asignar roles',
            self::UsersManagePermissions => 'Cambiar permisos de los roles',
            self::RolesView => 'Ver roles y permisos',
            self::AuditView => 'Ver auditoría',
            self::SettingsView => 'Ver ajustes',
            self::SettingsUpdate => 'Editar ajustes',
            self::MembersView => 'Ver clientes',
            self::MembersCreate => 'Crear clientes',
            self::MembersUpdate => 'Editar clientes',
            self::MembersDelete => 'Eliminar clientes',
            self::MembersExport => 'Exportar clientes',
            self::ConsentTemplatesManage => 'Gestionar plantillas de consentimiento',
            self::MembershipsView => 'Ver membresías',
            self::MembershipsCreate => 'Vender membresías',
            self::MembershipsUpdate => 'Editar membresías',
            self::MembershipsFreeze => 'Congelar membresías',
            self::MembershipsCancel => 'Cancelar membresías',
            self::MembershipsManagePlans => 'Gestionar planes',
            self::CheckInsView => 'Ver check-ins',
            self::CheckInsCreate => 'Registrar check-in manual',
            self::CheckInsOverride => 'Autorizar ingreso rechazado',
            self::AppointmentsView => 'Ver citas propias',
            self::AppointmentsViewAll => 'Ver la agenda de todos',
            self::AppointmentsCreate => 'Agendar citas',
            self::AppointmentsUpdate => 'Editar citas',
            self::AppointmentsCancel => 'Cancelar citas',
            self::ServicesManage => 'Gestionar servicios',
            self::SessionCreditsAdjust => 'Ajustar y devolver sesiones',
            self::ClinicalNotesView => 'Ver notas clínicas',
            self::ClinicalNotesCreate => 'Crear notas clínicas',
            self::ClinicalNotesUpdate => 'Editar notas clínicas',
            self::ClinicalNotesSign => 'Firmar notas clínicas',
            self::ClinicalNotesExport => 'Exportar historia clínica',
            self::ClinicalNotesEmergency => 'Acceso de emergencia a historias clínicas',
            self::TrainingView => 'Ver programas de entrenamiento',
            self::TrainingCreate => 'Crear programas de entrenamiento',
            self::TrainingUpdate => 'Editar programas de entrenamiento',
            self::TrainingDelete => 'Eliminar programas de entrenamiento',
            self::AssessmentsView => 'Ver evaluaciones',
            self::AssessmentsCreate => 'Crear evaluaciones',
            self::AssessmentsUpdate => 'Editar evaluaciones',
            self::AssessmentsDelete => 'Eliminar evaluaciones',
            self::PaymentsView => 'Ver pagos',
            self::PaymentsCreate => 'Registrar pagos',
            self::PaymentsRefund => 'Reembolsar pagos',
            self::PaymentsVoid => 'Anular comprobantes y pagos',
            self::PaymentsDiscount => 'Aplicar descuentos',
            self::ReportsView => 'Ver reportes',
            self::ReportsExport => 'Exportar reportes',
        };
    }
}
