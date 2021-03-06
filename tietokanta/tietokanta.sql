SET FOREIGN_KEY_CHECKS=0; -- Taulut ovat väärässä järjestyksessä FOREIGN KEY tarkastuksia varten.

CREATE TABLE IF NOT EXISTS `kayttaja` (
  `id` mediumint UNSIGNED NOT NULL AUTO_INCREMENT, -- PK
  `sahkoposti` varchar(255) NOT NULL, -- UK
  `salasana_hajautus` varchar(100) NOT NULL,
  `salasana_vaihdettu` timestamp DEFAULT CURRENT_TIMESTAMP,
  `etunimi` varchar(20) DEFAULT NULL,
  `sukunimi` varchar(20) DEFAULT NULL,
  `yritys_id` smallint UNSIGNED NOT NULL, -- FK
  `puhelin` varchar(20) DEFAULT NULL,
  `yllapitaja` tinyint(1) NOT NULL DEFAULT 0, -- Tarkoituksella ei boolean
  `aktiivinen` boolean NOT NULL DEFAULT 1,
  `demo` boolean NOT NULL DEFAULT 0, -- Välikaikainen tunnus sivuston demoamista varten
  `viime_sijainti` varchar(100) DEFAULT NULL,
  `viime_kirjautuminen` timestamp NULL DEFAULT NULL,
  `luotu` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `voimassaolopvm` timestamp DEFAULT 0, -- Miten pitkään tunnus on voimassa, jos demo == 1
  `salasana_uusittava` boolean NOT NULL DEFAULT 0,
  `vahvista_eula` boolean NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`), UNIQUE KEY (`sahkoposti`),
  CONSTRAINT fk_kayttaja_yritys FOREIGN KEY (`yritys_id`) REFERENCES `yritys`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `yritys` (
  `id` smallint UNSIGNED NOT NULL AUTO_INCREMENT, -- PK
  `nimi` varchar(255) NOT NULL,
  `y_tunnus` varchar(9) NOT NULL,  -- UK
  `sahkoposti` varchar(255) DEFAULT NULL,
  `puhelin` varchar(20) DEFAULT NULL,
  `katuosoite` varchar(255) DEFAULT NULL,
  `postinumero` varchar(10) DEFAULT NULL,
  `postitoimipaikka` varchar(255) DEFAULT NULL,
  `maa` varchar(200) NOT NULL DEFAULT 'Suomi',
  `aktiivinen` boolean NOT NULL DEFAULT 1,
  `rahtimaksu` decimal(11,2) NOT NULL DEFAULT 15.00,
  `ilmainen_toimitus_summa_raja` decimal(11,2) NOT NULL DEFAULT 1000.00,
  `alennus_prosentti` decimal(3,2) NOT NULL DEFAULT 0.00,
  `maksutapa` tinyint(1) UNSIGNED NOT NULL DEFAULT 0, -- 0: Paytrail, 1: lasku 14pv, >1: 501 HTTP
  PRIMARY KEY (`id`), UNIQUE KEY (`y_tunnus`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `tuote` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, -- PK
  `articleNo` varchar(30) NOT NULL, -- UK
  `brandNo` varchar(20) NOT NULL, -- UK
  `hankintapaikka_id` smallint UNSIGNED NOT NULL, -- FK, UK
  `tuotekoodi` varchar(30) NOT NULL, -- Tuotteen näkyvä koodi. Muotoa hankintapaikka_id-articleNo
  `tilauskoodi` varchar(30) NOT NULL, -- Koodi, jota käytetään tilauskirjaa tehdessä.
  `nimi` varchar(40) DEFAULT NULL,
  `valmistaja` varchar(40) DEFAULT NULL,
  `kuva_url` varchar(100) DEFAULT NULL,
  `infot` varchar(300) DEFAULT NULL, -- Erottimena |
  `hinta_ilman_ALV` decimal(11,4) NOT NULL,
  `ALV_kanta` tinyint(1) UNSIGNED NOT NULL DEFAULT 0, -- FK
  `varastosaldo` mediumint NOT NULL DEFAULT 0,
  `minimimyyntiera` mediumint UNSIGNED NOT NULL DEFAULT 1,
  `sisaanostohinta` decimal(11,4) NOT NULL DEFAULT 0.00,
  `yhteensa_kpl` mediumint NOT NULL DEFAULT 0, -- Tämän avulla lasketaan keskiostohinta.
  `keskiostohinta` decimal(11,4) NOT NULL DEFAULT 0.00,
  `hyllypaikka` varchar(10) DEFAULT NULL,
  `vuosimyynti` int(11) NOT NULL DEFAULT 0,
  `ensimmaisen_kerran_varastossa` timestamp NULL DEFAULT NULL, -- Tuotetta tilataan ensimmäisen kerran
  `paivitettava` boolean NOT NULL DEFAULT FALSE, -- Tarkastettava pitääkö tuotetta ostaa lisää
  `tecdocissa` boolean NOT NULL DEFAULT TRUE, -- Onko tuote tecdocissa vai itse lisätty
  `aktiivinen` boolean NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`), UNIQUE KEY (`articleNo`, `brandNo`, `hankintapaikka_id`),
  CONSTRAINT fk_tuote_hankintapaikka FOREIGN KEY (hankintapaikka_id) REFERENCES hankintapaikka(id),
  CONSTRAINT fk_tuote_alvKanta FOREIGN KEY (`ALV_kanta`) REFERENCES `ALV_kanta`(`kanta`),
  INDEX (hyllypaikka)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `tilaus` (
  `id` mediumint UNSIGNED NOT NULL AUTO_INCREMENT, -- PK
  `kayttaja_id` mediumint UNSIGNED NOT NULL, -- FK
  `kasitelty` boolean NOT NULL DEFAULT 0,
  `maksettu` boolean DEFAULT 0,
  `maksutapa` tinyint DEFAULT NULL,
  `tilaustuotteet_tilattu` boolean NOT NULL DEFAULT 0, -- Onko tilaustuotteet tilattu hankintapaikalta
  `laskunro` int DEFAULT NULL, -- Otetaan laskunumero-taulusta
  `paivamaara` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `pysyva_rahtimaksu` decimal(11,2) NOT NULL DEFAULT 15.00,
  PRIMARY KEY (`id`),
  CONSTRAINT fk_tilaus_kayttaja FOREIGN KEY (`kayttaja_id`) REFERENCES `kayttaja`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `tilaus_tuote` (
  `tilaus_id` mediumint UNSIGNED NOT NULL, -- PK, FK
  `tuote_id` int UNSIGNED NOT NULL, -- PK, FK
  `tilaustuote` boolean NOT NULL DEFAULT 0, -- PK -- Onko tuote tilattu suoraan tehtaalta
  `tuotteen_nimi` varchar(20) NOT NULL,
  `valmistaja` varchar(30) NOT NULL,
  `pysyva_hinta` decimal(11,4) NOT NULL, -- Ei sisällä ALV:ia
  `pysyva_alv` decimal(3,2) NOT NULL,
  `pysyva_alennus` decimal(3,2) NOT NULL DEFAULT 0.00,
  `kpl` mediumint NOT NULL DEFAULT 1,
  PRIMARY KEY (`tilaus_id`, `tuote_id`),
  CONSTRAINT fk_tilausTuote_tilaus FOREIGN KEY (`tilaus_id`) REFERENCES `tilaus`(`id`),
  CONSTRAINT fk_tilausTuote_tuote FOREIGN KEY (`tuote_id`) REFERENCES `tuote`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `tilaus_toimitusosoite` (
  `tilaus_id` mediumint UNSIGNED NOT NULL, -- PK, FK
  `pysyva_etunimi` varchar(255) NOT NULL, -- Rivin tiedot tulevat toimitusosoitteesta.
  `pysyva_sukunimi` varchar(255) NOT NULL,
  `pysyva_sahkoposti` varchar(255) NOT NULL,
  `pysyva_puhelin` varchar(20) NOT NULL,
  `pysyva_yritys` varchar(50) NOT NULL,
  `pysyva_katuosoite` varchar(255) NOT NULL,
  `pysyva_postinumero` varchar(10) NOT NULL,
  `pysyva_postitoimipaikka` varchar(255) NOT NULL,
  `pysyva_maa` varchar(200) NOT NULL,
  PRIMARY KEY (`tilaus_id`),
  CONSTRAINT fk_tilausToimitusosoite_tilaus FOREIGN KEY (`tilaus_id`) REFERENCES `tilaus`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `pw_reset` (
  `kayttaja_id` mediumint UNSIGNED NOT NULL, -- PK, FK
  `reset_key_hash` varchar(40) NOT NULL, -- PK
  `reset_exp_aika` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `kaytetty` boolean NOT NULL DEFAULT 0, -- Onko avain jo käytetty?
  PRIMARY KEY (`kayttaja_id`, `reset_key_hash`),
  CONSTRAINT fk_pwReset_kayttaja FOREIGN KEY (`kayttaja_id`) REFERENCES `kayttaja`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `ALV_kanta` (
  `kanta` tinyint(1) UNSIGNED NOT NULL, -- PK
  `prosentti` decimal(3,2) UNSIGNED NOT NULL,
  PRIMARY KEY (`kanta`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `toimitusosoite` (
  `kayttaja_id` mediumint UNSIGNED NOT NULL, -- PK, FK
  `osoite_id` tinyint UNSIGNED NOT NULL, -- PK
  `etunimi` varchar(255) DEFAULT NULL,
  `sukunimi` varchar(255) DEFAULT NULL,
  `sahkoposti` varchar(255) DEFAULT NULL,
  `puhelin` varchar(20) DEFAULT NULL,
  `yritys` varchar(50) DEFAULT NULL,
  `katuosoite` varchar(255) NOT NULL, -- Not null, koska se on osoite.
  `postinumero` varchar(10) NOT NULL, -- Ditto
  `postitoimipaikka` varchar(255) NOT NULL, -- Ditto
  `maa` varchar(200) NOT NULL DEFAULT 'Suomi',
  PRIMARY KEY (`kayttaja_id`, `osoite_id`),
  CONSTRAINT fk_toimitusosoite_kayttaja FOREIGN KEY (`kayttaja_id`) REFERENCES `kayttaja`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

/* Valikoimassa olevaa tuotetta varten (varastosaldo == 0) */
CREATE TABLE IF NOT EXISTS `tuote_ostopyynto` (
  `tuote_id` int(11) UNSIGNED NOT NULL, -- PK, FK
  `kayttaja_id` mediumint UNSIGNED NOT NULL, -- PK, FK
  `pvm` timestamp DEFAULT CURRENT_TIMESTAMP, -- PK
  `kasitelty` tinyint UNSIGNED DEFAULT NULL, -- Mitä ostopyynnölle on tehty, ylläpidon toimi.
  PRIMARY KEY (`tuote_id`, `kayttaja_id`, `pvm`),
  CONSTRAINT fk_tuoteOstopyynto_tuote FOREIGN KEY (`tuote_id`) REFERENCES `tuote`(`id`),
  CONSTRAINT fk_tuoteOstopyynto_kayttaja FOREIGN KEY (`kayttaja_id`) REFERENCES `kayttaja`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

/* Ei-valikoimassa olevat tuotteet. */
CREATE TABLE IF NOT EXISTS `tuote_hankintapyynto` (
  `articleNo` varchar(20) NOT NULL, -- PK
  `kayttaja_id` mediumint UNSIGNED NOT NULL, -- PK, FK
  `pvm` timestamp DEFAULT CURRENT_TIMESTAMP, -- PK
  `valmistaja` varchar(30) NOT NULL,
  `tuotteen_nimi` varchar(30) NOT NULL,
  `korvaava_okey` boolean NOT NULL DEFAULT 1,
  `selitys` varchar(1000) DEFAULT NULL,
  `kasitelty` tinyint UNSIGNED DEFAULT NULL, -- Mitä hankintapyynnölle on tehty, ylläpidon toimi.
  PRIMARY KEY (`articleNo`, `kayttaja_id`, `pvm`),
  CONSTRAINT fk_tuoteHankintapyynto_kayttaja FOREIGN KEY (`kayttaja_id`) REFERENCES `kayttaja`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `tuote_erikoishinta` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, -- PK
  `tuote_id` int(11) UNSIGNED NOT NULL, -- FK
  `maaraalennus_kpl` mediumint UNSIGNED NOT NULL DEFAULT 0,
  `alennus_prosentti` decimal(3,2) NOT NULL DEFAULT 0.00,
  `alkuPvm` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Tarjouksen alkamis pvm
  `loppuPvm` timestamp NULL DEFAULT NULL, -- Tarjouksen loppumis pvm
  PRIMARY KEY (`id`),
  UNIQUE KEY (`tuote_id`, `maaraalennus_kpl`, `alennus_prosentti`),
  CONSTRAINT fk_tuoteErikoishinta_tuote FOREIGN KEY (`tuote_id`) REFERENCES `tuote`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `tuoteyritys_erikoishinta` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, -- PK
  `tuote_id` int(11) UNSIGNED NOT NULL, -- FK
  `yritys_id` smallint UNSIGNED NOT NULL, -- FK
  `maaraalennus_kpl` int(11) UNSIGNED DEFAULT 0,
  `alennus_prosentti` decimal(3,2) DEFAULT 0.00,
  `alkuPvm` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Tarjouksen alkamis pvm
  `loppuPvm` timestamp NULL DEFAULT NULL, -- Tarjouksen loppumis pvm
  PRIMARY KEY (`id`),
  UNIQUE KEY (`tuote_id`, `yritys_id`, `maaraalennus_kpl`, `alennus_prosentti`),
  CONSTRAINT fk_tuoteyritysErikoishinta_tuote FOREIGN KEY (`tuote_id`) REFERENCES `tuote`(`id`),
  CONSTRAINT fk_tuoteyritysErikoishinta_yritys FOREIGN KEY (`yritys_id`) REFERENCES `yritys`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `tuoteryhma_erikoishinta` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, -- PK
  `hankintapaikka_id` smallint UNSIGNED NOT NULL, -- FK
  `tuoteryhma_id` smallint UNSIGNED NOT NULL, -- FK
  `yritys_id` smallint UNSIGNED NOT NULL DEFAULT 0, -- FK -- ei CONSTRAINTia koska voi olla ei-yrityskohtainen
  `maaraalennus_kpl` int(11) UNSIGNED DEFAULT 0,
  `alennus_prosentti` decimal(3,2) DEFAULT 0.00,
  `alkuPvm` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Tarjouksen alkamis pvm
  `loppuPvm` timestamp NULL DEFAULT NULL, -- Tarjouksen loppumis pvm
  PRIMARY KEY (`id`),
  UNIQUE KEY (`hankintapaikka_id`,`tuoteryhma_id`,`maaraalennus_kpl`,`alennus_prosentti`),
  CONSTRAINT fk_tuoteryhmaErikoishinta_hankintapaikka
    FOREIGN KEY (`hankintapaikka_id`) REFERENCES `hankintapaikka`(`id`),
  CONSTRAINT fk_tuoteryhmaErikoishinta_tuoteryhma
    FOREIGN KEY (`tuoteryhma_id`) REFERENCES `tuoteryhma`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `ostoskori` ( -- Teknisesti ottaen täysin turha taulu.
  `id` smallint UNSIGNED NOT NULL AUTO_INCREMENT, -- PK
  `yritys_id` smallint UNSIGNED NOT NULL, -- UK, FK
  PRIMARY KEY (`id`),
  UNIQUE KEY (`yritys_id`),
  CONSTRAINT fk_ostoskori_yritys FOREIGN KEY (`yritys_id`) REFERENCES `yritys`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `ostoskori_tuote` (
  `ostoskori_id` smallint UNSIGNED NOT NULL, -- PK, FK
  `tuote_id` int(11) UNSIGNED NOT NULL, -- PK, FK
  `kpl_maara` smallint UNSIGNED NOT NULL DEFAULT 1,
  `tilaustuote` boolean NOT NULL DEFAULT 0, -- Tilataan suoraan hankintapaikalta
  PRIMARY KEY (`ostoskori_id`, `tuote_id`),
  CONSTRAINT fk_ostoskoriTuote_ostoskori FOREIGN KEY (`ostoskori_id`) REFERENCES `ostoskori`(`id`),
  CONSTRAINT fk_ostoskoriTuote_tuote FOREIGN KEY (`tuote_id`) REFERENCES `tuote`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `hankintapaikka` (
  `id` smallint UNSIGNED NOT NULL AUTO_INCREMENT, -- PK
  `nimi` varchar(11) NOT NULL, -- UK
  `katuosoite` varchar(50) DEFAULT '',
  `postinumero` varchar(11) DEFAULT '',
  `kaupunki` varchar(50) DEFAULT '',
  `maa` varchar(50) DEFAULT '',
  `puhelin` varchar(50) DEFAULT '',
  `yhteyshenkilo_nimi` varchar(50) DEFAULT '',
  `yhteyshenkilo_puhelin` varchar(50) DEFAULT '',
  `yhteyshenkilo_email` varchar(50) DEFAULT '',
  `email` varchar(50) DEFAULT '',
  `fax` varchar(50) DEFAULT '',
  `www_url` varchar(50) DEFAULT '',
  `tilaustapa` varchar(50) DEFAULT '',
  `tehdassaldo_viim_paivitys` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY (`nimi`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `toimittaja_tehdassaldo` (
  `hankintapaikka_id` int(11) UNSIGNED NOT NULL, -- PK
  `tuote_articleNo` varchar(30) NOT NULL, -- PK
  `tehdassaldo` mediumint NOT NULL,
  PRIMARY KEY (`hankintapaikka_id`,`tuote_articleNo`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `brandi` (
  `id` int(11) UNSIGNED NOT NULL, -- PK -- TecDocin id tai oma. Omat id:t alkaa 100 000 ->
  `nimi` varchar(50) NOT NULL, -- UK
  `url` varchar(100) DEFAULT NULL,
  `oma_brandi` boolean NOT NULL DEFAULT FALSE,
  `aktiivinen` boolean NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`), UNIQUE KEY (`nimi`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

/* Voi olla turha! Poistan heti kun olen varma, että ei tarvita */
CREATE TABLE IF NOT EXISTS `valmistajan_hankintapaikka` (
  `brandId` int(11) NOT NULL, -- PK
  `hankintapaikka_id` smallint UNSIGNED NOT NULL, -- PK, FK
  `brandName` varchar(50) NOT NULL,
  `hinnaston_sisaanajo_pvm` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`brandId`, `hankintapaikka_id`),
  CONSTRAINT fk_valmistajanHankintapaikka_hankintapaikka
    FOREIGN KEY (`hankintapaikka_id`) REFERENCES `hankintapaikka`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `brandin_linkitys` (
  `hankintapaikka_id` smallint UNSIGNED NOT NULL, -- PK, FK
  `brandi_id` int UNSIGNED NOT NULL, -- PK, FK
  `brandi_kaytetty_id` varchar(50) NOT NULL, -- Se brandin id, mitä hankintapaikka käyttää (voi erota tecdoc_id:stä)
  `hinnaston_sisaanajo_pvm` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`hankintapaikka_id`, `brandi_id`),
  CONSTRAINT fk_brandinLinkitys_hankintapaikka FOREIGN KEY (`hankintapaikka_id`) REFERENCES `hankintapaikka`(`id`),
  CONSTRAINT fk_brandinLinkitys_brandi FOREIGN KEY (`brandi_id`) REFERENCES `brandi`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `ostotilauskirja` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, -- PK
  `hankintapaikka_id` smallint UNSIGNED NOT NULL,  -- FK, UK
  `tunniste` varchar(50) NOT NULL, -- UK
  `rahti` decimal(11,2), -- Rahtimaksu
  `oletettu_lahetyspaiva` timestamp NULL DEFAULT NULL,
  `oletettu_saapumispaiva` timestamp NULL DEFAULT NULL,
  `toimitusjakso` tinyint UNSIGNED NOT NULL DEFAULT 6, -- Tilauksen toimitusväli viikkoina, 0: erikoistilaus
  PRIMARY KEY (`id`),
  UNIQUE KEY (`hankintapaikka_id`, `tunniste`),
  CONSTRAINT fk_ostotilauskirja_hankintapaikka
	  FOREIGN KEY (`hankintapaikka_id`) REFERENCES `hankintapaikka`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `ostotilauskirja_tuote` (
  `ostotilauskirja_id` int(11) UNSIGNED NOT NULL, -- PK, FK
  `tuote_id` int(11) UNSIGNED NOT NULL, -- PK, FK
  `automaatti` boolean NOT NULL DEFAULT 0, -- PK -- Onko automaatin lisäämä
  `tilaustuote` boolean NOT NULL DEFAULT 0, -- PK -- Onko tilaustuote
  `kpl` int(11) UNSIGNED NOT NULL,
  `selite` varchar(100) NOT NULL,
  `lisays_pvm` timestamp DEFAULT CURRENT_TIMESTAMP,
  `lisays_kayttaja_id` mediumint UNSIGNED, -- FK, Kuka lisännyt (0: automaatio)
  PRIMARY KEY (`ostotilauskirja_id`, `tuote_id`, `automaatti`, `tilaustuote`),
	CONSTRAINT fk_ostotilauskirjaTuote_tuote FOREIGN KEY (`tuote_id`) REFERENCES `tuote`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `ostotilauskirja_arkisto` ( -- Tänne valmiit tilauskirjat (MUUTTUMATTOMAT)
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, -- PK
  `hankintapaikka_id` smallint UNSIGNED NOT NULL,  -- FK
  `tunniste` varchar(50) NOT NULL,
  `original_rahti` decimal(11,2) NOT NULL,
  `rahti` decimal(11,2) NOT NULL,
  `oletettu_saapumispaiva` timestamp NULL,
  `lahetetty` timestamp NULL,
  `lahettaja` int(11), -- Tilauskirjan lähettäjän käyttäjä ID
  `saapumispaiva` timestamp NULL,
  `hyvaksytty` boolean NOT NULL DEFAULT 0, -- Odottavassa tilassa vai vastaanotettu ja hyväksytty
  `vastaanottaja` int(11), -- Tilauskirjan vastaanottajan käyttäjä ID
  `ostotilauskirja_id` smallint UNSIGNED DEFAULT NULL, -- FK, ei pakollinen
  PRIMARY KEY (`id`),
  CONSTRAINT fk_ostotilauskirjaArkisto_hankintapaikka
    FOREIGN KEY (`hankintapaikka_id`) REFERENCES `hankintapaikka`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `ostotilauskirja_tuote_arkisto` ( -- Tänne valmiit tilauskirjan tuotteet (MUUTTUMATTOMAT)
  `ostotilauskirja_id` int(11) UNSIGNED NOT NULL, -- PK, FK
  `tuote_id` int(11) UNSIGNED NOT NULL, -- PK, FK
  `automaatti` boolean NOT NULL DEFAULT 0, -- PK -- Onko automaatin lisäämä
  `tilaustuote` boolean NOT NULL DEFAULT 0, -- PK -- Onko tilaustuote
  `original_kpl` int(11) UNSIGNED NOT NULL,
  `kpl` int(11) UNSIGNED NOT NULL,
  `selite` varchar(100) NOT NULL,
  `ostohinta` decimal(11,4) NOT NULL,
  `lisays_pvm` timestamp DEFAULT CURRENT_TIMESTAMP,
  `lisays_kayttaja_id` mediumint UNSIGNED, -- FK, Kuka lisännyt (0: automaatio)
  PRIMARY KEY (`ostotilauskirja_id`, tuote_id, automaatti, `tilaustuote`),
  CONSTRAINT fk_ostotilauskirjaTuoteArkisto_tuote FOREIGN KEY (`tuote_id`) REFERENCES `tuote`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `etusivu_uutinen` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, -- PK
  `tyyppi` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `otsikko` varchar(50) NOT NULL,
  `summary` varchar(500) NOT NULL,
  `details` varchar(10000) NOT NULL, -- Max. pituus noin 16k.
  `aktiivinen` boolean NOT NULL DEFAULT 1,
  `pvm` timestamp DEFAULT CURRENT_TIMESTAMP,
  `loppu_pvm` timestamp NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

/* Juokseva numerointi. Tilausta tehdessä otetaan sen hetkinen numero, joka tallennetaan pysyvästi tilaus-tauluun. */
CREATE TABLE IF NOT EXISTS `laskunumero` (
  `laskunro` int(11) UNSIGNED NOT NULL, -- Laskujen juoksevaa numerointia varten
  PRIMARY KEY (`laskunro`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

/* TEMP-taulu varastosaldon päivitystä varten tilausta tehdessä. */
CREATE TABLE IF NOT EXISTS `temp_tuote`(
  `tuote_id` int(11) UNSIGNED NOT NULL, -- PK, FK
  `varastosaldo` int(11) NOT NULL,
  PRIMARY KEY (`tuote_id`),
  CONSTRAINT fk_tempTuote_tuote FOREIGN KEY (`tuote_id`) REFERENCES `tuote`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `tuoteryhma`(
  `id` smallint UNSIGNED NOT NULL AUTO_INCREMENT, -- PK
  `parent_id` smallint UNSIGNED DEFAULT 0, -- FK, osoittaa saman taulun ID-kolumniin (Puu-rakenne, 0 == root)
  `oma_taso` varchar(11) NOT NULL,
  `nimi` varchar(120) NOT NULL,
  `hinnoittelukerroin` decimal(3,2) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `tuoteryhma_tuote`(
  `tuoteryhma_id` smallint(5) UNSIGNED NOT NULL, -- PK, FK
  `tuote_id` int(11) UNSIGNED NOT NULL, -- PK, FK
  PRIMARY KEY (`tuote_id`,`tuoteryhma_id`),
  CONSTRAINT fk_tuoteryhmaTuote_tuoteryhma FOREIGN KEY (`tuoteryhma_id`) REFERENCES `tuoteryhma`(`id`),
  CONSTRAINT fk_tuoteryhmaTuote_tuote FOREIGN KEY (`tuote_id`) REFERENCES `tuote`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

CREATE TABLE IF NOT EXISTS `tuote_linkitys`(
  `tuote_id` int(11) UNSIGNED NOT NULL, -- PK, FK
  `brandNo` int(11) UNSIGNED NOT NULL,
  `articleNo` varchar(30) NOT NULL,
  `genericArticleId` smallint DEFAULT NULL, -- Ilmaisee tuotteen tyypin (-1: ei löydy tecdocista)
  PRIMARY KEY (`tuote_id`),
  CONSTRAINT fk_tuoteLinkitys_tuote FOREIGN KEY (`tuote_id`) REFERENCES `tuote`(`id`)
) DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;
