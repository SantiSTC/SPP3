<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Poo\AccesoDatos;

require_once "accesoDatos.php";
require_once __DIR__ . "/autentificadora.php";

class Usuario {
    public int $id;
    public string $correo;
    public string $clave;
    public string $nombre;
    public string $apellido;
    public string $perfil;
    public string $foto;

    public function traerTodos(Request $request, Response $response, array $args): Response {
        $objRetorno = new stdclass();
        $objRetorno->exito = false;
        $objRetorno->mensaje = "Error al intentar agregar al usuario.";
        $objRetorno->data = "{}";
        $objRetorno->status = 424;

		$listaUsuarios = Usuario::traer();

        if(count($listaUsuarios) > 0) {
            $objRetorno->exito = true;
            $objRetorno->mensaje = "Usuarios:";
            $objRetorno->data = json_encode($listaUsuarios);
            $objRetorno->status = 200;
        }
  
		$retorno = $response->withStatus($objRetorno->status);
        $retorno->getBody()->write(json_encode($objRetorno));

        return $retorno->withHeader('Content-Type', 'application/json');	
	}

    public static function traer() : array {
        $usuarios = array();

        $conexion = AccesoDatos::dameUnObjetoAcceso();
        $sql = $conexion->retornarConsulta("SELECT id, correo AS correo, clave AS clave, nombre AS nombre, apellido AS apellido, foto AS foto, perfil AS perfil FROM usuarios");
        $sql->execute();
        $filas = $sql->fetchAll();

        foreach($filas as $fila) {
            $usuario = new Usuario();
            $usuario->id = $fila[0];
            $usuario->correo = $fila[1];
            $usuario->clave = $fila[2];
            $usuario->nombre = $fila[3];
            $usuario->apellido = $fila[4];
            $usuario->perfil = $fila[5];
            $usuario->foto = $fila[6];

            array_push($usuarios, $usuario);
        }

        return $usuarios;
    }

    public function login(Request $request, Response $response, array $args): Response {
        $params = $request->getParsedBody();

        $objRetorno = new stdClass();
        $objRetorno->exito = false;
        $objRetorno->mensaje = "Error al intentar encontrar al usuario.";
        $objRetorno->status = 424;

        if(isset($params['user'])) {
            $objeto = json_decode($params['user']);

            $usuario = Usuario::verificar($objeto);

            if($usuario != null) {
                $usuarioData = new Usuario();
                $usuarioData->correo = $usuario->correo;
                $usuarioData->nombre = $usuario->nombre;
                $usuarioData->apellido = $usuario->apellido;
                $usuarioData->perfil = $usuario->perfil;
                $usuarioData->foto = $usuario->foto;

                $data = new stdclass();
                $data->usuario = json_encode($usuarioData);
                $data->alumno = "Iannello Santiago";
                $data->dni_alumno = "44195364";

                $objRetorno->exito = true;
                $objRetorno->mensaje = "Token creado.";
                $objRetorno->jwt = Autentificadora::crearJWT($data);
                $objRetorno->status = 200;
            }
        }

		$retorno = $response->withStatus($objRetorno->status);
        $retorno->getBody()->write(json_encode($objRetorno));

        return $retorno->withHeader('Content-Type', 'application/json');
    }

    public static function verificar($objeto) : Usuario | null | bool {
        $usuario = null;
        $conexion = AccesoDatos::dameUnObjetoAcceso();

        $sql = $conexion->retornarConsulta( "SELECT * FROM usuarios WHERE correo = :correo AND clave = :clave");

        $sql->bindValue(':correo', $objeto->correo, PDO::PARAM_STR);
        $sql->bindValue(':clave', $objeto->clave, PDO::PARAM_STR);

        if($sql->execute()) {
            $usuario = $sql->fetchObject('Usuario');
        }

        return $usuario;
    }

    public function verificarJWT(Request $request, Response $response, array $args): Response {
        $contenidoAPI = "";
        $objRetorno = new stdClass();
        $objRetorno->exito = false;
        $objRetorno->status = 403;

        if (isset($request->getHeader("token")[0])) {
            $token = $request->getHeader("token")[0];

            $obj = Autentificadora::verificarJWT($token);

            if ($obj->verificado) {
                $objRetorno->exito = true;
                $objRetorno->status = 200;
            }

            $objRetorno->mensaje = $obj;
        }

        $contenidoAPI = json_encode($objRetorno);

        $retorno = $response->withStatus($objRetorno->status);
        $retorno->getBody()->write($contenidoAPI);

        return $retorno->withHeader('Content-Type', 'application/json');
    }

    public function agregar() : bool | int {
        $retorno = false;

        $conexion = AccesoDatos::dameUnObjetoAcceso();

        $sql = $conexion->retornarConsulta("INSERT INTO usuarios(correo, clave, nombre, apellido, perfil, foto)" . "VALUES(:correo, :clave, :nombre, :apellido, :perfil, :foto)");
    
        $sql->bindValue(':correo', $this->correo, PDO::PARAM_STR);
        $sql->bindValue(':clave', $this->clave, PDO::PARAM_STR);
        $sql->bindValue(':nombre', $this->nombre, PDO::PARAM_STR);
        $sql->bindValue(':apellido', $this->apellido, PDO::PARAM_STR);
        $sql->bindValue(':perfil', $this->perfil, PDO::PARAM_STR);
        $sql->bindValue(':foto', $this->foto, PDO::PARAM_STR);

        if($sql->execute()) {
            $retorno = $conexion->retornarUltimoIdInsertado();
        }

        return $retorno;
    }

    public static function verificarCorreo($objeto) : bool {
        $retorno = false;
        $conexion = AccesoDatos::dameUnObjetoAcceso();
        
        $sql = $conexion->retornarConsulta( "SELECT * FROM usuarios WHERE correo = :correo");

        $sql->bindValue(':correo', $objeto->correo, PDO::PARAM_STR);

        if($sql->execute()) {
            $retorno = true;
        }

        return $retorno;
    }
}

?>