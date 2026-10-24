<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency middleware for mutating API endpoints.
 *
 * Consumers SHOULD send a unique Idempotency-Key header (UUID recommended)
 * with every POST that creates a ledger transaction. When the same key is
 * received within the TTL window the middleware returns the cached response
 * directly — no controller or DB is touched.
 *
 * If no key is provided the request passes through normally (the per-table
 * lock in the controller is the second line of defence).
 *
 * Response headers added:
 *   Idempotency-Key-Status: original | replayed | key-mismatch
 */
class IdempotencyMiddleware
{
    /** How long (seconds) to keep a cached idempotent response. */
    private const TTL = 60;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        // No key supplied → pass through (per-table lock in controller protects)
        if (empty($key)) {
            return $next($request);
        }

        $cacheKey = 'idempotency:' . sha1($key);
        $bodyHash = sha1($request->getContent());

        if ($cached = Cache::get($cacheKey)) {
            // Guard: same key but different body → key is being misused, refuse
            if ($cached['body_hash'] !== $bodyHash) {
                return response()->json([
                    'success' => false,
                    'message' => 'Idempotency-Key reused with a different request body.',
                ], 422)->header('Idempotency-Key-Status', 'key-mismatch');
            }

            // Return the original response verbatim — no DB write performed
            return response($cached['body'], $cached['status'], $cached['headers'])
                ->header('Idempotency-Key-Status', 'replayed');
        }

        // First time seeing this key — let the request through
        $response = $next($request);

        // Only cache 2xx responses; do NOT cache validation errors or 5xx failures
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            Cache::put($cacheKey, [
                'body_hash' => $bodyHash,
                'body'      => $response->getContent(),
                'status'    => $response->getStatusCode(),
                'headers'   => array_filter([
                    'Content-Type' => $response->headers->get('Content-Type'),
                ]),
            ], self::TTL);
        }

        $response->headers->set('Idempotency-Key-Status', 'original');

        return $response;
    }
}
