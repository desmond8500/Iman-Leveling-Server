<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['name', 'token', 'last_used_at'])]
#[Hidden(['token'])]
class ApiToken extends Model
{
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashFor(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * Crée un jeton pour l'utilisateur et renvoie sa valeur en clair (affichée une seule fois).
     */
    public static function issueFor(User $user, string $name = 'mobile'): string
    {
        $plainToken = Str::random(64);

        $user->apiTokens()->create([
            'name' => $name,
            'token' => self::hashFor($plainToken),
        ]);

        return $plainToken;
    }
}
