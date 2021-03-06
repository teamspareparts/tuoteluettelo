<?php declare(strict_types=1);
require '_start.php'; global $db, $user, $cart;

if ( !$user->isAdmin() ) {
	header( "Location:etusivu.php" );
	exit();
}

/**
 * @param DByhteys $db
 * @param int[]    $tuote_tiedot <p> [BrandNo, HankintapaikkaID]. Jos nolla, hakee kaikki.
 * @param int[]    $pagination <p> [ppp, offset]. Products Per Page, ja monennestako tuotteesta aloitetaan palautus.
 * @param int[]    $ordering <p> [kolumni, ASC|DESC]. 1. int on kolumnin järjestys taulukossa. 2. 0=ASC, 1=DESC
 * @return array <p> [0] = row count, [1] tuotteet
 */
function hae_tuotteet( DByhteys $db, array $tuote_tiedot=[0,0], array $pagination=[20,0], array $ordering=[1,1] ) : array {

	$brandNo = $tuote_tiedot[0];
	$hankintapaikka_id = $tuote_tiedot[1];

	$ppp = $pagination[0];
	$offset = $pagination[1];

	$orders = array(
		["brandNo", "tuotekoodi", "a_hinta", "hinta_ilman_alv", "sisaanostohinta", "hinnoittelukate", "varastosaldo", "hyllypaikka"],
		["ASC","DESC"]
	);
	$ordering = "{$orders[0][$ordering[0]]} {$orders[1][$ordering[1]]}";


	$sql_start = "SELECT tuote.id, articleNo, brandNo, tuote.hankintapaikka_id, tuotekoodi,
					tilauskoodi, varastosaldo, minimimyyntiera, valmistaja, tuote.nimi,
					ALV_kanta.prosentti AS alv_prosentti, hyllypaikka, sisaanostohinta AS ostohinta, 
					(hinta_ilman_alv * (1+ALV_kanta.prosentti)) AS a_hinta,
					(hinta_ilman_alv * (1+ALV_kanta.prosentti)) AS a_hinta_alennettu,
					hinta_ilman_alv AS a_hinta_ilman_alv, hinta_ilman_alv AS a_hinta_alennettu_ilman_alv,
					toimittaja_tehdassaldo.tehdassaldo, paivitettava, tecdocissa, aktiivinen, vuosimyynti,
					ensimmaisen_kerran_varastossa as ensimmaisenKerranVarastossa,
					hankintapaikka.nimi as hankintapaikkaNimi, keskiostohinta, yhteensa_kpl AS yhteensaKpl
				FROM tuote
				LEFT JOIN ALV_kanta ON tuote.ALV_kanta = ALV_kanta.kanta
				LEFT JOIN toimittaja_tehdassaldo 
					ON tuote.hankintapaikka_id = toimittaja_tehdassaldo.hankintapaikka_id
						AND tuote.articleNo = toimittaja_tehdassaldo.tuote_articleNo
				LEFT JOIN hankintapaikka ON tuote.hankintapaikka_id = hankintapaikka.id ";

	if ( $brandNo and $hankintapaikka_id ) {
		$sql_end = "WHERE brandNo = ? AND tuote.hankintapaikka_id = ?
					ORDER BY {$ordering} LIMIT ? OFFSET ?";
		$results = $db->query( $sql_start.$sql_end, [ $brandNo, $hankintapaikka_id, $ppp, $offset ],
		                      FETCH_ALL, null, "Tuote" );

		$row_count = $db->query("SELECT COUNT(id) AS row_count FROM tuote WHERE brandNo = ? AND hankintapaikka_id = ?",
		                   [$brandNo, $hankintapaikka_id])->row_count;
	}
	elseif ( $brandNo or $hankintapaikka_id ) {
		$sql_end = "WHERE tuote.brandNo = ? OR tuote.hankintapaikka_id = ?
					ORDER BY {$ordering} LIMIT ? OFFSET ?";
		$results = $db->query( $sql_start.$sql_end, [ $brandNo, $hankintapaikka_id, $ppp, $offset ],
		                      FETCH_ALL, null, "Tuote" );

		$row_count = $db->query("SELECT COUNT(id) AS row_count FROM tuote WHERE brandNo = ? OR hankintapaikka_id = ?",
						   [$brandNo, $hankintapaikka_id])->row_count;
	}
	else {
		$sql_end = "ORDER BY {$ordering} LIMIT ? OFFSET ?";
		$results = $db->query( $sql_start.$sql_end, [ $ppp, $offset ], FETCH_ALL, null, "Tuote" );
		$row_count = $db->query("SELECT COUNT(id) AS row_count FROM tuote")->row_count;
	}

	/** @var Tuote[] $results */

	foreach ( $results as $t ) {
		$t->haeTuoteryhmat( $db, true );
		$t->haeAlennukset( $db );

		/**
		 * Seuraava osio voisi olla luultavasti huomattavasti nopeampi jos lisätty ylhäällä olevaan isoon hakuun.
		 * Mutta en jaksa. Joten nopea ratkaisu. Tämä on ollut jo tarpeeksi pitkään tekeillä.
		 * --jj 180122
		 */
		/** @var \stdClass $temp_myyntitiedot */
		$temp_myyntitiedot = TuoteMyyntitiedot::tuotteenVuosimyynti( $db, $t->id );
		//debug( $temp_myyntitiedot, true );
		$t->keskimyyntihinta = $temp_myyntitiedot->keskimyyntihinta ?? 0;
		$t->vuosimyynti = $temp_myyntitiedot->kpl_maara ?? 0;
	}

	return [$row_count, $results];
}

