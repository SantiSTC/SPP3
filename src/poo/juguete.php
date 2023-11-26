<?php
require_once "accesoDatos.php";

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Poo\AccesoDatos;

class Juguete {
    public int $id;
    public string $marca;
    public float $precio;
    public string $pathFoto;

    public function agregarUno(Request $request, Response $response, array $args): Response {
        $parametros = $request->getParsedBody();

        $objetoRetorno = new stdclass();
        $objetoRetorno->exito = false;
        $objetoRetorno->mensaje = "No se pudo agregar el juguete";
        $objetoRetorno->status = 418;

        if(isset($parametros["juguete_json"])) {
            $objetoJuguete = json_decode($parametros["juguete_json"]);
            $archivos = $request->getUploadedFiles();

            $nombreAnterior = $archivos['foto']->getClientFilename();
            $extension = explode(".", $nombreAnterior);
            $extension = array_reverse($extension);
            $destino = "./src/fotos/";
            
            $juguete = new Juguete();
            $juguete->marca = $objetoJuguete->marca;
            $juguete->precio = (float)$objetoJuguete->precio;
            $juguete->pathFoto = $destino . $juguete->marca . "." . $extension[0];

            $archivos['foto']->moveTo("." .  $juguete->pathFoto);
          
           if($juguete->agregar()) {
                $objetoRetorno->exito = true;
                $objetoRetorno->mensaje = "Juguete agregado";
                $objetoRetorno->status = 200;
            }
        }

        $newResponse = $response->withStatus($objetoRetorno->status);
        $newResponse->getBody()->write(json_encode($objetoRetorno));

        return $newResponse->withHeader('Content-Type', 'application/json');
    }

    public function agregar() : bool {
        $retorno = false;

        $objetoAcceso = AccesoDatos::dameUnObjetoAcceso();

        $consulta = $objetoAcceso->retornarConsulta("INSERT INTO juguetes (marca, precio, path_foto) VALUES(:marca, :precio, :path_foto)");

        $consulta->bindValue(':marca', $this->marca, PDO::PARAM_STR);
        $consulta->bindValue(':precio', (float)$this->precio, PDO::PARAM_INT);
        $consulta->bindValue(':path_foto', $this->pathFoto, PDO::PARAM_STR);

        if($consulta->execute()) {
            $retorno = true;
        }

        return $retorno;
    }

    public function traerTodos(Request $request, Response $response, array $args): Response 
	{
        $objetoRetorno = new stdclass();
        $objetoRetorno->exito = false;
        $objetoRetorno->mensaje = "No se pudo traer la lista";
        $objetoRetorno->tabla = "null";
        $objetoRetorno->status = 424;

		$listaUsuarios = Juguete::traer();

        if(count($listaUsuarios) > 0) {
            $objetoRetorno->exito = true;
            $objetoRetorno->mensaje = "Listado de juguetes";
            $objetoRetorno->tabla = json_encode($listaUsuarios);
            $objetoRetorno->status = 200;
        }
  
		$newResponse = $response->withStatus($objetoRetorno->status);
        $newResponse->getBody()->write(json_encode($objetoRetorno));

        return $newResponse->withHeader('Content-Type', 'application/json');	
	}

    public static function traer() : array
    {
        $juguetes = array();
        $objetoAcceso = AccesoDatos::dameUnObjetoAcceso();

        $consulta = $objetoAcceso->retornarConsulta("SELECT id, marca AS marca, precio AS precio, path_foto AS path_foto FROM juguetes");

        $consulta->execute();

        $filas = $consulta->fetchAll();

        foreach($filas as $fila) {
            $juguete = new Juguete();
            $juguete->id = $fila[0];
            $juguete->marca = $fila[1];
            $juguete->precio = $fila[2];
            $juguete->pathFoto = $fila[3];

            array_push($juguetes, $juguete);
        }

        return $juguetes;
    }

