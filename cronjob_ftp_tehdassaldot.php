<?php declare(strict_types=1);
error_reporting(E_ERROR);
ini_set('display_errors', "1");
set_include_path(get_include_path().PATH_SEPARATOR.'luokat/');
spl_autoload_extensions('.class.php');
spl_autoload_register();

chdir(__DIR__); // Määritellään työskentelykansio // This breaks symlinks on Windows
set_time_limit(300); // 5min

$db = new DByhteys();

$ohita_otsikkorivi = true;
$rows_in_query_at_one_time = 15000;
$hankintapaikka_id = 140;
$ftp_server = "ftp.nippon-pieces.com";
$ftp_user_name = "osax";
$ftp_user_pass = "5g876bBq=DOj3J)8+674Kq1(RzrR6kx";

$conn_id = ftp_connect($ftp_server);
$login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);

$temp_handle = fopen('php://temp', 'r+');
ftp_fget( $conn_id, $temp_handle, "NPS_OSAX_STK.csv", FTP_ASCII );
ftp_close( $conn_id );

rewind($temp_handle);

if ( !empty( $temp_handle ) ) {
	if ( $ohita_otsikkorivi ) {
		fgetcsv($temp_handle, 1, ";");
	}

	$values = array();
	$check_number = 0;
	$null_rows = 0;
	while ( ($data = fgetcsv( $temp_handle, 200, ";" )) !== false ) {
		if ( $data[0] != null ) {
			$values[] = (int)$hankintapaikka_id; // hkp-ID
			$values[] = utf8_encode( str_replace( " ", "", $data[ 0 ] ) );  // tuote artikkeli-nro
			$values[] = ($data[ 1 ] === "0") ? 0 : 1; // tehdassaldo // arvo tiedostossa on joko 0 tai 'OK'
			++$check_number;
		} else ++$null_rows;
	}

	echo "NULL row error check: number of valid rows: " . $check_number . "<br>" . PHP_EOL;
	echo "NULL row error check: number of NULL rows: " . $null_rows . "<br>" . PHP_EOL;


	$sql = "INSERT INTO toimittaja_tehdassaldo (hankintapaikka_id, tuote_articleNo, tehdassaldo) VALUES (?,?,?)
			ON DUPLICATE KEY UPDATE tehdassaldo = VALUES(tehdassaldo)";

	$values = array_chunk( $values, $rows_in_query_at_one_time );

	foreach ( $values as $values_chunk ) {
		$db->query(
			str_replace("(?,?,?)",
						str_repeat('(?, ?, ?),', (count($values_chunk)/3)-1) . "(?, ?, ?)",
						$sql),
			$values_chunk);
	}

	$db->query( "UPDATE hankintapaikka SET tehdassaldo_viim_paivitys = NOW() WHERE id = ?",
				[$hankintapaikka_id] );
}
