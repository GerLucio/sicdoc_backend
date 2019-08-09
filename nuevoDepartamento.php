<?PHP
	error_reporting(0);
	
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	header('content-type: application/json;');
	
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
	$departamento->nombre = trim($usuario_request['departamento']['nombre']);
	$departamento->id_subdireccion = trim($usuario_request['departamento']['id_subdireccion']);
	$departamento->id_lider = trim($usuario_request['departamento']['id_lider']);

	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_inserta = "insert into departamentos values(
		(SELECT NVL(MAX(id_depto), 0) + 1 FROM departamentos),
		:nombre,
		:id_lider,
		:id_subdireccion,
		1
	)";
	
	$ejecuta_consulta_inserta = oci_parse($conexion, $consulta_inserta);
	oci_bind_by_name($ejecuta_consulta_inserta, ':nombre', $departamento->nombre);
	oci_bind_by_name($ejecuta_consulta_inserta, ':id_lider', $departamento->id_lider);
	oci_bind_by_name($ejecuta_consulta_inserta, ':id_subdireccion', $departamento->id_subdireccion);
	$inserta = oci_execute($ejecuta_consulta_inserta);
	
	if($inserta){
		$consulta_modifica = "update usuarios set 
					departamento = (SELECT id_depto FROM departamentos where nombre = :nombre),
					rol = 2
					where id_usuario = :id_lider";
		$ejecuta_consulta_modifica = oci_parse($conexion, $consulta_modifica);
		oci_bind_by_name($ejecuta_consulta_modifica, ':nombre', $departamento->nombre);
		oci_bind_by_name($ejecuta_consulta_modifica, ':id_lider', $departamento->id_lider);
		$modifica = oci_execute($ejecuta_consulta_modifica);
		if($modifica){
			$respuesta = array('Exito' => 'Departamento creado correctamente');
			echo json_encode($respuesta);
		}
		else{
			$e = oci_error($ejecuta_consulta_inserta);
			//$respuesta = array('Error' => $e['message']);
			$respuesta = array('Error' => 'Error al asignar al líder del departamento');
			echo json_encode($respuesta);
		}
	}
	else{
		$e = oci_error($ejecuta_consulta_inserta);
		if(substr($e['message'], 0, 9) == 'ORA-00001')
			$respuesta = array('Error' => 'No se puede repetir el nombre del departamento');
		else{
			$mensaje = explode('_', $e['message']);
			$respuesta = array('Error' => $mensaje['0'].$mensaje['1']);
		}
		//$respuesta = array('Error' => 'Error al crear departamento');
		echo json_encode($respuesta);
	}
	
	oci_free_statement($ejecuta_consulta_inserta);
	oci_close($conexion);
	
	exit;
	
?>