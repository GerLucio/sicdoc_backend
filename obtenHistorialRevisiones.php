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
	

	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$contador = 0;
	
	$consulta_procesos = "select a.departamento as id_depto, b.nombre as departamento, a.id_proceso, a.nombre as proceso from procesos a 
        JOIN departamentos b ON b.id_depto = a.departamento
    where a.estado = 1
    order by departamento, proceso";
	
	$ejecuta_consulta_procesos = oci_parse($conexion, $consulta_procesos);
	oci_execute($ejecuta_consulta_procesos);
	while($row = oci_fetch_array($ejecuta_consulta_procesos, OCI_ASSOC+OCI_RETURN_NULLS)){
		$procesos[] = $row;
	}
	oci_free_statement($ejecuta_consulta_procesos);
	
	$consulta_documentos = "SELECT 
								a.id_documento,
								a.codigo,
								a.nombre,
								a.tipo,
								a.proceso,
								a.id_proceso,
								a.departamento,
								a.num_revisiones AS no_cambios
							FROM v_documentos_activos a
							order by a.proceso";
	
	$ejecuta_consulta_documentos = oci_parse($conexion, $consulta_documentos);
	oci_execute($ejecuta_consulta_documentos);
	while($row = oci_fetch_array($ejecuta_consulta_documentos, OCI_ASSOC+OCI_RETURN_NULLS)){
		$documentos[] = $row;
	}
	oci_free_statement($ejecuta_consulta_documentos);
	
	/*$consulta_revisiones = "SELECT 
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
							WHERE b.estado != 0
							ORDER BY b.id_documento, a.id_revision";
	
	$ejecuta_consulta_revisiones = oci_parse($conexion, $consulta_revisiones);
	oci_execute($ejecuta_consulta_revisiones);
	while($row = oci_fetch_array($ejecuta_consulta_revisiones, OCI_ASSOC+OCI_RETURN_NULLS)){
		$respuesta['revisiones'][$row['ID_DOCUMENTO']-1][] = $row;
	}
	oci_free_statement($ejecuta_consulta_revisiones);*/
	
	foreach($procesos as $proceso){
		$respuesta['procesos'][$contador] = $proceso;
		foreach($documentos as $documento){
			if($documento['ID_PROCESO'] == $proceso['ID_PROCESO'])
				$respuesta['documentos'][$contador][] = $documento;
		}
		$contador++;
	}
	
	oci_close($conexion);
	
	echo json_encode($respuesta);
	exit;
	
?>