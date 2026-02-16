<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditPassportFlow
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param  \Closure(\Illuminate\Http\Request): \Symfony\Component\HttpFoundation\Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $event = $this->resolveEvent($request);
        $clientId = $this->extractClientId($request);
        /** @var User|null $user */
        $user = $request->user('web');

        if ($request->is('oauth/authorize') && $user && ! $user->is_active) {
            $this->auditLogger->log('authorize', 'failed', $user, $clientId, [
                'reason' => 'user_inactive',
            ]);

            Auth::guard('web')->logout();
            abort(403, 'User is inactive.');
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            if ($event) {
                $this->auditLogger->log($event, 'failed', $user, $clientId, [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'error' => $exception->getMessage(),
                ]);
            }

            throw $exception;
        }

        if (! $event) {
            return $response;
        }

        $status = $response->getStatusCode() < 400 ? 'success' : 'failed';

        if ($event === 'token_issued' && $status === 'success') {
            return $response;
        }

        $this->auditLogger->log($event, $status, $user, $clientId, [
            'method' => $request->method(),
            'path' => $request->path(),
            'response_status' => $response->getStatusCode(),
            'scope' => $request->input('scope', $request->query('scope')),
        ]);

        return $response;
    }

    private function resolveEvent(Request $request): ?string
    {
        if ($request->is('oauth/authorize')) {
            return 'authorize';
        }

        if ($request->is('oauth/token')) {
            return 'token_issued';
        }

        return null;
    }

    private function extractClientId(Request $request): ?string
    {
        $value = $request->input('client_id', $request->query('client_id'));

        return $value === null ? null : (string) $value;
    }
}
