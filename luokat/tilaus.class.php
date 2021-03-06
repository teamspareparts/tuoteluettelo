<?php declare(strict_types=1);

/**
 * Class Tilaus
 */
class Tilaus {

	public $id;
	public $kayttaja_id;
	public $kasitelty;
	public $maksettu;
	public $maksutapa;
	public $tilaustuotteet_tilattu;
	public $laskunro;
	public $paivamaara;

	/** @var User */
	public $asiakas = null;
	/** @var Yritys */
	public $yritys = null;

	/** @var String[] */
	public $toimitusosoite = null;

	/** @var Tuote[] */
	public $tuotteet = null;
	public $tuotteet_kpl = 0; // Tuotteiden kappalemäärä

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

	/**
	 * @param DByhteys $db
	 * @param int      $tilaus_id
	 */
	function __construct( DByhteys $db = null, int $tilaus_id = null ) {

		if ( $db === null or $tilaus_id === null ) return;

		$sql = "SELECT id, paivamaara, pysyva_rahtimaksu, maksutapa, laskunro, maksettu, kasitelty, kayttaja_id
				FROM tilaus
				WHERE id = ? LIMIT 1";
		$row = $db->query( $sql, [ $tilaus_id ] );

		if ( $row ) {
			foreach ( $row as $property => $propertyValue ) {
				$this->{$property} = $propertyValue;
			}
			$this->hintatiedot[ 'rahtimaksu' ] = (float)$row->pysyva_rahtimaksu;
		}

		$this->asiakas = new User($db, $this->kayttaja_id);
		$this->yritys = new Yritys($db, $this->asiakas->yritys_id);
		$this->haeToimitusosoite( $db );
		$this->haeTuotteet( $db );
	}

	/**
	 * Hakee toimitusosoitteen tiedot.
	 * @param \DByhteys $db
	 */
	function haeToimitusosoite( DByhteys $db ) {
		$sql = "SELECT pysyva_katuosoite AS katuosoite, pysyva_postinumero AS postinumero,
					pysyva_postitoimipaikka AS postitoimipaikka,
					CONCAT(pysyva_etunimi, ' ', pysyva_sukunimi) AS koko_nimi,
					pysyva_puhelin AS puhelin,
					pysyva_sahkoposti AS sahkoposti,
					pysyva_yritys AS yritys
				FROM tilaus_toimitusosoite WHERE tilaus_id = ? LIMIT 1";
		$this->toimitusosoite = $db->query( $sql, [ $this->id ], false, PDO::FETCH_ASSOC );
	}

