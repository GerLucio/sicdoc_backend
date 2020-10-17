<?PHP
	error_reporting(0);
    //error_reporting(E_ALL);
    //ini_set('display_errors', TRUE);
    //ini_set('display_startup_errors', TRUE);
	
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	header('content-type: application/json;');
	
	include 'clases/clase_usuario.php';
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

	$usuario = new Usuario();
	$usuario->id_usuario = trim($usuario_request['usuario_editar']['id_usuario']);
	$usuario->nombre = trim($usuario_request['usuario_editar']['nombre']);
	$usuario->apellido = trim($usuario_request['usuario_editar']['apellido']);
	$usuario->puesto = trim($usuario_request['usuario_editar']['puesto']);
	$usuario->correo = trim($usuario_request['usuario_editar']['correo']);
	$usuario->departamento = trim($usuario_request['usuario_editar']['id_departamento']);
	$usuario->rol = trim($usuario_request['usuario_editar']['id_rol']);	

	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_modifica = "UPDATE usuarios set
			nombre = :nombre,
			apellido = :apellido,
			puesto = :puesto,
			correo = :correo,
			departamento = :departamento,
			rol = :rol
			WHERE id_usuario = :id_usuario";
	$ejecuta_consulta_modifica = oci_parse($conexion, $consulta_modifica);
	
	oci_bind_by_name($ejecuta_consulta_modifica, ':nombre', $usuario->nombre);
	oci_bind_by_name($ejecuta_consulta_modifica, ':apellido', $usuario->apellido);
	oci_bind_by_name($ejecuta_consulta_modifica, ':puesto', $usuario->puesto);
	oci_bind_by_name($ejecuta_consulta_modifica, ':correo', $usuario->correo);
	oci_bind_by_name($ejecuta_consulta_modifica, ':departamento', $usuario->departamento);
	oci_bind_by_name($ejecuta_consulta_modifica, ':rol', $usuario->rol);
	oci_bind_by_name($ejecuta_consulta_modifica, ':id_usuario', $usuario->id_usuario);
	
	$resultado = oci_execute($ejecuta_consulta_modifica);
	
	if($resultado){
		$consulta_complementa = "UPDATE usuarios set rol = 3 where rol = 1 and id_usuario != :id_usuario";
		$ejecuta_consulta_complementa = oci_parse($conexion, $consulta_complementa);
		oci_bind_by_name($ejecuta_consulta_complementa, ':id_usuario', $usuario->id_usuario);
		$resultado = oci_execute($ejecuta_consulta_complementa);
		if($resultado){		
			$respuesta = array('Exito' => 'Usuario modificado correctamente');
			echo json_encode($respuesta);
		}
		oci_free_statement($ejecuta_consulta_complementa);
	}
	else{
		$e = oci_error($ejecuta_consulta_modifica);
		$mensaje = explode('_', $e['message']);
		$respuesta = array('Error' => $mensaje['0'].$mensaje['1']);
		//$respuesta = array('Error' => 'Error al modificar los datos del usuario');
		echo json_encode($respuesta);
	}
	
	oci_free_statement($ejecuta_consulta_modifica);
	oci_close($conexion);
	
	exit;
	
?>