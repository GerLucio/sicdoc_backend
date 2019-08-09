<?PHP
	class Usuario{
		public $id_usuario;
		public $nombre;
		public $apellido;
		public $puesto;
		public $correo;
		public $password;
		public $id_departamento;
		public $departamento;
		public $id_rol;
		public $rol;
		public $id_estado;
		public $estado;
		
		function __construct(){
		}
		
		function setPassword(string $password) {
			$password = base64_encode($password);
			$options = ['memory_cost' => 1024, 'time_cost' => 2, 'threads' => 2];
			$password_hash = password_hash($password, PASSWORD_ARGON2I, $options);
			$this->password = base64_encode($password_hash);
			return;
		}
		
		function generaPassword() : string {
			return substr( md5(microtime()), 1, 8);
		}
		
		function validaUsuario(string $password) : bool {
			return password_verify(base64_encode($this->password), base64_decode($password));
		}
		
		
	}
?>