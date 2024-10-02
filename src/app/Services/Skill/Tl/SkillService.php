<?php

namespace App\Services\Skill\Tl;

use App\Models\Game;
use App\Models\Skill;
use App\Models\SkillParam;
use App\Models\SkillParamLang;
use App\Models\SkillType;
use App\Models\UseFormat;
use App\Models\Weapon;
use App\Services\Upload\UploadImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SkillService
{


    public function getSkillsAll()
    {
        $skill = Skill::query()
            ->with(['weapon:id,name'])
            ->with(['params:id,key,name,info_original,info,skill_id'])
            ->where('game_id', 1)
            ->get();

        return $skill;
    }

    public function getSkillsAllStorage()
    {
        $skills = $this->getSkillsAll();
        $skillsParser = (new ParserServerService())->getSkillsAll();
        $skillsParser = collect($skillsParser);

        $mergedData = $skillsParser->map(function ($parserItem) use ($skills) {
            $skillItem = $skills->firstWhere('skill_link_id', $parserItem['skill_link_id']);
            $parserItem['description'] = nl2br($parserItem['description']);
            return [
                'skill_link_id' => $parserItem['skill_link_id'],
                'site_info_original' => $this->formatDataInfoOriginal($skillItem),
                'site_info' => $this->formatDataInfo($skillItem),
                'parser_info' => $parserItem,
            ];
        });

        return $mergedData;
    }

    public function formatDataInfoOriginal(?Skill $skillItem)
    {
        if(!$skillItem){
            return null;
        }
        $materials = !empty($skillItem->params) ? collect($skillItem->params)->firstWhere('key', 'materials') : null;
        $skillHasTraits = !empty($skillItem->params) ? collect($skillItem->params)->firstWhere('key', 'skill_has_traits') : null;

        $description = collect($skillItem->params)->firstWhere('key', 'description')['info_original'];
        $type = collect($skillItem->params)->firstWhere('key', 'type')['info_original'];
        $level = collect($skillItem->params)->firstWhere('key', 'level')['info_original'];
        $typeLevel = collect($skillItem->params)->firstWhere('key', 'type_level')['info_original'];
        $info = collect($skillItem->params)->firstWhere('key', 'info')['info_original'];
        $updateLvl = collect($skillItem->params)->firstWhere('key', 'update_lvl')['info_original'];
        $unlockedLvl = collect($skillItem->params)->firstWhere('key', 'unlocked_lvl')['info_original'];
        $res = [
            'id' => $skillItem->id,
            'skill_link_id' => $skillItem->skill_link_id,
            'name' => $skillItem->name,
            'image' => Storage::url($skillItem->image),
            'description' => !empty($description['value']) ? nl2br($description['value']) : '',
            'type' => $type['value'] ?? '',
            'level' => $level['value'] ?? '',
            'type_level' => $typeLevel['value'] ?? '',
            'info' => $info ?? '',
            'update_lvl' => $updateLvl ?? '',
            'unlocked_lvl' => $unlockedLvl['value'] ?? '',
            'materials' => !empty($materials['info']) ? $materials['info'] : null,
            'skill_has_traits' => !empty($skillHasTraits['info']) ? $skillHasTraits['info'] : null,
            'weapon_type' => $skillItem->weapon,
        ];

        return $res;
    }

    public function formatDataInfo(?Skill $skillItem)
    {
        if(!$skillItem){
            return null;
        }
        $skillHasTraits = !empty($skillItem->params) ? collect($skillItem->params)->firstWhere('key', 'skill_has_traits') : null;

        $description = collect($skillItem->params)->firstWhere('key', 'description')['info'];
        $type = collect($skillItem->params)->firstWhere('key', 'type')['info'];
        $level = collect($skillItem->params)->firstWhere('key', 'level')['info'];
        $typeLevel = collect($skillItem->params)->firstWhere('key', 'type_level')['info'];
        $info = collect($skillItem->params)->firstWhere('key', 'info')['info'];
        $updateLvl = collect($skillItem->params)->firstWhere('key', 'update_lvl')['info'];
        $unlockedLvl = collect($skillItem->params)->firstWhere('key', 'unlocked_lvl')['info'];
        $res = [
            'id' => $skillItem->id,
            'skill_link_id' => $skillItem->skill_link_id,
            'name' => $skillItem->name,
            'name_ru' => $skillItem->name_ru,
            'image' => Storage::url($skillItem->image),
            'description' => $description ?? '',
            'type' => $type ?? '',
            'level' => $level ?? '',
            'type_level' => $typeLevel ?? '',
            'info' => $info ?? '',
            'update_lvl' => $updateLvl ?? '',
            'unlocked_lvl' => $unlockedLvl ?? '',
            'skill_has_traits' => !empty($skillHasTraits['info']) ? $skillHasTraits['info'] : null,
            'weapon_type' => $skillItem->weapon,
        ];

        return $res;
    }

    public function transferToSite(array $data)
    {

        $game = Game::query()->where('domain_name', 'tl')->first();

        try {
            DB::beginTransaction();

            $weapon = Weapon::firstOrCreate([
                'name' => $data['weapon_type']['name'],
                'game_id' => $game->id
            ]);
            $skillType = SkillType::firstOrCreate([
                'name' => $data['type'],
                'game_id' => $game->id
            ]);
            $skill = Skill::firstOrCreate(
                ['name' => $data['name'], 'game_id' => $game->id],
                [
                    'skill_link_id' => $data['skill_link_id'],
                    'skill_type_id' => $skillType->id,
                    'weapon_id' => $weapon->id,
                ]
            );
            SkillParam::firstOrCreate(
                ['name' => 'Type level', 'skill_id' => $skill->id, 'key' => 'type_level'],
                ['info_original' => ['value' => $data['type_level']]]
            );
            SkillParam::firstOrCreate(
                ['name' => 'Type', 'skill_id' => $skill->id, 'key' => 'type'],
                ['info_original' => ['value' => $data['type']]]
            );
            SkillParam::firstOrCreate(
                ['name' => 'Level', 'skill_id' => $skill->id, 'key' => 'level'],
                ['info_original' => ['value' => $data['level']]]
            );
            SkillParam::firstOrCreate(
                ['name' => 'Name', 'skill_id' => $skill->id, 'key' => 'name'],
                ['info_original' => ['value' => $data['name']]]
            );
            SkillParam::firstOrCreate(
                ['name' => '', 'skill_id' => $skill->id, 'key' => 'info'],
                ['info_original' => $data['info']]
            );

            SkillParam::firstOrCreate(
                ['name' => 'Description', 'skill_id' => $skill->id, 'key' => 'description'],
                ['info_original' => ['value' => $data['description']]]
            );
            SkillParam::firstOrCreate(
                ['name' => 'Update params', 'skill_id' => $skill->id, 'key' => 'update_lvl'],
                ['info_original' => $data['update_lvl']]
            );
            SkillParam::firstOrCreate(
                ['name' => 'Unlocked at level', 'skill_id' => $skill->id, 'key' => 'unlocked_lvl'],
                ['info_original' => ['value' => $data['unlocked_lvl']]]
            );

            $imagePath = (new UploadImage())->saveImageFromUrl($data['image'], $skill->id, 'skills');
            $skill->image = $imagePath;
            $skill->save();

            DB::commit();
            return $skill;
        }
        catch (\Throwable $exception) {
            DB::rollBack();
            throw ValidationException::withMessages(['message' => $exception->getMessage()]);
        }
    }
}
