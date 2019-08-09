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
	
	$contador = 0;

	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_tipos = "SELECT * FROM v_tipos_activos order by id_tipo";
	
	$ejecuta_consulta_tipos = oci_parse($conexion, $consulta_tipos);
	oci_execute($ejecuta_consulta_tipos);
	while($row = oci_fetch_array($ejecuta_consulta_tipos, OCI_ASSOC+OCI_RETURN_NULLS)){
		$respuesta[$contador++] = $row;
	}
	oci_free_statement($ejecuta_consulta_tipos);
	oci_close($conexion);
	
	echo json_encode($respuesta);
	exit;
	
?>