# Formationen

**Datei:** `formationen.php`
**Berechtigung:** `formationen – lesen`

Ein Verein besteht oft nicht nur aus einer einzigen Besetzung. Es gibt z.B. die **Stammkapelle**, eine kleinere **7er-Partie** für Umzüge, eine **Jugendkapelle** oder eine **Musikkapelle** und ein **Blasorchester** unter einem gemeinsamen Dach. Für jede dieser parallelen Gruppen können in Syncopa eigene Mitgliederlisten, Ausrückungen, Noten, Finanzen usw. geführt werden – das nennt man in Syncopa eine **Formation**.

---

## Was ist eine Formation?

Eine Formation ist eine eigenständige Gruppe innerhalb des Vereins, z.B.:

- die Stammkapelle
- eine Jugendkapelle
- eine 7er- oder 12er-Partie
- ein Blasorchester oder eine Musikkapelle als eigene Einheit

Jede Formation hat ihre eigene Farbe, ein Kürzel und ihre eigenen Mitglieder. Viele Bereiche der Anwendung lassen sich nach der **aktiven Formation** filtern, sodass jede Gruppe nur ihre eigenen Daten sieht.

> 💡 **Tipp:** Vereine mit nur einer einzigen Besetzung müssen sich um dieses Thema nicht kümmern – ohne angelegte Formationen funktioniert Syncopa wie gewohnt und zeigt einfach alle Daten des Vereins an.

---

## Formationsliste

Die Übersicht zeigt alle angelegten Formationen mit:

