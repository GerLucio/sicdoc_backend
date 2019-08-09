<?PHP
	class Token{
		public $tkn;
		
		function __construct(){
		}

		function generaToken($correo) : void {
			$fecha_actual = date("d-m-Y H:i:s");
			$nueva_fecha = strtotime ('+8 hour', strtotime($fecha_actual));
			$expira = date("d-m-Y H:i:s", $nueva_fecha);

			$correo_b64 = base64_encode($correo);
			$expira_b64 = base64_encode($expira);
			
			$tknt = $correo_b64 . "." . $expira_b64;
			$tknt_c = $this->cifra($tknt);
			$this->tkn = base64_encode($tknt_c);

		}
		
		function cifra($texto) : string {
			$file = fopen("C:/apps/2314568765845/2345.txt", "r") or die("Error al abrir el archivo");
			//$file = fopen("/var/www/2314568765845/2345.txt", "r") or die("Error al abrir el archivo");
			while(!feof($file)) {
				$z = fgets($file);
			}
			fclose($file);
			/**llave de 16 bytes para poder descifrar, dada en hexadecimal*/
			$key = pack('H*', $z);
			
			$algoritmo = 'aes-256-cbc';
			/**Vector de inicialización de 16 bytes para aes 256 en modo CBC*/
			$iv = random_bytes(16);
			
			$texto_cifrado = openssl_encrypt($texto, $algoritmo, $key, OPENSSL_RAW_DATA, $iv);
			
			return $iv.$texto_cifrado;
		}
		
		function validaToken($texto): bool {
			
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
			$text_dec = base64_decode($texto);
			/**obtiene el iv codificado*/
			$iv_dec = substr($text_dec, 0, 16);

			/**obtiene el texto a descifrar (sin el iv)*/
			$ciphertext_dec = substr($text_dec, 16);

			/**descifra el texto dado y lo deja con longitud de 16 caracteres*/
			$texto = openssl_decrypt($ciphertext_dec, $algoritmo, $key, OPENSSL_RAW_DATA, $iv_dec);
			
			if($texto){
				$atributos = explode('.', $texto);
				$expira = base64_decode($atributos[1]);
				$fecha_actual = date("d-m-Y H:i:s");
				$expira = strtotime($expira);
				$fecha_actual = strtotime($fecha_actual);
				if($expira > $fecha_actual){
					return true;
				}
				else return false;
			}
			
			return false;
		}
		
		
	}
?>