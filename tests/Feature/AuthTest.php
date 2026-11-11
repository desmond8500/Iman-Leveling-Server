<?php

use App\Models\ApiToken;
use App\Models\User;

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Aïcha',
        'email' => 'aicha@example.com',
        'password' => 'motdepasse-solide',
    ], $overrides);
}

describe('inscription', function () {
    it('crée un compte et renvoie un jeton', function () {
        $response = $this->postJson('/api/auth/register', registrationPayload());

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['name', 'email']])
            ->assertJsonPath('user.email', 'aicha@example.com');

        expect(User::where('email', 'aicha@example.com')->exists())->toBeTrue();
    });

    it('ne stocke que le hash du jeton et du mot de passe', function () {
        $token = $this->postJson('/api/auth/register', registrationPayload())->json('token');

        $stored = ApiToken::firstOrFail();
        expect($stored->token)->not->toBe($token)
            ->and($stored->token)->toBe(hash('sha256', $token));
        expect(User::firstOrFail()->password)->not->toBe('motdepasse-solide');
    });

    it('refuse une adresse déjà utilisée', function () {
        User::factory()->create(['email' => 'aicha@example.com']);

        $this->postJson('/api/auth/register', registrationPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('message', 'Un compte existe déjà avec cette adresse e-mail.');
    });

    it('refuse un mot de passe trop court', function () {
        $this->postJson('/api/auth/register', registrationPayload(['password' => 'court']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    });
});

describe('connexion', function () {
    it('renvoie un jeton avec de bons identifiants', function () {
        User::factory()->create(['email' => 'aicha@example.com', 'password' => 'motdepasse-solide']);

        $this->postJson('/api/auth/login', ['email' => 'aicha@example.com', 'password' => 'motdepasse-solide'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['name', 'email']]);
    });

    it('refuse un mauvais mot de passe ou un compte inconnu avec le même message', function () {
        User::factory()->create(['email' => 'aicha@example.com', 'password' => 'motdepasse-solide']);

        $this->postJson('/api/auth/login', ['email' => 'aicha@example.com', 'password' => 'mauvais'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Identifiants incorrects.');

        $this->postJson('/api/auth/login', ['email' => 'inconnu@example.com', 'password' => 'motdepasse-solide'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Identifiants incorrects.');
    });

    it('limite les essais répétés', function () {
        foreach (range(1, 10) as $attempt) {
            $this->postJson('/api/auth/login', ['email' => 'aicha@example.com', 'password' => 'mauvais']);
        }

        $this->postJson('/api/auth/login', ['email' => 'aicha@example.com', 'password' => 'mauvais'])
            ->assertTooManyRequests();
    });
});

describe('déconnexion', function () {
    it('révoque uniquement le jeton utilisé', function () {
        $user = User::factory()->create();
        $first = ApiToken::issueFor($user, 'téléphone');
        $second = ApiToken::issueFor($user, 'tablette');

        $this->withToken($first)->postJson('/api/auth/logout')->assertNoContent();

        expect($user->apiTokens()->count())->toBe(1);
        $this->withToken($first)->getJson('/api/progress')->assertUnauthorized();
        $this->withToken($second)->getJson('/api/progress')->assertNotFound();
    });

    it('exige un jeton', function () {
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    });
});

it('rejette un jeton inconnu', function () {
    $this->withToken('jeton-invalide')->getJson('/api/progress')->assertUnauthorized();
});

it('met à jour la date de dernière utilisation du jeton', function () {
    $user = User::factory()->create();
    $plain = ApiToken::issueFor($user);

    expect($user->apiTokens()->first()->last_used_at)->toBeNull();

    $this->withToken($plain)->getJson('/api/progress');

    expect($user->apiTokens()->first()->last_used_at)->not->toBeNull();
});
