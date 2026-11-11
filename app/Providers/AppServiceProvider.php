<?php

namespace App\Providers;

use App\Models\ApiToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Authentification par jeton porteur : on compare le hash du jeton reçu à celui stocké.
        Auth::viaRequest('api-token', function (Request $request) {
            $plainToken = $request->bearerToken();

            if (! $plainToken) {
                return null;
            }

            $token = ApiToken::with('user')
                ->where('token', ApiToken::hashFor($plainToken))
                ->first();

            if (! $token) {
                return null;
            }

            // Permet à la déconnexion de révoquer précisément ce jeton.
            $request->attributes->set('api_token', $token);

            if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinutes(5))) {
                $token->forceFill(['last_used_at' => now()])->save();
            }

            return $token->user;
        });

        // Inscription et connexion : limite par adresse IP et e-mail contre les essais en série.
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip().'|'.strtolower((string) $request->input('email')));
        });
    }
}
