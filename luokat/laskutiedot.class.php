<?php
/**
 * @version 2017-02-xx <p> WIP
 */
class Laskutiedot {

	public $tilaus_pvm = ''; // Tilauksen päivämäärä. Haetaan tietokannasta.
	public $tilaus_nro = ''; // Tilausnumero. Haetaan tietokannasta.
	public $laskunro = null; // Laskun numero. Merkitään laskua luodessa.

	/** @var User */
	public $asiakas = null;
	/** @var Yritys */
	public $yritys = null;
	/** @var Yritys */
	public $osax = null;

	/** @var String[] */
	public $toimitusosoite = null;
	/** @var DByhteys */
	public $db = null;
	/** @var Tuote[] */
	public $tuotteet = null;
	/** @var array */
	public $hintatiedot = array(
		'alv_kannat' => array(),  // Yksittäisten alv-kantojen tietoja varten, kuten perus ja määrä alempana
		'alv_perus' => 0.00,      // Summa yhteensä, josta alv lasketaan
		'alv_maara' => 0.00,      // yhteenlaskettu ALV-maara
		'tuotteet_yht' => 0.00,   // Yhteenlaskettu summa kaikista tuotteista
		'rahtimaksu' => 0.00,     // Rahtimaksu, with ALV
		'rahtimaksu_alv' => 0.24, // Rahtimaksun ALV. 24 % vakituinen arvo
		'summa_yhteensa' => 0.00, // Kaikki maksut yhteenlaskettu. Lopullinen asiakkaan maksama summa.
	);
	public $maksutapa = null;

	public $laskuHeader = null;

	/**
	 * @param DByhteys $db
	 * @param int      $tilaus_id
	 * @param User     $user
	 */
	function __construct( DByhteys $db, /*int*/ $tilaus_id = null, User $user ) {
		/*
		 * Alustetaan luokan muuttujat ja oliot
		 */
		$this->tilaus_nro = !empty( $tilaus_id ) ? $tilaus_id : '[Til nro]';
		$this->db = $db;
		$this->asiakas = $user;
		$this->osax = new Yritys( $db, 1 );

		$this->laskuHeader = ( $_SESSION['indev'] )
			? "<h2 style='color: red;'>InDev testilasku</h2>"
			: "<img src='img/osax_logo.jpg' alt='Osax.fi'>";

		/*
		 * Haetaan tilauksen tiedot, toimitusosoite, tuotteet, ja lopuksi laskun numero tietokannasta.
		 */
		$this->haeTilauksenTiedot();
		$this->haeToimitusosoite();
		$this->haeTuotteet();
		$this->haeLaskunNumero();
	}

	/**
	 * Hakee tilauksen päivämäärän ja rahtimaksun tietokannasta.
	 */
	function haeTilauksenTiedot() {
		$sql = "SELECT paivamaara, pysyva_rahtimaksu, maksutapa, laskunro FROM tilaus WHERE id = ? LIMIT 1";
		$row = $this->db->query( $sql, [ $this->tilaus_nro ] );
		if ( $row ) {
			$this->tilaus_pvm = $row->paivamaara;
			$this->hintatiedot[ 'rahtimaksu' ] = $row->pysyva_rahtimaksu;
			$this->maksutapa = $row->maksutapa;
			$this->laskunro = $row->laskunro;
		}
	}

	/**
	 * Hakee toimitusosoitteen tiedot.
	 */
	function haeToimitusosoite() {
		$sql = "SELECT pysyva_katuosoite AS katuosoite, pysyva_postinumero AS postinumero,
					pysyva_postitoimipaikka AS postitoimipaikka
				FROM tilaus_toimitusosoite WHERE tilaus_id = ? LIMIT 1";
		$this->toimitusosoite = $this->db->query( $sql, [ $this->tilaus_nro ], false, PDO::FETCH_ASSOC );
	}

