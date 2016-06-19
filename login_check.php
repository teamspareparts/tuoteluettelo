<!DOCTYPE html>
<html>
<head>
<style type="text/css">
body {
	background-color:#3399CC;
}
</style>
</head>
<body>
<h1>Redirecting...</h1>

<!-- Jotta ilmoitus vanhentuneesta salasanasta saadaan lähetettyä -->
<form action="login_check.php" method="post" id="hidden_form">
    <input type="hidden" name="mode" value="password_expired">
    <input type="hidden" name="email" value=" <?php if (isset($_SESSION['email'])) echo $_SESSION['email']; ?> ">
</form>
   					
<?php
	require 'tietokanta.php';
	require 'email.php';
	require 'IP.php';
	session_start();
	
	// Luodaan yhteys
	$connection = mysqli_connect( DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME ) 
					or die("Connection error:" . mysqli_connect_error());
	
	if (!isset($_POST["mode"])) {
		header("Location:login.php?redir=4");
		exit();
	}
	
	$mode = $_POST["mode"];

	//Mode --> Sisääkirjautuminen
	if ( $mode == "login" ) {
		$email 			= trim(strip_tags( $_POST["email"] ));
		$password 		= trim(strip_tags( $_POST["password"] ));
		
		// SQL-kysely
		$sql_query = "
			SELECT 	*
			FROM 	kayttaja
			WHERE 	sahkoposti = '$email'";
		
		$result = mysqli_query($connection, $sql_query) or die(mysqli_error($connection));	// Kyselyn tulos
		$row_count = mysqli_num_rows($result);				// Kyselyn  tuloksen rivien määrä
	
		if ( $row_count > 0 ) {
			$row = mysqli_fetch_assoc($result);
			if ($row["aktiivinen"] == 0){
				header("Location:login.php?redir=2");
				exit;
			}
			else if ( password_verify($password, $row['salasana_hajautus']) ) {
		   		$_SESSION['email']	= $row['sahkoposti'];
		   		$_SESSION['admin']	= $row['yllapitaja'];
		   		$_SESSION['id']		= $row['id'];
		   		
		   		$id=$row['id'];
		   		//Haetaan asiakkaan oikea ip osoite
		   		$remoteaddr = new RemoteAddress();
		   		$ip = $remoteaddr->getIpAddress();
		   		//Haetaan kaupunki lähettämällä asiakkaan ip ipinfo.io serverille
		   		//Toimii vain staattisille osoitteille!
		   		$details = json_decode(file_get_contents("http://ipinfo.io/{$ip}"));
		   		//echo $details->city; //kaupunki
		   		//echo $details->region; //alue
		   		//echo $details->country; //maa
		   		
		   		/** 
		   		 * 
		   		 * 
		   		 * 
		   		 * 
		   		 * 
		   		 * Tähän väliin IP osoitteen tarkastus.
		   		 * 
		   		 * 
		   		 * 
		   		 * 
		   		 * 
		   		 
		   		
		   		$nykyinen_sijainti = $details->city;
		   		$viime_sijainti = $row['viime_sijainti'];
		   		
		   		//Jos sijainti tiedossa
		   		if ($nykyinen_sijainti != ""){
		   			if ($viime_sijainti != "") {
		   				$match = strcmp($nykyinen_sijainti, $viime_sijainti);
		   				if ($match != 0){
		   					//lähetetään ylläpidolle ilmoitus
		   					laheta_ilmoitus_epailyttava_IP($email, $viime_sijainti, $nykyinen_sijainti_sijainti);
		   				}
		   			}
		   			//päivitetään sijainti tietokantaan
		   			$query = "UPDATE kayttaja 
		   						SET viime_sijainti=$viime_sijainti WHERE kayttaja_id=$id";
		   			$result = mysqli_query($connection, $query) or die(mysqli_error($connection));
		   		}
		   		
		   		
		   		
		   		**/
		   		
		   		
		   		
		   		
		   		/** Tarkastetaan onko salasana vanhentunut */
		   		//180päivän välein
		   		$time_then 	= new DateTime( $row['salasana_aika'] );//   muunnettuna DateTime-muotoon
				$time_now	= new DateTime();
		   		if ($time_then->modify('+180 days') < $time_now) {
		   			
		   			//echo $time_then->format('Y-m-d H:i:s');
		   			//echo $time_now->format('Y-m-d H:i:s');	   			
		   			?> 		
		   				
		   			<script type="text/javascript">
		   				document.getElementById('hidden_form').submit();
  					</script>
		   			
		   			<?php
		   		}
		   		
		   		else {
		   		//JOS KAIKKI OK->
		   		header("Location:tuotehaku.php");
		   		exit;
		   		}
			}
			else {	//väärä salasana
				header("Location:login.php?redir=3");
				exit;
			}
		   
		} else { //Ei tuloksia == väärä käyttäjätunnus --> lähetä takaisin
			header("Location:login.php?redir=1");
			exit;
		}
	}

	//Mode --> Salasanan resetointi TAI salasana vanhentunut
	elseif ( $mode == "password_reset" || $mode == "password_expired") {
		$email = trim(strip_tags( $_POST["email"] ));
		$sql_query = "
			SELECT	id, sahkoposti, aktiivinen
			FROM	kayttaja
			WHERE	sahkoposti = '$email'";
		$result = mysqli_query($connection, $sql_query) or die(mysqli_error($connection));	// Kyselyn tulos
		
		if ( $result->num_rows > 0 ) {
			$row = $result->fetch_assoc();
			if ($row["aktiivinen"] == 1){
				$key = GUID();
				// SQL-kysely
				$sql_query = "
					INSERT INTO pw_reset 
						(reset_key, user_id)
					VALUES 
						('$key','$email')";
				
				$result = mysqli_query($connection, $sql_query) or die(mysqli_error($connection));
				
				//Jos salasana vanhentunut, ohjataan suoraan sivulle
				if ($mode == "password_expired"){
					header("Location:pw_reset.php?id=".$key);
					
				}
				
				//jos salasanaa pyydetty sähköpostiin, lähetetään linkki
				else{ 
					laheta_salasana_linkki($email, $key);
					header("Location:login.php?redir=6");
					exit();
				}
			}
			else {
				//käyttäjätili deaktivoitu
				header("Location:login.php?redir=2");
				exit();
			}
		}
		else {
			header("Location:login.php?redir=1");
			exit();
		}
	}
	
	function GUID()	{ // Just... don't even ask.
		if (function_exists('com_create_guid') === true) {
			return trim(com_create_guid(), '{}');
		} else
		return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
	}
?>
</html>
</body>