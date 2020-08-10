<?php

namespace App\Http\Controllers\Ajax;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PagevalidatorController extends Controller
{

    protected static $finalSave = null;

    /**
     * @param Request $request
     */
    protected static function validateUrls(Request $request)
    {
        // получаем реквест
        $input = $request->all();
        $textboxCnt = count($input['textbox']);
        $finReturn = null;

        // валидация урлов
        for ($t=0; $t<$textboxCnt; $t++) {
            self::$finalSave .= self::validateUrl($input['textbox'][$t], $finReturn);
        }

        // работа с бд
        self::saveToDb(self::$finalSave);
    }

    /**
     * @param $urlValue
     * @param $finReturn
     * @return string
     */
    protected static function validateUrl(string $urlValue, null $finReturn)
    {
        // подсчет всех открытых и закрытых тегов
        $html = file_get_contents($urlValue);
        preg_match_all('#<([a-z]+)(?: .*)?(?<![/|/ ])>#iU', $html, $result);
        $openedtags = $result[1];
        preg_match_all('#</([a-z]+)>#iU', $html, $result);
        $closedtags = $result[1];
        $len_opened = count($openedtags);
        $finRes = '';
        $finCount = 0;
        $openedtags = array_reverse($openedtags);
        // массив исключений, теги - которые не закрываются, согласно принятым стандартам
        $tagExeption = array('input', 'br', 'Br', 'img', 'meta', 'link', 'base', 'path', 'hr', 'html');
        // проходимся по обоим массивам и ищем не закрытые теги
        for ($i=0; $i < $len_opened; $i++) {
            if (in_array($openedtags[$i], $tagExeption)) {
            } else {
                if (!in_array($openedtags[$i], $closedtags)) {
                    $finCount++;
                    $finRes .= ' Тег ' . $openedtags[$i] . ' не закрыт';
                } else {
                    unset($closedtags[array_search($openedtags[$i], $closedtags)]);
                }
            }
        }

        // фиксируем результаты проверки
        if ($finRes=='') {
            $finReturn .= '<p class="resNoUrls">По урлу <a href="'.$urlValue.'">'.$urlValue
                .'</a> не закрытых тегов не найдено.</p>';
        } else {
            $finReturn .= '<p class="resHasUrls">По урлу <a href="'.$urlValue.'">'.$urlValue
                .'</a> количество не закрытых тегов - '.$finCount.' Детально: '.$finRes.'.</p>';
        }

        return $finReturn;
    }

    /**
     * @param $data
     * @return string
     */
    protected static function saveToDb($data)
    {

        // вызов модели и добавление записи по результатам проверки
        $pagevalidator = new \App\Pagevalidator();
        $pagevalidator->result = $data;
        $pagevalidator->save();

        return 'Запись успешно добавлена';
    }
}