<?PHP
	error_reporting(0);
    //error_reporting(E_ALL);
    //ini_set('display_errors', TRUE);
    //ini_set('display_startup_errors', TRUE);
	
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

	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_procesos = "select a.departamento as id_depto, b.nombre as departamento, a.id_proceso, a.nombre as proceso from procesos a 
        JOIN departamentos b ON b.id_depto = a.departamento
    where a.estado = 1 and (a.departamento = :departamento or 
		UPPER(a.nombre) in(UPPER('Auditoria Interna'), UPPER('Medición, análisis y mejora'), 
		UPPER('Revisión por la Dirección'), UPPER('Coordinación del Sistema de Gestión de la Calidad')))
    order by departamento, proceso";
	
	$ejecuta_consulta_procesos = oci_parse($conexion, $consulta_procesos);
	oci_bind_by_name($ejecuta_consulta_procesos, ':departamento', $departamento);
	oci_execute($ejecuta_consulta_procesos);
	while($row = oci_fetch_array($ejecuta_consulta_procesos, OCI_ASSOC+OCI_RETURN_NULLS)){
		$procesos[] = $row;
	}
	oci_free_statement($ejecuta_consulta_procesos);

	$consulta_documentos = "SELECT * from v_documentos_activos where id_depto = :departamento or 
		UPPER(proceso) in(UPPER('Auditoria Interna'), UPPER('Medición, análisis y mejora'), 
		UPPER('Revisión por la Dirección'), UPPER('Coordinación del Sistema de Gestión de la Calidad')) 
		order by id_tipo";
	$ejecuta_consulta_documentos = oci_parse($conexion, $consulta_documentos);
	oci_bind_by_name($ejecuta_consulta_documentos, ':departamento', $departamento);
	
	oci_execute($ejecuta_consulta_documentos);
	while($row = oci_fetch_array($ejecuta_consulta_documentos, OCI_ASSOC+OCI_RETURN_NULLS)){
		$documentos[] = $row;
	}
	oci_free_statement($ejecuta_consulta_documentos);
	oci_close($conexion);
	
	$contador = 0;
	foreach($procesos as $proceso){
		$respuesta['procesos'][$contador] = $proceso;
		foreach($documentos as $documento){
			if($documento['ID_PROCESO'] == $proceso['ID_PROCESO'])
				$respuesta['documentos'][$contador][] = $documento;
		}
		$contador++;
	}
	
	echo json_encode($respuesta);
	exit;
	
?>