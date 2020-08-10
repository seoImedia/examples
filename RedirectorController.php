<?php

namespace App\Http\Controllers\Ajax;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class RedirectorController extends Controller
{

    const FTP = 21;
    const SSH = 22;
    const FILE = '/seoRedirects.csv';
    const FILE_OLD = 'seoRedirectsOld.csv';
    const FILE_NEW = 'seoRedirectsNew.csv';
    protected static $file = null;

    /**
     * @param Request $request
     * @return string
     */
    protected static function sendCsvToClient(Request $request)
    {
        // получаем данные с аякс запроса
        $input = $request->all();

        // если есть файл сайтмепа
        if ($input['redirectorInput'] != '') {
            //сохраняем его
            self::$file = $request->file('redirectorInput');
            self::$file->move($_SERVER['DOCUMENT_ROOT'] .
                '/storage/app/redirectors', $input['redirectorIp'] . self::FILE_NEW);

            // разбор входящих портов
            if (intval($input['redirectorPort']) === self::SSH) {
                self::sendCsvViaSsh($input);
                return ('Запись по ссш успешна');
            } elseif (intval($input['redirectorPort']) === self::FTP) {
                self::sendCsvViaFtp($input);
                return ('Запись по фтп успешна');
            } else {
                return ('Не найдено действие для указанного порта соединения');
            }
        }
    }

    /**
     * @param $input
     */
    protected static function sendCsvViaFtp(array $input)
    {
        // коннект к удаленному серверу для фтп
        $link = ftp_connect($input['redirectorIp']);
        ftp_login($link, $input['redirectorLogin'], $input['redirectorPass']);

        //сохранение пустого файла
        self::saveEmptyCsv($input['redirectorIp']);

        //запись новой версии файла на клиенте
        $handle2 = fopen($_SERVER['DOCUMENT_ROOT'] . '/storage/app/redirectors/' .
            $input['redirectorIp'] . self::FILE_NEW, 'r');
        ftp_fput($link, $input['redirectorPath'] . self::FILE, $handle2, FTP_ASCII);

        // закрытие соединений и файлов
        ftp_close($link);
        fclose($handle2);
    }

    /**
     * @param $input
     */
    protected static function sendCsvViaSsh(array $input)
    {
        // коннект к удаленному серверу для ссш
        $link = ssh2_connect($input['redirectorIp'], $input['redirectorPort']);
        ssh2_auth_password($link, $input['redirectorLogin'], $input['redirectorPass']);

        //сохранение пустого файла
        self::saveEmptyCsv($input['redirectorIp']);

        //запись новой версии файла
        ssh2_scp_send($link, $_SERVER['DOCUMENT_ROOT'] . '/storage/app/redirectors/' . $input['redirectorIp'] .
            self::FILE_NEW, $input['redirectorPath'] . self::FILE, 0644);

        //закрываем соединение
        ssh2_disconnect($link);
    }

    /**
     * @param $data
     */
    protected static function saveEmptyCsv(string $data)
    {
        //сохранение пустого файла
        $newRedirector = fopen($_SERVER['DOCUMENT_ROOT'] . '/storage/app/redirectors/' . $data . self::FILE_OLD, "w");
        fwrite($newRedirector, "");
        fclose($newRedirector);
    }
}