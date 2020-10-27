<?PHP
	/**se define la clase cifra*/
	class CifraPass{
		/**password del correo usado para el envío automático de correos*/
		public $password_correo;
		
		

		/**constructor que le asigna valores a los atributos*/
		function __construct(){
			$this->password_correo = "PrW7t/De0DFJODtStCGfsQtitWednkniVFhTOZUlVrw=";
			
		}

		/**funcion para decifrar, recibe como argumento el texto a descifrar, cifrado y
		codificado en base 64*/
		function descifra($text){
			
			$algoritmo = 'aes-256-cbc';

			// $file = fopen("C:/apps/2314568765845/2345.txt", "r");
			// //$file = fopen("/var/www/2314568765845/2345.txt", "r") or die("Error al abrir el archivo");
			// while(!feof($file)) {
			// 	$z = fgets($file);
			// }
			// fclose($file);

			$z = file_get_contents("C:/apps/2314568765845/2345.txt");
			if(!$z){
				$z = file_get_contents("/var/www/2314568765845/2345.txt");
			}

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