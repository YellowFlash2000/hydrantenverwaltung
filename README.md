# Hydrantenverwaltung – Version 1.1

Webanwendung zur Verwaltung von Löschwasser-Entnahmestellen (Hydranten) einer Feuerwehr: Hydranten mit Karte, Fotos und Stammdaten,
Touren und jährliche Prüfdurchgänge, Revisionen mit Mängeln (auch ohne Anmeldung über einen geheimen Tour-Link), Mängelübersicht,
Import und Export (CSV, OpenStreetMap), Benutzer mit Rollen, Protokoll. Reines PHP und MySQL, läuft auf einfachem Webspace (Installation per FTP).

Lizenz: GNU Affero General Public License Version 3 oder später (siehe Abschnitt „Lizenz“ am Ende und Datei `LICENSE`).

## Funktionen im Überblick
- **Touren sind feste Pläne.** Sie bleiben bestehen; ein Prüfdurchgang wählt nur aus, welche Touren geprüft werden.
- **Stammdaten** (Admin): Hydranten-Typen mit frei wählbarem Symbol, Positionen und Mängelkatalog auf einer Seite.
- **Kompakte Hydrantenliste** mit Typ-Symbol und Legende statt Bild und ausgeschriebenem Typ. Filter u. a. nach **Prüfstatus** (offen / geprüft / Mangel); „offen“ und „geprüft“ beziehen sich auf den aktiven Durchgang, „Mangel“ findet jeden offenen Mangel. In der Spalte „Prüfung“ erscheint zusätzlich **„Mangel (ohne Durchgang)“**, wenn der neueste Eintrag eines Hydranten ein direkt am Hydranten (außerhalb eines Durchgangs) erfasster Mangel ist; ein neuerer „in Ordnung“-Eintrag behebt ihn. Ebenso behebt ein später ohne Durchgang eingetragener „in Ordnung“-Eintrag einen Mangel des Durchgangs (Spalte zeigt dann „✓ geprüft“, der Filter „Mangel“ findet ihn nicht mehr); die Mängelübersicht zeigt den Mangel des Durchgangs weiterhin. Die Spalte ist auch ohne aktiven Durchgang sichtbar, sobald es solche Mängel gibt.
- **CSV-Import/-Export** mit Spalte `tour`.
- **Hydranten aus OpenStreetMap** holen (Admin, Import/Export → „Aus OpenStreetMap holen“).
- **Protokoll** (Admin): wer wann was getan hat, mit Filtern, CSV und Änderungsverlauf am Hydranten.
- **Export für OpenStreetMap** (Admin): CSV oder OSM-XML für JOSM mit OSM-Schlüsseln.

## Touren und Prüfdurchgänge

**Tour** (Menü „Touren“, ab Bearbeiter): fester Plan, z. B. „Tour Nord“. Hydranten ordnen Sie in der Hydrantenliste zu
(ankreuzen, Tour wählen, „Tour zuweisen“), im Formular des Hydranten oder per CSV-Spalte `tour`. Ein Hydrant gehört zu höchstens einer Tour.
Eine Tour lässt sich nur löschen, solange es dazu keine Revisionen gibt; ihre Hydranten haben dann keine Tour mehr.

**Prüfdurchgang** (Menü „Touren“ → „Prüfdurchgänge“): z. B. „Prüfung 2026 (alle Touren)“ oder „Frühjahrsprüfung Nord“. Beim Anlegen wählen Sie
die Touren aus (vorausgewählt sind die des letzten Durchgangs). Es ist immer nur **ein** Durchgang aktiv, ein neuer beendet den bisherigen.
Touren lassen sich im aktiven Durchgang später hinzufügen oder entfernen (Entfernen nur ohne Revisionen).
Beendete Durchgänge bleiben mit allen Revisionen erhalten und lassen sich wieder öffnen, solange kein anderer aktiv ist.