/**
 * Kaikki brändit, kaikki hankintapaikat<br>
 * Kaikki brändit, [hankintapaikka]<br>
 * [Brändi], kaikki hankintapaikat<br>
 * [Brändi], [hankintapaikka]
 * @param int    $brand_id
 * @param int    $hankintapaikka_id
 * @param \Tuote $tuote
 * @return string
 */
function luo_otsikon_tulostus ( int $brand_id, int $hankintapaikka_id, Tuote $tuote ) : string {
	if ( $brand_id != 0 ) {
		$echo = $tuote->valmistaja . ', ';
	} else $echo = 'Kaikki brändit, ';

	if ( $hankintapaikka_id != 0 ) {
		$echo .= $tuote->hankintapaikkaNimi;
	} else $echo .= 'kaikki hankintapaikat';

	return $echo;
}

$brand_id = (int)($_GET[ 'brand' ] ?? 0);
$hankintapaikka_id = (int)($_GET[ 'hkp' ] ?? 0);
$page = (int)($_GET[ 'page' ] ?? 1); // Mikä sivu tuotelistauksessa
$products_per_page = (int)($_GET[ 'ppp' ] ?? 20); // Miten monta tuotetta per sivu näytetään.
$order_column = (int)($_GET[ 'col' ] ?? 1); // Mikä sivu tuotelistauksessa
$order_direction = (int)($_GET[ 'dir' ] ?? 1); // Miten monta tuotetta per sivu näytetään.

if ( $page < 1 ) { $page = 1; }
if ( $products_per_page < 1 || $products_per_page > 5000 ) { $products_per_page = 20; }
$offset = ($page - 1) * $products_per_page; // SQL-lausetta varten; kertoo monennestako tuloksesta aloitetaan haku

$results = hae_tuotteet( $db, [$brand_id, $hankintapaikka_id], [$products_per_page, $offset],
                         [$order_column,$order_direction] );

$total_products = $results[0];
/**
 * @var Tuote[] $tuotteet
 */
$tuotteet = $results[1];

$otsikko_tulostus = luo_otsikon_tulostus($brand_id, $hankintapaikka_id, $tuotteet[0]);


if ( $total_products < $products_per_page ) {
	$products_per_page = $total_products;
}

$total_pages = ( $total_products !== 0 )
	? ceil( $total_products / $products_per_page )
	: 1;

if ( $page > $total_pages ) {
	header( "Location:yp_valikoima.php?brand={$brand_id}&hkp={$hankintapaikka_id}&page={$total_pages}&ppp={$products_per_page}&col={$order_column}&dir={$order_direction}" );
	exit();
}

