<?php declare(strict_types=1);
/**
 * @var $user \User
 * @var $cart \Ostoskori
 */
?>

<noscript>
    <meta http-equiv="refresh" content="0; url=index.php">
</noscript>

<header class="header_container">
	<!-- Tiedoston latausta varten -->
	<form id="download_hinnasto" method="post" action="download.php">
		<input type="hidden" name="filepath" value="hinnasto/hinnasto.txt">
	</form>

    <section class="header_top" id="headertop">
        <div id="head_logo">
	        <a href="etusivu.php"> <img src="./img/osax_logo.jpg" align="left" alt="No pics, plz"> </a>
        </div>

        <div id="head_info">
            <?= $_SESSION['header_tervehdys'] . ' ' . $user->kokoNimi() ?><br>
            Kirjautuneena: <?= $user->sahkoposti ?>
        </div>

        <div id="head_cart">
            <a href='ostoskori.php' class="flex_row">
                <div style="margin:auto 5px;">
                    <i class="material-icons">shopping_cart</i>
                </div>
                <div>
                    Ostoskori<br>
                    Tuotteita: <span id="head_cart_tuotteet"><?= $cart->montako_tuotetta ?></span>
                    (Kpl:<span id="head_cart_kpl"><?= $cart->montako_tuotetta_kpl_maara_yhteensa ?></span>)
                </div>
            </a>
        </div>
    </section>

    <section class="navigationbar" id="navbar">
        <ul>
            <li><a href='etusivu.php' style="padding-left:15px; padding-right: 15px;">
                <i class="material-icons" style="margin-top: -3px;">home</i></a></li>
            <li><a href='tuotehaku.php'>Tuotehaku</a></li>
            <?php if ( $user->isAdmin() ) : ?>
                <li><a href='yp_yritykset.php'>Yritykset</a></li>
                <li><a href='yp_tuotteet.php'>Tuotteet</a></li>
                <li><a href='yp_tilaukset.php'>Tilaukset</a></li>

                <li><a id="dropdown_link" href="javascript:void(0)">Muut
                        <i id="dropdown_icon" class="material-icons" style="font-size: 18px;">arrow_drop_down</i>
	                </a>
                    <ul class="dropdown-content" id="navbar_dropdown-content">
                        <li><a href="yp_ostotilauskirja_odottavat.php">Varastoon saapuminen</a></li>
                        <li><a href="yp_ostotilauskirja_hankintapaikka.php">Tilauskirjat</a></li>
                        <li><a href="yp_hallitse_eula.php">EULA</a></li>
                        <li><a href="yp_hankintapyynnot.php">Hankintapyynnöt</a></li>
                        <li><a href="yp_muokkaa_alv.php">ALV-muokkaus</a></li>
                        <li><a href='yp_toimittajat.php'>Toimittajat</a></li>
                        <li><a href='yp_hankintapaikat.php'>Hankintapaikat</a></li>
                        <li><a href='yp_raportit.php'>Raportit</a></li>
	                    <li><a href='yp_tuoteryhmat.php'>Tuoteryhmät</a></li>
                        <li><a href='omat_tiedot.php'>Omat tiedot</a></li>
                    </ul>
                </li>
			<?php else : ?>
                <li><a href='omat_tiedot.php'>Omat tiedot</a></li>
                <li><a href='tilaushistoria.php'>Tilaushistoria</a></li>
                <li><a href='#' onclick="document.getElementById('download_hinnasto').submit()">
		                Lataa hinnasto<i class="material-icons">file_download</i></a>
                </li>
			<?php endif; ?>
            <li class="last">
	            <a href="logout.php?redir=5">Kirjaudu ulos <i class="material-icons">exit_to_app</i></a>
            </li>
        </ul>
    </section>

	<script async>
		//navbar active link
		let pgurl = window.location.href.substr(window.location.href
				.lastIndexOf("/")+1).split('?')[0];
		//Tarkastetaan alasivut
		switch(pgurl) {
			case "yp_muokkaa_yritysta.php":
			case "yp_lisaa_yritys.php":
			case "yp_asiakkaat.php":
			case "yp_muokkaa_asiakasta.php":
			case "yp_lisaa_asiakas.php":
				pgurl = "yp_yritykset.php";
				break;
			case "yp_tilaushistoria.php":
			case "tilaus_info.php":
				pgurl = "yp_tilaukset.php";
				break;
			case "yp_toimittajan_hallinta.php":
			case "yp_valikoima.php":
				pgurl = "yp_toimittajat.php";
				break;
			case "yp_ostotilauskirja.php":
			case "yp_ostotilauskirja_tuote.php":
				pgurl = "yp_ostotilauskirja_hankintapaikka.php";
				break;
			case "yp_ostotilauskirja_tuote_odottavat.php":
				pgurl = "yp_ostotilauskirja_odottavat.php";
				break;
			case "yp_raportti_varastolistaus.php":
			case "yp_raportti_myyntiraportti.php":
			case "yp_raportti_myyntitapahtumalistaus.php":
			case "yp_raportti_tuotekohtainen_myynti.php":
				pgurl = "yp_raportit.php";
				break;
			case "yp_hankintapaikka.php":
			case "yp_hankintapaikka_linkitys.php":
			case "yp_lisaa_tuotteita.php":
			case "yp_lisaa_omia_tuotteita.php":
				pgurl = "yp_hankintapaikat.php";
				break;
		}

		let links = document.getElementsByClassName("navigationbar")[0].getElementsByTagName("a");
		for ( let i = 0; i < links.length; i++ ) {
			if ( links[i].getAttribute("href") === pgurl ) {
				links[i].className += "active";
				//Jos dropdpdown valikko, myös "MUUT"-painike active
				if ( links[i].parentElement.parentElement.className === "dropdown-content") {
					document.getElementById("dropdown_link").className += "active";
				}
			}
		}

		//dropdown icon toiminnallisuus
		document.getElementById("dropdown_link").onclick = function () {
			const dropdown_icon = document.getElementById("dropdown_icon");
			const dropdown_content = document.getElementById("navbar_dropdown-content");
			if ( dropdown_icon.innerHTML === "arrow_drop_down" ){
				dropdown_icon.innerHTML = "arrow_drop_up";
				dropdown_content.style.display = 'block';
			} else {
				dropdown_icon.innerHTML = "arrow_drop_down";
				dropdown_content.style.display = 'none';
			}
		};
	</script>
</header>