	/**
	 * Hakee tilattujen tuotteiden tiedot, ja laskee hintatiedot ja summat ja ALV:t samassa.
	 */
	function haeTuotteet() {
		$this->tuotteet = array();

		/*
		 * SQL-käsky, ja DB-luokan metodit
		 */
		$sql = "SELECT tuote.id, tuote.tuotekoodi, tuote.hyllypaikka,
					tilaus_tuote.tuotteen_nimi AS nimi,
					tilaus_tuote.valmistaja, 
					tilaus_tuote.kpl AS kpl_maara,
					tilaus_tuote.pysyva_alv AS alv_prosentti,
					tilaus_tuote.pysyva_alennus AS alennus_prosentti,
					(tilaus_tuote.pysyva_hinta * (1-tilaus_tuote.pysyva_alennus)) 
						AS a_hinta_ilman_alv,
					((tilaus_tuote.pysyva_hinta * (1+tilaus_tuote.pysyva_alv)) * (1-tilaus_tuote.pysyva_alennus))
						AS a_hinta,					
					((tilaus_tuote.pysyva_hinta * (1+tilaus_tuote.pysyva_alv)) * (1-tilaus_tuote.pysyva_alennus))
						AS a_hinta_alennettu,
					(((tilaus_tuote.pysyva_hinta * (1+tilaus_tuote.pysyva_alv)) * (1-tilaus_tuote.pysyva_alennus))
					 	* tilaus_tuote.kpl)
						AS summa 
				FROM tilaus_tuote
				LEFT JOIN tuote ON tuote.id = tilaus_tuote.tuote_id 
				WHERE tilaus_tuote.tilaus_id = ?";
		$this->db->prepare_stmt( $sql );
		$this->db->run_prepared_stmt( [ $this->tilaus_nro ] );

		/*
		 * Käydään läpi tuotteet yksi kerrallaan
		 */
		while ( $row = $this->db->get_next_row( null, 'Tuote' ) ) {

			/** @var $row Tuote */
			$this->tuotteet[] = $row;
			$this->hintatiedot[ 'tuotteet_yht' ] += $row->summa;

			/*
			 * Loppu on hintatietojen laskelua, josta suurin osa ALV-tietojen muistiin pistämistä.
			 * ALV-tiedot säilytetään arrayssa, jossa on kolme arvoa:
			 *   kanta, esim. 24 (%);
			 *   perus, eli summa josta ALV lasketaan; ja
			 *   määrä, eli lasketun ALV:n määrä.
			 */
			// Tarkistetaan, että tuotteen ALV-kanta on listalla.
			if ( !array_key_exists( $row->alv_toString(true), $this->hintatiedot[ 'alv_kannat' ] ) ) {
				$this->hintatiedot[ 'alv_kannat' ][ $row->alv_toString(true) ][ 'kanta' ] = $row->alv_toString();
				$this->hintatiedot[ 'alv_kannat' ][ $row->alv_toString(true) ][ 'perus' ] = 0;
				$this->hintatiedot[ 'alv_kannat' ][ $row->alv_toString(true) ][ 'maara' ] = 0;
			}
			/*
			 * Lisätään ALV-tiedot arrayhin. Ensin yksittäiset ALV-kannat.
			 */
			// Ensimmäisenä lasketaan ALV-perus. Kpl-hinta-ilman-ALV * Kpl-määrä
			$this->hintatiedot[ 'alv_kannat' ][ $row->alv_toString(true) ][ 'perus' ]
				+= $row->a_hinta_ilman_alv * $row->kpl_maara;
			// ALV-määrä. ALV:n määrä * Kpl-määrä
			$this->hintatiedot[ 'alv_kannat' ][ $row->alv_toString(true) ][ 'maara' ]
				+= ($row->a_hinta - $row->a_hinta_ilman_alv) * $row->kpl_maara;

			// ... ja sitten ALV-kannat yhteensä.
			$this->hintatiedot[ 'alv_perus' ] += $row->a_hinta_ilman_alv * $row->kpl_maara;
			$this->hintatiedot[ 'alv_maara' ] += ($row->a_hinta - $row->a_hinta_ilman_alv) * $row->kpl_maara;

		}

		// Vielä lopuksi lisätään rahtimaksun tiedot ALV-hintaan (jos > 0), ja kokonaissummaan.
		if ( $this->hintatiedot[ 'rahtimaksu' ] > 0 ) {
			// Lasketaan veroton rahtimaksu
			$rahti_ilman_alv = $this->hintatiedot[ 'rahtimaksu' ] / ($this->hintatiedot[ 'rahtimaksu_alv' ] + 1);
			// Lisätään ALV:n määrä muiden joukkoon.
			if ( !array_key_exists( 24, $this->hintatiedot[ 'alv_kannat' ] ) ) {
				$this->hintatiedot[ 'alv_kannat' ][ '24' ][ 'kanta' ] = '24 &#37;'; // &#37; == %
				$this->hintatiedot[ 'alv_kannat' ][ '24' ][ 'perus' ] = 0;
				$this->hintatiedot[ 'alv_kannat' ][ '24' ][ 'maara' ] = 0;
			}
			$this->hintatiedot[ 'alv_kannat' ][ '24' ][ 'perus' ] += $rahti_ilman_alv;
			$this->hintatiedot[ 'alv_kannat' ][ '24' ][ 'maara' ]
				+= $this->hintatiedot[ 'rahtimaksu' ] - $rahti_ilman_alv;

			$this->hintatiedot[ 'alv_perus' ] += $rahti_ilman_alv;
			$this->hintatiedot[ 'alv_maara' ] += $this->hintatiedot[ 'rahtimaksu' ] - $rahti_ilman_alv;
		}
		$this->hintatiedot[ 'summa_yhteensa' ] =
			$this->hintatiedot[ 'tuotteet_yht' ] + $this->hintatiedot[ 'rahtimaksu' ];
	}

