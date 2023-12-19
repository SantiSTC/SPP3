<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as ResponseMW;

require_once "accesoDatos.php";
require_once __DIR__ . "/autentificadora.php";

class MW {
    public static function ValidarParamVacios(Request $request, RequestHandler $handler): ResponseMW {
        $contenidoAPI = "";

        $params = $request->getParsedBody();

        $objRetorno = new stdclass();
        $objRetorno->mensaje = "Error";
        $objRetorno->status = 409;

        if(isset($params['user']) || isset($params['usuario'])){
            $objUsuario = isset($params['user']) != null ? json_decode($params['user']) : json_decode($params['usuario']);

            if($objUsuario){
                if($objUsuario->correo != "" && $objUsuario->clave != ""){
                    $response = $handler->handle($request);
                    $contenidoAPI = (string) $response->getBody();
                    $api_respuesta = json_decode($contenidoAPI);
                    $objRetorno->status = $api_respuesta->status;
                } else {
                    $mensajeError = "Parametros vacios: ";
                    if($objUsuario->correo == ""){
                        $mensajeError.= "Correo - ";
                    }
                    if($objUsuario->clave == ""){
                        $mensajeError.= "Clave - ";
                    }
                    $objRetorno->mensaje = $mensajeError;
                    $contenidoAPI = json_encode($objRetorno);
                }
            }
        } else {
            $objRetorno->mensaje = "Error, Usuario o Contraseña vacios.";
            $contenidoAPI = json_encode($objRetorno);
        }
        $response = new ResponseMW();
        $response = $response->withStatus($objRetorno->status);
        $response->getBody()->write($contenidoAPI);
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function ValidarCorreoYClaveExisten(Request $request, RequestHandler $handler): ResponseMW {
        $parametros = $request->getParsedBody();

        $objetoRetorno = new stdclass();
        $objetoRetorno->mensaje = "El correo o la clave son inexistentes.";
        $objetoRetorno->status = 403;
        $objetoUsuario = null;

        if(isset($parametros['user'])) {
            $objetoUsuario = json_decode($parametros['user']);

            if(Usuario::verificar($objetoUsuario)) {
                $retorno = $handler->handle($request);
                $contenidoAPI = (string) $retorno->getBody();
                $api_respuesta = json_decode($contenidoAPI);
                $objetoRetorno->status = $api_respuesta->status;
            } else {
                $contenidoAPI = json_encode($objetoRetorno);
            }
        }

        $retorno = new ResponseMW();
        $retorno = $retorno->withStatus($objetoRetorno->status);
        $retorno->getBody()->write($contenidoAPI);

        return $retorno->withHeader('Content-Type', 'application/json');
    }

    public function ValidarToken(Request $request, RequestHandler $handler): ResponseMW {
        $contenidoAPI = "";
        $objetoRetorno = new stdClass();
        $objetoRetorno->exito = false;
        $objetoRetorno->status = 403;

        if (isset($request->getHeader("token")[0])) {
            $token = $request->getHeader("token")[0];

            $obj = Autentificadora::verificarJWT($token);

            if ($obj->verificado) {
                $retorno = $handler->handle($request);
                $contenidoAPI = (string) $retorno->getBody();
                $api_respuesta = json_decode($contenidoAPI);
                $objetoRetorno->status = $api_respuesta->status;
            } else {
                $contenidoAPI = json_encode($objetoRetorno);
            }

            $objetoRetorno->mensaje = $obj;
        }

        $retorno = new ResponseMW();
        $retorno = $retorno->withStatus($objetoRetorno->status);
        $retorno->getBody()->write($contenidoAPI);

        return $retorno->withHeader('Content-Type', 'application/json');
    }

    public static function ListarUsuarios_Get(Request $request, RequestHandler $handler): ResponseMW {
        $objetoRetorno = new stdclass();
        $objetoRetorno->exito = false;
        $objetoRetorno->mensaje = "Error al intentar traer la lista de usuarios.";
        $objetoRetorno->tabla = "null";
        $objetoRetorno->status = 424;

		$listaUsuarios = Usuario::traer();

        if(count($listaUsuarios) > 0) {
            foreach($listaUsuarios as $usuario){
                unset($usuario->clave);
            }

            $objetoRetorno->exito = true;
            $objetoRetorno->mensaje = "Tabla de Usuarios";
            $objetoRetorno->tabla = MW::ArmarTabla($listaUsuarios, "<tr><th>ID</th><th>CORREO</th><th>NOMBRE</th><th>APELLIDO</th><th>FOTO</th><th>PERFIL</th></tr>");
            $objetoRetorno->status = 200;
        }

        $retorno = new ResponseMW();
        $retorno = $retorno->withStatus($objetoRetorno->status);
        $retorno->getBody()->write(json_encode($objetoRetorno));

        return $retorno->withHeader('Content-Type', 'application/json');	
    }

    public function ListarJuguetes_Get(Request $request, RequestHandler $handler): ResponseMW {
        $listaJuguetesImpares = array();

        $objetoRetorno = new stdclass();
        $objetoRetorno->exito = false;
        $objetoRetorno->mensaje = "Error al intentar traer la lista";
        $objetoRetorno->tablaData = "null";
        $objetoRetorno->status = 424;

		$listaJuguetes = Juguete::traer();

        if(count($listaJuguetes) > 0) {
            foreach($listaJuguetes as $juguete) {
                if($juguete->id % 2 != 0) {
                    array_push($listaJuguetesImpares, $juguete);
                }
            }

            $objetoRetorno->exito = true;
            $objetoRetorno->mensaje = "Tabla de juguetes";
            $objetoRetorno->tablaData = MW::ArmarTabla($listaJuguetesImpares, "<tr><th>ID</th><th>MARCA</th><th>PRECIO</th><th>FOTO</th></tr>");
            $objetoRetorno->status = 200; 
        }

        $retorno = new ResponseMW();
        $retorno = $retorno->withStatus($objetoRetorno->status);
        $retorno->getBody()->write(json_encode($objetoRetorno));

        return $retorno->withHeader('Content-Type', 'application/json');	
    }

    public static function ListarUsuarios_Post(Request $request, RequestHandler $handler): ResponseMW {
        $objetoRetorno = new stdclass();
        $objetoRetorno->exito = false;
        $objetoRetorno->mensaje = "Error al intentar listar.";
        $objetoRetorno->status = 403;

        if(isset($request->getHeader('token')[0])){
            $token = $request->getHeader('token')[0];
            $datosToken = Autentificadora::obtenerPayLoad($token);
            $usuarioToken = json_decode($datosToken->payload->data->usuario);
            $perfilUsuario = $usuarioToken->perfil;

            if($perfilUsuario == "propietario") {
                $listaUsuarios = Usuario::traer();

                if(count($listaUsuarios) > 0) {
                    foreach($listaUsuarios as $usuario) {
                        unset($usuario->id);
                        unset($usuario->clave);
                        unset($usuario->foto);
                        unset($usuario->perfil);
                    }

                    $objetoRetorno->exito = true;
                    $objetoRetorno->mensaje = "Listado usuarios";
                    $objetoRetorno->tabla = MW::ArmarTabla($listaUsuarios, "<tr><th>CORREO</th><th>NOMBRE</th><th>APELLIDO</th></tr>");
                    $objetoRetorno->status = 200;
                }
            } else {

                $objetoRetorno->mensaje = "No tiene autorizacion para listar usuarios, se necesita ser propietario y usted es: {$usuarioToken->perfil}";
            }
        }

        $retorno = new ResponseMW();
        $retorno = $retorno->withStatus($objetoRetorno->status);
        $retorno->getBody()->write(json_encode($objetoRetorno));

        return $retorno->withHeader('Content-Type', 'application/json');	
    }

    public static function ArmarTabla(array $lista, string $header) : string {
        $tabla = '<table class="table table-hover">';

        $tabla .= $header;
        
        foreach($lista as $item) {
            $tabla .= "<tr>";

            foreach ($item as $key => $value){
                if ($key == "perfil") {
                    $tabla .= "<td><img src='{$value}' width=25px></td>";
                } else {
                     $tabla .= "<td>{$value}</td>";
                }
            }
                
            $tabla .= "</tr>";
        }

        $tabla .= '</table>';

        return $tabla;
    }

    public function ValidarCorreoNoExistente(Request $request, RequestHandler $handler): ResponseMW {
        $parametros = $request->getParsedBody();

        $objetoRetorno = new stdclass();
        $objetoRetorno->mensaje = "Error. El correo ya existe. Ingrese otro.";
        $objetoRetorno->status = 403;
        $objetoUsuario = null;

        if(isset($parametros['usuario'])) {
            $objetoUsuario = json_decode($parametros['usuario']);

            if(!Usuario::verificarCorreo($objetoUsuario)) {
                $retorno = $handler->handle($request);
                $contenidoAPI = (string) $retorno->getBody();
                $api_respuesta = json_decode($contenidoAPI);
                $objetoRetorno->status = $api_respuesta->status;
            } else {
                $contenidoAPI = json_encode($objetoRetorno);
            }
        }

        $retorno = new ResponseMW();
        $retorno = $retorno->withStatus($objetoRetorno->status);
        $retorno->getBody()->write($contenidoAPI);

        return $retorno->withHeader('Content-Type', 'application/json');
    }

}


?>