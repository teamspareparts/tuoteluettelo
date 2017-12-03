<?php declare(strict_types=1);
/**
 * Class Tuote
 */
class Tuote {
	/** @var int $id <p> Tuotteen ID meidän tietokannassa */ public $id = null;
	/** @var string $articleNo <p> Tunnus TecDocista */ public $articleNo = null;
	/** @var string $brandNo <p> Valmistajan tunnus TecDocista */ public $brandNo = null;
	/** @var int $hankintapaikkaID <p> Hankintapaikan ID, meidän tietokannasta */ public $hankintapaikkaID = 0;
	/** @var string $tuotekoodi <p> Tuotteen koodi, TecDocista */ public $tuotekoodi = null;
	/** @var string $tilauskoodi <p> Koodi tilauskirjaa varten */ public $tilauskoodi = null;

	/** @var string $hyllypaikka <p> */ public $hyllypaikka = null;
	/** @var int $varastosaldo <p> */ public $varastosaldo = 0;
	/** @var int $tehdassaldo <p> */ public $tehdassaldo = 0;
	/** @var int $minimimyyntiera <p> */ public $minimimyyntiera = 0;


	/** @var string $nimi <p> */ public $nimi = null;
	/** @var string $valmistaja <p> */ public $valmistaja = null;

	/** @var array $tuoteryhmat <p> Kaikkien ryhmien ID, joissa tuote on. */ public $tuoteryhmat = array();

	/** @var float $a_hinta_ilman_alv <p> Kappale-hinta ilman alennusta, tai ALV:ta */ public $a_hinta_ilman_alv = 0.00;
	/** @var float $a_hinta <p> Kappale-hinta ALV:n kanssa (ilman alennusta) */ public $a_hinta = 0.00;
	/** @var float $a_hinta_alennettu <p> With ALV ja alennus. */ public $a_hinta_alennettu = 0.00;
	/** @var float $a_hinta_alennettu <p> w/out ALV, mutta w/ alennus. */ public $a_hinta_alennettu_ilman_alv = 0.00;

	/** @var float $alv_prosentti <p> */ public $alv_prosentti = 0.00;
	/** @var float $alennus_prosentti <p> */ public $alennus_prosentti = 0.00;

	/** @var int $kpl_maara <p> */ public $kpl_maara = 0;
	/** @var float $summa <p> */ public $summa = 0.00;

	// Yp_valikoima sivulla käytössä olevat muuttujat
	/** @var float $ostohinta <p> Ylläpitoa varten */ public $ostohinta = 0.00;
	/** @var int $vuosimyynti <p> */ public $vuosimyynti = 0;
	/** @var string $ensimmaisenKerranVarastossa <p> timestamp */ public $ensimmaisenKerranVarastossa = null;
	/** @var bool $paivitettava <p> */ public $paivitettava = null;
	/** @var bool $tecdocissa <p> */ public $tecdocissa = null;
	/** @var bool $aktiivinen <p> */ public $aktiivinen = null;
	/** @var string $kuvaURL <p> */ public $kuvaURL = null;
	/** @var string $infot <p> */ public $infot = null;
	/** @var string $hankintapaikkaNimi <p> */ public $hankintapaikkaNimi = null;

	/**
	 * <code>
	 * Array [
	 * 	kpl-määrä => [ kpl-määrä,
	 * 				   alennus-prosentti ], ...
	 * ]
	 * </code>
	 * @var array $maaraalennus_kpl_raja <p> Määräalennuksen kpl-rajat, ja alennusprosentit
	 */
	public $maaraalennukset = array();
	/** @var string $alennus_huomautus <p> */ public $alennus_huomautus = '---';

