# Sicherheitshinweise

## Sicherheitslücken melden

Bitte melden Sie Sicherheitslücken **nicht** öffentlich als Issue, sondern vertraulich über
den Reiter **„Security“ → „Report a vulnerability“** dieses GitHub-Repositories
(„Private vulnerability reporting“).

Bitte beschreiben Sie, wie sich das Problem nachvollziehen lässt (betroffene Seite, Schritte,
Version). Ich antworte, sobald es mir möglich ist, und veröffentliche eine Korrektur, bevor
Einzelheiten bekannt gemacht werden.

## Unterstützte Versionen

Korrekturen gibt es für die jeweils neueste veröffentlichte Version. Bitte aktualisieren Sie
Ihre Installation auf diese Version (siehe README, Abschnitt „Update und Datensicherung“).

## Hinweise für Betreiber

- Nach der Installation `install.php` löschen, falls sie sich nicht selbst gelöscht hat.
- Das Verzeichnis `storage/` darf nie öffentlich erreichbar sein (die mitgelieferte `.htaccess`
  sperrt es auf Apache). Dort liegen Zugangsdaten und Uploads.
- Die Anwendung nur über HTTPS betreiben und regelmäßig Datensicherungen anlegen.
- Zugangsdaten, `storage/config.php` oder Datenbank-Dumps niemals in ein öffentliches
  Repository einchecken.
