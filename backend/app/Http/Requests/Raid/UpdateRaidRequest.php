<?php

namespace App\Http\Requests\Raid;

use Domains\Raid\Models\Raid;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $guild = $this->route('guild');
        $raidId = $this->route('raid');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('raids', 'id')->where('guild_id', $guild->id),
                Rule::notIn([$raidId]),
                $this->parentMustHaveNoMembers(),
                $this->parentMustNotBeAtMaxDepth(),
            ],
            'leader_character_id' => [
                'nullable',
                'integer',
                Rule::exists('guild_members', 'character_id')->where('guild_id', $guild->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название рейда.',
            'name.max' => 'Название не должно превышать 255 символов.',
            'leader_character_id.exists' => 'Выбранный персонаж не состоит в этой гильдии.',
            'parent_id.exists' => 'Родительский рейд не найден или принадлежит другой гильдии.',
            'parent_id.not_in' => 'Рейд не может быть родителем самого себя.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'название',
            'description' => 'описание',
            'parent_id' => 'родительский рейд',
            'leader_character_id' => 'лидер',
            'sort_order' => 'порядок',
        ];
    }

    private const MAX_RAID_DEPTH = 5;

    private function parentMustHaveNoMembers(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null) {
                return;
            }
            $parent = Raid::query()
                ->where('id', $value)
                ->where('guild_id', $this->route('guild')->id)
                ->withCount('members')
                ->first();
            if ($parent && $parent->members_count > 0) {
                $fail('Рейд с прикреплёнными участниками не может иметь дочерних рейдов. Выберите другой родительский рейд.');
            }
        };
    }

    private function parentMustNotBeAtMaxDepth(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null) {
                return;
            }
            $depth = $this->getRaidDepth((int) $value);
            if ($depth >= self::MAX_RAID_DEPTH - 1) {
                $fail('Максимальная вложенность рейдов — '.self::MAX_RAID_DEPTH.' уровней. Выберите родителя выше по дереву.');
            }
        };
    }

    private function getRaidDepth(int $raidId): int
    {
        $depth = 0;
        $current = Raid::query()
            ->where('id', $raidId)
            ->where('guild_id', $this->route('guild')->id)
            ->first();
        while ($current && $current->parent_id !== null) {
            $depth++;
            $current = Raid::query()
                ->where('id', $current->parent_id)
                ->where('guild_id', $this->route('guild')->id)
                ->first();
        }

        return $depth;
    }
}
