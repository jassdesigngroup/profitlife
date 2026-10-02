<?php

namespace App\Http\Admin\Middleware;

use App\Domain\Identity\Enums\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanAccessAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->can(Permission::AdminAccess->value), 403, 'No tiene acceso al panel administrativo.');

        return $next($request);
    }
}
