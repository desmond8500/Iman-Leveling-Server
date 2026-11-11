<?php

use App\Models\ApiToken;
use App\Models\Progress;
use App\Models\User;

function progressPayload(): array
{
    return [
        'version' => 1,
        'days' => [
            '2026-11-09' => [
                'prayers' => ['fajr' => true, 'dhuhr' => true],
                'mosque' => ['fajr' => true],
                'sunnah' => ['fajr-before' => true],
                'adhkar' => ['morning:kursi' => true],
            ],
            '2026-11-10' => ['prayers' => ['fajr' => false]],
        ],
    ];
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = ApiToken::issueFor($this->user);
});

it('exige une authentification', function () {
    $this->getJson('/api/progress')->assertUnauthorized();
    $this->putJson('/api/progress', ['data' => progressPayload()])->assertUnauthorized();
});

it('renvoie 404 quand rien n\'est encore enregistré', function () {
    $this->withToken($this->token)->getJson('/api/progress')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

it('enregistre puis relit la progression à l\'identique', function () {
    $this->withToken($this->token)->putJson('/api/progress', ['data' => progressPayload()])
        ->assertOk()
        ->assertJsonStructure(['updated_at']);

    $this->withToken($this->token)->getJson('/api/progress')
        ->assertOk()
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.days.2026-11-09.prayers.dhuhr', true)
        ->assertJsonPath('data.days.2026-11-09.mosque.fajr', true)
        ->assertJsonPath('data.days.2026-11-09.adhkar.morning:kursi', true)
        ->assertJsonPath('data.days.2026-11-10.prayers.fajr', false)
        ->assertJsonStructure(['updated_at']);
});

it('remplace la progression précédente et garde un seul enregistrement', function () {
    $this->withToken($this->token)->putJson('/api/progress', ['data' => progressPayload()])->assertOk();
    $this->withToken($this->token)->putJson('/api/progress', [
        'data' => ['version' => 1, 'days' => ['2026-11-11' => ['prayers' => ['asr' => true]]]],
    ])->assertOk();

    expect(Progress::count())->toBe(1);
    $this->withToken($this->token)->getJson('/api/progress')
        ->assertJsonPath('data.days.2026-11-11.prayers.asr', true)
        ->assertJsonMissingPath('data.days.2026-11-09');
});

it('garde les tables vides sous forme d\'objets JSON', function () {
    $this->withToken($this->token)->putJson('/api/progress', ['data' => ['version' => 1, 'days' => []]])->assertOk();

    $raw = $this->withToken($this->token)->get('/api/progress', ['Accept' => 'application/json'])->getContent();
    expect($raw)->toContain('"days":{}');
});

it('isole la progression de chaque utilisateur', function () {
    $other = User::factory()->create();
    $otherToken = ApiToken::issueFor($other);

    $this->withToken($this->token)->putJson('/api/progress', ['data' => progressPayload()])->assertOk();

    $this->withToken($otherToken)->getJson('/api/progress')->assertNotFound();
});

it('ignore les groupes inconnus et refuse les formats invalides', function () {
    $payload = progressPayload();
    $payload['days']['2026-11-09']['inconnu'] = ['x' => true];
    $this->withToken($this->token)->putJson('/api/progress', ['data' => $payload])->assertOk();

    expect(Progress::first()->data['days']['2026-11-09'])->not->toHaveKey('inconnu');

    $badDate = ['version' => 1, 'days' => ['hier' => ['prayers' => ['fajr' => true]]]];
    $this->withToken($this->token)->putJson('/api/progress', ['data' => $badDate])
        ->assertUnprocessable()->assertJsonValidationErrors('data.days');

    $badFlag = ['version' => 1, 'days' => ['2026-11-09' => ['prayers' => ['fajr' => 'oui']]]];
    $this->withToken($this->token)->putJson('/api/progress', ['data' => $badFlag])
        ->assertUnprocessable()->assertJsonValidationErrors('data.days');

    $this->withToken($this->token)->putJson('/api/progress', [])
        ->assertUnprocessable()->assertJsonValidationErrors('data');

    $this->withToken($this->token)->putJson('/api/progress', ['data' => ['version' => 2, 'days' => []]])
        ->assertUnprocessable()->assertJsonValidationErrors('data.version');
});

it('supprime les données avec le compte', function () {
    $this->withToken($this->token)->putJson('/api/progress', ['data' => progressPayload()])->assertOk();

    $this->user->delete();

    expect(Progress::count())->toBe(0)->and(ApiToken::count())->toBe(0);
});
