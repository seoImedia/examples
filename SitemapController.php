<?php

namespace App\Http\Controllers\Ajax;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SitemapController extends Controller
{

    const FTP = 21;
    const SSH = 22;
    const FILE = '/sitemap.xml';
    const FILE_OLD = 'sitemapOld.xml';
    const FILE_NEW = 'sitemapNew.xml';
    protected static $file = null;

    /**
     * @param Request $request
     * @return string
     */
    protected static function sendSitemapToClient(Request $request)
    {
        // получаем данные с аякс запроса
        $input = $request->all();

        // если есть файл сайтмепа
        if ($input['sitemapInput'] != '') {
            //сохраняем его
            self::$file = $request->file('sitemapInput');
            self::$file->move($_SERVER['DOCUMENT_ROOT'] .
                '/storage/app/sitemaps', $input['sitemapIp'] . self::FILE_NEW);

            // разбор входящих портов
            if (intval($input['sitemapPort']) === self::SSH) {
                self::sendSitemapViaSsh($input);
                return ('Запись по ссш успешна');
            } elseif (intval($input['sitemapPort']) === self::FTP) {
                self::sendSitemapViaFtp($input);
                return ('Запись по фтп успешна');
            } else {
                return ('Не найдено действие для указанного порта соединения');
            }
        }
    }

    /**
     * @param $input
     */
    protected static function sendSitemapViaFtp(array $input)
    {
        // коннект к удаленному серверу для фтп
        $link = ftp_connect($input['sitemapIp']);
        ftp_login($link, $input['sitemapLogin'], $input['sitemapPass']);

        //проверка файла на существование
        $isRemoteFile = @fopen('http://www.' . $input['sitemapName'] . self::FILE, 'r');

        if ($isRemoteFile) {
            //если есть удаленный файл сохранение предыдущей версии файла
            $handle = fopen($_SERVER['DOCUMENT_ROOT'] . '/storage/app/sitemaps/' .
                $input['sitemapIp'] . self::FILE_OLD, 'w');
            ftp_fget($link, $handle, $input['sitemapPath'] . self::FILE, FTP_ASCII, 0);
            fclose($handle);
        } else {
            //сохранение пустого файла
            self::saveEmptySitemap($input['sitemapIp']);
        }

        //запись новой версии файла на клиенте
        $handle2 = fopen($_SERVER['DOCUMENT_ROOT'] . '/storage/app/sitemaps/' .
            $input['sitemapIp'] . self::FILE_NEW, 'r');
        ftp_fput($link, $input['sitemapPath'] . self::FILE, $handle2, FTP_ASCII);

        // закрытие соединений и файлов
        ftp_close($link);
        fclose($handle2);
    }

    /**
     * @param $input
     */
    protected static function sendSitemapViaSsh(array $input)
    {
        // коннект к удаленному серверу для ссш
        $link = ssh2_connect($input['sitemapIp'], $input['sitemapPort']);
        ssh2_auth_password($link, $input['sitemapLogin'], $input['sitemapPass']);

        //проверка файла на существование
        $isRemoteFile = @fopen('http://www.' . $input['sitemapName'] . self::FILE, 'r');

        if ($isRemoteFile) {
            //если есть удаленный файл сохранение предыдущей версии файла
            ssh2_scp_recv($link, $input['sitemapPath'] . self::FILE, $_SERVER['DOCUMENT_ROOT'] .
                '/storage/app/sitemaps/' . $input['sitemapIp'] . self::FILE_OLD);
        } else {
            //сохранение пустого файла
            self::saveEmptySitemap($input['sitemapIp']);
        }

        //запись новой версии файла
        ssh2_scp_send($link, $_SERVER['DOCUMENT_ROOT'] . '/storage/app/sitemaps/' . $input['sitemapIp'] .
            'sitemapNew.xml', $input['sitemapPath'] . self::FILE, 0644);

        //закрываем соединение
        ssh2_disconnect($link);
    }

    /**
     * @param $data
     */
    protected static function saveEmptySitemap(string $data)
    {
        //сохранение пустого файла
        $newSitemap = fopen($_SERVER['DOCUMENT_ROOT'] . '/storage/app/sitemaps/' . $data . self::FILE_OLD, "w");
        fwrite($newSitemap, "");
        fclose($newSitemap);
    }

}