	/**
	 * Hakee tilattujen tuotteiden tiedot, ja laskee hintatiedot ja summat ja ALV:t samassa.
	 * @param \DByhteys $db
	 */
	function haeTuotteet( DByhteys $db ) {
		$this->tuotteet = array();

		$sql = "SELECT tuote.id, tuote.tuotekoodi, tuote.hyllypaikka,
					tilaus_tuote.tilaustuote,
					tilaus_tuote.tuotteen_nimi AS nimi,
					tilaus_tuote.valmistaja, 
					tilaus_tuote.kpl AS kpl_maara,
					tilaus_tuote.pysyva_alv AS alv_prosentti,
					tilaus_tuote.pysyva_alennus AS alennus_prosentti,
					tilaus_tuote.pysyva_hinta AS a_hinta_ilman_alv,
					(tilaus_tuote.pysyva_hinta * (1+tilaus_tuote.pysyva_alv)) AS a_hinta,					
					((tilaus_tuote.pysyva_hinta * (1+tilaus_tuote.pysyva_alv)) * (1-tilaus_tuote.pysyva_alennus))
						AS a_hinta_alennettu,	
					(tilaus_tuote.pysyva_hinta * (1-tilaus_tuote.pysyva_alennus))
						AS a_hinta_alennettu_ilman_alv,
					(((tilaus_tuote.pysyva_hinta * (1+tilaus_tuote.pysyva_alv)) * (1-tilaus_tuote.pysyva_alennus))
					 	* tilaus_tuote.kpl)
						AS summa 
				FROM tilaus_tuote
				LEFT JOIN tuote ON tuote.id = tilaus_tuote.tuote_id 
				WHERE tilaus_tuote.tilaus_id = ?";
		$db->prepare_stmt( $sql );
		$db->run_prepared_stmt( [ $this->id ] );

		/*
		 * Käydään läpi tuotteet yksi kerrallaan
		 */
		while ( $row = $db->get_next_row( null, 'Tuote' ) ) {

			/** @var $row Tuote */
			$this->tuotteet[] = $row;
			$this->hintatiedot[ 'tuotteet_yht' ] += $row->summa;
			$this->tuotteet_kpl += $row->kpl_maara;

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
				+= $row->a_hinta_alennettu_ilman_alv * $row->kpl_maara;
			// ALV-määrä. ALV:n määrä * Kpl-määrä
			$this->hintatiedot[ 'alv_kannat' ][ $row->alv_toString(true) ][ 'maara' ]
				+= ($row->a_hinta_alennettu - $row->a_hinta_alennettu_ilman_alv) * $row->kpl_maara;

			// ... ja sitten ALV-kannat yhteensä.
			$this->hintatiedot[ 'alv_perus' ] += $row->a_hinta_alennettu_ilman_alv * $row->kpl_maara;
			$this->hintatiedot[ 'alv_maara' ] += ($row->a_hinta_alennettu - $row->a_hinta_alennettu_ilman_alv) * $row->kpl_maara;
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
			// Summa josta ALV lasketaan
			$this->hintatiedot[ 'alv_kannat' ][ '24' ][ 'perus' ] += $rahti_ilman_alv;
			// ALV:n määrä
			$this->hintatiedot[ 'alv_kannat' ][ '24' ][ 'maara' ]
				+= $this->hintatiedot[ 'rahtimaksu' ] - $rahti_ilman_alv;

			// Yhteensä kaikki (ml. tuotteet ja kaikki ALV-kannat)
			$this->hintatiedot[ 'alv_perus' ] += $rahti_ilman_alv;
			$this->hintatiedot[ 'alv_maara' ] += $this->hintatiedot[ 'rahtimaksu' ] - $rahti_ilman_alv;
		}
		$this->hintatiedot[ 'summa_yhteensa' ] =
			$this->hintatiedot[ 'tuotteet_yht' ] + $this->hintatiedot[ 'rahtimaksu' ];
	}

	/**
	 * @param float $number
	 * @param int   $dec_count [optional] default=2 <p> Kuinka monta desimaalia.
	 * @return string
	 */
	function float_toString( float $number, int $dec_count = 2 ) {
		return number_format( (float)$number, $dec_count, ',', '.' );
	}

	/**
	 * Palauttaa rahtimaksun muodossa 15[ €]. ALV mukaan laskettuna.
	 * @param bool $ilman_euro [optional] default=false <p> Tulostetaanko hinta ilman €-merkkiä.
	 * @return string
	 */
	function rahtimaksu_toString ( /*bool*/ $ilman_euro = false ) {
		return number_format( $this->hintatiedot[ 'rahtimaksu' ], 2, ',', '.' )
			. ( $ilman_euro ? '' : '&nbsp;&euro;' );
	}

	/**
	 * Palauttaa rahtimaksun muodossa 15[ €], ilman ALV.
	 * @param bool $ilman_euro [optional] default=false <p> Tulostetaanko hinta ilman €-merkkiä.
	 * @return string
	 */
	function rahtimaksuIlmanALV_toString ( bool $ilman_euro = false ) {
		$rahti = $this->hintatiedot[ 'rahtimaksu' ] / ($this->hintatiedot[ 'rahtimaksu_alv' ] + 1);

		return number_format( $rahti, 2, ',', '.' )
			. ( $ilman_euro ? '' : '&nbsp;&euro;' );
	}

	/**
	 * Palauttaa rahtimaksun ALV:n. Mahdollinen formaatti: [0,]xx[ %]
	 * @param bool $ilmanPros [optional] default=false <p> Tulostetaanko ALV ilman %-merkkiä.
	 * @param int  $decCount  [optional] default=0 <p> Montako desimaalia (0 == pyöristetty kokonaisluku).
	 * @return string
	 */
	function rahtimaksuALV_toString ( bool $ilmanPros = false, int $decCount = 0 ) {
		$rahtiALV = ($decCount == 0)
			? $this->hintatiedot[ 'rahtimaksu_alv' ] * 100
			: $this->hintatiedot[ 'rahtimaksu_alv' ] ;

		return number_format( $rahtiALV, $decCount, ',', '.' )
			. ( $ilmanPros ? '' : '&nbsp;&#37;' );
	}

}
