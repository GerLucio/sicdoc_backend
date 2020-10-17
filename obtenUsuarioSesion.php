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
	include 'conexion/conexion.php';
	include 'clases/clase_token.php';
	
	$usuario_request = json_decode(file_get_contents("php://input"), true);
	
	$tkn = $usuario_request['tkn'];
	$token = new Token();
	$tkn_valido = $token->validaToken($tkn);
	
	if(!$tkn_valido){
		$respuesta = array('ErrorToken' => 'Token no válido');
		echo json_encode($respuesta);
		exit;
	}
	
	$usuario = new Usuario();
	$usuario->id_usuario = trim($usuario_request['usuario']['id_usuario']);
	
	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_existe_usuario = "SELECT * FROM v_usuarios_activos
    WHERE id_usuario = :id_usuario";
	
	$ejecuta_existe_usuario = oci_parse($conexion, $consulta_existe_usuario);
	oci_bind_by_name($ejecuta_consulta_modifica, ':id_usuario', $usuario->id_usuario);
	oci_execute($ejecuta_existe_usuario);
	if($row = oci_fetch_array($ejecuta_existe_usuario, OCI_ASSOC+OCI_RETURN_NULLS)){
		$row['PASSWORD'] = null;
		if($row['ID_ESTADO'] == 2)
			$row['ESTADO'] = "Cambio contraseña";
		$respuesta['usuario'] = $row;
		echo json_encode($respuesta);
	}
	else{
		$respuesta = array('Error' => 'Usuario no encontrado');
		echo json_encode($respuesta);
	}
	
	oci_free_statement($ejecuta_existe_usuario);
	oci_close($conexion);
	exit;
	
?>