<?php

namespace Domains\Event\Actions;

use Domains\Event\Models\EventHistoryTitle;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class UpdateEventHistoryTitleAction
{
    /**
     * @param  array{name: string, dkp_base_points?: int|null, distribute_dkp_to_participants?: bool}  $data
     */
    public function __invoke(EventHistoryTitle $title, array $data): EventHistoryTitle
    {
        $title->name = $data['name'];

        if (array_key_exists('distribute_dkp_to_participants', $data)) {
            $title->distribute_dkp_to_participants = (bool) $data['distribute_dkp_to_participants'];
        }

        if ($title->distribute_dkp_to_participants) {
            $title->dkp_base_points = null;
        } elseif (array_key_exists('dkp_base_points', $data)) {
            $title->dkp_base_points = $data['dkp_base_points'];
        }

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

