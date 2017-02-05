<?php
require '_start.php'; global $db, $user, $cart;

if ( !$user->isAdmin() ) {
	header("Location:etusivu.php"); exit();
}

//Tarkastetaan yritys_id:n oikeellisuus
$yritys_id = !empty($_GET['yritys_id']) ? $_GET['yritys_id'] : 0;
if (!$db->query("SELECT id FROM yritys WHERE id = ? AND aktiivinen = 1 LIMIT 1", [$yritys_id])){
	header("Location:yp_yritykset.php"); exit();
}

if ( !empty($_POST['submit']) ) {
	$_POST['demo_user'] = (!empty($_POST['demo_user']) && $_POST['demo_user']!=='false') ? '1' : '0';
	$_POST['paivat'] = !empty($_POST['paivat']) ? (int)$_POST['paivat'] : '1';// Demokäyttäjän käyttöaika
	if ( $_POST['demo_user'] === 1 && $_POST['paivat'] < 1 ) { // Tarkistetaan demoajan järjellisyys
		$_POST['paivat'] = 3;
	}
	// Tarkistetaan, että halutulla sähköpostilla ei ole jo aktivoitua käyttäjää.
	$sql = "SELECT id FROM kayttaja WHERE sahkoposti=? AND aktiivinen=1 LIMIT 1";
	$row = $db->query( $sql, [$_POST['sposti']] );

	if ( !$row ) {
		$ss_length = strlen( $_POST['password'] );
		if ( $ss_length >= 8 && $ss_length < 300 ) {
			if ( $_POST['password'] === $_POST['confirm_password'] ) {
				$_POST['password'] = password_hash( $_POST['password'], PASSWORD_DEFAULT );
				$_POST[] = $_POST['paivat']; $_POST[] = $_POST['paivat'];
				unset($_POST['submit']); unset($_POST['confirm_password']); unset($_POST["paivat"]);

				$sql = "INSERT INTO kayttaja 
							( sahkoposti, etunimi, sukunimi, puhelin, salasana_hajautus,
							demo, yritys_id, voimassaolopvm, salasana_uusittava )
						VALUES ( ?, ?, ?, ?, ?, ?, ?, NOW()+INTERVAL ? DAY, '1' )
						ON DUPLICATE KEY UPDATE 
							sahkoposti=VALUES(sahkoposti), etunimi=VALUES(etunimi), sukunimi=VALUES(sukunimi), 
							puhelin=VALUES(puhelin), salasana_hajautus=VALUES(salasana_hajautus), 
							demo=VALUES(demo), yritys_id=VALUES(yritys_id), voimassaolopvm=NOW()+INTERVAL ? DAY,
							salasana_uusittava='1', aktiivinen='1' ";
				$db->query($sql, array_values($_POST));
				header("Location:yp_asiakkaat.php?yritys_id={$_GET['yritys_id']}&feedback=success"); exit;

			} else {
				$feedback = "<p class='error'>Salasanan vahvistus ei täsmää.</p>";
			}
		} else {
			$feedback = "<p class='error'>Salasanan pitää olla vähintään kahdeksan merkkiä pitkä.</p>";
		}
	} else {
		$feedback = "<p class='error'>Kyseisellä sähköpostilla on jo aktivoitu käyttäjä. ID: {$row->id}</p>";
	}
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
	<meta charset="UTF-8">
	<link rel="stylesheet" href="css/styles.css">
	<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
	<script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
	<title>Lisää asiakas</title>
</head>
<body>
<?php require 'header.php'; ?>
<main class="main_body_container lomake">
	<?= !empty($feedback) ? $feedback : '' ?>
	<a class="nappi grey" href="yp_asiakkaat.php?yritys_id=<?= $yritys_id ?>">Takaisin</a><br><br>
	<form action="" name="uusi_asiakas" method="post" accept-charset="utf-8">
		<fieldset><legend>Uuden käyttäjän tiedot</legend>
			<br>
			<label class="required" for="sposti"> Sähköposti </label>
			<input id="sposti" name="sposti" type="email" pattern=".{1,255}" required>
			<br><br>
			<label for="enimi"> Etunimi </label>
			<input id="enimi" name="etunimi" type="text" pattern="[a-zA-Z]{3,20}">
			<br><br>
			<label for="snimi"> Sukunimi </label>
			<input id="snimi" name="sukunimi" type="text" pattern="[a-zA-Z]{3,20}">
			<br><br>
			<label for="puh"> Puhelin </label>
			<input id="puh" name="puh" type="text" pattern="((\+|00)?\d{3,5}|)((\s|-)?\d){3,10}">
			<br><br>
			<label for="ss" class="required"> Salasana </label>
			<input id="ss" name="password" type="password" pattern=".{8,300}"
				   title="Pituus min 8 merkkiä." required>
			<br><br>
			<label for="vahv_ss" class="required"> Vahvista salasana </label>
			<input id="vahv_ss" name="confirm_password" type="password" pattern=".{8,300}"
				   title="Pituus min 8 merkkiä." required><br>
			<span id="check"></span>
			<br><br><br>
			<label for="demo"> Testiasiakas </label>
			<input name="demo_user" type="hidden" value="false"><!-- Tarvitaan, jos checkbox ei ole valittu -->
			<input name="demo_user" type="checkbox" title="Asiakas aktiivinen vain määräajan." id="demo">

            <div id="paivat" style="padding-left: 20px;font-weight: bold; display: inline-block">
			    <label>Päivät:</label>
			    <input name="paivat" type="number" value="7" min="1" maxlength="4"
				   title="Kuinka monta päivää aktiivinen" style="width: 50px;">
            </div>

			<input name="yritys_id" type="hidden" value="<?=$yritys_id?>" >
			<br><br>
			<span class="small_note"><span class="required"></span> = pakollinen kenttä</span>
			<br>

			<div class="center">
				<input class="nappi" name="submit" value="Lisää asiakas" type="submit" id="asiakas_submit">
			</div>
		</fieldset>
	</form><br><br>
</main>
	
<script type="text/javascript">
	$(document).ready(function(){
		/** Muuttujien alustusta */
		let pwSubmit = $('#asiakas_submit'); // Salasanan pituuden ja vahvistuksen tarkistusta varten
		let newPassword = $('#ss'); // Ditto
		let pwCheck = $('#check'); // Ditto

		/** Demo-valinnan alustusta */
		$("#paivat").addClass('disabled');		// Otetaan pvm-input pois käytöstä aluksi
		/** Testiasiakas-valinta
			Onko päivät-valinta disabled? */
		$("#demo").change(function(){
			if ( this.checked ) {
				$("#paivat").removeClass('disabled');
			} else {
				$("#paivat").addClass('disabled');
			}
		});

		/** Salasanojen tarkastus reaaliajassa */
		$('#ss, #vahv_ss').on('keyup', function () {
			pwSubmit.prop('disabled', true).addClass('disabled');
			if ( newPassword.val().length >= 8 ) {
				if ( newPassword.val() === $('#vahv_ss').val() ) {
					pwCheck.html('<i class="material-icons">done</i>Salasana OK.').css('color', 'green');
					pwSubmit.prop('disabled', false).removeClass('disabled');
				} else {
					pwCheck.html('<i class="material-icons">warning</i>Salasanat eivät täsmää').css('color', 'red');
				}
			} else {
				pwCheck.html('<i class="material-icons">warning</i>Salasanan min. pituus on 8 merkkiä.')
					.css('color', 'red');
			}
		});
	});
</script>
</body>
</html>
