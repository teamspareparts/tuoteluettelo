<?php declare(strict_types=1);
chdir(__DIR__); // Määritellään työskentelykansio // This breaks symlinks on Windows
set_time_limit(300); // 5min

set_include_path(get_include_path().PATH_SEPARATOR.'luokat/');
spl_autoload_extensions('.class.php');
spl_autoload_register();
require './mpdf/mpdf.php';

$config = parse_ini_file( "./config/config.ini.php" ); // Jannen sähköpostin tarkistusta varten

$db = new DByhteys( $config );

// Haetaan niiden tilauksien tiedot, joilla ei ole vielä laskua (siten juuri tilattu)
$sql = "SELECT id FROM tilaus WHERE maksettu = 1 AND laskunro IS NULL";
$rows = $db->query( $sql, [], FETCH_ALL );

if ( $rows ) {

	// Aivan ensimmäiseksi päivitämme kaikkiin tilauksiin laskunumeron, jotta ei tule ongelmia päällekkäisyyden kanssa.
	// Cronjob ajetaan 1 minuutin välein, laskujen luominen saattaa kestää pitempään.
	$laskunro = $db->query("SELECT laskunro FROM laskunumero LIMIT 1" )->laskunro;

	echo "Laskunumero: {$laskunro}<br>\r\n";
	echo "---<br>\r\n";

	foreach ( $rows as $tilaus ) {
		$sql = "UPDATE tilaus SET laskunro = ? WHERE id = ?";
		$result = $db->query( $sql, [$laskunro++, $tilaus->id], FETCH_ALL );

		echo "{$tilaus->id} lisätty laskunumero ". ($laskunro-1) . "<br>\r\n";
	}

	$db->query("UPDATE laskunumero SET laskunro = ? LIMIT 1", [$laskunro] );
	echo "---<br>\r\n";
	echo "{$laskunro} päivitetty laskunumero<br>\r\n";
	echo "---<br>\r\n";
	echo "Luodaan laskut ja sähköpostit:<br>\r\n";

	if ( !file_exists('./tilaukset') ) {
		mkdir( './tilaukset' );
	}

	foreach ( $rows as $tilaus ) {

		echo "- $tilaus->id :: ";

		$lasku = new Lasku( $db, $tilaus->id, $config['indev'] );

		require './misc/lasku_html.php';     // HTML-tiedostot vaativat $lasku-objektia, joten siksi nämä ei alussa.
		require './misc/noutolista_html.php';

		/********************
		 * Laskun luonti
		 ********************/
		$mpdf = new mPDF();
		$mpdf->SetHTMLHeader( $pdf_lasku_html_header );
		$mpdf->SetHTMLFooter( $pdf_lasku_html_footer );
		$mpdf->WriteHTML( $pdf_lasku_html_body );
		$lasku_nimi = "./tilaukset/lasku-" . sprintf('%05d', $lasku->laskunro) . "-{$lasku->asiakas->id}.pdf";
		$mpdf->Output( $lasku_nimi, 'F' );

		if ( file_exists("./tilaukset/lasku-" . sprintf('%05d', $lasku->laskunro)
						 . "-{$lasku->asiakas->id}.pdf") ) {
			echo " Lasku: OK -";
		}

		/********************
		 * Noutolistan luonti
		 ********************/
		$mpdf = new mPDF();
		$mpdf->SetHTMLHeader( $pdf_noutolista_html_header );
		$mpdf->SetHTMLFooter( $pdf_noutolista_html_footer );
		// Tavalliset tuotteet ja tehdastilaus
		if ( $pdf_noutolista_tuotteet != "" && $pdf_noutolista_tilaustuotteet != "" ) {
			$mpdf->WriteHTML($pdf_noutolista_html_body);
			$mpdf->AddPage();
			$mpdf->WriteHTML($pdf_noutolista_tehdastilaus_html_body);
		}
		// Vain tavalliset tuotteet
		elseif ( $pdf_noutolista_tuotteet != "" ) {
			$mpdf->WriteHTML($pdf_noutolista_html_body);
		}
		// Vain tehdastilaus
		else {
			$mpdf->WriteHTML($pdf_noutolista_tehdastilaus_html_body);
		}

		$noutolista_nimi = "./tilaukset/noutolista-" . sprintf('%05d', $lasku->laskunro)
			. "-{$lasku->asiakas->id}.pdf";
		$mpdf->Output( $noutolista_nimi, 'F' );

		if ( file_exists("./tilaukset/noutolista-" . sprintf('%05d', $lasku->laskunro)
						 . "-{$lasku->asiakas->id}.pdf") ) {
			echo " Noutolista: OK";
		}

		/********************
		 * Sähköpostit
		 ********************/
		Email::lahetaTilausvahvistus( $lasku->asiakas->sahkoposti, $lasku, $lasku_nimi );
		Email::lahetaNoutolista( $tilaus->id, $noutolista_nimi );

		if ( !$_SESSION['indev'] ) {
			Email::lahetaTilausvahvistus( 'janne@osax.fi', $lasku, $lasku_nimi );
		}
		echo "<br>\r\n";
	}

	echo "---<br>\r\n";
	echo "Kaikki valmiina!<br>\r\n";
}