	function haeLaskunNumero() {
		if ( $this->laskunro === null ) {
			$sql = "SELECT laskunro FROM laskunumero LIMIT 1";
			$row = $this->db->query( $sql );
			$this->laskunro = $row->laskunro;
			$sql = "UPDATE laskunumero SET laskunro = laskunro + 1 LIMIT 1";
			$this->db->query( $sql );
			$sql = "UPDATE tilaus SET laskunro = ? WHERE id = ? LIMIT 1";
			$this->db->query( $sql, [ $this->laskunro, $this->tilaus_nro ] );
		}
	}

	/**
	 * @param float $number
	 * @param int   $dec_count [optional] default=2 <p> Kuinka monta desimaalia.
	 * @return string
	 */
	function float_toString( /*float*/ $number, /*int*/ $dec_count = 2 ) {
		return number_format( (float)$number, $dec_count, ',', '.' );
	}

	/**
	 * Palauttaa rahtimaksun muodossa 15[ €], joko verollisena tai ilman.
	 * @param bool $veroton    [optional] default=false <p> Veroton (ilman ALV)
	 * @param bool $ilman_euro [optional] default=false <p> Tulostetaanko hinta ilman €-merkkiä.
	 * @return string
	 */
	function rahtimaksu_toString ( /*bool*/ $veroton = false, /*bool*/ $ilman_euro = false ) {
		if ( $veroton ) {
			$rahti = $this->hintatiedot[ 'rahtimaksu' ] / ($this->hintatiedot[ 'rahtimaksu_alv' ] + 1);
		} else {
			$rahti = $this->hintatiedot[ 'rahtimaksu' ];
		}
		return number_format( $rahti, 2, ',', '.' ) . ( $ilman_euro ? '' : ' &euro;' );
	}

	/**
	 * Palauttaa rahtimaksun ALV:n. Mahdollinen formaatti: [0,]xx[ %]
	 * @param bool $ilman_pros [optional] default=false <p> Tulostetaanko ALV ilman %-merkkiä.
	 * @param bool $decimaalina [optional] default=false <p> Tulostetaanko ALV decimaalina (vai kokonaislukuna).
	 * @return string
	 */
	function rahtimaksuALV_toString ( /*bool*/ $ilman_pros = false, /*bool*/ $decimaalina = false ) {
		if ( !$decimaalina ) {
			return round( (float)$this->hintatiedot[ 'rahtimaksu_alv' ] * 100 ) . ( $ilman_pros ? '' : ' &#37;' );
		} else
			return number_format( (double)$this->hintatiedot[ 'rahtimaksu_alv' ], 2, ',' );
	}

	/**
	 * @param bool $alku <p> Onko tulostus laskun alkuun vai loppuun? Lopussa pidempi teksti.
	 * @return string
	 */
	function maksutapa_toString ( /*bool*/ $alku = true ) {
		if ( $this->maksutapa ) {
			return ( $alku ? "Lasku 14 pv." : "! Maksetaan laskulla &mdash; maksuaika 14 päivää !" );
		} else {
			return ( $alku ? "e-korttimaksu" : "! Maksettu korttiveloituksena tilausta tehdessä !" );
		}
	}
}
