<?php

namespace App\Http\Controllers\Ajax;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class StringSearchController extends Controller
{

    const FTP = 21;
    const SSH = 22;
    protected static $localPath = null;
    protected static $searchSting = null;
    protected static $tmpVar = null;

    protected static function remoteFilesDownload(Request $request)
    {
        // получаем данные с аякс запроса
        $input = $request->all();
        self::$localPath = $_SERVER['DOCUMENT_ROOT'] . '/storage/app/public/remoteSearchDirs/';

        // разбор входящих портов
        if (intval($input['ssPort']) === self::SSH) {
            return self::downloadFilesViaSsh($input);
        } elseif (intval($input['ssPort']) === self::FTP) {
            return self::downloadFilesViaFtp($input);
        } else {
            return self::downloadFilesViaUnusualPort($input);
        }
    }

    protected static function downloadFilesViaFtp(array $input)
    {
        // настройки для wget строки
        $ftp_server = $input['ssIp'];
        $user = $input['ssLogin'];
        $password = $input['ssPass'];
        $document_root = $input['ssPath'];
        self::$localPath = $_SERVER['DOCUMENT_ROOT'] . '/storage/app/public/remoteSearchDirs/';

        // загрузка нужных файлов
        $cmd = "wget -r --no-passive --level=100 -b -np -A html,php,tpl,css,shtml,htaccess,js,txt,json,xml 'ftp://" . $user .
            ":" . urlencode($password) . "@" . $ftp_server . "" . $document_root . "'";
        chdir(self::$localPath);
        exec($cmd);

        return 'Процесс бекапа поставлен в очередь. Стандартное время на создание файлового бекапа 15 - 30 минут.';
    }

    protected static function downloadFilesViaSsh(array $input)
    {
        self::$localPath = $_SERVER['DOCUMENT_ROOT'] . '/storage/app/public/remoteSearchDirs/';
        chdir(self::$localPath);
        // создание папки с бекапом если ее нету
        if (!file_exists($input['ssIp']) && !is_dir($input['ssIp'])) {
            mkdir($input['ssIp']);
        }
        $cmd2 = 'sshpass -p "' . $input['ssPass'] . '" rsync -r  --exclude="*.gif"  --exclude="*.png" --exclude="*.jpg" --exclude="*.jpeg" --exclude="*.tar" --exclude="*.gz" --exclude="*.rar" --exclude="*.zip" --exclude="*.ico" --exclude="bitrix/backup/" --exclude="bitrix/cache/" -e "ssh -o StrictHostKeyChecking=no" ' . $input['ssLogin'] . '@' . $input['ssIp'] . ':' . $input['ssPath'] . ' ' . $_SERVER['DOCUMENT_ROOT'] . '/storage/app/public/remoteSearchDirs/178.20.237.7';
        exec($cmd2);
        return 'Процесс бекапа завершен.';
    }

    protected static function downloadFilesViaUnusualPort(array $input)
    {
        // настройки для wget строки
        $ftp_server = $input['ssIp'];
        $user = $input['ssLogin'];
        $password = $input['ssPass'];
        $document_root = $input['ssPath'];
        $port = $input['ssPort'];
        self::$localPath = $_SERVER['DOCUMENT_ROOT'] . '/storage/app/public/remoteSearchDirs/';

        // загрузка нужных файлов
        $cmd = "wget -r --no-passive --level=100 -b -np -A html,php,tpl,css,shtml,htaccess,js,txt,json,xml 'ftp://" . $user .
            ":" . urlencode($password) . "@" . $ftp_server . ":" . $port . "" . $document_root . "'";
        chdir(self::$localPath);
        exec($cmd);

        return 'Процесс бекапа поставлен в очередь. Стандартное время на создание файлового бекапа 15 - 30 минут.';
    }

    protected static function isFileBackupReady(Request $request)
    {
        // получаем данные с аякс запроса
        $input = $request->all();
        self::$localPath = $_SERVER['DOCUMENT_ROOT'] . '/storage/app/public/remoteSearchDirs/';
        // проверяем готовность бекапа
        $existsDir = file_exists(self::$localPath . $input['irIp']);
        $existsDir2 = file_exists(self::$localPath . $input['irIp'] . ':' . $input['irPort']);
        if ($existsDir === true) {
            return 1;
        } elseif ($existsDir2 === true) {
            return 1;
        } else {
            return 2;
        }
    }

    protected static function stringSearch(Request $request)
    {
        // получаем данные с аякс запроса
        $input = $request->all();
        $returnData = '';
        // добавление не стандартного порта
        if (($input['ssPort'] != self::SSH) and ($input['ssPort'] != self::FTP)) {
            $curport = ':' . $input['ssPort'];
        } else {
            $curport = '';
        }
        self::$localPath = $_SERVER['DOCUMENT_ROOT'] . '/storage/app/public/remoteSearchDirs/';
        // грепаем строку по бекапу
        $cmd = 'grep -rnw "' . addslashes($input["ss"]) . '" "' . self::$localPath . $input['ssIp'] . $curport . '/" >  ' . self::$localPath . $input['ssIp'] . $curport . '.txt';
        exec($cmd);
        // если файл существует
        if (file_exists(self::$localPath . $input["ssIp"] . $curport . '.txt')) {
            // если файл пустой
            if (0 == filesize(self::$localPath . $input["ssIp"] . $curport . '.txt')) {
                $returnData = "По поиску ничего не найдено";
            } else {
                $resFile = '/remoteSearchDirs/' . $input["ssIp"] . $curport . '.txt';
                $returnData .= 'Список найденных совпадений можно скачать по ссылке:' . PHP_EOL;
                $returnData .= '<a download href="' . $resFile . '">Результат поиска по строке</a>';
            }
            return $returnData;
        } else {
            // если файл не создался
            return "По поиску ничего не найдено";
        }
    }
}
