<?php

//Iannello Santiago - Segundo Parcial Programacion III

use Slim\Factory\AppFactory;
use \Slim\Routing\RouteCollectorProxy;

require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . "/../poo/usuario.php";
require_once __DIR__ . "/../poo/MW.php";
require_once __DIR__ . "/../poo/juguete.php";

$app = AppFactory::create();

$app->get('/', Usuario::class . ':traerTodos');
$app->get('/juguetes', Juguete::class . ':traerTodos');

$app->post('/', \Juguete::class . ':agregarUno');
$app->post('/login', \Usuario::class . ':login')
->add(\MW::class . ':ValidarCorreoYClaveExisten')
->add(\MW::class . '::ValidarParamVacios');        

$app->get('/login', Usuario::class . ':verificarJWT');

$app->group('/toys', function (RouteCollectorProxy $grupo) {   
    $grupo->delete('/{id}', \Juguete::class . ':borrarUno');
    $grupo->post('/', \Juguete::class . ':modificarUno')
    ->add(\MW::class . ':ValidarToken');
});

$app->group('/tablas', function (RouteCollectorProxy $grupo) {   
    $grupo->get('/usuarios', Usuario::class . ':traerTodos')
    ->add(\MW::class . '::ListarUsuarios_Get');

    $grupo->post('/usuarios', \Usuario::class . ':traerTodos')
    ->add(\MW::class . ':ValidarToken')
    ->add(\MW::class . '::ListarUsuarios_Post');

    $grupo->get('/juguetes', Juguete::class . ':traerTodos')
    ->add(\MW::class . ':ValidarToken')
    ->add(\MW::class . ':ListarJuguetes_Get');
});        

$app->post('/usuarios', \Usuario::class . ':agregarUno')
->add(\MW::class . ':ValidarCorreoNoExistente') 
->add(\MW::class . '::ValidarParamVacios')
->add(\MW::class . ':ValidarToken'); 

try 
{
   $app->run();
}
catch (Exception $e) 
{
    die(json_encode(array("status" => "failed", "message" => "Accion no permitida.")));
}
?>