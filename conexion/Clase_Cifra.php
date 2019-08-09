<?PHP
	/**se define la clase cifra*/
	class Cifra{
		/**usuario para hacer la conexion a la base de datos*/
		public $user;
		/**password para hacer la conexion a la base de datos*/
		public $pass;
		/**servidor para hacer la conexion a la base de datos*/
		public $server;
		/**puerto para hacer la conexion a la base de datos*/
		public $port;
		/**instancia para hacer la conexion a la base de datos*/
		public $instancia;

		/**constructor que le asigna valores a los atributos*/
		function __construct(){
			/**asigno el usuario cifrado al atributo usuario*/
			$this->user="3BqPW6+/Jz4cFtMLsNzY7m8y/YhB2yLWRkB6OfekYKI=";
			/**asigno el password cifrado al atributo password*/
			$this->pass="3GpWC3u4xAOnwG2ap4kSAWOG63LCwoXMzpI89+fR6g8=";
			/**asigno el servidor cifrado al atributo servidor*/
			$this->db="5l/wt2XPZyVpvwQEDbLyP02yaefGP6ziQOZ51oLkquM=";
		}

		/**funcion para decifrar, recibe como argumento el texto a descifrar, cifrado y
		codificado en base 64*/
		function descifra($text){
			
			$algoritmo = 'aes-256-cbc';

			$file = fopen("C:/apps/2314568765845/2345.txt", "r") or die("Error al abrir el archivo");
			//$file = fopen("/var/www/2314568765845/2345.txt", "r") or die("Error al abrir el archivo");
			while(!feof($file)) {
				$z = fgets($file);
			}
			fclose($file);

			/**llave de 16 bytes para poder descifrar, dada en hexadecimal*/
			$key = pack('H*', $z);

			/**se decodifica el texto que estaba en base 64*/
			$text_dec = base64_decode($text);

			/**obtiene el iv codificado*/
			$iv_dec = substr($text_dec, 0, 16);

			/**obtiene el texto a descifrar (sin el iv)*/
			$ciphertext_dec = substr($text_dec, 16);

			/**descifra el texto dado y lo deja con longitud de 16 caracteres*/
			$texto = openssl_decrypt($ciphertext_dec, $algoritmo, $key, OPENSSL_RAW_DATA, $iv_dec);

			//retorna el texto descifrado
			return trim($texto);
		}
	}
?>