**Tour-Link** (`…/t/<langer Code>`): Jede Tour hat **in jedem Durchgang einen eigenen Link**, der mit dem Durchgang endet.
Wer ihn kennt, braucht keine Anmeldung. Sie können ihn sperren, freigeben, durch einen neuen ersetzen und per E-Mail senden
(alles auf der Seite des Durchgangs). Beim ersten Öffnen fragt die Seite nach den Namen der Teilnehmer; sie werden je Tour und Gerät
gemerkt (Cookie) und bei jeder Revision gespeichert. Falsche Codes werden je IP-Adresse gedrosselt.

Auf der Seite der Tour schalten die Knöpfe **„Alle“ / „Nur offene“** Liste und Karte um: „Nur offene“ zeigt nur Hydranten, die im Durchgang noch nicht geprüft wurden. Die Zähler an den Knöpfen und der Fortschrittsbalken beziehen sich immer auf die ganze Tour. Die Wahl wird je Tour im Gerät (Browser) gemerkt; beim ersten Besuch gilt „Alle“.

**Revision:** Ergebnis „In Ordnung“ oder „Mangel“. Bei „Mangel“ sind mehrere Mängel, Fotos (bis zu 6, mehrere nacheinander aufnehmbar) und eine
Bemerkung möglich; ein Mangel braucht mindestens einen gewählten Mangel oder eine Bemerkung. **Die letzte Revision im Durchgang zählt**, frühere bleiben im Verlauf.
Revisionen trägt man über den Tour-Link oder, ab Rolle Revisor, auf der Detailseite des Hydranten ein. Dort gilt: Gehört die Tour des Hydranten zum
aktiven Durchgang, zählt die Revision für diesen Durchgang; sonst (kein Durchgang aktiv oder Tour nicht dabei) wird sie ohne Durchgang gespeichert und steht im Verlauf des Hydranten. Darauf weist die Seite des Hydranten vorher und die Meldung nach dem Speichern ausdrücklich hin: Eine solche Revision erscheint nicht im Fortschritt und nicht in der Mängelübersicht.

**Mängelübersicht** (Menü „Mängel“, ab Bearbeiter): einen oder mehrere Durchgänge und/oder „Ohne Prüfdurchgang“ wählen. Zu „Ohne Prüfdurchgang“ gehören Revisionen, die in der App am Hydranten ohne passenden Durchgang erfasst wurden; je Hydrant zählt dort seine neueste Revision überhaupt, ein Mangel gilt also als erledigt, sobald der Hydrant später als „in Ordnung“ geprüft wird (in der App oder in einem Durchgang). Die Standardansicht zeigt den aktiven Durchgang plus „Ohne Prüfdurchgang“, also die aktuell offenen Mängel.
Weitere Filter: optional nach Tour filtern oder auch Hydranten ohne Mangel zeigen, mit **CSV-Export**.

## Stammdaten (Admin)

- **Typen:** Name und Symbol (Dreieck unten/oben, Quadrat, Kreis, Raute, Kreuz, Stern) frei wählbar; das Symbol erscheint in der Themenfarbe auf Karte und Liste, der Name als Tooltip und in der Legende. Typen lassen sich sperren (dann bei Neuanlage nicht mehr wählbar) und löschen, solange kein Hydrant sie verwendet. Ein neuer Typ gilt zunächst für die ersten vier Mängel; im Mängelkatalog änderbar.
- **Positionen:** Name, Reihenfolge, Sperren, Löschen (nur unbenutzt).
- **Mängelkatalog:** Namen, Reihenfolge, Sperren, Zuordnung zu Typen.

## Hydranten aus OpenStreetMap (Admin)

