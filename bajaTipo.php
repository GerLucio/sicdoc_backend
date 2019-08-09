<?PHP
	error_reporting(0);
	
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	header('content-type: application/json;');
	
	include 'clases/clase_tipos.php';
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
	
	$tipo = new Tipo();
	$tipo->id_tipo = $usuario_request['id_tipo'];

	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_elimina = "UPDATE TIPOS SET estado = 0 WHERE id_tipo = :id_tipo";
	
	$ejecuta_consulta_elimina = oci_parse($conexion, $consulta_elimina);
	oci_bind_by_name($ejecuta_consulta_elimina, ':id_tipo', $tipo->id_tipo);
	$resultado = oci_execute($ejecuta_consulta_elimina);
	
	if($resultado){
		$respuesta = array('Exito' => 'Tipo eliminado correctamente');
		echo json_encode($respuesta);
	}
	else{
		$e = oci_error($ejecuta_consulta_elimina);
		$mensaje = explode('_', $e['message']);
		$respuesta = array('Error' => $mensaje['0'].$mensaje['1']);
		//$respuesta = array('Error' => 'Error al dar de baja el tipo');
		echo json_encode($respuesta);
	}
	
	oci_free_statement($ejecuta_consulta_elimina);
	oci_close($conexion);
	
	
	
	exit;
	
?>