	/**
	 * @param DByhteys $db [optional]
	 * @param int      $id [optional] <p> Halutun tuotteen ID tietokannassa
	 */
	function __construct ( DByhteys $db = null, int $id = 0 ) {
		if ( $db === null or $id === null ) return;

		$sql = "SELECT tuote.id, articleNo, brandNo, tuote.hankintapaikka_id, tuotekoodi,
					tilauskoodi, varastosaldo, minimimyyntiera, valmistaja, nimi,
					ALV_kanta.prosentti AS alv_prosentti, hyllypaikka, sisaanostohinta AS ostohinta, 
					(hinta_ilman_alv * (1+ALV_kanta.prosentti)) AS a_hinta,
					(hinta_ilman_alv * (1+ALV_kanta.prosentti)) AS a_hinta_alennettu,
					hinta_ilman_alv AS a_hinta_ilman_alv, hinta_ilman_alv AS a_hinta_alennettu_ilman_alv,
					toimittaja_tehdassaldo.tehdassaldo
				FROM tuote
				LEFT JOIN ALV_kanta ON tuote.ALV_kanta = ALV_kanta.kanta
				LEFT JOIN toimittaja_tehdassaldo 
					ON tuote.hankintapaikka_id = toimittaja_tehdassaldo.hankintapaikka_id
						AND tuote.articleNo = toimittaja_tehdassaldo.tuote_articleNo
				WHERE tuote.id = ? LIMIT 1";
		// a_hinta_alennettu on sama, jotta a_hinta_toString() toimii.
		// Se on kuitenkin tarkoitus laskea manuaalisti jälkeenpäin, koska alennukset haetaan erikseen.
		$row = $db->query( $sql, [ $id ] );

		foreach ( $row as $property => $propertyValue ) {
			$this->{$property} = $propertyValue;
		}
	}

	/**
	 * @param DByhteys $db
	 */
	function haeTuoteryhmat( DByhteys $db ) {
		$rows = $db->query( "SELECT tuoteryhma_id FROM tuoteryhma_tuote WHERE tuote_id = ?",
							[ $this->id ], FETCH_ALL, PDO::FETCH_NUM );
		foreach ( $rows as $row ) {
			$this->tuoteryhmat[] = $row[0];
		}
	}

	/**
	 * @deprecated Tämä funktio on käytössä vain ostoskorissa. Siirretty ostoskori luokkaan.
	 *
	 * @param DByhteys $db
	 * @param int      $yritys_id
	 */
	function haeAlennukset ( DByhteys $db ) {
		/*
		 * Tuotteiden normaalit määräalennukset.
		 */
		$sql = "SELECT 1 AS alennusTyyppi, maaraalennus_kpl, alennus_prosentti, alkuPvm, loppuPvm
				FROM tuote_erikoishinta
				WHERE tuote_id = ?
					AND (alkuPvm <= CURRENT_TIMESTAMP)
					AND (loppuPvm >= CURRENT_TIMESTAMP OR loppuPvm IS NULL)
				ORDER BY maaraalennus_kpl";
		$tuote_maaraalennukset = $db->query( $sql, [ $this->id ], FETCH_ALL );

		/*
		 * Yrityksekohtaiset määräalennukset (hakee eri taulusta).
		 */
		$sql = "SELECT 2 AS alennusTyyppi, yritys_id, yritys.nimi, maaraalennus_kpl,
					tuoteyritys_erikoishinta.alennus_prosentti, alkuPvm, loppuPvm
				FROM tuoteyritys_erikoishinta
				JOIN yritys ON yritys.id = tuoteyritys_erikoishinta.yritys_id
				WHERE tuote_id = ?
					AND (alkuPvm <= CURRENT_TIMESTAMP)
					AND (loppuPvm >= CURRENT_TIMESTAMP OR loppuPvm IS NULL)
				ORDER BY maaraalennus_kpl";
		$yritys_maaraalennukset = $db->query( $sql, [ $this->id ], FETCH_ALL );

		/*
		 * Tuoteryhmäkohtaiset määräalennukset (hakee kolmannesta taulusta).
		 */
		$ryhma_maaraalennukset = [];
		if ( $this->tuoteryhmat ) {
			$inQuery = implode(',', array_fill(0, count($this->tuoteryhmat), '?'));
			$values = array_merge( [ $this->hankintapaikkaID ], $this->tuoteryhmat );

			$sql = "SELECT 3 AS alennusTyyppi, maaraalennus_kpl, alennus_prosentti, alkuPvm, loppuPvm
					FROM tuoteryhma_erikoishinta
					WHERE hankintapaikka_id = ?
						AND (alkuPvm <= CURRENT_TIMESTAMP)
						AND (loppuPvm >= CURRENT_TIMESTAMP OR loppuPvm IS NULL)
						AND tuoteryhma_id IN ( {$inQuery} )
					ORDER BY maaraalennus_kpl";
			$ryhma_maaraalennukset = $db->query( $sql, $values, FETCH_ALL );
		}

		$this->maaraalennukset = array_merge( $tuote_maaraalennukset, $yritys_maaraalennukset, $ryhma_maaraalennukset );
		asort( $this->maaraalennukset ); // Järjestää ne kpl-määrän mukaan, joten niiden läpikäynti helpompaa.
	}

