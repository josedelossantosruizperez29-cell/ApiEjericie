<?php

namespace App\Services;

use App\Models\GuestAccessToken;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuestIdentity
{
    /** Return the secret once, for secure storage by the client. */
    public function issue(User $user, ?string $deviceLabel = null): string
    {
        $secret = bin2hex(random_bytes(32));
        $token = new GuestAccessToken;
        $token->token_hash = hash('sha256', $secret);
        $token->device_label = $deviceLabel;
        $token->expires_at = now()->addDays(90);
        $user->guestAccessTokens()->save($token);

        return $secret;
    }

    public function rotate(User $user, #[\SensitiveParameter] string $secret): string
    {
        return DB::transaction(function () use ($user, $secret) {
            $old = $user->guestAccessTokens()->where('token_hash', hash('sha256', $secret))->whereNull('revoked_at')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->lockForUpdate()->first();
            if (! $old) {
                throw new AuthenticationException;
            }
            $old->revoked_at = now();
            $old->save();

            return $this->issue($user, $old->device_label);
        });
    }

    public function authenticate(string $publicId, #[\SensitiveParameter] string $secret): ?User
    {
        if (! Str::isUuid($publicId)) {
            return null;
        }

        return DB::transaction(function () use ($publicId, $secret) {
            $token = GuestAccessToken::where('token_hash', hash('sha256', $secret))
                ->whereHas('user', fn ($q) => $q->where('public_id', $publicId)->where('status', 'active'))
                ->whereNull('revoked_at')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->lockForUpdate()->first();
            if ($token) {
                $token->last_used_at = now();
                $token->save();
            }

            return $token?->user;
        });
    }
}