Import/Export → „Aus OpenStreetMap holen“. So geht es:
1. Name der Gemeinde eintragen (z. B. „Musterstadt“; bei gleichnamigen Orten genauer, z. B. „Musterstadt, Musterkreis“).
2. „Abfrage in Overpass Turbo öffnen“. Dort oben auf „Ausführen“ klicken, dann „Export“ → „Daten“ → „als GeoJSON herunterladen“.
3. Die heruntergeladene Datei hier hochladen. Gelesen werden GeoJSON, Overpass-JSON („Rohdaten“) und OSM-XML; alles, was kein Hydrant (`emergency=fire_hydrant`) ist, wird ignoriert (auch `emergency=suction_point`, eine offene Wasserentnahmestelle).

Daraus entsteht eine CSV-Datei im Importformat, die die normale Importvorschau durchläuft und auch heruntergeladen werden kann. Die Anwendung selbst stellt keine Verbindung zu OpenStreetMap her.

**Kennung wählbar:** bis zu drei Felder in frei wählbarer Reihenfolge – Referenznummer (`ref`), Lage (`fire_hydrant:location`) und OSM-Knoten („OSM 123“) – mit Trennzeichen
(Gedankenstrich, Schrägstrich, Komma, Leerzeichen, Bindestrich, Unterstrich). Leere Felder entfallen; sind alle leer, gilt „OSM <Nummer>“; doppelte Kennungen (auch zu bereits vorhandenen Hydranten) werden mit der OSM-Nummer bzw. einem Zähler eindeutig gemacht.
**Bemerkung wählbar:** Referenz, Lage, Notiz aus OSM und „Quelle: OSM-Knoten“ lassen sich einzeln ankreuzen; jede Angabe steht in einer eigenen Zeile mit Bezeichnung („Lage: …“). Voreinstellung: Notiz und Quelle.
**Durchfluss und Druck:** `flow_rate` wird als Durchfluss in l/min übernommen (auch „1600 l/min“, „1.600“, „26 l/s“, „96 m³/h“, „l/h“ und „gpm“ werden umgerechnet; „<400“ und „>400“ bleiben erhalten; bei mehreren Zahlen wie „800-1600“ gilt die erste), `fire_hydrant:pressure` als Druck in bar (auch „kPa“, „MPa“, „mbar“, „psi“). Nicht erkennbare Werte bleiben leer.
**Typ:** `fire_hydrant:type` underground → Unterflur, pillar → Überflur, pipe → Saugbrunnen; alles andere erhält den im Formular gewählten Typ. Hydranten, die weniger als 15 m von einem vorhandenen entfernt sind, werden auf Wunsch übersprungen.
Daten: © OpenStreetMap-Mitwirkende (ODbL).

**Automatisches Datenbank-Update:** Beim ersten Aufruf nach dem Hochladen legt die Anwendung neue Tabellen an (`campaign_tours`, `hydrant_types`, `positions`) und
übernimmt vorhandene Daten: gleichnamige Touren verschiedener Durchgänge werden zu einer festen Tour zusammengeführt, bestehende Links bleiben für ihren Durchgang gültig.
Dafür braucht der Datenbank-Benutzer die Rechte CREATE und ALTER; sonst erscheint eine Fehlerseite mit Hinweis, es gehen keine Daten verloren. Vor dem Update bitte eine Datenbanksicherung machen.

## Import und Export (nur Admin, Menü „Import/Export“)

