<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Skill\TranslateRequest;
use App\Jobs\TransferSkillToSiteJob;
use App\Models\Skill;
use App\Services\Skill\Tl\ParserServerService;
use App\Services\Skill\Tl\SkillService;
use App\Services\Skill\Tl\SkillTranslatorService;
use App\Services\SocketService;
use DOMDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SkillController extends Controller
{
    public function index(SkillService $skillService)
    {
//        dd(env('APP_LOCALE'));
//        dd($_ENV);
//        dd(env('APP_ENV'));
        $skills = $skillService->getSkillsAllStorage();

        return Inertia::render('Admin/Skills/Index', ['skills' => $skills->toArray()]);
    }

    public function getSkillsAllStorage(SkillService $skillService)
    {
        $skills = $skillService->getSkillsAllStorage();

        return response()->json($skills);
    }

    public function transferToSite(Request $request, SkillService $skillService)
    {
        $skillService->transferToSite($request->all());

        $skills = $skillService->getSkillsAllStorage();

        return response()->json($skills);
    }

    public function transferToSiteAll(SkillService $skillService)
    {
        $skillsParser = (new ParserServerService())->getSkillsAll();
        foreach ($skillsParser as $skillsParserItem){
            TransferSkillToSiteJob::dispatch($skillsParserItem);
//            break;
        }

        $skills = $skillService->getSkillsAllStorage();

        return response()->json($skills);
    }

    public function translate(
        TranslateRequest $request,
        SkillTranslatorService $skillTranslatorService,
        SkillService $skillService,
    )
    {
        $skill = Skill::query()->with(['params'])->where('skill_link_id', $request->skill_link_id)->first();

        $arTypeLevel = $this->textToTranslateJson($request->type_level);
        $arType = $this->textToTranslateJson($request->type);
        $arLevel = [
            'content' => $request->level,
            'content_ru' => $request->level,
            'items' => [],
        ];
        $nameOriginal = preg_replace('/ Lv\. \d+$/', '', $request->name);
        $name = $skillTranslatorService->translator($nameOriginal);

        $arInfo = Arr::map($request->info ?? [], function ($infoItem) use ($skillTranslatorService){
            return [
                'name_original' => $infoItem['name'],
                'value_original' => $infoItem['value'],
                'name' => $skillTranslatorService->translator($infoItem['name']),
                'value' => $skillTranslatorService->translator($infoItem['value']),
            ];
        });
//        dd(str_replace("<br />", "\n", str_replace("\n", '', $request->description)));
        $arDescription = $this->textToTranslateJson(str_replace("\n", '', $request->description));
        $arUpdateLvl = Arr::map($request->update_lvl ?? [], function ($updateLvlItem) use ($skillTranslatorService){
            return [
                'name_original' => $updateLvlItem['name'],
                'value_original' => $updateLvlItem['value'],
                'name' => $skillTranslatorService->translator($updateLvlItem['name']),
                'value' => $skillTranslatorService->translator($updateLvlItem['value']),
            ];
        });
        [$nameUnlockedLvl, $lvlUnlockedLvl] = explode(':', $request->unlocked_lvl);
        $arUnlockedLvl = [
            'original_name' => $nameUnlockedLvl,
            'name' => $skillTranslatorService->translator($nameUnlockedLvl),
            'value' => $lvlUnlockedLvl,
        ];

        try{
            DB::beginTransaction();

            $skill->name = $nameOriginal;
            $skill->name_ru = $name;
            foreach ($skill->params as $paramKey=>$paramItem){
                if($paramItem->key === 'type_level'){
                    $skill->params[$paramKey]->info = $arTypeLevel;
                    $skill->params[$paramKey]->save();
                }
                elseif ($paramItem->key === 'type'){
                    $skill->params[$paramKey]->info = $arType;
                    $skill->params[$paramKey]->save();
                }
                elseif ($paramItem->key === 'level'){
                    $skill->params[$paramKey]->info = $arLevel;
                    $skill->params[$paramKey]->save();
                }
                elseif ($paramItem->key === 'name'){
                    $skill->params[$paramKey]->info_original = ['value' => $nameOriginal];
                    $skill->params[$paramKey]->info = ['value' => $name];
                    $skill->params[$paramKey]->save();
                }
                elseif ($paramItem->key === 'info'){
                    $skill->params[$paramKey]->info = $arInfo;
                    $skill->params[$paramKey]->save();
                }
                elseif ($paramItem->key === 'description'){
                    $skill->params[$paramKey]->info = $arDescription;
                    $skill->params[$paramKey]->save();
                }
                elseif ($paramItem->key === 'update_lvl'){
                    $skill->params[$paramKey]->info = $arUpdateLvl;
                    $skill->params[$paramKey]->save();
                }
                elseif ($paramItem->key === 'unlocked_lvl'){
                    $skill->params[$paramKey]->info = $arUnlockedLvl;
                    $skill->params[$paramKey]->save();
                }
            }

            $skill->save();

            DB::commit();
        }
        catch (\Throwable $exception) {
            DB::rollBack();
            throw ValidationException::withMessages(['message' => 'Ошибка при пеерводе умения.']);
        }

        $skills = $skillService->getSkillsAllStorage();

        return response()->json($skills);
    }

    public function translateAll(SkillService $skillService)
    {
        $skills = $skillService->getSkillsAll();
        $skills = $skills->map(function ($skillItem) use($skillService) {
            dd($skillService->formatDataInfo($skillItem));
            return $skillService->formatDataInfo($skillItem);
        });

        return $skills;
    }

    private function textToTranslateJson(string $text): array
    {
        $skillTranslatorService = new SkillTranslatorService();
        $arText = $this->convertHtmlToJson($text);

        $arTextOriginals = array_column($arText['items'], 'text_original');
        $content = preg_replace('#<br\s*/?>#i', "\n", $arText['content']);

        $arContent = explode("\n", $content);
        $contentRu = '';
        foreach ($arContent as $contentItem){
            if($contentItem){
                $contentRu .= $skillTranslatorService->translator($contentItem) . "\n";
            }
            else{
                $contentRu .= "\n";
            }
        }

        $arText['content_ru'] = $this->replacePlaceholders($contentRu, $arTextOriginals);
        $arText['items'] = Arr::map($arText['items'], function ($item) use ($skillTranslatorService) {
            $item['text_ru'] = $skillTranslatorService->translator($item['text_original']);
            return $item;
        });

        return $arText;
    }

    /**
     *  Из HTML форируем json
     * @param $htmlString
     * @return array
     */
    private function convertHtmlToJson($htmlString) {
        // Создаем новый объект DOMDocument
        $dom = new DOMDocument();
        // Загружаем HTML строку
        @$dom->loadHTML($htmlString, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Массив для хранения найденных элементов
        $items = [];

        // Проходим по всем элементам
        foreach ($dom->getElementsByTagName('span') as $element) {
            // Извлекаем текст элемента
            $text = $element->textContent;

            // Извлекаем все атрибуты элемента
            $attributes = [];
            foreach ($element->attributes as $attr) {
                $attributes[$attr->name] = $attr->value;
            }

            // Добавляем элемент в массив items
            $items[] = [
                'text_original' => $text,
                'attributes' => $attributes,
            ];

            // Заменяем текст элемента на {{текст}}
            $htmlString = str_replace($element->ownerDocument->saveHTML($element), '[[' . $text . ']]', $htmlString);
        }

        // Формируем итоговый JSON
        $result = [
            'content' => $htmlString,
            'items' => $items,
        ];

        return $result;
//        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Заменяем содержимое [[]] на значения переданные в массиве
     * В этом тексте "Особый навык роста, [[который]] может быть [[который]]" - Заменить на ['может', 'нет']
     * @param $string
     * @param $replacements
     * @return array|mixed|string|string[]
     */
    private function replacePlaceholders($string, $replacements) {
        // Находим все подстроки в формате [[...]] с помощью регулярного выражения
        preg_match_all('/\[\[(.*?)\]\]/', $string, $matches);

        // Проверяем, сколько найдено подстрок
        $placeholders = $matches[0]; // полные строки с "[[...]]"
        $keys = $matches[1] ?? []; // текст внутри скобок

        // Проходим по каждому ключу и заменяем его на соответствующее значение из массива $replacements
        foreach ($keys as $index => $key) {
            if (isset($replacements[$index])) {
                $string = str_replace($placeholders[$index], '[[' . $replacements[$index] . ']]', $string);
            }
        }

        return $string;
    }

// Пример использования функции
//$htmlString = 'A special growth skill that can only be grown with <span style="color: #FEE79E">Precious Skill Growth Book: Omnipotence</span>. Resets the cooldown of the recently used skill. This is only available when a resettable skill exists, and it is not affected by Skill cooldown speed and skill cooldown reduction. <span style="color: #F54451">Not Applicable Skills</span> <span style="color: #FEE79E">Immortal Pride, Devastating Smash, Frost Smokescreen, Judgment Lightning, Strafing, and Arrow Vortex</span>';
//
//$jsonResult = convertHtmlToJson($htmlString);
//echo $jsonResult;

}