	/**
	 * @param boolean $ilman_euro [optional] default=false <p> Tulostetaanko hinta ilman €-merkkiä.
	 * @param int     $dec_count  [optional] default=2 <p> Kuinka monta desimaalia.
	 * @return string
	 */
	function aHinta_toString ( bool $ilman_euro = false, int $dec_count = 2 ) {
		return number_format( $this->a_hinta, $dec_count, ',', '.' )
			. ($ilman_euro ? '' : '&nbsp;&euro;');
	}

	/**
	 * @param boolean $ilman_euro [optional] default=false <p> Tulostetaanko hinta ilman €-merkkiä.
	 * @param int     $dec_count  [optional] default=2 <p> Kuinka monta desimaalia.
	 * @return string
	 */
	function aHintaIlmanALV_toString ( bool $ilman_euro = false, int $dec_count = 2 ) {
		return number_format( $this->a_hinta_ilman_alv, $dec_count, ',', '.' )
			. ($ilman_euro ? '' : '&nbsp;&euro;');
	}

	/**
	 * @param boolean $ilman_euro [optional] default=false <p> Tulostetaanko hinta ilman €-merkkiä.
	 * @param int     $dec_count  [optional] default=2 <p> Kuinka monta desimaalia.
	 * @return string
	 */
	function aHintaAlennettu_toString ( bool $ilman_euro = false, int $dec_count = 2 ) {
		return number_format( $this->a_hinta_alennettu, $dec_count, ',', '.' )
			. ($ilman_euro ? '' : '&nbsp;&euro;');
	}

	/**
	 * @param boolean $ilman_euro [optional] default=false <p> Tulostetaanko hinta ilman €-merkkiä.
	 * @param int     $dec_count  [optional] default=2 <p> Kuinka monta desimaalia.
	 * @return string
	 */
	function aHintaAlennettuIlmanALV_toString ( bool $ilman_euro = false, int $dec_count = 2 ) {
		return number_format( $this->a_hinta_alennettu_ilman_alv, $dec_count, ',', '.' )
			. ($ilman_euro ? '' : '&nbsp;&euro;');
	}

	/**
	 * @param boolean $ilman_alv  [optional] default=false <p> Tulostetaanko hinta ilman ALV:ta.
	 * @param boolean $ilman_euro [optional] default=false <p> Tulostetaanko hinta ilman €-merkkiä.
	 * @param int     $dec_count  [optional] default=2 <p> Kuinka monta desimaalia.
	 * @return string
	 */
	function summa_toString ( bool $ilman_alv = false, bool $ilman_euro = false, int $dec_count = 2 ) {
		$summa = ($ilman_alv)
			? ($this->a_hinta_alennettu_ilman_alv * $this->kpl_maara)
			: $this->summa;

		return number_format( $summa, $dec_count, ',', '.' ) . ($ilman_euro ? '' : '&nbsp;&euro;');
	}

	/**
	 * Palauttaa ALV:n. Mahdollinen formaatti: [0,]xx[ %]
	 * @param bool $ilman_pros [optional] default=false <p> Tulostetaanko ALV ilman %-merkkiä.
	 * @param int  $decCount   [optional] default=0 <p> Montako desimaalia (0 == kokonaislukuna).
	 * @return string
	 */
	function alv_toString ( bool $ilman_pros = false, int $decCount = 0 ) {
		return ($decCount)
			? number_format( $this->alv_prosentti, $decCount, ',', '.' )
			: number_format( $this->alv_prosentti * 100, 0, ',', '.' )
				. ($ilman_pros ? '' : '&nbsp;&#37;');
	}

	/**
	 * @param bool $ilman_pros [optional] default=false <p> Tulostetaanko ALV ilman %-merkkiä.
	 * @param int  $decCount   [optional] default=0 <p> Tulostetaanko ALV decimaalina (vai kokonaislukuna).
	 * @return string
	 */
	function alennus_toString ( bool $ilman_pros = false, int $decCount = 0 ) {
		return ($decCount)
			? number_format( $this->alennus_prosentti, $decCount, ',', '.' )
			: number_format( $this->alennus_prosentti * 100, 0, ',', '.' )
				. ($ilman_pros ? '' : '&nbsp;&#37;');
	}

	/**
	 * @param bool $ilmanEuro [optional] default=false <p> Tulostetaanko ilman €-merkkiä.
	 * @param int  $decCount  [optional] default=2 <p> Kuinka monta desimaalia
	 * @return string
	 */
	function ostohinta_toString ( bool $ilmanEuro = false, int $decCount = 2 ) {
		return number_format( $this->ostohinta, $decCount, ',', '.' )
			. ($ilmanEuro ? '' : '&nbsp;&euro;');
	}
}
