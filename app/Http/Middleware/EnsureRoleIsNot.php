<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoleIsNot
{
    /**
     * Handle an incoming request.
     * Blocks access for the specified roles.
     * Usage: role_not:trabajador or role_not:trabajador,parcial
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $blocked = array_map('trim', $roles);
        $blocked = array_filter($blocked);

        if (empty($blocked)) {
            return $next($request);
        }

        $isNumeric = array_reduce($blocked, fn ($carry, $r) => $carry && is_numeric($r), true);

        if ($isNumeric) {
            $blockedIds = array_map('intval', $blocked);
            if (in_array((int) $user->rol_id, $blockedIds, true)) {
                return response()->json(['message' => 'No tiene permiso para esta acción.'], 403);
            }
        } else {
            $user->loadMissing('rol');
            if ($user->rol && in_array(strtolower($user->rol->name), $blocked, true)) {
                return response()->json(['message' => 'No tiene permiso para esta acción.'], 403);
            }
        }

        return $next($request);
    }
}
