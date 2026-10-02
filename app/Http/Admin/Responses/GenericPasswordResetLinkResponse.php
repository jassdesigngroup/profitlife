<?php

namespace App\Http\Admin\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * Respuesta idéntica para emails existentes y no existentes.
 */
class GenericPasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public const MESSAGE = 'Si el correo está registrado, recibirá un enlace para restablecer su contraseña.';

    public function __construct(protected string $status = '') {}

    public function toResponse($request): JsonResponse|RedirectResponse
    {
        /** @var Request $request */
        return $request->wantsJson()
            ? new JsonResponse(['message' => self::MESSAGE], 200)
            : back()->with('status', self::MESSAGE);
    }
}
