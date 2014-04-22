<?php
require_once '../vendor/autoload.php';

use \Jloosli\SwaggerDoc;

$composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'));

$app = new \Slim\Slim();
$app->add(new SwaggerDoc('/swagger.json', array(
    'apiVersion'     => '1.0',
    'swaggerVersion' => '1.2',
//    'basePath'       => 'http://' . $_SERVER['HTTP_HOST'],
    'resourcePath'   => '/api/v1'
)));

$checkRoute = function (\Slim\Route $rt) {
    echo "<pre>";
    print_r($rt);
    echo "</pre>";
};

// Version endpoint.
$app->get('/version', \Jloosli\SwaggerDoc::routeDoc(array('bob', 'june')), $checkRoute, function () use ($app, $composer) {
    $app->response->write($composer->version);
})->name('version');
// Version endpoint.

$getRoute = $app->get('/get/:first/:second',$checkRoute, function ($first='', $optional='') use ($app, $composer) {
    $app->response->write("GET");
})->name('get')
->swagger = array(
    'summary' => 'Here is the summary of this path',
    'notes' => 'Another note about how this works',
    'type' => 'theType',
    'nickname' =>'theNickname',
    'parameters' => array('should','be','self','generating'),
    'responseMessages'=>array('404','304')
);

$app->post('/post', \Jloosli\SwaggerDoc::routeDoc(array('bob', 'june')), function (\Slim\Route $rt) {
    echo "<pre>";
    print_r($rt);
    echo "</pre>";
}, function () use ($app, $composer) {
    $app->response->write("POST");
})->name('post');

$app->put('/put', \Jloosli\SwaggerDoc::routeDoc(array('bob', 'june')), function (\Slim\Route $rt) {
    echo "<pre>";
    print_r($rt);
    echo "</pre>";
}, function () use ($app, $composer) {
    $app->response->write("PUT");
})->name('put');

$app->map('/map/:firstArg/(:optionalSecond)', \Jloosli\SwaggerDoc::routeDoc(array('bob', 'june')), function (\Slim\Route $rt) {
    echo "<pre>";
    print_r($rt);
    echo "</pre>";
}, function () use ($app, $composer) {
    $app->response->write("MAP");
})->via('GET', 'OPTIONS')->name('map');

$app->run();
