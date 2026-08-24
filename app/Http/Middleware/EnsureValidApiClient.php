<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidApiClient
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $plainToken = $request->header('X-API-Key');

        if (! $plainToken) {
            abort(401, 'Missing API key.');
        }

        $apiClient = ApiClient::query()
            ->where('is_active', true)
            ->get()
            ->first(function (ApiClient $client) use ($plainToken) {
                return Hash::check($plainToken, $client->key_hash);
            });

        if (! $apiClient) {
            abort(401, 'Invalid API key.');
        }

        $origin = $request->headers->get('origin') ?: $request->headers->get('referer');

        if ($origin && $apiClient->domains()->where('is_active', true)->exists()) {
            $allowed = $apiClient->domains()
                ->where('is_active', true)
                ->get()
                ->contains(function ($domain) use ($origin) {
                    return str_starts_with($origin, $domain->domain);
                });

            if (! $allowed) {
                abort(403, 'Domain not allowed.');
            }
        }

        $apiClient->update([
            'last_used_at' => now(),
        ]);

        $request->attributes->set('api_client', $apiClient);

        return $next($request);
    }
}