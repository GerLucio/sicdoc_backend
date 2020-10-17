<?PHP
	error_reporting(0);
    //error_reporting(E_ALL);
    //ini_set('display_errors', TRUE);
    //ini_set('display_startup_errors', TRUE);
	
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	//header('content-type: application/json;');
	
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
	
	$nombre = trim($usuario_request['nombre']);
	$departamento = $usuario_request['departamento'];
	

	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_documentos = "SELECT * from v_documentos_activos where (UPPER(nombre) = UPPER(:nombre) or UPPER(codigo) = UPPER(:nombre))
	AND (id_depto = :departamento or UPPER(proceso) in(UPPER('Auditoria Interna'), UPPER('Medición, análisis y mejora'), 
			UPPER('Revisión por la Dirección'), UPPER('Coordinación del Sistema de Gestión de la Calidad')))";
	
	$ejecuta_consulta_documentos = oci_parse($conexion, $consulta_documentos);
	oci_bind_by_name($ejecuta_consulta_documentos, ':nombre', $nombre);
	oci_bind_by_name($ejecuta_consulta_documentos, ':departamento', $departamento);
	oci_execute($ejecuta_consulta_documentos);
	if($row = oci_fetch_array($ejecuta_consulta_documentos, OCI_ASSOC+OCI_RETURN_NULLS)){
		$respuesta = array(
			'nombre' => $row['NOMBRE'],
			'codigo' => $row['CODIGO'],
			'proceso' => $row['PROCESO'],
			'departamento' => $row['DEPARTAMENTO'],
			'tipo' => $row['TIPO'],
			'revision' =>'R'.($row['NO_REVISION']-1),
			'ubicacion' => $row['UBICACION'],
			'ruta' => $row['RUTA']
		);
	}
	else
		$respuesta = array('Error' => 'Documento no encontrado, intenta nuevamente');
	oci_free_statement($ejecuta_consulta_documentos);
	oci_close($conexion);
	
	echo json_encode($respuesta);
	exit;
	
?>