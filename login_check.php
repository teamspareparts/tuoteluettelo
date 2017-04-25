<?php
/**
 * Tämä sivu on puhtaasti PHP:tä, ei yhtään tulostusta käyttäjälle. Tarkoitus on tarkistaa
 * kirjautumisen kaikki vaiheet, ja lähettää eteenpäin seuraavalle sivulle.
 */
require 'luokat/dbyhteys.class.php';
require 'luokat/email.class.php';
require 'luokat/remoteaddress.class.php';
require 'tecdoc.php';

/**
 * Tarkistaa käyttäjän käyttöoikeuden sivustoon, keskitetysti yhdessä funktiossa.
 * Tarkistaa salasanan, aktiivisuuden, ja demo-tilanteen, siinä järjestyksessä.
 * @param stdClass $user          <p> Käyttää salasana_hajautus-, aktiivinen-, demo-, ja voimassaolopvm-muuttujia
 * @param string   $user_password <p> käyttäjän antama salasana
 * @param bool     $skip_pw_check [optional] <p> jos pw_reset, niin salasanaa ei tarvitse tarkistaa.
 *                                Huom. jos TRUE, ei tarkista salasanaa!
 */
function beginning_user_checks( stdClass $user, /*string*/ $user_password, /*bool*/ $skip_pw_check = false ) {
	if ( !password_verify( $user_password, $user->salasana_hajautus ) && !$skip_pw_check ) {
		header( "Location:index.php?redir=2" );
		exit; //Salasana väärin
	}
	if ( !$user->aktiivinen ) { // Tarkistetaan käyttäjän aktiivisuus
		header( "Location:index.php?redir=3" );
		exit; //Käyttäjä de-aktivoitu
	}
	if ( $user->demo ) { // Onko käyttäjätunnus väliaikainen
		//tarkistetaan, onko kokeilujakso loppunut
		if ( new DateTime( $user->voimassaolopvm ) < new DateTime() ) {
			header( "Location:index.php?redir=9" );
			exit(); //Käyttöoikeus vanhentunut
		}
	}
}

/**
 * Tarkistaa käyttäjän IP:n ja lähettää ylläpitäjälle sähköpostin epäilyttävästä käytöksestä.
 * Lisäksi, jos löytää uuden sijainnin, päivittää sen tietokantaan.
 * Huom. toimii vain staattisilla IP-osoitteilla.
 * @param DByhteys $db
 * @param stdClass $user <p> Käyttää viime_sijainti-muuttujaa.
 */
function check_IP_address( DByhteys $db, stdClass $user ) {
	$ip = RemoteAddress::getIpAddress(); //Haetaan asiakkaan IP-osoite
	$details = json_decode( file_get_contents( "http://ipinfo.io/{$ip}" ) );
	//Haetaan kaupunki lähettämällä asiakkaan ip ipinfo.io serverille
	$nykyinen_sijainti = $details->city;

	if ( $nykyinen_sijainti != "" ) { //Jos sijainti tiedossa
		if ( $user->viime_sijainti != "" ) {
			$match = strcmp( $nykyinen_sijainti, $user->viime_sijainti );
			if ( $match != 0 ) {
				Email::lahetaIlmoitus_EpailyttavaIP( $user, $user->viime_sijainti, $nykyinen_sijainti );
			}
		}
		//päivitetään sijainti tietokantaan
		$sql = "UPDATE kayttaja SET viime_sijainti = $nykyinen_sijainti WHERE sahkoposti = ?";
		$db->query( $sql, [ $user->sahkoposti ] );
	}
}

/**
 * Resetoidaan käyttäjän salasana, joko käyttäjän toimesta, tai ylläpidollisista syistä.
 * Lähettää käyttäjälle linkin sähköpostilla pw_reset-sivulle, tai jos salasana vanhentunut:
 *  ohjaa suoraan kyseiselle sivulle.
 * @param DByhteys $db
 * @param stdClass $user       <p> Käyttää id-, sahkoposti-muuttujia.
 * @param string   $reset_mode <p> onko kyseessä 'reset' vai 'expired'. Eka lähettää linkin, toka ohjaa suoraan.
 */
function password_reset( DByhteys $db, stdClass $user, /*string*/ $reset_mode ) {
	$key = GUID();
	$key_hashed = sha1( $key );

	$sql = "INSERT INTO pw_reset (kayttaja_id, reset_key_hash) VALUES ( ?, ? )";
	$db->query( $sql, [ $user->id, $key_hashed ] );

	if ( $reset_mode === "expired" ) { //Jos salasana vanhentunut, ohjataan suoraan salasananvaihtosivulle
		header( "Location:pw_reset.php?id={$key}" );
		exit;
	}
	else { // jos salasanaa pyydetty sähköpostiin, lähetetään linkki
		Email::lahetaSalasanaLinkki( $user->sahkoposti, $key );
		header( "Location:index.php?redir=6" );
		exit(); // Palautuslinkki lähetetty
	}
}

