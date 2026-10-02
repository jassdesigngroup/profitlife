<?php

namespace App\Console\Commands;

use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Staff\Enums\StaffStatus;
use App\Domain\Staff\Models\Staff;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Crea el primer Super Admin en un entorno sin datos de ejemplo
 * (producción). Los demás usuarios se invitan desde el panel.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'profitlife:create-super-admin
        {--first-name= : Nombres}
        {--last-name= : Apellidos}
        {--email= : Correo de acceso}';

    protected $description = 'Crea un usuario Super Admin con su perfil de staff';

    public function handle(): int
    {
        if (! Role::query()->where('name', RoleName::SuperAdmin->value)->exists()) {
            $this->error('No existen los roles. Ejecute primero: php artisan db:seed --force');

            return self::FAILURE;
        }

        $data = [
            'first_name' => $this->option('first-name') ?? text('Nombres', required: true),
            'last_name' => $this->option('last-name') ?? text('Apellidos', required: true),
            'email' => Str::lower((string) ($this->option('email') ?? text('Correo electrónico', required: true))),
            'password' => password('Contraseña (mín. 10 caracteres, mayúsculas, minúsculas y números)', required: true),
        ];
        $data['password_confirmation'] = password('Repita la contraseña', required: true);

        $validator = Validator::make($data, [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ], [], ['first_name' => 'nombres', 'last_name' => 'apellidos', 'email' => 'correo', 'password' => 'contraseña']);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'name' => trim("{$data['first_name']} {$data['last_name']}"),
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
                'locale' => config('profitlife.locale'),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole(RoleName::SuperAdmin->value);

            Staff::query()->create([
                'user_id' => $user->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'status' => StaffStatus::Active,
            ]);

            return $user;
        });

        $this->info("Super Admin creado: {$user->email}. En el primer ingreso deberá configurar la verificación en dos pasos.");

        return self::SUCCESS;
    }
}
