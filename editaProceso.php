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
	$proceso->id_proceso = trim($usuario_request['proceso']['id_proceso']);
	$proceso->nombre = trim($usuario_request['proceso']['nombre']);
	$proceso->id_departamento = $usuario_request['proceso']['id_departamento'];
	
	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_modifica = "UPDATE procesos set nombre = :nombre,
								departamento = :id_departamento
								WHERE id_proceso = :id_proceso";
	$ejecuta_consulta_modifica = oci_parse($conexion, $consulta_modifica);
	oci_bind_by_name($ejecuta_consulta_modifica, ':nombre', $proceso->nombre);
	oci_bind_by_name($ejecuta_consulta_modifica, ':id_departamento', $proceso->id_departamento);
	oci_bind_by_name($ejecuta_consulta_modifica, ':id_proceso', $proceso->id_proceso);
	$resultado = oci_execute($ejecuta_consulta_modifica);
	
	if($resultado){
		$respuesta = array('Exito' => 'Proceso modificado correctamente');
		echo json_encode($respuesta);
	}
	else{
		$e = oci_error($ejecuta_consulta_modifica);
		if(substr($e['message'], 0, 9) == 'ORA-00001')
			$respuesta = array('Error' => 'No se puede repetir el nombre del proceso');
		else{
			$mensaje = explode('_', $e['message']);
			$respuesta = array('Error' => $mensaje['0'].$mensaje['1']);
		}
		//$respuesta = array('Error' => 'Error al modificar proceso');
		echo json_encode($respuesta);
	}
	
	oci_free_statement($ejecuta_consulta_modifica);
	oci_close($conexion);
	
	exit;
	
?>