/**
 * So apparently, com_-aluiset funktiot on poistettu PHP-coresta 5 version jälkeen,
 * ja ne saa vaan lisäämällä manuaalisti.
 * Jälkimmäinen osio luo käytännössä saman asian kuin com_create_guid.
 * Koodi kopioitu PHP-manuaalista, user comment.
 * //TODO päivitä GUID v4 --JJ 17-04-15
 * @return string
 */
function GUID() {
	if ( function_exists( 'com_create_guid' ) ) {
		return trim( com_create_guid(), '{}' );
	}
	else {
		return sprintf( '%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand( 0, 65535 ), mt_rand( 0, 65535 ),
						mt_rand( 0, 65535 ), mt_rand( 16384, 20479 ), mt_rand( 32768, 49151 ), mt_rand( 0, 65535 ),
						mt_rand( 0, 65535 ), mt_rand( 0, 65535 ) );
	}
}

if ( empty( $_POST[ "mode" ] ) ) {
	header( "Location:index.php?redir=4" );
	exit(); // Not logged in
}

$db = new DByhteys();
$mode = $_POST[ "mode" ];
$email = isset( $_POST[ "email" ] ) ? trim( $_POST[ "email" ] ) : null;
$password = (isset( $_POST[ "password" ] ) && strlen( $_POST[ "password" ] ) < 300)
	? trim( $_POST[ "password" ] )
	: null;
$salasanan_voimassaoloaika = 180;

date_default_timezone_set( "Europe/Helsinki" );

/*************************
 *  Sisäänkirjautuminen  *
 *************************/
if ( $mode === "login" ) {
	session_start();
	session_regenerate_id( true );

	// Haetaan käyttäjän tiedot
	$sql = "SELECT id, yritys_id, sahkoposti, salasana_hajautus, vahvista_eula, aktiivinen, demo,
				voimassaolopvm,	viime_sijainti, salasana_vaihdettu, salasana_uusittava
			FROM kayttaja WHERE sahkoposti = ?";
	$login_user = $db->query( $sql, [ $email ] );

	if ( $login_user ) {
		beginning_user_checks( $login_user, $password ); //Tarkistetaan salasana, aktiivisuus, ja demo-tilanne
		// Jos läpi tarkistuksista -->

		//check_IP_address( $db, $user->id, $user_info->viime_sijainti );

		/** Tarkistetaan salasanan voimassaoloaika */
		$time_then = new DateTime( strval( $login_user->salasana_vaihdettu ) ); // muunnettuna DateTime-muotoon
		$time_now = new DateTime();
		//Jos salasana vanhentunut tai salasana on uusittava
		if ( ($time_then->modify( "+{$salasanan_voimassaoloaika} days" ) < $time_now)
				OR $login_user->salasana_uusittava ) {
			password_reset( $db, $login_user, 'expired' );
		}

		else { //JOS KAIKKI OK->
			$_SESSION[ 'id' ] = $login_user->id;
			$_SESSION[ 'yritys_id' ] = $login_user->yritys_id;
			$_SESSION[ 'email' ] = $login_user->sahkoposti;

			$config = parse_ini_file( "./config/config.ini.php" );
			$_SESSION['indev'] = $config['indev'];
			$_SESSION['header_tervehdys'] = $config['header_tervehdys'];

			addDynamicAddress();

			if ( $login_user->vahvista_eula ) {
				header( "Location:eula.php" );
				exit;
			}
			else {
				header( "Location:etusivu.php" );
				exit;
			}
		}

	}
	else { //Ei tuloksia == väärä käyttäjätunnus --> lähetä takaisin
		header( "Location:index.php?redir=1" );
		exit; // Sähköpostia ei löytynyt
	}
}

/***************************
 *  Salasanan vaihtaminen  *
 ***************************/
elseif ( $mode === "password_reset" ) {

	$sql = "SELECT id, sahkoposti, aktiivinen, demo, voimassaolopvm
			FROM kayttaja WHERE sahkoposti = ?";
	$login_user = $db->query( $sql, [ $email ] );

	if ( $login_user ) {
		beginning_user_checks( $login_user, null, true );
		password_reset( $db, $login_user, 'reset' );
	}
	else {
		header( "Location:index.php?redir=1" ); //Sähköpostia ei löytynyt
		exit();
	}
}

else {
	header( "Location:index.php?redir=4" );
	exit();
}
