<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['data'])]
class Progress extends Model
{
    protected $table = 'progress';

    /** Groupes de cases cochées (booléens) conservés pour chaque jour. */
    public const FLAG_GROUPS = ['prayers', 'mosque', 'sunnah', 'adhkar'];

    /** Groupe de compteurs de récitations (entiers positifs) conservé pour chaque jour. */
    public const COUNT_GROUP = 'counts';

    public const MAX_COUNT = 100000;

    /** Tous les groupes conservés pour chaque jour. */
    public const DAY_GROUPS = [...self::FLAG_GROUPS, self::COUNT_GROUP];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ne garde que la forme attendue (version, jours, cases booléennes) : tout le reste est ignoré.
     *
     * @param  array<string, mixed>  $input
     * @return array{version: int, days: array<string, array<string, array<string, bool>>>}
     */
    public static function sanitize(array $input): array
    {
        $days = [];

        foreach ((array) ($input['days'] ?? []) as $date => $day) {
            if (! is_string($date) || ! is_array($day)) {
                continue;
            }

            $record = ['prayers' => []];
            foreach (self::FLAG_GROUPS as $group) {
                if (isset($day[$group]) && is_array($day[$group])) {
                    $record[$group] = array_filter($day[$group], is_bool(...));
                }
            }

            if (isset($day[self::COUNT_GROUP]) && is_array($day[self::COUNT_GROUP])) {
                $record[self::COUNT_GROUP] = array_filter(
                    $day[self::COUNT_GROUP],
                    fn ($value) => is_int($value) && $value > 0 && $value <= self::MAX_COUNT,
                );
            }

            $days[$date] = $record;
        }

        return ['version' => 1, 'days' => $days];
    }

    /**
     * Données prêtes pour le JSON : les tables vides restent des objets `{}` (et non `[]`),
     * ce que le client attend.
     *
     * @return array{version: int, days: object}
     */
    public function payload(): array
    {
        $days = [];

        foreach ($this->data['days'] ?? [] as $date => $day) {
            foreach (self::DAY_GROUPS as $group) {
                if (isset($day[$group])) {
                    $day[$group] = (object) $day[$group];
                }
            }
            $days[$date] = $day;
        }

        return ['version' => 1, 'days' => (object) $days];
    }
}
