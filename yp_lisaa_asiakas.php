<!DOCTYPE html>
<html lang="fi">
<head>
	<meta charset="UTF-8">
	<link rel="stylesheet" href="css/styles.css">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.0/jquery.min.js"></script>
	<title>Asiakkaat</title>
</head>
<body>
<?php include("header.php");?>
<h1 class="otsikko">Lisää asiakas</h1>
<br><br>
<div id="lomake">
	<form action="yp_lisaa_asiakas.php" name="uusi_asiakas" method="post" accept-charset="utf-8">
		<fieldset><legend>Uuden käyttäjän tiedot</legend>
			<br>
			<label><span>Sähköposti<span class="required">*</span></span></label>
			<input name="sposti" type="email" pattern=".{1,255}" required="required">
			<br><br>
			<label><span>Etunimi</span></label>
			<input name="etunimi" type="text" pattern="[a-zA-Z]{3,20}">
			<br><br>
			<label><span>Sukunimi</span></label>
			<input name="sukunimi" type="text" pattern="[a-zA-Z]{3,20}">
			<br><br>
			<label><span>Puhelin</span></label>
			<input name="puh" type="text" pattern=".{1,20}">
			<br><br>
			<label><span>Yrityksen nimi</span></label>
			<input name="yritysnimi" type="text" pattern=".{1,50}">
			<br><br>
			<label><span>Salasana<span class="required">*</span></span></label>
			<input name="password" type="password" pattern=".{6,}" title="Pituus min 6 merkkiä." required="required">
			<br><br>
			<label><span>Vahvista salasana<span class="required">*</span></span></label>
			<input name="confirm_password" type="password" pattern=".{6,}" title="Pituus min 6 merkkiä." required="required">
			<br><br><br>
			<label><span>Testiasiakas</span></label>
			<input name="demo_user" type="checkbox" title="Asiakas aktiivinen vain määräajan." id="demo">
			
			<span id=inner_label class="hidden">Päivät:</span>
			<input name="paivat" type="number" value="7" class="hidden" min="1" pattern="[0-9]" id="paivat">
			
			<br><br><br>

			<div id="submit">
				<input name="submit" value="Lisää asiakas" type="submit">
			</div>
		</fieldset>

	</form><br><br>

	<?php	
	if (!is_admin()) {
		header("Location:tuotehaku.php");
		exit();
	}
	require 'tietokanta.php';

		if (isset($_POST['sposti'])){
			//jos ei demokäyttäjä, niin aktiiviset paivat 
			if (isset($_POST['demo_user'])){
				$demo = 1;
				$paivat = $_POST['paivat'];
			} else {
				$demo = 0;
				$paivat = 0;
			}
				
			$result = db_lisaa_asiakas($_POST['etunimi'], $_POST['sukunimi'], $_POST['sposti'], $_POST['puh'],
										$_POST['yritysnimi'], $_POST['password'], $_POST['confirm_password'], $demo, $paivat);
			if($result == -1){
				echo "Sähköposti varattu.";
			}
			elseif ($result == -2){
				echo "Salasanat eivät täsmää.";
			}
			elseif ($result == 2) {
				echo "Käyttäjä aktivoitu.";
			}
			else {
				echo "Lisäys onnistui.";
			}
		}

		//return:
		//-1	salasanat ei täsmää
		//-2	käyttäjätunnus on jo olemassa
		//1		lisäys onnistui
		//2		kayttaja aktivoitu uudelleen
		function db_lisaa_asiakas($asiakas_etunimi, $asiakas_sukunimi, $asiakas_sposti,
				$asiakas_puh, $asiakas_yritysnimi, $asiakas_salasana, $asiakas_varmista_salasana, $demo, $paivat){

					$asiakas_hajautettu_salasana = password_hash($asiakas_salasana, PASSWORD_DEFAULT);

					//Tarkastetaan, että salsana ja vahvistussalasana ovat samat.
					if ($asiakas_salasana != $asiakas_varmista_salasana){
						return -2;	//salasanat ei täsmää
					}else {


						//Palvelimeen liittyminen
						$connection = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME) or die("Connection error:" . mysqli_connect_error());
						$tbl_name = 'kayttaja';

						//Tarkastetaan onko samannimistä käyttäjätunnusta
						$query = "SELECT * FROM $tbl_name WHERE sahkoposti='$asiakas_sposti'";
						$result = mysqli_query($connection, $query);
						$count = mysqli_num_rows($result);
						$row = mysqli_fetch_assoc($result);
						

						
						if($count != 0 && $row["aktiivinen"] == 1) {
							return -1; //käyttäjänimi varattu
						}
						elseif ($count != 0 && $row["aktiivinen"] == 0){
							$query = "UPDATE $tbl_name 
										SET aktiivinen=1, etunimi='$asiakas_etunimi', sukunimi='$asiakas_sukunimi', yritys='$asiakas_yritysnimi',
											puhelin='$asiakas_puh', salasana_hajautus='$asiakas_hajautettu_salasana', salasana_vaihdettu=NOW(), demo='$demo', voimassaolopvm=NOW()+INTERVAL '$paivat' DAY
										WHERE sahkoposti='$asiakas_sposti'";
							$result = mysqli_query($connection, $query) or die("Error:" . mysqli_error($connection));
							return 2;	//kayttaja aktivoitu
						}
						else {
							//lisätään tietokantaan
							$query = "INSERT INTO $tbl_name (salasana_hajautus, salasana_vaihdettu, etunimi, sukunimi, yritys, sahkoposti, puhelin, demo, voimassaolopvm)
							VALUES ('$asiakas_hajautettu_salasana', NOW(), '$asiakas_etunimi', '$asiakas_sukunimi', '$asiakas_yritysnimi', '$asiakas_sposti', '$asiakas_puh', '$demo', NOW()+INTERVAL '$paivat' DAY)";
							$result = mysqli_query($connection, $query) or die("Error:" . mysqli_error($connection));;
							return 1;	//kaikki ok
						}
					}
		}
	?>
	</div>
	
	<script type="text/javascript">

	$(document).ready(function(){

			$("#demo").change(function(){

				if(this.checked){
					$("#paivat").removeClass('hidden');
					$("#inner_label").removeClass('hidden');
				}else{
					$("#paivat").addClass('hidden');
					$("#inner_label").addClass('hidden');
				}
			});

		
	});

	</script>
</body>
</html>
