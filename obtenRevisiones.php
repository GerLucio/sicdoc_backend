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
	

	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$id_documento = $usuario_request['id_documento'];
	
	$consulta_revisiones = "SELECT 
								b.id_documento,
								a.id_revision,
								a.no_revision,
								a.vigente,
								a.responsable as id_responsable,
								d.nombre || ' ' || d.apellido as responsable,
								a.fecha_revision,
								a.ruta
							FROM revisiones a
								JOIN documentos b ON a.documento = b.id_documento
								JOIN  usuarios d ON a.responsable = d.id_usuario
							WHERE b.id_documento = :id_documento
							ORDER BY a.id_revision";
	
	$ejecuta_consulta_revisiones = oci_parse($conexion, $consulta_revisiones);
	oci_bind_by_name($ejecuta_consulta_revisiones, ':id_documento', $id_documento);
	oci_execute($ejecuta_consulta_revisiones);
	while($row = oci_fetch_array($ejecuta_consulta_revisiones, OCI_ASSOC+OCI_RETURN_NULLS)){
		if($row['VIGENTE'] == 1)
			$row['VIGENTE'] = true;
		else
			$row['VIGENTE'] = false;
		$respuesta[] = $row;
	}
	oci_free_statement($ejecuta_consulta_revisiones);
	
	
	oci_close($conexion);
	
	echo json_encode($respuesta);
	exit;
	
?>