$first_page = "?brand={$brand_id}&hkp={$hankintapaikka_id}&page=1&ppp={$products_per_page}&col={$order_column}&dir={$order_direction}";
$prev_page = "?brand={$brand_id}&hkp={$hankintapaikka_id}&page=".($page-1)."&ppp={$products_per_page}&col={$order_column}&dir={$order_direction}";
$next_page = "?brand={$brand_id}&hkp={$hankintapaikka_id}&page=".($page+1)."&ppp={$products_per_page}&col={$order_column}&dir={$order_direction}";
$last_page = "?brand={$brand_id}&hkp={$hankintapaikka_id}&page={$total_pages}&ppp={$products_per_page}&col={$order_column}&dir={$order_direction}";
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Valikoima</title>
	<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
	<link rel="stylesheet" href="./css/dialog-polyfill.css">
	<link rel="stylesheet" href="./css/styles.css">
	<script src="./js/dialog-polyfill.js"></script>
	<script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
	<style>
	</style>
</head>
<body>
<?php require 'header.php'; ?>

<main class="main_body_container">
	<div class="otsikko_container">
		<section class="otsikko">
			<h1>Valikoima</h1>
			<span><?= $otsikko_tulostus ?></span>
		</section>
	</div>

	<nav style="white-space: nowrap; display: inline-flex; margin:5px auto 20px;">
		<a class="nappi" href="<?=$first_page?>"> <i class="material-icons">first_page</i> </a>
		<a class="nappi" href="<?=$prev_page?>"> <i class="material-icons">navigate_before</i> </a>

		<div class="white-bg" style="border: 1px solid; margin:auto; padding:10px; line-height: 25px;">
			<form method="GET" style="display: inline;">
				<input type="hidden" name="brand" value="<?=$brand_id?>">
				<input type="hidden" name="hkp" value="<?=$hankintapaikka_id?>">
				<input type="hidden" name="ppp" value="<?=$products_per_page?>">
				<input type="hidden" name="col" value="<?=$order_column?>">
				<input type="hidden" name="dir" value="<?=$order_direction?>">
				<label>Sivu:
					<input type="number" name="page" value="<?=$page?>"
					       min="1" max="<?=$total_pages?>"  maxlength="2"
					       style="padding:5px; border:0; width:3.5rem; text-align: right;">
				</label>/ <?=format_number($total_pages,0)?>
				<input class="hidden" type="submit">
			</form>
			<br>Tuotteet: <?=format_number($offset,0)?>&ndash;<?=format_number($offset + $products_per_page,0)?> /
				<?= format_number($total_products, 0) ?>
		</div>

		<a class="nappi" href="<?=$next_page?>"> <i class="material-icons">navigate_next</i> </a>
		<a class="nappi" href="<?=$last_page?>"> <i class="material-icons">last_page</i> </a>

		<div class="white-bg" style="display:flex; flex-direction:column; margin:auto 40px auto; border: 1px solid; padding:5px;">
			<span>Valitse sivunumero, ja paina Enter-näppäintä vaihtaaksesi sivua.</span>
			<div>Tuotteita per sivu:
				<form class="productsPerPageForm" method="GET">
					<input type="hidden" name="brand" value="<?=$brand_id?>">
					<input type="hidden" name="hkp" value="<?=$hankintapaikka_id?>">
					<input type="hidden" name="page" value="<?=$page?>">
					<select name="ppp" title="Montako tuotetta sivulla näytetään kerralla.">
						<?php $x=10; for ( $i = 0; $i<5; ++$i ) {
							echo ( $x == $products_per_page )
								? "<option value='{$x}' selected>{$x}</option>"
								: "<option value='{$x}'>{$x}</option>";
							$x = ($i%2 == 0)
								? $x=$x*5
								: $x=$x*2;
						} ?>
					</select>
					<input type="submit" value="Muuta">
				</form>
			</div>
		</div>
	</nav>

	<table>
		<thead>
		<tr><th colspan="11" class="center" style="background-color:#1d7ae2;">Tuotteet</th></tr>
		<tr><th>Valmistaja</th>
			<th>Tuote</th>
			<th>Nimi</th>
			<th>Myyntihinta</th>
			<th>ALV 0&nbsp;%</th>
			<th>Ostohinta ALV 0&nbsp;%</th>
			<th>Hinnoittelukate</th>
			<th>Varastossa</th>
			<th>Myyty kpl</th>
			<th>Hyllypaikka</th>
			<th></th>
		</tr>
		</thead>

		<tbody>
		<?php foreach ( $tuotteet as $t ) : ?>
			<tr data-id="<?= $t->id ?>">
				<td><?= $t->valmistaja ?></td>
				<td><?= $t->articleNo ?></td>
				<td><?= $t->nimi ?></td>
				<td><?= $t->aHinta_toString() ?></td>
				<td><?= $t->aHintaIlmanALV_toString() ?></td>
				<td><?= $t->ostohinta_toString() ?></td>
				<td><?= round(100*(($t->a_hinta_ilman_alv - $t->ostohinta)/$t->a_hinta_ilman_alv), 0)?>&nbsp;%</td>
				<td><?= $t->varastosaldo ?></td>
				<td><?= "" ?></td>
				<td><?= $t->hyllypaikka ?></td>

				<td><button class="nappi show" data-dialog-id="#dialog_<?=$t->id?>">Info</button>

					<dialog id="dialog_<?=$t->id?>" style="width: 500px;">

						<div class="otsikko_container blue">
							<section class="otsikko">
								<h2>Tuotteen tiedot</h2>
								<button class="close" data-dialog-id="#dialog_<?=$t->id?>"
								        style="margin-left:50px; padding: 4px; color:black; background-color:white;">
									Sulje X</button>
							</section>
						</div>

						<dl>
							<dt>ID</dt> <dd> <?= $t->id ?> </dd>
							<dt>Artikkeli Nro</dt> <dd> <?= $t->articleNo ?> </dd>
							<dt>Brandi Nro</dt> <dd> <?= $t->brandNo ?></dd>
							<dt>Hankintapaikka ID</dt> <dd> <?= $t->hankintapaikkaID ?> </dd>
							<dt>Tuotekoodi</dt> <dd><?= $t->tuotekoodi ?></dd>
							<dt>Tilauskoodi</dt> <dd><?= $t->tilauskoodi ?></dd>
						</dl>
						<hr>
						<dl>
							<dt>Nimi</dt> <dd><?= $t->nimi ?></dd>
							<dt>Valmistaja</dt> <dd><?= $t->valmistaja ?></dd>
							<dt>Hankintapaikka</dt> <dd><?= $t->hankintapaikkaNimi ?></dd>
							<dt>hyllypaikka</dt> <dd><?= $t->hyllypaikka ?></dd>
							<dt>Tuoteryhmät</dt>
								<dd><?php foreach( $t->trTiedot as $tr ) :
										echo $tr->oma_taso . ": " . $tr->t1_nimi . "<br>";
									endforeach;
									if ( empty($t->tuoteryhmat) ) { echo "---"; }
									?>
								</dd>
							<dt>Kuvan URL</dt> <dd><?= $t->kuvaURL ?? "---" ?></dd>
							<dt>Infot</dt> <dd><?= $t->infot ?? "---" ?></dd>
						</dl>
						<hr>
						<dl>
							<dt>Hinta (ALV 0 %)</dt> <dd><?= $t->aHintaIlmanALV_toString() ?></dd>
							<dt>ALV_kanta</dt> <dd><?= $t->alv_toString() ?></dd>
							<dt>Keskimyyntihinta</dt> <dd><?= format_number($t->keskimyyntihinta) ?></dd>
							<dt>Ostohinta</dt> <dd><?= $t->ostohinta_toString() ?></dd>
							<dt>Keskiostohinta</dt> <dd><?= format_number($t->keskiostohinta) ?></dd>
						</dl>
						<hr>
						<dl>
							<dt>Varastosaldo</dt> <dd><?= $t->varastosaldo ?></dd>
							<dt>Minimimyyntiera</dt> <dd><?= $t->minimimyyntiera ?></dd>
							<dt>Vuosimyynti</dt> <dd><?= $t->vuosimyynti ?></dd>
							<dt>1. kerran varastossa</dt> <dd><?= $t->ensimmaisenKerranVarastossa ?></dd>
							<dt>Yhteensä ostettu sisään</dt> <dd><?= $t->yhteensaKpl ?></dd>
						</dl>
						<hr>
						<dl>
							<dt>Päivitettava</dt> <dd><?= $t->paivitettava ? 'Kyllä' : 'Ei' ?></dd>
							<dt>Tecdocissa</dt> <dd><?= $t->tecdocissa ? "Kyllä" : "Ei" ?></dd>
							<dt>Aktiivinen</dt> <dd><?= $t->aktiivinen ? "Kyllä" : "Ei" ?></dd>
						</dl>

						<?php foreach( $t->maaraalennukset as $alennus ) :
							debug( $alennus );
						endforeach; ?>
					</dialog>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<nav style="white-space: nowrap; display: inline-flex; margin:20px auto;">
		<a class="nappi" href="<?=$first_page?>"> <i class="material-icons">first_page</i> </a>
		<a class="nappi" href="<?=$prev_page?>"> <i class="material-icons">navigate_before</i> </a>

		<div class="white-bg" style="border: 1px solid; margin:auto; padding:10px; line-height: 25px;">
			Sivu: <?=$page?> / <?=$total_pages?><br>
			Tuotteet: <?=$offset?>&ndash;<?=$offset + $products_per_page?> / <?=$total_products?>
		</div>

		<a class="nappi" href="<?=$next_page?>"> <i class="material-icons">navigate_next</i> </a>
		<a class="nappi" href="<?=$last_page?>"> <i class="material-icons">last_page</i> </a>

		<div class="white-bg" style="display:flex; flex-direction:column; margin:auto 40px auto; border: 1px solid; padding:5px;">
			<span>Tuotteita per sivu: <?=$products_per_page?></span>
		</div>
	</nav>

