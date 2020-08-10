<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::group(['prefix' => 'admin'], function () {
    Voyager::routes();
});

Route::post('/rewriterobots', 'Ajax\RobotsController@sendRobotsToClient');

Route::post('/rewritesitemap', 'Ajax\SitemapController@sendSitemapToClient');

Route::post('/getremoterobots', 'Ajax\RobotsController@getRobotsFromClient');

Route::post('/pagevalidator', 'Ajax\PagevalidatorController@validateUrls');

Route::post('/redirector', 'Ajax\RedirectorController@sendCsvToClient');

Route::post('/downloadremotefiles', 'Ajax\StringSearchController@remoteFilesDownload');

Route::post('/isfilebackupready', 'Ajax\StringSearchController@isFileBackupReady');

Route::post('/stringsearch', 'Ajax\StringSearchController@stringSearch');