<?php

namespace App\Domain\CheckIns\Services;

use App\Domain\CheckIns\Models\MemberAccessCredential;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Services\MemberNumber;
use App\Support\Scopes\LocationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Identifica al cliente en el kiosco a partir de lo que digita o escanea.
 * No distingue "no existe" de "dato incorrecto" para no revelar quién es
 * cliente.
 */
class KioskMemberResolver
{
    public function __construct(private readonly MemberNumber $numbers) {}

    public function byQr(string $token): ?Member
    {
        $token = trim($token);

        if ($token === '' || strlen($token) > 200) {
            return null;
        }

        $credential = MemberAccessCredential::query()->withoutGlobalScopes()
            ->where('token_hash', MemberAccessCredential::hashToken($token))
            ->usable()
            ->first();

        if ($credential === null) {
            return null;
        }

        $credential->forceFill(['last_used_at' => Date::now()])->save();

        return $this->members()->find($credential->member_id);
    }

    public function byMemberNumber(string $input): ?Member
    {
        $input = strtoupper(trim($input));

        if ($input === '' || strlen($input) > 30) {
            return null;
        }

        // Solo dígitos: se interpreta con el prefijo vigente (123 → PL-000123).
        $candidates = ctype_digit($input) ? [$this->numbers->for((int) $input), $input] : [$input];

        return $this->members()->whereIn('member_number', $candidates)->first();
    }

    /**
     * Celular + PIN de 4 dígitos. Limita los intentos fallidos por celular.
     */
    public function byPhoneAndPin(string $phone, string $pin): ?Member
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) < 7 || strlen($digits) > 15 || ! preg_match('/^\d{4}$/', $pin)) {
            return null;
        }

        $key = $this->throttleKey($digits);
        if (RateLimiter::tooManyAttempts($key, (int) config('profitlife.check_ins.pin_max_attempts'))) {
            return null;
        }

        // Celular guardado con o sin indicativo de país (+57).
        $member = $this->members()
            ->whereNotNull('checkin_pin_hash')
            ->where(fn ($q) => $q->where('phone', $digits)->orWhere('phone', 'like', '%'.$digits))
            ->limit(10)
            ->get()
            ->first(fn (Member $m) => Hash::check($pin, $m->checkin_pin_hash));

        if ($member === null) {
            RateLimiter::hit($key, (int) config('profitlife.check_ins.pin_decay_seconds'));

            return null;
        }

        RateLimiter::clear($key);

        return $member;
    }

    public function phoneIsLocked(string $phone): bool
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return $digits !== '' && RateLimiter::tooManyAttempts($this->throttleKey($digits), (int) config('profitlife.check_ins.pin_max_attempts'));
    }

    /**
     * @return Builder<Member>
     */
    private function members()
    {
        return Member::query()->withoutGlobalScope(LocationScope::class);
    }

    private function throttleKey(string $digits): string
    {
        return 'kiosk-pin:'.hash('sha256', $digits);
    }
}
