<?php print('<pre>');
//chdir(__DIR__); // Määritellään työskentelykansio // This breaks symlinks on Windows
set_time_limit(300); // 5min

require './luokat/dbyhteys.class.php';
$db = new DByhteys();

function debug($var,$var_dump=false){
	echo"<br>\r\n<pre>Print_r ::<br>\r\n";print_r($var);echo"</pre>";
	if($var_dump){echo"<br><pre>Var_dump ::<br>\r\n";var_dump($var);echo"</pre><br>\r\n";};
}

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
	while (($data = fgetcsv($temp_handle, 200, ";")) !== false) {
		$values[] = (int)$hankintapaikka_id; // hkp-ID
		$values[] = utf8_encode($data[0]);  // tuote artikkeli-nro
		$values[] = ($data[1] === "0") ? 0 : 1; // tehdassaldo
	}

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
}