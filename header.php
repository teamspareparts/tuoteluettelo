<?php

session_start();

//
// Kirjautumiseen liittyviä apufunktioita
//

function is_logged_in() {
	return isset($_SESSION['email']);
}

function is_admin() {
	return isset($_SESSION['admin']) && $_SESSION['admin'] == 1;
}

// Tarkistetaan, onko käyttäjä kirjautunut sisään;
// jos ei, niin ohjataan suoraan kirjautumissivulle.
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

?>
<div id="header">
	<img alt="Logo" src="img/rantak_varao-Logo.jpg" height="100" width="300" align="left">
	<!-- <h1>Logo</h1> -->
	<p class="kirjautuneena">Kirjautuneena:</p>
	<?php
		//tarkastaa onko nimi talletettu sessioniin
		if (is_logged_in()){
			echo ('<p class="username">' . $_SESSION['email'] . '</p>');
		}
	?>
</div>

<div id="navigationbar">
	<ul>
        <li><a href='tuotehaku.php'><span>Tuotehaku</span></a></li>
        <li><a href='omat_tiedot.php'><span>Omat tiedot</span></a></li>
    <?php
        if (is_admin()) {
    ?>
        <li><a href='yp_asiakkaat.php'><span>Asiakkaat</span></a></li>
        <li><a href='yp_hinnat.php'><span>Hinnat</span></a></li>
        <li><a href='yp_tuotteet.php'><span>Tuotteet</span></a></li>
        <li><a href='yp_tilaukset.php'><span>Tilaukset</span></a></li>
        <li><a href='yp_raportit.php'><span>Raportit</span></a></li>
    <?php
        } else {
    ?>
        <li><a href='tilaushistoria.php'><span>Tilaushistoria</span></a></li>
    <?php
        }
		if (is_logged_in()) {
			echo '<li class="last"><a href="logout.php"><span>Kirjaudu ulos</span></a></li>';
		} else {
			echo '<li class="last"><a href="login.php"><span>Kirjaudu sisään</span></a></li>';
		}
    ?>
	</ul>
</div>