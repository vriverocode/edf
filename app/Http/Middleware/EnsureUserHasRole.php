<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     * Expects roles as comma-separated values: role:1,2 or role:admin,propietario
     * Numeric values are matched against rol_id; non-numeric against roles.name.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $allowed = array_map('trim', $roles);
        $allowed = array_filter($allowed);

        if (empty($allowed)) {
            return response()->json(['message' => 'No tiene permiso para esta acción.'], 403);
        }

        $isNumeric = array_reduce($allowed, fn ($carry, $r) => $carry && is_numeric($r), true);

        if ($isNumeric) {
            $allowedIds = array_map('intval', $allowed);
            if (in_array((int) $user->rol_id, $allowedIds, true)) {
                return $next($request);
            }
        } else {
            $user->loadMissing('rol');
            if ($user->rol && in_array($user->rol->name, $allowed, true)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'No tiene permiso para esta acción.'], 403);
    }
}
