<?php

namespace Domains\Event\Actions;

use Domains\Event\Models\EventHistoryTitle;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class UpdateEventHistoryTitleAction
{
    public function __invoke(EventHistoryTitle $title, string $name): EventHistoryTitle
    {
        $title->name = $name;

        try {
            $title->save();
        } catch (QueryException $e) {
            // Уникальное имя уже существует
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