</main>

<?php require 'footer.php'; ?>

<script>
	/**
	 * Pagination
	 */
	let backwards = document.getElementsByClassName('backward_nav');
	let forwards = document.getElementsByClassName('forward_nav');
	let total_pages = <?= $total_pages ?>;
	let current_page = <?= $page ?>;
	let i = 0; //for-looppia varten

	if ( current_page === 1 ) { // Ei anneta mennä taaksepäin ekalla sivulla
		for ( i=0; i< backwards.length; i++ ) {
			backwards[i].setAttribute("disabled","");
		}
	}
	if ( current_page === total_pages ) { // ... sama juttu, mutta eteenpäin-nappien kohdalla (viimeinen sivu)
		for ( i=0; i< forwards.length; i++ ) {
			forwards[i].setAttribute("disabled","");
		}
	}

	$(".pageNumberForm").keypress(function(event) {
		// 13 == Enter-näppäin //TODO: tarkista miten mobiililla toimii.
		if (event.which === 13) {
			$("form.pageNumberForm").submit();
			return false;
		}
	});

	/**
	 * Modal toiminnallisuus
	 */
	let dialogs = document.querySelectorAll('dialog');
	let openButtons = document.querySelectorAll('.show');
	let closeButtons = document.querySelectorAll('.close');
	for (i = 0; i < dialogs.length; i++) {
		dialogPolyfill.registerDialog(dialogs[i]); // Polyfill

		dialogs[i].addEventListener("click", function(e) {
			if ( e.target.classList.contains("DIALOG") ) {
				let d = document.getElementById( e.target.id );
				console.log( d );
				d.close();
			}
		});
	}
	for (i = 0; i < openButtons.length; i++) {
		openButtons[i].addEventListener("click", function(e) {
			let d = document.querySelector( e.target.dataset.dialogId );
			d.showModal();
		});
	}
	for (i = 0; i < closeButtons.length; i++) {
		closeButtons[i].addEventListener("click", function(e) {
			let d = document.querySelector( e.target.dataset.dialogId );
			d.close();
		});
	}
</script>
</body>
</html>
