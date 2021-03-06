<?php declare(strict_types=1);
require '_start.php'; global $db, $user, $cart;

if ( !$user->isAdmin() ) { // Sivu tarkoitettu vain ylläpitäjille
	header( "Location:etusivu.php" );
	exit();
}

$sql = "SELECT articleNo, valmistaja, tuotteen_nimi, kayttaja_id, pvm, DATE_FORMAT(pvm,'%Y-%m-%d') AS pvm_formatted,
			korvaava_okey, selitys, yritys.nimi AS yritys_nimi, kayttaja.sukunimi
		FROM tuote_hankintapyynto
		JOIN kayttaja ON kayttaja.id = kayttaja_id
		JOIN yritys ON yritys.id = yritys_id
		WHERE kasitelty IS NULL 
		ORDER BY pvm ASC";
$hankintapyynnot = $db->query( $sql, [], FETCH_ALL );

$sql = "SELECT t_op.tuote_id, t_op.kayttaja_id, t_op.pvm, DATE_FORMAT(t_op.pvm,'%Y-%m-%d') AS pvm_formatted,
			t1.nimi AS tuote_nimi, t1.tuotekoodi, t1.valmistaja, t1.varastosaldo, t1.hyllypaikka,
			yritys.nimi AS yritys_nimi, kayttaja.sukunimi, 
			(SELECT SUM(kpl) FROM ostotilauskirja_tuote otk_t WHERE otk_t.tuote_id = t_op.tuote_id) 
				AS otk_kpl_od,
			(SELECT SUM(kpl) FROM ostotilauskirja_tuote_arkisto otk_t_ark
				INNER JOIN ostotilauskirja_arkisto otk_ark ON otk_ark.ostotilauskirja_id = otk_t_ark.ostotilauskirja_id
				WHERE otk_t_ark.tuote_id = t_op.tuote_id AND otk_ark.hyvaksytty = FALSE) 
				AS otk_kpl_til
		FROM tuote_ostopyynto t_op
		INNER JOIN kayttaja ON kayttaja.id = t_op.kayttaja_id
		INNER JOIN yritys ON yritys.id = kayttaja.yritys_id
		INNER JOIN tuote t1 ON t1.id = t_op.tuote_id
		WHERE kasitelty IS NULL
		ORDER BY pvm ASC";
$ostopyynnot = $db->query( $sql, [], FETCH_ALL );

foreach ( $ostopyynnot as $op_rivi ) {
	if ( !empty($op_rivi->hyllypaikka) ) {
		$op_rivi->vastaavat = TuoteMyyntitiedot::haeVastaavat( $db, $op_rivi->tuote_id, $op_rivi->hyllypaikka );
		foreach ( $op_rivi->vastaavat as $vast_tuote ) {
			$op_rivi->vast_varastosaldo_yht += $vast_tuote->varastosaldo;
		}
	} else $op_rivi->vastaavat = [];
}

