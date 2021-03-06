<?php declare(strict_types=1);
require "_start.php"; global $db, $user;
require "tecdoc.php";

if ( !$user->isAdmin() ) { // Sivu tarkoitettu vain ylläpitäjille
	header("Location:etusivu.php");
	exit();
}

// Alustetaan GET ja POST muuttujat
$ostotilauskirja_id = isset($_GET['id']) ? $_GET['id'] : null;

// Tarkistetaan muuttujien oikeellisuus
$otk = $db->query("SELECT * FROM ostotilauskirja_arkisto WHERE id = ? LIMIT 1", [$ostotilauskirja_id]);
if ( !$otk ){
	header("Location: etusivu.php");
	exit();
}

$sql = "SELECT tuote.tilauskoodi, tuote.articleNo, tuote.valmistaja, 
			SUM(ostotilauskirja_tuote_arkisto.original_kpl) AS kpl
  		FROM ostotilauskirja_tuote_arkisto
        LEFT JOIN tuote
        	ON ostotilauskirja_tuote_arkisto.tuote_id = tuote.id 
        WHERE ostotilauskirja_id = ?
        	AND tilaustuote = 0
        GROUP BY tuote_id";
$tuotteet = $db->query($sql, [$ostotilauskirja_id], FETCH_ALL);

// Luodaan raportti
$raportti = "";
foreach ($tuotteet as $tuote) {
	// Mikäli tilauskoodi on jostain syystä tyhjä, käytetään artikkelinumeroa.
	$tilauskoodi = !empty($tuote->tilauskoodi) ? $tuote->tilauskoodi : $tuote->articleNo;
	$raportti .= "{$tilauskoodi};{$tuote->kpl}\r\n";
}

// Ladataan tiedosto suoraan selaimeen
$name = $otk->hankintapaikka_id."-".$otk->tunniste."-".$otk->lahetetty.".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename='. $name);
header('Pragma: no-cache');
header("Expires: 0");
$outstream = fopen("php://output", "w");
fwrite($outstream, $raportti);
fclose($outstream);
exit();