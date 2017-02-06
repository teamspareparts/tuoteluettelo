<?php
require "_start.php"; global $db, $user;

if ( !$user->isAdmin() ) { // Sivu tarkoitettu vain ylläpitäjille
	header("Location:etusivu.php"); exit();
}
if ( !isset($_POST["luo_raportti"]) ) {
	header("Location:etusivu.php"); exit();
}

//TODO: Hae myös myynti maksutavan mukaan kortti/verkkomaksu --SL

/** Haetaan kokonaismyynti annetulla aikavälillä */
$sql = "	SELECT COUNT(tilaus.id) AS tapahtumat, SUM(pysyva_hinta*kpl) AS myynti_alvillinen,
	 		SUM(pysyva_hinta*kpl/(1+pysyva_alv)) AS myynti_alviton
			FROM tilaus
			LEFT JOIN tilaus_tuote
				ON tilaus.id = tilaus_tuote.tilaus_id
			WHERE tilaus.paivamaara > ? AND tilaus.paivamaara < ? + INTERVAL 1 DAY";
$myynti = $db->query($sql, [$_POST["pvm_from"], $_POST["pvm_to"]]);

/** Haetaan myynti luokiteltuna ALV-ryhmiin */
$sql = "	SELECT 	pysyva_alv, SUM(pysyva_hinta*kpl) AS myynti_alvillinen
			FROM tilaus
			LEFT JOIN tilaus_tuote
				ON tilaus.id = tilaus_tuote.tilaus_id
			WHERE tilaus.paivamaara > ? AND tilaus.paivamaara < ? + INTERVAL 1 DAY
			GROUP BY tilaus_tuote.pysyva_alv";
$myynti_by_alv = $db->query($sql, [$_POST["pvm_from"], $_POST["pvm_to"]], FETCH_ALL);


/** Ladataan tiedosto suoraan selaimeen */
$datetime = date("d-m-Y h-i-s");
$name = "Myyntiraportti-{$datetime}.txt";
header('Content-Type: text');
header('Content-Disposition: attachment; filename='. $name);
header('Pragma: no-cache');
header("Expires: 0");

$outstream = fopen("php://output", "w");
fwrite($outstream, chr(0xEF).chr(0xBB).chr(0xBF)); //UTF-8 BOM  --ehkä turha -SL
$raportti = "Myyntiraportti aikaväliltä ".date('d.m.Y', strtotime($_POST["pvm_from"])) .
				" - " . date('d.m.Y', strtotime($_POST["pvm_to"])) . "\r\n\r\n" .
			"Myynti yhteensä {$myynti->myynti_alvillinen} € sis alv\r\n" .
			"Myynti yhteensä {$myynti->myynti_alviton} € alv 0%\r\n" .
			"Tapahtumamäärä {$myynti->tapahtumat} kpl\r\n" .
			"\r\n" .
			"ALV erottelu myynnistä\r\n";
foreach ($myynti_by_alv as $alv) {
	$raportti .= "ALV ".($alv->pysyva_alv*100)."% {$alv->myynti_alvillinen}\r\n";

}
fwrite($outstream, $raportti);



fclose($outstream);
exit();