| Spalte | Beschreibung |
|---|---|
| Name | Bezeichnung der Formation, mit farbigem Punkt in der hinterlegten Farbe |
| Kürzel | Kurzform, die in Badges und Listen angezeigt wird (z.B. „JK" für Jugendkapelle) |
| Mitglieder | Anzahl zugeordneter Mitglieder – Klick öffnet die Mitgliederzuordnung |
| Status | `Aktiv` oder `Inaktiv` |
| Aktionen | Mitglieder zuordnen · Bearbeiten · Löschen |

---

## Formation anlegen oder bearbeiten

**Datei:** `formation_bearbeiten.php`
**Berechtigung:** `formationen – schreiben`

1. Klicke in der Formationsliste auf **+ Neue Formation**
2. Fülle das Formular aus
3. Klicke auf **Erstellen** bzw. **Speichern**

### Formularfelder

| Feld | Pflicht | Hinweis |
|---|---|---|
| Name | ✅ | Bezeichnung der Formation, z.B. „Jugendkapelle" |
| Kürzel | – | Kurzform für Badges und Listen, z.B. „JK", „BO" (max. 10 Zeichen) |
| Farbe | – | Farbe, mit der die Formation überall in der Anwendung gekennzeichnet wird (z.B. bei Ausrückungen, Instrumentenzuordnung) |
| Beschreibung | – | Freitext, z.B. Zweck oder Besonderheiten der Formation |
| Formation aktiv | – | Nur aktive Formationen stehen zur Auswahl (z.B. im Formations-Switcher oder bei neuen Ausrückungen) |

> 💡 **Tipp:** Setze eine inaktive Formation auf „inaktiv" statt sie zu löschen – so bleiben alle bisherigen Daten (Mitglieder, Ausrückungen, Noten) erhalten, die Formation taucht aber nicht mehr in Auswahllisten auf.

---

## Mitglieder einer Formation zuordnen

**Datei:** `formation_mitglieder.php`
**Berechtigung:** `formationen – schreiben`

Auf dieser Seite legst du fest, welche Mitglieder zu einer Formation gehören. Ein Mitglied kann dabei **mehreren Formationen gleichzeitig** angehören.

1. Klicke in der Formationsliste bei der gewünschten Formation auf das Personen-Symbol (**Mitglieder zuordnen**)
2. Auf der rechten Seite findest du alle **verfügbaren** aktiven Mitglieder, die dieser Formation noch nicht angehören – bei Bedarf über das Suchfeld filtern
3. Klicke bei einem Mitglied auf **+**, um es der Formation hinzuzufügen
4. Das Mitglied erscheint nun links in der Tabelle **„Zugeordnete Mitglieder"**
5. Trage optional eine **Rolle in Formation** ein (z.B. „Stimmführer", „Kapellmeister") – die Eingabe wird automatisch gespeichert
6. Um ein Mitglied wieder zu entfernen, klicke rechts in seiner Zeile auf das **✕**-Symbol

Die Tabelle zeigt außerdem das **Datum**, seit wann das Mitglied der Formation angehört (wird automatisch beim Hinzufügen gesetzt).

> ⚠️ **Achtung:** Wird ein Mitglied aus einer Formation entfernt, verschwindet es aus allen formationsgefilterten Ansichten dieser Formation (Mitgliederliste, Ausrückungen, Noten usw.), solange diese Formation aktiv ausgewählt ist. Die Stammdaten des Mitglieds selbst bleiben unverändert erhalten.

---

## Der Formations-Switcher in der Kopfzeile

Benutzer, die **mehreren Formationen** angehören (oder Administratoren), sehen oben in der Kopfzeile (Topbar) einen **Formations-Switcher** – erkennbar an einem farbigen Punkt mit dem Kürzel bzw. Namen der aktuell aktiven Formation.

1. Klicke oben in der Kopfzeile auf die aktuelle Formation
2. Ein Dropdown öffnet sich mit allen Formationen, denen du angehörst
3. Wähle die gewünschte Formation aus – die Seite wird neu geladen und zeigt ab sofort nur noch Daten dieser Formation
4. **Administratoren** sehen zusätzlich den Eintrag **„Alle Formationen"**, um formationsübergreifend zu arbeiten

### Was bedeutet „Alle Formationen"?

Wenn keine bestimmte Formation aktiv ist (Auswahl „Alle Formationen", nur für Admins verfügbar), werden **keine** Formationsfilter angewendet – es sind alle Mitglieder, Ausrückungen, Noten usw. des gesamten Vereins sichtbar, unabhängig davon, welcher Formation sie zugeordnet sind.

Das Gleiche gilt für einzelne Datensätze: Ist bei einer Ausrückung, einem Instrument oder einer Notenzuordnung **keine** Formation hinterlegt (`formation_id` ist leer / `NULL`), gilt dieser Datensatz **für alle Formationen** und wird immer angezeigt, egal welche Formation gerade aktiv ist. Das ist z.B. sinnvoll für Instrumente, die ein Mitglied unabhängig von der Besetzung spielt.

> 💡 **Tipp:** Normale Benutzer, die nur einer einzigen Formation angehören, sehen den Switcher gar nicht erst – bei ihnen ist automatisch immer ihre eine Formation aktiv.

---

## Welche Bereiche werden nach Formation gefiltert?

Die aktive Formation aus der Kopfzeile wirkt sich automatisch auf zahlreiche Bereiche der Anwendung aus. Nur Daten der aktiven Formation (bzw. formationsübergreifende Daten ohne Zuordnung) werden angezeigt bzw. neu angelegt:

- **Mitglieder** – Mitgliederliste und Statistiken (→ [Mitglieder](mitglieder.md))
- **Instrumentenzuordnung** je Mitglied (→ [Mitglieder](mitglieder.md#gespielte-instrumente-formationsabhängig))
- **Ausrückungen** – Liste, Kalender und automatisch angelegte Anwesenheits-Einträge (→ [Ausrückungen](ausrueckungen.md))
- **Noten / Notenbücher**
- **Finanzen**
- **Live-Probe** (probe.php)

Beim **Anlegen neuer Datensätze** (z.B. einer neuen Ausrückung) wird die aktuell aktive Formation automatisch vorausgewählt bzw. hinterlegt.

---

## Berechtigungen

Das Modul `formationen` kennt wie alle Module drei Berechtigungsstufen, die pro Rolle in der Rollenverwaltung vergeben werden (→ [Rollen](rollen.md)):

| Berechtigung | Ermöglicht |
|---|---|
| `formationen – lesen` | Formationsliste ansehen |
| `formationen – schreiben` | Formationen anlegen, bearbeiten, Mitglieder zuordnen |
| `formationen – löschen` | Formationen löschen |

> ⚠️ **Achtung:** Beim Löschen einer Formation werden **alle Mitglieder-Zuordnungen** dieser Formation entfernt. Ausrückungen, Noten usw., die bisher exklusiv dieser Formation zugeordnet waren, bleiben zwar erhalten, verlieren aber ihre Formationszuordnung. War die gelöschte Formation gerade aktiv ausgewählt, springt die Anwendung automatisch auf „Alle Formationen" zurück.

Nur **Administratoren** dürfen eine beliebige Formation auswählen bzw. auf „Alle Formationen" wechseln. Normale Benutzer können im Switcher nur zwischen den Formationen wechseln, denen sie über die Mitgliederzuordnung selbst angehören.
