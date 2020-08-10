<?php

namespace App\Http\Controllers\Ajax;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;

class RobotsController extends Controller
{

    const FTP = 21;
    const SSH = 22;
    const FILE = '/robots.txt';
    const FILE_OLD = 'robotsOld.txt';
    const FILE_NEW = 'robotsNew.txt';
    protected static $remoteRobots = null;

    /**
     * @param Request $request
     * @return string
     */
    protected static function sendRobotsToClient(Request $request)
    {
        // получаем данные с аякс запроса
        $input = $request->all();

        // разбор входящих портов
        if (intval($input['robotsPort']) === self::SSH) {
            self::sendRobotsViaSsh($input);
            return ('Запись по ссш успешна');
        } elseif (intval($input['robotsPort']) === self::FTP) {
            self::sendRobotsViaFtp($input);
            return ('Запись по фтп успешна');
        } else {
            return ('Не найдено действие для указанного порта соединения');
        }
    }

    /**
     * @param $input
     */
    protected static function sendRobotsViaFtp(array $input)
    {
        // коннект к удаленному серверу для фтп
        $link = ftp_connect($input['robotsIp']);
        ftp_login($link, $input['robotsLogin'], $input['robotsPass']);

        //формирование новой версии файлов
        File::put($_SERVER['DOCUMENT_ROOT'] . '/storage/app/robots/' .
            $input['robotsIp'] . self::FILE_NEW, $input['robotsVal']);
        File::put($_SERVER['DOCUMENT_ROOT'] . '/storage/app/robots/' . $input['robotsIp'] . self::FILE_OLD, '');

        //проверка файла на существования
        $isRemoteFile = @fopen('http://www.' . $input['robotsName'] . self::FILE, 'r');

        if ($isRemoteFile) {
            //если есть удаленный файл сохранение предыдущей версии файла
            $handle = fopen($_SERVER['DOCUMENT_ROOT'] . '/storage/app/robots/' . $input['robotsIp'] .
                self::FILE_OLD, 'w');
            ftp_fget($link, $handle, $input['robotsPath'] . self::FILE, FTP_ASCII, 0);
            fclose($handle);
        } else {
            //сохранение пустого файла
            self::saveEmptyRobots($input['robotsName']);
        }

        //запись новой версии файла или создание
        $handle2 = fopen($_SERVER['DOCUMENT_ROOT'] . '/storage/app/robots/' . $input['robotsIp'] .
            self::FILE_NEW, 'r');
        ftp_fput($link, $input['robotsPath'] . self::FILE, $handle2, FTP_ASCII);

        // закрытие соединений и файлов
        ftp_close($link);
        fclose($handle2);
    }

    /**
     * @param $input
     */
    protected static function sendRobotsViaSsh(array $input)
    {
        // коннект к удаленному серверу для ссш
        $link = ssh2_connect($input['robotsIp'], $input['robotsPort']);
        ssh2_auth_password($link, $input['robotsLogin'], $input['robotsPass']);

        //формирование новой версии файла
        File::put($_SERVER['DOCUMENT_ROOT'] . '/storage/app/robots/' . $input['robotsIp'] .
            self::FILE_NEW, $input['robotsVal']);

        //проверка файла на существования
        $isRemoteFile = @fopen('http://www.' . $input['robotsName'] . self::FILE, 'r');

        if ($isRemoteFile) {
            //если есть удаленный файл сохранение предыдущей версии файла
            ssh2_scp_recv($link, $input['robotsPath'] . self::FILE, $_SERVER['DOCUMENT_ROOT'] .
                '/storage/app/robots/' . $input['robotsIp'] . self::FILE_OLD);
        } else {
            //сохранение пустого файла
            self::saveEmptyRobots($input['robotsName']);
        }

        //запись новой версии файла
        ssh2_scp_send($link, $_SERVER['DOCUMENT_ROOT'] . '/storage/app/robots/' .
            $input['robotsIp'] . self::FILE_NEW, $input['robotsPath'] . self::FILE, 0644);

        //закрываем соединение
        ssh2_disconnect($link);
    }

    /**
     * @param Request $request
     * @return bool|string
     */
    protected static function getRobotsFromClient(Request $request)
    {
        // получаем реквест
        $input = $request->all();
        // получаем контент файла и возвращаем
        $remoteRobots = file_get_contents('http://' . $input['robotsName'] . self::FILE);
        return $remoteRobots;
    }

    /**
     * @param $data
     */
    protected static function saveEmptyRobots(string $data)
    {
        //сохранение пустого файла
        $newRobots = fopen($_SERVER['DOCUMENT_ROOT'] . '/storage/app/robots/' . $data . self::FILE_OLD, "w");
        fwrite($newRobots, "");
        fclose($newRobots);
    }
}
