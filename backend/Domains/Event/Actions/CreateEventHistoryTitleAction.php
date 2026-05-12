<?php

namespace Domains\Event\Actions;

use Domains\Event\Models\EventHistoryTitle;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class CreateEventHistoryTitleAction
{
    public function __invoke(string $name, ?int $dkpBasePoints = null): EventHistoryTitle
    {
        $title = new EventHistoryTitle([
            'name' => $name,
            'dkp_base_points' => $dkpBasePoints,
        ]);

        try {
            $title->save();
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                throw ValidationException::withMessages([
                    'name' => ['Такое название уже существует. Введите другое название.'],
                ]);
            }

            throw $e;
        }

        return $title->fresh();
    }
}
