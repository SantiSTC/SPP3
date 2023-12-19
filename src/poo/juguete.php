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

    public function agregar() : bool {
        $retorno = false;

        $conexion = AccesoDatos::dameUnObjetoAcceso();

        $sql = $conexion->retornarConsulta("INSERT INTO juguetes (marca, precio, path_foto) VALUES(:marca, :precio, :path_foto)");
        $sql->bindValue(':marca', $this->marca, PDO::PARAM_STR);
        $sql->bindValue(':precio', (float)$this->precio, PDO::PARAM_INT);
        $sql->bindValue(':path_foto', $this->pathFoto, PDO::PARAM_STR);

        if($sql->execute()) {
            $retorno = true;
        }

        return $retorno;
    }

    public function agregarUno(Request $request, Response $response, array $args): Response {
        $params = $request->getParsedBody();

        $obj = new stdclass();
        $obj->exito = false;
        $obj->mensaje = "Error al intentar agregar el juguete.";
        $obj->status = 418;

        if(isset($params["juguete_json"])) {
            $objJuguete = json_decode($params["juguete_json"]);
            $archivos = $request->getUploadedFiles();

            $nombreAnterior = $archivos['foto']->getClientFilename();
            $extension = explode(".", $nombreAnterior);
            $extension = array_reverse($extension);
            $destino = "./src/fotos/";
            
            $juguete = new Juguete();
            $juguete->marca = $objJuguete->marca;
            $juguete->precio = (float)$objJuguete->precio;
            $juguete->pathFoto = $destino . $juguete->marca . "." . $extension[0];

            $archivos['foto']->moveTo("." .  $juguete->pathFoto);
          
           if($juguete->agregar()) {
                $obj->exito = true;
                $obj->mensaje = "Juguete agregado correctamente.";
                $obj->status = 200;
            }
        }

        $retorno = $response->withStatus($obj->status);
        $retorno->getBody()->write(json_encode($obj));

        return $retorno->withHeader('Content-Type', 'application/json');
    }

    public static function traer() : array
    {
        $juguetes = array();

        $conexion = AccesoDatos::dameUnObjetoAcceso();

        $sql = $conexion->retornarConsulta("SELECT id, marca AS marca, precio AS precio, path_foto AS path_foto FROM juguetes");

        $sql->execute();

        $filas = $sql->fetchAll();

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

    public function traerTodos(Request $request, Response $response, array $args): Response 
	{
        $obj = new stdclass();
        $obj->exito = false;
        $obj->mensaje = "Error al intentar traer la lista";
        $obj->tablaData = "null";
        $obj->status = 424;

		$listaUsuarios = Juguete::traer();

        if(count($listaUsuarios) > 0) {
            $obj->exito = true;
            $obj->mensaje = "Juguetes:";
            $obj->tablaData = json_encode($listaUsuarios);
            $obj->status = 200;
        }
  
		$retorno = $response->withStatus($obj->status);
        $retorno->getBody()->write(json_encode($obj));

        return $retorno->withHeader('Content-Type', 'application/json');	
	}

    public static function borrar(int $_id) : bool {
        $retorno = false;

        $conexion = AccesoDatos::dameUnObjetoAcceso(); 

        $sql = $conexion->RetornarConsulta("DELETE FROM juguetes WHERE id = :id");	
        $sql->bindValue(':id', $_id, PDO::PARAM_INT);		

        $sql->execute();

        if($sql->rowCount() > 0) {
            $retorno = true;
        }

        return $retorno;
    }

    public function borrarUno(Request $request, Response $response, array $args): Response {
        $obj = new stdclass();
        $obj->exito = false;
        $obj->mensaje = "Error al intentar eliminar el juguete.";
        $obj->status = 418;

        if(isset($request->getHeader('token')[0]) && isset($args['id'])) {
            $token = $request->getHeader('token')[0];
            $id = $args['id'];

            $dataToken = Autentificadora::obtenerPayLoad($token);
            $usuarioToken = json_decode($dataToken->payload->data->usuario);
            $perfilUsuario = $usuarioToken->perfil;

            if($perfilUsuario == "supervisor") {
                if(Juguete::borrar($id)){
                    $obj->exito = true;
                    $obj->mensaje = "Juguete eliminado";
                    $obj->status = 200;
                } else {
                    $obj->mensaje = "Error. El juguete no se encuentra en la lista";
                }
            } else {
                $obj->mensaje = "No tiene autorizacion para eliminar juguetes, se necesita ser supervisor y usted es: {$usuarioToken->perfil}";
            }
        }

        $retorno = $response->withStatus(200, "OK");
		$retorno->getBody()->write(json_encode($obj));	

		return $retorno->withHeader('Content-Type', 'application/json');
    }

    public function modificar() : bool {
        $retorno = false;
        $conexion = AccesoDatos::dameUnObjetoAcceso();

        $sql = $conexion->retornarConsulta("UPDATE juguetes SET marca = :marca, precio = :precio, path_foto = :path_foto WHERE id = :id");
        $sql->bindValue(':id', $this->id, PDO::PARAM_INT);
        $sql->bindValue(':marca', $this->marca, PDO::PARAM_STR);
        $sql->bindValue(':precio', (float)$this->precio, PDO::PARAM_INT);
        $sql->bindValue(':path_foto', $this->pathFoto, PDO::PARAM_STR);

        $sql->execute();

        if($sql->rowCount() > 0) {
            $retorno = true;
        }

        return $retorno;
    }

    public function modificarUno(Request $request, Response $response, array $args): Response {
        $params = $request->getParsedBody();

        $obj = new stdclass();
        $obj->exito = false;
        $obj->mensaje = "Error al intentar modificar el juguete.";
        $obj->status = 418;

        if(isset($request->getHeader('token')[0]) && isset($params["juguete"])) {
            $token = $request->getHeader('token')[0];
            $objetoJuguete = json_decode($params["juguete"]);
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
                    $obj->exito = true;
                    $obj->mensaje = "Juguete modificado";
                    $obj->status = 200;
                } else {
                    $obj->mensaje = "Error. El juguete no se encuentra en la lista";
                }
            } else {
                $obj->mensaje = "No tiene autorizacion para modificar juguetes, se necesita ser supervisor y usted es: {$usuarioToken->perfil}";
            }
        }

        $retorno = $response->withStatus($obj->status);
        $retorno->getBody()->write(json_encode($obj));

        return $retorno->withHeader('Content-Type', 'application/json');
    }

    public static function traerJuguete(int $id) {
        $conexion = AccesoDatos::dameUnObjetoAcceso();

        $sql = $conexion->retornarConsulta("SELECT * FROM juguetes WHERE id = :id");
        $sql->bindValue(":id", $id, PDO::PARAM_INT);

        $sql->execute();

        $juguete = $sql->fetchObject('Juguete');
        $juguete->pathFoto = $juguete->path_foto;

        return $juguete;
    }
}
?>