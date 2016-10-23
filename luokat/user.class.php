<?php

/**
 * Class User
 */
class User {

	public $id = NULL;
	public $yritys_id = 0;
    public $aktiivinen = NULL;

	public $sahkoposti = '';

	public $etunimi = '';
	public $sukunimi = '';
	public $puhelin = 0;

	public $yllapitaja = FALSE;
	public $demo = FALSE;
	public $voimassaolopvm = NULL;
	public $salasana_uusittava = NULL;
	/** @var stdClass[] */
	public $toimitusosoitteet = array();

	/**
	 * user constructor.<p>
	 * Hakee käyttäjän tiedot tietokannasta.
	 * Jos 2. parametri on NULL, niin ei tee mitään. Jos ei löydä käyttäjää ID:llä, niin kaikki
	 *  olion arvot pysyvät default arvoissaan. Testaa, löytyikö käyttäjä tarkastamalla, onko ID == NULL.
	 * @param DByhteys $db
	 * @param int $user_id
	 */
	function __construct ( DByhteys $db, /*int*/ $user_id ) {
		if ( $user_id !== NULL ) { // Varmistetaan parametrin oikeellisuus
			$sql = "SELECT id, yritys_id, sahkoposti, etunimi, sukunimi, puhelin,
				  		yllapitaja, demo, voimassaolopvm, salasana_uusittava, aktiivinen
					FROM kayttaja 
					WHERE id = ?
					LIMIT 1";
			$foo = $db->query( $sql, [ $user_id ] );

			if ( $foo ) { // Varmistetaan, että jokin asiakas löytyi
				$this->id = $foo->id;
				$this->yritys_id = $foo->yritys_id;
				$this->sahkoposti = $foo->sahkoposti;
                $this->aktiivinen = $foo->aktiivinen;

				$this->etunimi = $foo->etunimi;
				$this->sukunimi = $foo->sukunimi;
				$this->puhelin = $foo->puhelin;

				$this->yllapitaja = $foo->yllapitaja;
				$this->demo = $foo->demo;
				$this->voimassaolopvm = $foo->voimassaolopvm;
				$this->salasana_uusittava = $foo->salasana_uusittava;
			}
		}
	}

	/**
	 * Palauttaa TRUE jos käyttäjä on ylläpitäjä, ja false muussa tapauksessa.
	 * @return bool <p> Ylläpitäjä-arvon tarkistuksen tulos
	 */
	public function isAdmin () {
		return ($this->yllapitaja === 1);
	}

	/**
	 * Palauttaa koko nimen; muotoiltuna, jos pituus liian pitkä.
	 * @return string
	 */
	public function kokoNimi() {
		$str = ( (strlen($this->etunimi) > 15)
			? (substr($this->etunimi, 0, 1) . ".")
			: $this->etunimi )
			. " {$this->sukunimi}";

		if ( strlen($str) > 30 ) {
			$str = substr($str, 0, 26) . "...";
		}
		return $str;
	}

	/**
	 * Hakee käyttäjän toimitusosoitteet, ja asettaa ne lokaaliin luokan muuttujaan "toimitusosoitteet".
	 * Toisella parametrilla voit määrittää, miten paljon tietoa tarkalleen haetaan.
	 * @param DByhteys $db
	 * @param int $to_id [optional] <p> Hae:
	 *        <ul><li> -2 : vain toimitusosoitteiden määrä (COUNT(id)) </li>
	 *            <li> -1 : kaikki toimitusosoitteet </li>
	 *            <li>  x : tietty toimitusosoite (x on osoitteen ID) </li>
	 *        </ul>
	 * @param bool $omat_tiedot <p> TEMP TODO: Korjaa kunnolla. Tämä on vain väliaikainen ratkaisu siihen,
	 * 		että tilaus käyttää assoc_arrayta, ja omat_tiedot objektia.
	 */
	public function haeToimitusosoitteet ( DByhteys $db, /*int*/ $to_id = -1, $omat_tiedot = false ) {
		if ($omat_tiedot) { $retType = PDO::FETCH_OBJ; } else { $retType = PDO::FETCH_ASSOC; }
		if ( $to_id == -2 ) {
			$sql = "SELECT COUNT(osoite_id) AS count FROM toimitusosoite WHERE kayttaja_id = ? ";
			$this->toimitusosoitteet['count'] = $db->query( $sql, [$this->id] )->count;

		} elseif ( $to_id == -1 ) {
			$sql = "SELECT	osoite_id, etunimi, sukunimi, sahkoposti, puhelin, yritys, 
						katuosoite, postinumero, postitoimipaikka, maa
					FROM	toimitusosoite
					WHERE	kayttaja_id = ?
					ORDER BY osoite_id";
			$this->toimitusosoitteet = $db->query( $sql, [$this->id], DByhteys::FETCH_ALL, $retType );

		} elseif ( $to_id >= 0 ) {
			$sql = "SELECT	osoite_id, etunimi, sukunimi, sahkoposti, puhelin, yritys, 
						katuosoite, postinumero, postitoimipaikka, maa
					FROM	toimitusosoite
					WHERE	kayttaja_id = ? AND osoite_id = ?
					ORDER BY osoite_id LIMIT 1";
			$this->toimitusosoitteet = $db->query( $sql, [$this->id, $to_id] );
		}
	}

	/**
	 * Vaihtaa salasanan käyttäjälle.
	 * Salasana hajautetaan metodissa. Salasanan pitää olla vähintään 8 merkkiä pitkä.
	 * @param DByhteys $db
	 * @param $uusi_salasana <p> Hajauttamaton uusi salasana, vähintään 8 merkkiä pitkä.
	 * @return bool <p> Palauttaa true, jos salasana > 8 ja vaihtaminen onnistui. Muuten false.
	 */
	public function vaihdaSalasana( DByhteys $db, /*string*/ $uusi_salasana ) {
		if ( strlen($uusi_salasana) >= 8 ) {
			$hajautettu_uusi_salasana = password_hash( $uusi_salasana, PASSWORD_DEFAULT );
			return $db->query(
				"UPDATE kayttaja SET salasana_hajautus = ? WHERE id = ?",
				[ $hajautettu_uusi_salasana, $this->id ] );
		} else {
			return false;
		}
	}

	/**
	 * Palauttaa, onko olio käytettävissä, eikä NULL.
	 * @return bool
	 */
	public function isValid () {
		return ( $this->id !== NULL );
	}
}