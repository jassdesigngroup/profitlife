<?php

namespace Database\Factories;

use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'locale' => config('app.locale'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    /**
     * Usuario invitado que todavía no ha definido su contraseña.
     */
    public function invited(): static
    {
        return $this->state(fn () => ['password' => null, 'email_verified_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * 2FA confirmado con un secreto TOTP válido.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt(json_encode(Collection::times(8, fn () => Str::random(10).'-'.Str::random(10))->all())),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function role(RoleName ...$roles): static
    {
        return $this->afterCreating(function (User $user) use ($roles) {
            $user->assignRole(array_map(fn (RoleName $r) => $r->value, $roles));
        });
    }
}