**Hydranten importieren.** CSV-Datei hochladen, Vorschau prüfen, dann ausführen. Erst beim Ausführen wird gespeichert.
- Spalten: `kennung` und `typ` sind Pflicht, optional `durchmesser_dn`, `durchfluss_lmin`, `druck_bar`, `position`, `tour`, `lat`, `lon`, `bemerkung`, `aktiv`. `typ` und `position` müssen den Namen unter „Stammdaten“ entsprechen; eine unbekannte `tour` wird beim Import automatisch angelegt (die Vorschau weist darauf hin), eine leere Zelle nimmt den Hydranten aus der Tour. Reihenfolge egal; Trennzeichen Semikolon, Komma oder Tabulator; Zeichensatz UTF-8 oder Windows (Excel). Die Mustervorlage zeigt das Format.
- HTML-Schreibweisen wie `&uuml;`, `&szlig;`, `&#228;` oder `&amp;` werden in lesbare Zeichen umgewandelt, ebenso verdrehte Umlaute („Ã¼“). Angaben wie „3200 l/min“ oder „DN 100“ werden auf die Zahl gekürzt. Spalten wie `id` und `osm-node` werden nicht übernommen.
- **Kennung schon vorhanden:** Sie wählen beim Hochladen, ob alle aktualisiert, alle neu angelegt, alle ignoriert oder jeder einzeln nachgefragt werden. In der Vorschau lässt sich jeder Hydrant einzeln entscheiden: aktualisieren, als neuen Hydranten anlegen (dann tippen Sie eine neue Kennung ein) oder ignorieren.
- **Aktualisieren:** Eine leere Zelle löscht den bisherigen Wert dieses Feldes. Spalten, die in der Datei fehlen, bleiben unverändert.
- Fehlerhafte Zeilen (unbekannter Typ, ungültige Koordinate, fehlende Kennung usw.) werden übersprungen und mit Zeilennummer und Grund aufgelistet. Die Spalten Druck, Durchfluss und Position werden auch übernommen, wenn das Feld in den Einstellungen ausgeblendet ist.
- Hinweis: Enthält eine mit Semikolon getrennte Datei HTML-Schreibweisen (`&uuml;` enthält ein Semikolon), müssen diese Zellen in Anführungszeichen stehen, sonst trennt das Semikolon die Zelle. Bei Dateien mit Komma als Trennzeichen und Anführungszeichen (wie Ihr bisheriger Export) ist das bereits der Fall.

**Bilder importieren.** Unabhängig vom Hydranten-Import und beliebig oft möglich.
1. Bilddateien (JPG, PNG, WebP) per FTP in `storage/import/` hochladen.
2. Zuordnungs-CSV hochladen: Spalten `kennung`, `datei` und optional `beschriftung`. Die Kennung muss der Kennung in der Datenbank entsprechen.
3. Nach der Vorschau werden die Bilder verkleinert, dem Hydranten angehängt und aus dem Importordner entfernt. Nicht verarbeitbare Dateien bleiben dort liegen. Bei vielen Bildern läuft die Verarbeitung in Etappen automatisch weiter (Hoster erlauben nur wenige Sekunden je Anfrage).

**Export.** „Hydranten als CSV herunterladen“ liefert alle Hydranten (auch deaktivierte) im gleichen Format zum Sichern oder Bearbeiten in Excel; die Datei lässt sich wieder importieren. Zellen, die mit `=`, `+` oder `@` beginnen, werden mit einem Apostroph geschützt (Schutz vor Excel-Formeln) und beim Import wieder bereinigt.

**Export für OpenStreetMap (nur Admin).** In der Hydrantenliste „Für OpenStreetMap exportieren“ (oder unter Import/Export) öffnet einen Dialog; exportiert wird genau die Liste mit dem aktuellen Filter (Status, Typ, Position, Tour, Suche). Geschrieben werden nur gefüllte Angaben, ohne Koordinaten werden Hydranten übersprungen:
- `emergency=fire_hydrant` für alle Hydranten; Saugbrunnen werden wie Unter- und Überflurhydranten behandelt (OSM-Wert `pipe`, dazu `fire_hydrant:pressure=suction` anstelle eines Drucks)
- `fire_hydrant:type` und `fire_hydrant:position` aus dem **OSM-Wert**, den Sie unter Stammdaten bei jedem Typ und jeder Position wählen (Voreinstellung: Unterflur = `underground`, Überflur = `pillar`, Saugbrunnen = `pipe`, Grünstreifen = `green`, Gehweg = `sidewalk`, Parkplatz = `parking_lot`, Straße = `lane`; eigene Typen/Positionen lassen sich zuordnen; ohne Wert wird das Feld weggelassen, der Dialog weist darauf hin)
- `fire_hydrant:diameter` (DN in mm), `flow_rate` (l/min, nur die Zahl; ein Vergleichszeichen < oder > entfällt), `fire_hydrant:pressure` (bar, nur die Zahl, Punkt als Dezimalzeichen)
- `ref` und `fire_hydrant:location`: je wählbar aus Kennung, Bemerkung oder nicht schreiben (Voreinstellung: ref = Kennung, location = nicht schreiben); Bemerkungen werden einzeilig (Trennung mit „;“) und auf 255 Zeichen gekürzt
- `lat`/`lon` (CSV) bzw. Knotenposition (XML)
Formate: CSV (Semikolon, OSM-Schlüssel als Spaltenköpfe) oder OSM-XML (neue Knoten mit negativen Nummern, direkt in JOSM ladbar). Vor dem Hochladen zu OpenStreetMap bitte mit den vorhandenen OSM-Daten abgleichen, damit keine Doppelten entstehen.

