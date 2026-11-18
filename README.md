# MeeleKompass

Anonüümne kooliõpilase vaimse heaolu veebirakendus ning parooliga kaitstud tugispetsialisti töölaud. Rakendus kasutab ainult HTML-i, CSS-i, Vanilla JavaScripti ja PHP-d; ehitustööriistu ega andmebaasi pole vaja.

## Paigaldamine

1. Laadi kogu kaust FTP-ga PHP 8.0+ toetavale veebiserverile. Veendu, et PHP sessioonid on serveris sisse lülitatud.
2. `data/` ja mõlemad JSON-failid peavad olema veebiserveri PHP kasutaja jaoks kirjutatavad, sest lukustusega uuendamisel avatakse fail lugemiseks ja kirjutamiseks. Kui PHP töötab kausta omanikuna, sobivad kataloogile `chmod 755 data` ja failidele `chmod 644 data/*.json`. Kui PHP töötab teise kasutajana, määra omanik/grupp vastavalt või anna kirjutamisõigus ainult sellele kasutajale (nt kataloogile `775` ja failidele `664`); ära kasuta `777`.
3. Apache korral blokeerib `data/.htaccess` andmefailidele otselingi. Nginxi või muu serveri puhul lisa vastav serveripoolne reegel, mis keelab kogu `/data/` URL-tee lugemise. Ära paiguta andmekataloogi veebijuurest väljapoole ilma `config.php` teed muutmata.
4. Ava `index.php`. Administraatori töölaud asub lingil `admin.php`.

Kõik HTTP päringud teenindab `api.php`. JSON-failide muutmisel kasutatakse eksklusiivset `flock(LOCK_EX)` lukku ja lugemisel jagatud `flock(LOCK_SH)` lukku. Rakendus ei kogu õpilaste IP-aadresse ega muid identifitseerivaid andmeid. Check-in'i serverikirje sisaldab ainult serveri kuupäeva, 1–5 skoori ja valitud kategooriat; isiklik ajalugu jääb õpilase brauseri `localStorage`-isse.

## Administraatori parool

`config.php` sisaldab `ADMIN_PASSWORD_HASH` parooli räsi. **Esialgne sisselogimisparool on `password`; vaheta see enne rakenduse avalikku kasutamist.** Loo uus räsi usaldusväärses PHP 8 keskkonnas:

```sh
php -r "echo password_hash('Sinu-pikk-ja-unikaalne-parool', PASSWORD_DEFAULT), PHP_EOL;"
```

Asenda `config.php` failis kogu `ADMIN_PASSWORD_HASH` väärtus saadud räsi stringiga. Kasuta vähemalt 16 märgist koosnevat unikaalset parooli. Ära lisa parooli avalikku hoidlasse.

## Privaatsus ja kasutus

- Õpilane ei vaja kontot ega e-posti aadressi. Tugispetsialistile saadetud pöördumine on avatav ainult juhusliku jälgimiskoodiga; hoia kood alles.
- Õpilase enda enesetunde ajalugu salvestatakse ainult kasutatava brauseri kohalikku salvestusruumi. Brauseri andmete kustutamisel see ajalugu kaob.
- Murepurustaja tekst jääb ainult lehele ega lähe serverisse ega brauseri salvestusruumi.
- Sessioonipõhine päringupiirang ja CSRF-kontroll aitavad vähendada rämpspäringuid. Kasuta HTTPS-i, hoia PHP ajakohasena ning taga, et `data/` pole veebist loetav.
- MeeleKompass ei asenda kriisiabi ega professionaalset tervishoiuteenust. Vahetu ohu korral võta ühendust usaldusväärse täiskasvanu või hädaabinumbriga 112.
