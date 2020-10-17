<?PHP
	error_reporting(0);
    //error_reporting(E_ALL);
    //ini_set('display_errors', TRUE);
    //ini_set('display_startup_errors', TRUE);
	
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	header('content-type: application/json;');
	
	include 'clases/clase_usuario.php';
	include 'clases/clase_token.php';
	include 'conexion/conexion.php';
		
	
	$usuario_request = json_decode(file_get_contents("php://input"), true);
	$usuario = new Usuario();
	$usuario->correo = trim($usuario_request['usuario']['correo']);
	$usuario->password = $usuario_request['usuario']['password'];

	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_existe_usuario = "SELECT * FROM v_usuarios_activos
    WHERE correo = :correo";
	
	$ejecuta_existe_usuario = oci_parse($conexion, $consulta_existe_usuario);
	oci_bind_by_name($ejecuta_existe_usuario, ':correo', $usuario->correo);
	oci_execute($ejecuta_existe_usuario);
	if($row = oci_fetch_array($ejecuta_existe_usuario, OCI_ASSOC+OCI_RETURN_NULLS)){
		$valido = $usuario->validaUsuario($row['PASSWORD']);
		if($valido){
			$row['PASSWORD'] = null;
			$respuesta['usuario'] = $row;
			$token = new Token();
			$token->generaToken($row['CORREO']);
			$respuesta['tkn'] = $token->tkn;
			echo json_encode($respuesta);
		}
		else{
			$respuesta = array('Error' => 'Contraseña incorrecta');
			echo json_encode($respuesta);
		}
	}
	else{
		$respuesta = array('Error' => 'Usuario no encontrado');
		echo json_encode($respuesta);
	}
	
	oci_free_statement($ejecuta_existe_usuario);
	oci_close($conexion);
	exit;
	
?>