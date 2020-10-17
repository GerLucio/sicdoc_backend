<?PHP
	error_reporting(0);
    //error_reporting(E_ALL);
    //ini_set('display_errors', TRUE);
    //ini_set('display_startup_errors', TRUE);
	
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	header('content-type: application/json;');
	
	include 'clases/clase_subdireccion.php';
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
	
	$subdireccion = new Subdireccion();
	$subdireccion->id_subdireccion = trim($usuario_request['subdireccion']['id_subdireccion']);
	$subdireccion->nombre = trim($usuario_request['subdireccion']['nombre']);
	
	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_modifica = "UPDATE subdirecciones set nombre = :nombre WHERE id_subdireccion = :id_subdireccion";
	$ejecuta_consulta_modifica = oci_parse($conexion, $consulta_modifica);
	oci_bind_by_name($ejecuta_consulta_modifica, ':nombre', $subdireccion->nombre);
	oci_bind_by_name($ejecuta_consulta_modifica, ':id_subdireccion', $subdireccion->id_subdireccion);
	$resultado = oci_execute($ejecuta_consulta_modifica);
	
	if($resultado){
		$respuesta = array('Exito' => 'subdireccion modificada correctamente');
		echo json_encode($respuesta);
	}
	else{
		$e = oci_error($ejecuta_consulta_modifica);
		//$respuesta = array('Error' => $e['message']);
		$respuesta = array('Error' => 'no se puede repetir el nombre de la subdirección');
		echo json_encode($respuesta);
	}
	
	oci_free_statement($ejecuta_consulta_modifica);
	oci_close($conexion);
	
	exit;
	
?>