<?php

namespace App\Http\Requests\Api;

use App\Models\Progress;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProgressRequest extends FormRequest
{
    /** Garde-fou : environ 55 ans de journées. */
    private const MAX_DAYS = 20000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'data' => ['required', 'array'],
            'data.version' => ['sometimes', 'integer', 'in:1'],
            // `present` plutôt que `required` : une progression vide (`{}`) est valide.
            'data.days' => ['present', 'array', 'max:'.self::MAX_DAYS, $this->daysAreWellFormed()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'data.required' => 'La progression est manquante.',
            'data.array' => 'La progression a un format invalide.',
            'data.days.present' => 'La progression doit contenir les jours.',
            'data.days.max' => 'La progression contient trop de jours.',
        ];
    }

    /**
     * Clés au format AAAA-MM-JJ ; chaque jour est une table de groupes (cases booléennes, ou compteurs entiers pour `counts`).
     */
    private function daysAreWellFormed(): Closure
    {
        return function (string $attribute, mixed $days, Closure $fail): void {
            foreach ((array) $days as $date => $day) {
                if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! is_array($day)) {
                    $fail('La progression contient un jour invalide.');

                    return;
                }

                foreach ($day as $group => $values) {
                    $isCounts = $group === Progress::COUNT_GROUP;
                    $check = $isCounts
                        ? fn ($value) => is_int($value) && $value >= 0 && $value <= Progress::MAX_COUNT
                        : is_bool(...);

                    if (! is_array($values) || in_array(false, array_map($check, $values), true)) {
                        $fail($isCounts
                            ? "Les compteurs du jour {$date} doivent être des entiers positifs."
                            : "Les cases du jour {$date} ({$group}) doivent être des booléens.");

                        return;
                    }
                }
            }
        };
    }
}
