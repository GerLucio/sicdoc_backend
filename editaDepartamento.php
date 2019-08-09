<?PHP
	//error_reporting(0);
	
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	//header('content-type: application/json;');
	
	include 'clases/clase_departamento.php';
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
	
	$departamento = new Departamento();
	$departamento->id_departamento = trim($usuario_request['departamento']['id_departamento']);
	$departamento->nombre = trim($usuario_request['departamento']['nombre']);
	$departamento->id_lider = $usuario_request['departamento']['id_lider'];
	$departamento->id_subdireccion = $usuario_request['departamento']['id_subdireccion'];
	
	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_modifica = "DECLARE num_doc number;
                                                BEGIN
                                                UPDATE usuarios set rol = 2 WHERE id_usuario = :id_lider;
                                                        UPDATE departamentos set nombre = :nombre,
                                                                lider = :id_lider,
                                                                subdireccion = :id_subdireccion
                                                                WHERE id_depto = :id_departamento;
                                                UPDATE usuarios set rol = 3 WHERE rol = 2 and departamento = :id_departamento and id_usuario != :id_lider;
                                                COMMIT;
                                                EXCEPTION
                                                        WHEN OTHERS THEN
                                                          ROLLBACK;
                                                END;";
	$ejecuta_consulta_modifica = oci_parse($conexion, $consulta_modifica);
	oci_bind_by_name($ejecuta_consulta_modifica, ':nombre', $departamento->nombre);
	oci_bind_by_name($ejecuta_consulta_modifica, ':id_lider', $departamento->id_lider);
	oci_bind_by_name($ejecuta_consulta_modifica, ':id_subdireccion', $departamento->id_subdireccion);
	oci_bind_by_name($ejecuta_consulta_modifica, ':id_departamento', $departamento->id_departamento);
	$resultado = oci_execute($ejecuta_consulta_modifica);
	
	if($resultado){
		$respuesta = array('Exito' => 'Departamento modificado correctamente');
		echo json_encode($respuesta);
	}
	else{
		$e = oci_error($ejecuta_consulta_modifica);
		if(substr($e['message'], 0, 9) == 'ORA-00001')
			$respuesta = array('Error' => 'No se puede repetir el nombre del departamento');
		else{
			$mensaje = explode('_', $e['message']);
			$respuesta = array('Error' => $mensaje['0'].$mensaje['1']);
		}
		//$respuesta = array('Error' => 'Error al modificar Departamento');
		echo json_encode($respuesta);
	}
	
	oci_free_statement($ejecuta_consulta_modifica);
	oci_close($conexion);
	
	exit;
	
?>