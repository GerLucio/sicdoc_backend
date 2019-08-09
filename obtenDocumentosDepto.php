<?PHP
	error_reporting(0);
	
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	header('content-type: application/json;');
	
	include 'clases/clase_documento.php';
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
	
	$departamento = $usuario_request['departamento'];
	$depto = $usuario_request['depto'];
	$rol = $usuario_request['rol'];
	
	$contador = 0;

	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	if($depto == 'Evaluación y Gestión de la Calidad' &&  $rol == '1'){
		$consulta_documentos = "SELECT * from v_documentos_activos order by id_tipo";
		$ejecuta_consulta_documentos = oci_parse($conexion, $consulta_documentos);
	}
	else{
		$consulta_documentos = "SELECT * from v_documentos_activos where id_depto = :departamento order by id_tipo";
		$ejecuta_consulta_documentos = oci_parse($conexion, $consulta_documentos);
		oci_bind_by_name($ejecuta_consulta_documentos, ':departamento', $departamento);
	}
	oci_execute($ejecuta_consulta_documentos);
	while($row = oci_fetch_array($ejecuta_consulta_documentos, OCI_ASSOC+OCI_RETURN_NULLS)){
		$respuesta[$contador++] = $row;
	}
	oci_free_statement($ejecuta_consulta_documentos);
	oci_close($conexion);
	
	echo json_encode($respuesta);
	exit;
	
?>