<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as ResponseMW;

require_once "accesoDatos.php";
require_once __DIR__ . "/autentificadora.php";

class MW {
    public static function ValidarParametrosVacios(Request $request, RequestHandler $handler): ResponseMW {
        $contenidoAPI = "";

        $parametros = $request->getParsedBody();

        $objetoRetorno = new stdclass();
        $objetoRetorno->mensaje = "Hay un parametro vacio";
        $objetoRetorno->status = 409;

        if(isset($parametros['user'])) {
            $objetoUsuario = json_decode($parametros['user']);

            if($objetoUsuario){
                if($objetoUsuario->correo != "" && $objetoUsuario->clave != "") {
                    $response = $handler->handle($request);
                    $contenidoAPI = (string) $response->getBody();
                    $api_respuesta = json_decode($contenidoAPI);
                    $objetoRetorno->status = $api_respuesta->status;
                } else {
                    $mensajeError = "Parametros vacios: ";

                    if($objetoUsuario->correo == "")
                    {
                        $mensajeError.= "correo - ";
                    }

                    if($objetoUsuario->clave == "")
                    {
                        $mensajeError.= "clave";
                    }

                    $objetoRetorno->mensaje = $mensajeError;
                    $contenidoAPI = json_encode($objetoRetorno);
                }
            }

            $response = new ResponseMW();
            $response = $response->withStatus($objetoRetorno->status);
            $response->getBody()->write($contenidoAPI);

            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    public function ValidarCorreoYClaveExistentes(Request $request, RequestHandler $handler): ResponseMW {
        $parametros = $request->getParsedBody();

        $objetoRetorno = new stdclass();
        $objetoRetorno->mensaje = "El correo y la clave no existen";
        $objetoRetorno->status = 403;
        $objetoUsuario = null;

        if(isset($parametros['user'])) {
            $objetoUsuario = json_decode($parametros['user']);

            if(Usuario::verificar($objetoUsuario)) {
                $response = $handler->handle($request);
                $contenidoAPI = (string) $response->getBody();
                $api_respuesta = json_decode($contenidoAPI);
                $objetoRetorno->status = $api_respuesta->status;
            } else {
                $contenidoAPI = json_encode($objetoRetorno);
            }
        }

        $response = new ResponseMW();
        $response = $response->withStatus($objetoRetorno->status);
        $response->getBody()->write($contenidoAPI);

        return $response->withHeader('Content-Type', 'application/json');
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
                $response = $handler->handle($request);
                $contenidoAPI = (string) $response->getBody();
                $api_respuesta = json_decode($contenidoAPI);
                $objetoRetorno->status = $api_respuesta->status;
            } else {
                $contenidoAPI = json_encode($objetoRetorno);
            }

            $objetoRetorno->mensaje = $obj;
        }

        $response = new ResponseMW();
        $response = $response->withStatus($objetoRetorno->status);
        $response->getBody()->write($contenidoAPI);

        return $response->withHeader('Content-Type', 'application/json');
    }

    public static function ListarTablaUsuariosGet(Request $request, RequestHandler $handler): ResponseMW {
        $objetoRetorno = new stdclass();
        $objetoRetorno->exito = false;
        $objetoRetorno->mensaje = "No se pudo traer la lista";
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

        $response = new ResponseMW();
        $response = $response->withStatus($objetoRetorno->status);
        $response->getBody()->write(json_encode($objetoRetorno));

        return $response->withHeader('Content-Type', 'application/json');	
    }

    public static function ListarTablaUsuariosPost(Request $request, RequestHandler $handler): ResponseMW {
        $objetoRetorno = new stdclass();
        $objetoRetorno->exito = false;
        $objetoRetorno->mensaje = "No se pudo listar";
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

                $objetoRetorno->mensaje = "No esta autorizado para listar usuarios, debe ser propietario y usted es {$usuarioToken->perfil}";
            }
        }

        $response = new ResponseMW();
        $response = $response->withStatus($objetoRetorno->status);
        $response->getBody()->write(json_encode($objetoRetorno));

        return $response->withHeader('Content-Type', 'application/json');	
    }

    public function ListarTablaJuguetesGet(Request $request, RequestHandler $handler): ResponseMW {
        $listaJuguetesImpares = array();

        $objetoRetorno = new stdclass();
        $objetoRetorno->exito = false;
        $objetoRetorno->mensaje = "No se pudo traer la lista";
        $objetoRetorno->tabla = "null";
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
            $objetoRetorno->tabla = MW::ArmarTabla($listaJuguetesImpares, "<tr><th>ID</th><th>MARCA</th><th>PRECIO</th><th>FOTO</th></tr>");
            $objetoRetorno->status = 200; 
        }

        $response = new ResponseMW();
        $response = $response->withStatus($objetoRetorno->status);
        $response->getBody()->write(json_encode($objetoRetorno));

        return $response->withHeader('Content-Type', 'application/json');	
    }

    public static function ArmarTabla(array $lista, string $header) : string {
        $tabla = '<table class="table table-hover">';

        $tabla .= $header;
        
        foreach($lista as $item) {
            $tabla .= "<tr>";

            foreach ($item as $key => $value)
            {
                if ($key == "perfil") 
                {
                    $tabla .= "<td><img src='{$value}' width=25px></td>";
                } 
                else 
                {
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
        $objetoRetorno->mensaje = "El correo ya existen";
        $objetoRetorno->status = 403;
        $objetoUsuario = null;

        if(isset($parametros['usuario'])) {
            $objetoUsuario = json_decode($parametros['usuario']);

            if(!Usuario::verificarCorreo($objetoUsuario)) {
                $response = $handler->handle($request);
                $contenidoAPI = (string) $response->getBody();
                $api_respuesta = json_decode($contenidoAPI);
                $objetoRetorno->status = $api_respuesta->status;
            } else {
                $contenidoAPI = json_encode($objetoRetorno);
            }
        }

        $response = new ResponseMW();
        $response = $response->withStatus($objetoRetorno->status);
        $response->getBody()->write($contenidoAPI);

        return $response->withHeader('Content-Type', 'application/json');
    }

}




?>