    public function borrarUno(Request $request, Response $response, array $args): Response {
        $objetoRetorno = new stdclass();
        $objetoRetorno->exito = false;
        $objetoRetorno->mensaje = "No se pudo eliminar el juguete";
        $objetoRetorno->status = 418;

        if(isset($request->getHeader('token')[0]) && isset($args['id'])) {
            $token = $request->getHeader('token')[0];
            $id = $args['id'];

            $datosToken = Autentificadora::obtenerPayLoad($token);
            $usuarioToken = json_decode($datosToken->payload->data->usuario);
            $perfilUsuario = $usuarioToken->perfil;

            if($perfilUsuario == "supervisor") {
                if(Juguete::borrar($id)){
                    $objetoRetorno->exito = true;
                    $objetoRetorno->mensaje = "Juguete eliminado";
                    $objetoRetorno->status = 200;
                } else {
                    $objetoRetorno->mensaje = "El juguete no se encuentra en el listado";
                }
            } else {
                $objetoRetorno->mensaje = "No esta autorizado para eliminar juguetes, debe ser supervisor y usted es {$usuarioToken->perfil}";
            }
        }

        $newResponse = $response->withStatus(200, "OK");
		$newResponse->getBody()->write(json_encode($objetoRetorno));	

		return $newResponse->withHeader('Content-Type', 'application/json');
    }

    public static function borrar(int $_id) : bool {
        $retorno = false;

        $objetoAccesoDato = AccesoDatos::dameUnObjetoAcceso(); 

       $consulta = $objetoAccesoDato->RetornarConsulta("DELETE FROM juguetes WHERE id = :id");	
       $consulta->bindValue(':id', $_id, PDO::PARAM_INT);		

       $consulta->execute();

       if($consulta->rowCount() > 0) {
           $retorno = true;
       }

       return $retorno;
    }

    public function modificarUno(Request $request, Response $response, array $args): Response {
        $parametros = $request->getParsedBody();

        $objetoRetorno = new stdclass();
        $objetoRetorno->exito = false;
        $objetoRetorno->mensaje = "No se pudo agregar el juguete";
        $objetoRetorno->status = 418;

        if(isset($request->getHeader('token')[0]) && isset($parametros["juguete"])) {
            $token = $request->getHeader('token')[0];
            $objetoJuguete = json_decode($parametros["juguete"]);
            $archivos = $request->getUploadedFiles();

            $nombreAnterior = $archivos['foto']->getClientFilename();
            $extension = explode(".", $nombreAnterior);
            $extension = array_reverse($extension);
            $destino = "./src/fotos/";
            
            $juguete = new Juguete();
            $juguete->id = $objetoJuguete->id_juguete;
            $juguete->marca = $objetoJuguete->marca;
            $juguete->precio = (float)$objetoJuguete->precio;
            $juguete->pathFoto = $destino . $juguete->marca . "_modificacion." . $extension[0];

            $archivos['foto']->moveTo("." .  $juguete->pathFoto);

            $datosToken = Autentificadora::obtenerPayLoad($token);
            $usuarioToken = json_decode($datosToken->payload->data->usuario);
            $perfilUsuario = $usuarioToken->perfil;

            if($perfilUsuario == "supervisor") {
                if($juguete->modificar()) {
                    $objetoRetorno->exito = true;
                    $objetoRetorno->mensaje = "Juguete modificado";
                    $objetoRetorno->status = 200;
                } else {
                    $objetoRetorno->mensaje = "El juguete no se encuentra en el listado";
                }
            } else {
                $objetoRetorno->mensaje = "No esta autorizado para eliminar juguetes, debe ser supervisor y usted es {$usuarioToken->perfil}";
            }
        }

        $newResponse = $response->withStatus($objetoRetorno->status);
        $newResponse->getBody()->write(json_encode($objetoRetorno));

        return $newResponse->withHeader('Content-Type', 'application/json');
    }

    public function modificar() : bool {
        $retorno = false;

        $objetoAcceso = AccesoDatos::dameUnObjetoAcceso();

        $consulta = $objetoAcceso->retornarConsulta("UPDATE juguetes SET marca = :marca, precio = :precio, path_foto = :path_foto WHERE id = :id");

        $consulta->bindValue(':id', $this->id, PDO::PARAM_INT);
        $consulta->bindValue(':marca', $this->marca, PDO::PARAM_STR);
        $consulta->bindValue(':precio', (float)$this->precio, PDO::PARAM_INT);
        $consulta->bindValue(':path_foto', $this->pathFoto, PDO::PARAM_STR);

        $consulta->execute();

        if($consulta->rowCount() > 0) {
            $retorno = true;
        }

        return $retorno;
    }

    public static function traerJuguete(int $id) {
        $objetoAcceso = AccesoDatos::dameUnObjetoAcceso();

        $consulta = $objetoAcceso->retornarConsulta("SELECT * FROM juguetes WHERE id = :id");

        $consulta->bindValue(":id", $id, PDO::PARAM_INT);

        $consulta->execute();

        $juguete = $consulta->fetchObject('Juguete');
        $juguete->pathFoto = $juguete->path_foto;

        return $juguete;
    }
}
?>