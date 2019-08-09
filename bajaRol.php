<?PHP
	error_reporting(0);
	
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	header('content-type: application/json;');
	
	include 'clases/clase_rol.php';
	include 'clases/clase_token.php';
	include 'conexion/conexion.php';
	
	$usuario_request = json_decode(file_get_contents("php://input"), true);
	
	$tkn = $usuario_request['tkn'];
	$token = new Token();
	$tkn_valido = $token->validaToken($tkn);
	
	if(!$tkn_valido){
		$respuesta = array('ErrorToken' => 'Token no válido');
		echo json_encode($respuesta);
		exit;
	}
	
	$rol = new Rol();
	$rol->id_rol = $usuario_request['id_rol'];

	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_elimina = "UPDATE roles SET estado = 0 WHERE id_rol = :id_rol";
	
	$ejecuta_consulta_elimina = oci_parse($conexion, $consulta_elimina);
	oci_bind_by_name($ejecuta_consulta_elimina, ':id_rol', $rol->id_rol);
	$resultado = oci_execute($ejecuta_consulta_elimina);
	
	if($resultado){
		$respuesta = array('Exito' => 'Rol de usuario eliminado correctamente');
		echo json_encode($respuesta);
	}
	else{
		$e = oci_error($ejecuta_consulta_elimina);
		$mensaje = explode('_', $e['message']);
		$respuesta = array('Error' => $mensaje['0'].$mensaje['1']);
		//$respuesta = array('Error' => 'Error al dar de baja el Rol de usuario');
		echo json_encode($respuesta);
	}
	
	oci_free_statement($ejecuta_consulta_elimina);
	oci_close($conexion);
	
	
	
	exit;
	
?>