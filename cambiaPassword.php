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
	include ("PHPMailer/class.phpmailer.php"); //Necesita estos dos archivos para funcionar 
	include ("PHPMailer/class.smtp.php");
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
	$usuario->id_usuario = $usuario_request['usuario']['id_usuario'];
	$usuario->password = trim($usuario_request['pass']);
	$new_pass = trim($usuario_request['new_pass']);
	
	if(!$conexion){
		$respuesta = array('Error' => 'Error al conectar con la base de datos');
		echo json_encode($respuesta);
		exit;
	}
	
	$consulta_password_valido = "SELECT * FROM v_usuarios_activos WHERE id_usuario = :id_usuario";
	
	$ejecuta_consulta_password_valido = oci_parse($conexion, $consulta_password_valido);
	
	oci_bind_by_name($ejecuta_consulta_password_valido, ':id_usuario', $usuario->id_usuario);
	oci_execute($ejecuta_consulta_password_valido);
	if($row = oci_fetch_array($ejecuta_consulta_password_valido, OCI_ASSOC+OCI_RETURN_NULLS)){
		$valido = $usuario->validaUsuario($row['PASSWORD']);
		if($valido){
			if (!preg_match('`[A-Z]`',$new_pass)){
				$respuesta = array('Error' => 'La nueva contraseña debe tener al menos una letra mayúscula');
				echo json_encode($respuesta);
			}
			else if (!preg_match('`[a-z]`',$new_pass)){
				$respuesta = array('Error' => 'La nueva contraseña debe tener al menos una letra minúscula');
				echo json_encode($respuesta);
			}
			else if (!preg_match('`[0-9]`',$new_pass)){
				$respuesta = array('Error' => 'La nueva contraseña debe tener al menos un número');
				echo json_encode($respuesta);
			}
			else{
				$usuario->setPassword($new_pass);
				$consulta_password = "UPDATE USUARIOS SET estado = 1, password = :password  WHERE id_usuario = :id_usuario";
				
				$ejecuta_consulta_password = oci_parse($conexion, $consulta_password);
				oci_bind_by_name($ejecuta_consulta_password, ':password', $usuario->password);
				oci_bind_by_name($ejecuta_consulta_password, ':id_usuario', $usuario->id_usuario);
				$resultado = oci_execute($ejecuta_consulta_password);
				
				if($resultado){
					$password_cambio = true;
					$row['PASSWORD'] = null;
					$row['ID_ESTADO'] = 1;
					$row['ESTADO'] = 'Activo';
					$respuesta['usuario'] = $row;
					echo json_encode($respuesta);
				}
				else{
					$e = oci_error($ejecuta_consulta_password);
					//$respuesta = array('Error' => $e['message']);
					$respuesta = array('Error' => 'No se pudo cambiar la contraseña');
					echo json_encode($respuesta);
				}
				oci_free_statement($ejecuta_consulta_password);
			}
		}
		else{
			$respuesta = array('Error' => 'Tu contraseña actual es incorrecta');
			echo json_encode($respuesta);
		}
	}
	else{
		$respuesta = array('Error' => 'Usuario no encontrado');
		echo json_encode($respuesta);
	}
	
	
	oci_free_statement($ejecuta_consulta_password_valido);
	oci_close($conexion);
	
	
	exit;
	
?>