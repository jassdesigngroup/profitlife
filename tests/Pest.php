<?php

use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Staff\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * Crea un usuario de staff con un rol, sus sedes y, por defecto, 2FA confirmado
 * (requisito de acceso para algunos roles).
 *
 * @param  list<Location>  $locations
 */
function staffUser(RoleName $role, array $locations = [], bool $twoFactor = true, array $userAttributes = []): User
{
    $factory = User::factory()->role($role);

    if ($twoFactor) {
        $factory = $factory->withTwoFactor();
    }

    $user = $factory->create($userAttributes);

    Staff::factory()->for($user)->atLocations(...$locations)->create();

    return $user->fresh();
}

function staffOf(User $user): Staff
{
    return Staff::query()->withoutGlobalScopes()->where('user_id', $user->id)->firstOrFail();
}
