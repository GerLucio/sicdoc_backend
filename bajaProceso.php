<?PHP
	error_reporting(0);
    //error_reporting(E_ALL);
    //ini_set('display_errors', TRUE);
    //ini_set('display_startup_errors', TRUE);
	
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	header('content-type: application/json;');
	
	include 'clases/clase_proceso.php';
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
	
	$proceso = new Proceso();
	$proceso->id_proceso = $usuario_request['id_proceso'];

	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_elimina = "UPDATE procesos SET estado = 0 WHERE id_proceso = :id_proceso";
	$ejecuta_consulta_elimina = oci_parse($conexion, $consulta_elimina);
	oci_bind_by_name($ejecuta_consulta_elimina, ':id_proceso', $proceso->id_proceso);
	$resultado = oci_execute($ejecuta_consulta_elimina);
	
	if($resultado){
		$respuesta = array('Exito' => 'Proceso eliminado correctamente');
		echo json_encode($respuesta);
	}
	else{
		$e = oci_error($ejecuta_consulta_elimina);
		$mensaje = explode('_', $e['message']);
		$respuesta = array('Error' => $mensaje['0'].$mensaje['1']);
		//$respuesta = array('Error' => 'Error al dar de baja el proceso');
		echo json_encode($respuesta);
	}
	
	oci_free_statement($ejecuta_consulta_elimina);
	oci_close($conexion);

	exit;
	
?>