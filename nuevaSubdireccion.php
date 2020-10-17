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
	$subdireccion->nombre = trim($usuario_request['subdireccion']['nombre']);

	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_inserta = "insert into subdirecciones values(
		(SELECT NVL(MAX(id_subdireccion), 0) + 1 FROM subdirecciones),
		:nombre,
		1
	)";
	
	$ejecuta_consulta_inserta = oci_parse($conexion, $consulta_inserta);
	oci_bind_by_name($ejecuta_consulta_inserta, ':nombre', $subdireccion->nombre);
	$inserta = oci_execute($ejecuta_consulta_inserta);
	
	if($inserta){
		$respuesta = array('Exito' => 'Subdirección creada correctamente');
		echo json_encode($respuesta);
	}
	else{
		$e = oci_error($ejecuta_consulta_inserta);
		//$respuesta = array('Error' => $e['message']);
		$respuesta = array('Error' => 'La subdirección ya existe');
		echo json_encode($respuesta);
	}
	
	oci_free_statement($ejecuta_consulta_inserta);
	oci_close($conexion);
	
	exit;
	
?>