/** Tarkistetaan feedback, ja estetään formin uudelleenlähetys */
if ( !empty( $_POST ) ) { //Estetään formin uudelleenlähetyksen
	header( "Location: " . $_SERVER[ 'REQUEST_URI' ] );
	exit();
}
else {
	$feedback = isset( $_SESSION[ 'feedback' ] ) ? $_SESSION[ 'feedback' ] : '';
	unset( $_SESSION[ "feedback" ] );
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
	<meta charset="UTF-8">
	<title>Hankintapyynnöt</title>
	<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
	<link rel="stylesheet" href="css/styles.css">
</head>
<body>
<?php require 'header.php'; ?>
<main class="main_body_container">

	<div class="otsikko_container">
		<section class="takaisin">
		</section>
		<section class="otsikko">
			<h1>Osto- ja hankintapyynnöt</h1>
		</section>
		<section class="napit">
		</section>
	</div>

	<?php if ( !$ostopyynnot && !$hankintapyynnot ) : ?>
		<p class="center">Ei jätettyjä ostopyyntöjä tai hankintapyyntöjä.</p>
	<?php endif;
	if ( $ostopyynnot ) : ?>
		<table style="min-width:80%;">
			<thead>
			<tr><th colspan="9" class="center" style="background-color:#1d7ae2;"> Ostopyynnöt </th></tr>
			<tr><th>#</th>
				<th>Tuote</th>
				<th></th>
				<th>Saldo</th>
				<th title="Ostotilauskirjalla kpl">OTK</th>
				<th title="Vastaavia tuotteita kpl">Vastaavat</th>
				<th>Käyttäjä</th>
				<th>Pvm.</th>
				<th>Käsittely:</th>
			</thead>
			<tbody>
			<?php $i = 0; foreach ( $ostopyynnot as $op ) : ?>
				<tr id="op<?=++$i?>" data-id="<?=$op->tuote_id?>">
					<td><?= $i ?></td>
					<td><?= $op->tuotekoodi ?></td>
					<td><?= $op->valmistaja ?><br><?= $op->tuote_nimi ?></td>
					<td><?= $op->varastosaldo ?></td>
					<td>Odottavat: <?=$op->otk_kpl_od ?? '--'?><br>
						Tilattu: <?=$op->otk_kpl_til ?? '--'?></td>
					<td><?= count($op->vastaavat) ?><br>Kpl: <?= $op->vast_varastosaldo_yht ?></td>
					<td><?= $op->sukunimi ?>,<br><?= $op->yritys_nimi ?></td>
					<td><?= $op->pvm_formatted ?></td>
					<td><form action="ajax_requests.php" method="post" data-row-id="op<?=$i?>">
							<select name="ostopyyntojen_kasittely" title="Valitse toiminto">
								<option disabled selected>Valitse vaihtoehto:</option>
								<option value="0">0: Tarkistettu, ei toimenpiteitä</option>
								<option value="1">1: Tarkistettu, säädetty parametreja</option>
								<option value="2">2: Lisätty valikoimaan, tiedotetaan asiakasta</option>
							</select>
							<input type="hidden" name="tuote_id" value="<?= $op->tuote_id ?>">
							<input type="hidden" name="user_id" value="<?= $op->kayttaja_id ?>">
							<input type="hidden" name="pvm" value="<?= $op->pvm ?>">
							<input type="submit" value="OK" class="nappi">
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?= ( $ostopyynnot && $hankintapyynnot ) ? '<br><hr><br>' : '' ?>

	<?php if ( $hankintapyynnot ) : ?>
		<table style="min-width:80%;">
			<thead>
			<tr><th colspan="9" class="center" style="background-color:#1d7ae2;"> Hankintapyynnöt </th></tr>
			<tr><th>#</th>
				<th>Tuote</th>
				<th></th>
				<th>Käyttäjä</th>
				<th>Pvm.</th>
				<th>Korvaava OK?</th>
				<th>Käsittely:</th>
			</tr>
			</thead>
			<tbody>
			<?php $i = 0; foreach ( $hankintapyynnot as $hkp ) : ?>
				<tr id="hkp<?=++$i?>" data-id="<?=$hkp->articleNo?>">
					<td rowspan="2" style="border-bottom:solid black 1px;"><?=$i?></td>
					<td><?= $hkp->articleNo ?></td>
					<td><?= $hkp->valmistaja ?><br><?= $hkp->tuotteen_nimi ?></td>
					<td><?= $hkp->sukunimi ?>,<br><?= $hkp->yritys_nimi ?></td>
					<td><?= $hkp->pvm_formatted ?></td>
					<td><?= ($hkp->korvaava_okey) ? 'Kyllä' : 'Ei' ?></td>
					<td><form action="ajax_requests.php" method="post" data-row-id="hkp<?=$i?>">
							<select name="hankintapyyntojen_kasittely" title="Valitse toiminto">
								<option disabled selected>Valitse vaihtoehto:</option>
								<option value="0">0: Tarkistettu, ei toimenpiteitä</option>
								<option value="1">1: Tarkistettu, säädetty parametreja</option>
								<option value="2">2: Lisätty valikoimaan, tiedotetaan asiakasta</option>
							</select>
							<input type="hidden" name="tuote_id" value="<?= $hkp->articleNo ?>">
							<input type="hidden" name="user_id" value="<?= $hkp->kayttaja_id ?>">
							<input type="hidden" name="pvm" value="<?= $hkp->pvm ?>">
							<input type="submit" value="OK" class="nappi">
						</form>
					</td>
				</tr>
				<tr><td colspan="8"><b>Selitys:</b> <?= !empty($hkp->selitys) ? $hkp->selitys : '[Tyhjä]' ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</main>

<?php require 'footer.php'; ?>

<script>

	document.addEventListener('submit', function(e) {
		let ajax = new XMLHttpRequest();
		let formData = new FormData(e.target);
		let row = document.getElementById(e.target.dataset.rowId);

		ajax.onreadystatechange = function() {
			if (ajax.readyState === 4 && ajax.status === 200) {
				console.log( ajax.responseText );
				if ( ajax.responseText === '1' ) {
					row.style.transition = "all 1s";
					row.style.opacity = "0.2";
				}
			}
		};

		ajax.open('POST', 'ajax_requests.php', true);
		ajax.send( formData );

		e.preventDefault();
		return false;
	});

</script>

</body>
</html>