## Hydranten

- **Kartensymbole:** je Typ unter „Stammdaten“ einstellbar (Voreinstellung: Unterflur = Dreieck nach unten, Überflur = Dreieck nach oben, Saugbrunnen = Quadrat). Auf der Detailseite und in der Auswahlkarte beim Anlegen/Bearbeiten erscheinen alle anderen aktiven Hydranten blass im Hintergrund; ein Klick öffnet ein Fenster mit Kennung und Typ (in der Detailkarte mit Link zum Hydranten).
- **Übersicht:** Auf dem Handy wechselt man mit den Reitern „Liste“ und „Karte“. „Auf Karte“ zeigt den Hydranten
  auf der Karte. Hydranten ohne Koordinaten stehen nur in der Liste.
- **Anlegen/Bearbeiten:** ab Rolle Bearbeiter. Kennung ist Freitext und muss eindeutig sein. Fotos werden schon im
  Browser verkleinert und auf dem Server nochmals neu gespeichert (EXIF-Standortdaten gehen dabei verloren).
  Je Hydrant sind bis zu 20 Bilder möglich, je Upload bis zu 10.
- **Bilder:** Beschriftung ändern, Titelbild festlegen oder ein Bild entfernen geht beim Bearbeiten.
  Bilder sind nur nach Anmeldung abrufbar.
