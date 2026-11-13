<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi server-to-server untuk project utama.
 * Kunci diatur di INTEGRATION_API_KEYS dengan format "nama:secret,nama2:secret2".
 */
class VerifyIntegrationKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();
        abort_if($token === '', 401, 'Token API tidak ditemukan.');

        $client = collect(config('services.integrations.keys'))
            ->search(fn (string $secret) => hash_equals($secret, $token));
        abort_if($client === false, 401, 'Token API tidak valid.');

        $request->attributes->set('integrationClient', $client);

        return $next($request);
    }
}