- **Karten:** Kartenkacheln kommen von openstreetmap.org. Dabei erfährt OpenStreetMap die IP-Adresse der Besucher
  und den Kartenausschnitt, nicht aber die Hydrantendaten.
  Der OpenStreetMap-Kachelserver ist für kleine Anwendungen gedacht; bei starker Nutzung
  verlangt die [Tile Usage Policy](https://operations.osmfoundation.org/policies/tiles/) einen eigenen Kachelanbieter.
- **Kartenlinks:** Das Format der OpenFireMap-Adresse steht an einer Stelle in `src/lib/Hydrant.php`
  (`openFireMapUrl`).

## Voraussetzungen

- PHP 8.1 oder neuer (getestet mit 8.3) mit den Erweiterungen PDO MySQL, mbstring, OpenSSL und GD
- eine MySQL-/MariaDB-Datenbank samt Benutzer. Entweder eine neue, leere Datenbank oder eine
  **bereits vorhandene**, die weiterhin von anderen Anwendungen genutzt wird (siehe Installation, Schritt 3).
  Der Datenbank-Benutzer braucht die Rechte zum Anlegen von Tabellen.
- HTTPS für die Domain
- empfohlen: ein Postfach der eigenen Domain für den Mailversand (SMTP-Server, Benutzer, Passwort)

## Installation

1. Den Ordner `hydranten/` aus der ZIP-Datei per FTP in den Webspace hochladen. Der Ordnername ist
   frei wählbar (z. B. `hydranten`), die Anwendung funktioniert in jedem Unterordner oder auch im
   Hauptverzeichnis einer Domain.
2. Im Browser `https://ihre-domain.de/hydranten/` öffnen. Die Anwendung leitet auf den Installer um.
3. Prüfen, ob alle Voraussetzungen ein Häkchen haben, dann das Formular ausfüllen:
   Datenbank-Zugangsdaten (Server ist meist `localhost`), Name der Anwendung, Basis-URL
   (wird für Links in E-Mails benutzt, bitte mit `https://` prüfen) und den ersten Administrator.
   Bei der Datenbank wählen Sie:
   - **Neue, leere Datenbank:** Die Tabellen heißen `users`, `hydrants` usw. Der Installer bricht ab,
     falls schon eine Tabelle mit so einem Namen existiert.
   - **Bestehende Datenbank mitbenutzen:** Sie geben ein Tabellen-Präfix an (Vorschlag `hyd_`). Alle
     Tabellen heißen dann `hyd_users`, `hyd_hydrants` usw. Vorhandene Tabellen und Daten werden weder gelesen
     noch verändert. Der Installer bricht ab, falls mit diesem Präfix schon Tabellen existieren.
     Das Präfix steht danach in `storage/config.php` (`db_prefix`).
4. „Installieren“ klicken. Der Installer löscht sich danach selbst. Falls das nicht klappt,
   `install.php` per FTP löschen.
5. Anmelden, dann unter **Einstellungen**:
   - E-Mail-Versand eintragen und mit „Testmail senden“ prüfen
   - Name, Farben und Logo festlegen (die Vorschau zeigt die Änderung sofort)
   - bei Bedarf die Felder „Druck“, „Durchfluss“ und „Position“ ausblenden (bei der Installation sind alle drei eingeschaltet)
   - unter **Karte** die Kartenmitte Ihres Ortes und die Zoomstufe festlegen (auf die Karte tippen). Die Auswahlkarte beim Anlegen eines Hydranten startet dann dort.
6. Unter **Benutzer** die weiteren Personen anlegen. Sie erhalten eine E-Mail mit einem Link,
   über den sie ihr Passwort selbst festlegen. Funktioniert der Mailversand nicht, zeigt die
   Anwendung den Link an, und Sie können ihn selbst weitergeben.

Bitte installieren Sie zeitnah nach dem Hochladen. Solange die Installation nicht abgeschlossen ist,
kann sie jeder aufrufen, der die Adresse kennt.

## Rollen

| Rolle | Rechte |
|---|---|
| Betrachter | Hydranten ansehen, Fortschritt der Touren auf der Startseite |
| Revisor | zusätzlich Revisionen eingeben (Detailseite des Hydranten) |
| Bearbeiter | zusätzlich Hydranten anlegen, bearbeiten, deaktivieren, Bilder verwalten sowie Touren und Prüfdurchgänge verwalten |
| Admin | zusätzlich Benutzer, Einstellungen, Import/Export und Protokoll |

Benutzer werden nie gelöscht, nur gesperrt. Ein gesperrter Benutzer wird sofort abgemeldet.
Der letzte aktive Admin kann sich nicht selbst sperren oder herabstufen.

## Sicherheit (Überblick)

- Passwörter nur als Hash gespeichert, mindestens 10 Zeichen
- Einladungs- und Reset-Links sind einmalig gültig (Einladung 7 Tage, Reset 2 Stunden), in der Datenbank steht nur ihr Hash
- Sperre nach 5 Fehlversuchen je E-Mail-Adresse (20 je IP-Adresse) für 15 Minuten
- Nach einer Passwortänderung werden alle anderen Sitzungen dieses Kontos beendet
- CSRF-Schutz auf allen Formularen, strenge Content-Security-Policy, HTTPS-Weiterleitung
- SMTP-Passwort wird mit einem Schlüssel aus `storage/config.php` verschlüsselt in der Datenbank abgelegt
- Alle Aktionen mit Änderungen werden im Protokoll (`audit_log`) festgehalten (Anzeige: Menü „Protokoll“, nur Admin)

## Update und Datensicherung

- **Update:** Alles außer dem Ordner `storage/` überschreiben. In `storage/` liegen Konfiguration,
  Logo, Hydrantenbilder und Sitzungen.
- **Sicherung:** Datenbank-Export (phpMyAdmin oder Hoster-Backup) und der Ordner `storage/` per FTP. Bei einer geteilten Datenbank genügt es, die Tabellen mit dem gewählten Präfix zu exportieren.

## Wenn etwas nicht klappt

| Problem | Lösung |
|---|---|
| Alle Seiten außer der Startseite melden „nicht gefunden“ | In `storage/config.php` `'pretty_urls' => false` setzen. Dann laufen die Adressen über `index.php/…`. |
| Nach dem Umzug auf eine andere Domain stimmen die Links in E-Mails nicht | In `storage/config.php` den Eintrag `base_url` anpassen. |
| Weiße Seite oder „Es ist ein Fehler aufgetreten“ | In `storage/config.php` `'debug' => true` setzen, Seite neu laden, danach wieder auf `false` stellen. Die Meldung steht außerdem im Fehlerprotokoll des Hosters. |
| E-Mails kommen nicht an | Unter Einstellungen SMTP-Daten eintragen (Port 587 mit STARTTLS oder 465 mit SSL/TLS) und die Testmail senden. Spam-Ordner prüfen. |
| Foto-Upload bricht ab oder meldet „zu groß“ | Der Hoster begrenzt `upload_max_filesize`/`post_max_size`/`memory_limit`. Die Seite verkleinert Fotos selbst; bei Problemen weniger Bilder auf einmal hochladen. |
| Installer meldet „Datenbank ist nicht leer“ oder „Tabellen mit diesem Präfix“ | „Bestehende Datenbank mitbenutzen“ mit einem anderen Präfix wählen oder eine leere Datenbank verwenden. |

## Technik

Reines PHP ohne Framework und ohne Composer, keine externen Dienste, keine Cookies außer dem Sitzungs-Cookie.
Verzeichnisse: `src/` (Programmcode, per `.htaccess` gesperrt), `storage/` (Daten, gesperrt), `assets/` (CSS, JavaScript).
PHPMailer 6.9.3 liegt in `src/vendor/PHPMailer/` (LGPL), Leaflet 1.9.4 in `assets/vendor/leaflet/` (BSD-2-Clause).

## Protokoll (nur Admin, Menü „Protokoll“)

- **Liste:** Neueste Einträge zuerst, 50 je Seite. Spalten: Zeit, Benutzer, Aktion (in Klartext), Objekt (mit Link, z. B. zum Hydranten, zur Tour, zum Benutzer) und Details. Fehlgeschlagene Anmeldungen sind rot hinterlegt.
- **Filter:** Zeitraum (von/bis), Art (z. B. Hydranten, Touren, Benutzer, Stammdaten, „Fehlgeschlagene Anmeldungen“), Benutzer und Suchtext (in Details und Benutzer). Die Blätterlinks behalten den Filter.
- **CSV:** „Als CSV herunterladen“ liefert alle Einträge des aktuellen Filters (höchstens 50 000) mit Zeit, Benutzer, Aktion, Objekt und Details. Der Export wird selbst protokolliert.
- **Änderungsverlauf am Hydranten:** In der Detailansicht eines Hydranten sehen nur Admins die letzten 20 Einträge zu diesem Hydranten (Anlage, Änderungen mit alt → neu, Aktivieren/Deaktivieren, Tour, Bilder, Revisionen) und einen Link zum vollständigen Protokoll.
- **Aufbewahrung:** Unter Einstellungen → Protokoll: unbegrenzt (Voreinstellung), 12, 24, 36 oder 60 Monate. Ältere Einträge werden höchstens einmal am Tag automatisch gelöscht (beim nächsten Aufruf des Protokolls oder bei einer Anmeldung); das Löschen steht als „Protokoll bereinigt“ im Protokoll.
- **Menü:** Wegen des zusätzlichen Menüpunkts erscheint das ausklappbare Menü (☰) schon unterhalb von 1340 Pixel Fensterbreite.

## Datenschutz (Hinweis für Betreiber)

Wer die Anwendung betreibt (z. B. die Feuerwehr oder Gemeinde), ist selbst für den Datenschutz verantwortlich,
einschließlich Datenschutzerklärung, gegebenenfalls Impressum und Verzeichnis der Verarbeitungstätigkeiten.
Dieser Abschnitt ist ein technischer Überblick, keine Rechtsberatung.

Gespeichert werden:
- **Benutzer:** Name, E-Mail-Adresse, Rolle und Passwort-Hash
- **Protokoll:** wer wann welche Änderung vorgenommen hat, sowie fehlgeschlagene Anmeldungen (Aufbewahrung einstellbar, siehe „Protokoll“)
- **Anmeldeversuche:** E-Mail-Adresse und IP-Adresse für die Sperre nach Fehlversuchen, werden nach kurzer Zeit automatisch gelöscht
- **Prüfungen:** Ergebnis, Mängel, Bemerkungen, Fotos und bei Prüfungen über den Tour-Link der angegebene Name der Teilnehmenden
- **Hydranten:** Standort, Stammdaten, Fotos

Beim Anzeigen der Karte laden die Browser Kacheln von openstreetmap.org; dabei erfährt OpenStreetMap die IP-Adresse der Besucher.
Die Hydrantendaten selbst werden nicht an Dritte übermittelt, außer wenn Sie sie bewusst für OpenStreetMap exportieren.

## Lizenz

Hydrantenverwaltung
Copyright (C) 2026 Henning Peters und Mitwirkende

Dieses Programm ist freie Software: Sie können es unter den Bedingungen der GNU Affero General Public License, wie von der Free Software Foundation veröffentlicht, weitergeben und/oder modifizieren, entweder gemäß Version 3 der Lizenz oder (nach Ihrer Wahl) jeder späteren Version.

Die Veröffentlichung dieses Programms erfolgt in der Hoffnung, dass es Ihnen von Nutzen sein wird, aber OHNE IRGENDEINE GARANTIE, sogar ohne die implizite Garantie der MARKTREIFE oder der VERWENDBARKEIT FÜR EINEN BESTIMMTEN ZWECK. Details finden Sie in der GNU Affero General Public License.

Sie sollten eine Kopie der GNU Affero General Public License zusammen mit diesem Programm erhalten haben (Datei `LICENSE`). Falls nicht, siehe <https://www.gnu.org/licenses/>. (Verbindlich ist der englische Originaltext.)

SPDX-License-Identifier: AGPL-3.0-or-later

**Hinweis zu § 13 (Netzwerknutzung):** Wer eine veränderte Version dieses Programms über ein Netzwerk betreibt, muss allen Nutzern dieser Version den Quelltext der veränderten Version zugänglich machen (z. B. ein Link auf ein öffentliches Repository). Der unveränderte Betrieb löst diese Pflicht nicht aus.

**Enthaltene Fremdsoftware** (unter eigenen Lizenzen, mit der AGPL-Veröffentlichung vereinbar):
- PHPMailer 6.9.3, GNU Lesser General Public License 2.1 (`src/vendor/PHPMailer/LICENSE`)
- Leaflet 1.9.4, BSD-2-Clause (`assets/vendor/leaflet/LICENSE.txt`)

**Kartendaten:** Die Karten verwenden Kacheln von OpenStreetMap (© OpenStreetMap-Mitwirkende, ODbL). Beim Import aus und Export für OpenStreetMap gelten deren [Lizenz und Nutzungsbedingungen](https://www.openstreetmap.org/copyright).

