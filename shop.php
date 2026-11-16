<?php
// shop.php – Vereins-Shop (Client): zeigt den vom Katalog-Server gelieferten Katalog, Warenkorb und Bestellung.
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (!Session::isAdmin()) {
    Session::setFlashMessage('danger', 'Der Shop steht derzeit nur dem Vorstand (Administratoren) zur Verfügung.');
    header('Location: index.php');
    exit;
}
if (!SHOP_IST_ZENTRALE && ShopKatalog::server() === '') {
    include 'includes/header.php';
    echo '<div class="alert alert-info alert-permanent mt-3"><i class="bi bi-info-circle"></i> Für diese Installation ist kein Katalog-Server hinterlegt.</div>';
    include 'includes/footer.php';
    exit;
}
// Zentrale Freischaltung prüfen (meldet diese Installation beim Server und holt den Freischalt-Status)
if (!ShopKatalog::status()['aktiv']) {
    include 'includes/header.php';
    echo '<div class="alert alert-info alert-permanent mt-3"><i class="bi bi-info-circle"></i> Der Shop ist für diesen Verein derzeit nicht freigeschaltet.</div>';
    include 'includes/footer.php';
    exit;
}

$db = Database::getInstance();
if (!isset($_SESSION['shop_warenkorb']) || !is_array($_SESSION['shop_warenkorb'])) $_SESSION['shop_warenkorb'] = [];

// ---------------------------------------------------------------- Aktionen (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $zielAnsicht = 'liste';
    try {
        $katalog = ShopKatalog::katalog();
        switch ($_POST['aktion'] ?? '') {
            case 'hinzufuegen':
                $a = ShopKatalog::artikel($katalog, (int)($_POST['artikel_id'] ?? 0));
                if (!$a) throw new RuntimeException('Artikel nicht gefunden.');
                if (!empty($a['ausverkauft'])) throw new RuntimeException('Dieser Artikel ist derzeit ausverkauft.');
                // Zeilen sammeln: mit Größen über mengen[variante_id] (mehrere auf einmal), sonst eine Menge
                $zeilen = [];
                if (!empty($a['varianten'])) {
                    $mengen = (array)($_POST['mengen'] ?? []);
                    if (!$mengen && !empty($_POST['variante_id'])) $mengen = [(int)$_POST['variante_id'] => (int)($_POST['menge'] ?? 1)];
                    foreach ($a['varianten'] as $v) {
                        $m = max(0, min(99, (int)($mengen[$v['id']] ?? 0)));
                        if ($m > 0) $zeilen[] = [$v, $m];
                    }
                    if (!$zeilen) throw new RuntimeException('Bitte bei mindestens einer Größe eine Menge eintragen.');
                } else {
                    $m = max(0, min(99, (int)($_POST['menge'] ?? 1)));
                    if ($m < 1) throw new RuntimeException('Bitte eine Menge eintragen.');
                    $zeilen[] = [null, $m];
                }
                // Veredelung (Stickerei/Druck je Position) – gilt für alle Stück dieses Hinzufügens, Aufpreis je Stück
                $ved = []; $vedPreis = 0.0;
                $gewaehlt = array_map('intval', array_keys(array_filter((array)($_POST['veredelung'] ?? []))));
                if ($gewaehlt) {
                    if (empty($a['veredelung'])) throw new RuntimeException('Für diesen Artikel ist keine Veredelung möglich.');
                    $angebot = [];
                    foreach ($katalog['veredelungen'] ?? [] as $v) $angebot[(int)$v['id']] = $v;
                    // erst alles prüfen, dann speichern (keine halben Uploads)
                    foreach ($gewaehlt as $vid) {
                        if (!isset($angebot[$vid])) throw new RuntimeException('Diese Veredelung wird nicht mehr angeboten. Bitte die Seite neu laden.');
                        if ((int)($_FILES['ved_datei']['error'][$vid] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                            throw new RuntimeException('Bitte für „' . $angebot[$vid]['art_name'] . ' – ' . $angebot[$vid]['position_name'] . '“ ein Logo bzw. einen Schriftzug hochladen.');
                        }
                    }
                    Shop::vorlagenAufraeumen();
                    foreach ($gewaehlt as $vid) {
                        $f = Shop::vorlageSpeichern([
                            'name' => $_FILES['ved_datei']['name'][$vid], 'tmp_name' => $_FILES['ved_datei']['tmp_name'][$vid],
                            'error' => $_FILES['ved_datei']['error'][$vid], 'size' => $_FILES['ved_datei']['size'][$vid],
                        ]);
                        $ved[] = [
                            'id' => $vid, 'bezeichnung' => $angebot[$vid]['art_name'] . ' – ' . $angebot[$vid]['position_name'],
                            'preis' => round((float)$angebot[$vid]['preis'], 2),
                            'hinweis' => mb_substr(trim((string)($_POST['ved_hinweis'][$vid] ?? '')), 0, 300),
                            'datei' => $f['datei'], 'original' => $f['original'],
                        ];
                        $vedPreis += (float)$angebot[$vid]['preis'];
                    }
                }
                $wk = &$_SESSION['shop_warenkorb'];
                $stueck = 0; $groessen = [];
                $vedKennung = $ved ? ':' . substr(md5(json_encode($ved)), 0, 10) : '';   // eigene Zeile je Veredelung
                foreach ($zeilen as [$variante, $menge]) {
                    $preis = $variante ? (float)$variante['preis'] : (float)$a['preis'];
                    $anr = $variante ? ($variante['artikelnummer'] ?: $a['artikelnummer']) : $a['artikelnummer'];
                    $schluessel = $a['id'] . ':' . ($variante['id'] ?? 0) . $vedKennung;
                    if (isset($wk[$schluessel])) $wk[$schluessel]['menge'] = min(99, $wk[$schluessel]['menge'] + $menge);
                    else $wk[$schluessel] = [
                        'artikel_id' => (int)$a['id'], 'artikel_name' => $a['name'],
                        'variante' => $variante['bezeichnung'] ?? null, 'artikelnummer' => $anr,
                        'grundpreis' => round($preis, 2),
                        'einzelpreis' => round($preis + $vedPreis, 2),   // inkl. Veredelung je Stück
                        'veredelung' => $ved,
                        'menge' => $menge,
                        'bild' => $a['bilder'][0] ?? null,
                    ];
                    $stueck += $menge;
                    if ($variante) $groessen[] = $menge . '× ' . $variante['bezeichnung'];
                }
                unset($wk);
                Session::setFlashMessage('success', $stueck . ' Stück in den Warenkorb gelegt' . ($groessen ? ' (' . implode(', ', $groessen) . ')' : '')
                    . ($ved ? ' – mit ' . count($ved) . ' Veredelung' . (count($ved) > 1 ? 'en' : '') : '') . '.');
                $zielAnsicht = 'artikel';
                break;
            case 'menge':
                $k = (string)($_POST['schluessel'] ?? '');
                $m = (int)($_POST['menge'] ?? 1);
                if (isset($_SESSION['shop_warenkorb'][$k])) {
                    if ($m <= 0) unset($_SESSION['shop_warenkorb'][$k]);
                    else $_SESSION['shop_warenkorb'][$k]['menge'] = min(99, $m);
                }
                $zielAnsicht = 'warenkorb';
                break;
            case 'entfernen':
                unset($_SESSION['shop_warenkorb'][(string)($_POST['schluessel'] ?? '')]);
                $zielAnsicht = 'warenkorb';
                break;
            case 'leeren':
                $_SESSION['shop_warenkorb'] = [];
                $zielAnsicht = 'warenkorb';
                break;
            case 'bestellen':
                if (!$_SESSION['shop_warenkorb']) throw new RuntimeException('Der Warenkorb ist leer.');
                // Vorlagen (Logos) einmal je Datei mitschicken; Positionen verweisen nur darauf
                $dateien = []; $positionen = [];
                foreach ($_SESSION['shop_warenkorb'] as $z) {
                    $vedSenden = [];
                    foreach ($z['veredelung'] ?? [] as $v) {
                        if (!isset($dateien[$v['datei']])) {
                            $pfad = Shop::vorlagePfad((string)$v['datei']);
                            if (!$pfad) throw new RuntimeException('Die Datei „' . $v['original'] . '“ ist nicht mehr vorhanden. Bitte „' . $z['artikel_name'] . '“ entfernen und erneut mit Veredelung in den Warenkorb legen.');
                            $dateien[$v['datei']] = ['original' => $v['original'], 'inhalt' => base64_encode((string)file_get_contents($pfad))];
                        }
                        $vedSenden[] = ['id' => $v['id'], 'hinweis' => $v['hinweis'], 'datei' => $v['datei']];
                    }
                    $positionen[] = [
                        'artikel_name' => $z['artikel_name'], 'variante' => $z['variante'], 'artikelnummer' => $z['artikelnummer'],
                        'einzelpreis' => $z['grundpreis'] ?? $z['einzelpreis'],   // Veredelung rechnet der Katalog-Server selbst dazu
                        'menge' => $z['menge'], 'veredelung' => $vedSenden,
                    ];
                }
                $nr = ShopKatalog::bestellungSenden([
                    'verein_name'      => trim((string)($_POST['verein_name'] ?? '')),
                    'verein_url'       => BASE_URL,
                    'besteller_name'   => trim((string)($_POST['besteller_name'] ?? '')),
                    'besteller_email'  => trim((string)($_POST['besteller_email'] ?? '')),
                    'besteller_telefon'=> trim((string)($_POST['besteller_telefon'] ?? '')),
                    'lieferadresse'    => trim((string)($_POST['lieferadresse'] ?? '')),
                    'bemerkung'        => trim((string)($_POST['bemerkung'] ?? '')),
                    'positionen'       => $positionen,
                    'dateien'          => $dateien,
                ]);
                // übertragene Vorlagen aus der Zwischenablage entfernen
                foreach (array_keys($dateien) as $ref) { $p = Shop::vorlagePfad((string)$ref); if ($p) @unlink($p); }
                $_SESSION['shop_warenkorb'] = [];
                header('Location: shop.php?ansicht=fertig&nr=' . urlencode($nr));
                exit;
        }
    } catch (\Throwable $e) {
        Session::setFlashMessage('danger', $e->getMessage());
        $zielAnsicht = $_POST['zuruck'] ?? $zielAnsicht;
    }
    // Beim Hinzufügen (auch bei Fehlern) auf der Artikelseite bleiben
    if (($_POST['aktion'] ?? '') === 'hinzufuegen' && !empty($_POST['artikel_id'])) $zielAnsicht = 'artikel';
    header('Location: shop.php?ansicht=' . $zielAnsicht . ($zielAnsicht === 'artikel' && !empty($_POST['artikel_id']) ? '&artikel=' . (int)$_POST['artikel_id'] : ''));
    exit;
}

// ---------------------------------------------------------------- Anzeige
$ansicht = $_GET['ansicht'] ?? 'liste';
$warenkorb = $_SESSION['shop_warenkorb'];
$wkAnzahl = array_sum(array_map(fn($z) => $z['menge'], $warenkorb));
$wkSumme  = array_sum(array_map(fn($z) => $z['einzelpreis'] * $z['menge'], $warenkorb));
$h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
$euro = fn($b) => number_format((float)$b, 2, ',', '.') . ' €';

$ladeFehler = null; $katalog = ['info' => ['titel' => 'Shop'], 'kategorien' => [], 'artikel' => []];
if ($ansicht !== 'fertig') {
    try { $katalog = ShopKatalog::katalog(); }
    catch (\Throwable $e) { $ladeFehler = $e->getMessage(); }
}
$titel = $katalog['info']['titel'] ?? 'Shop';

// Versandkosten (vom Katalog-Server vorgegeben) für den aktuellen Warenkorb
$vkSatz   = (float)($katalog['info']['versandkosten'] ?? 0);
$vkFrei   = (float)($katalog['info']['versandkosten_frei_ab'] ?? 0);
$versand  = ($vkSatz > 0 && !($vkFrei > 0 && $wkSumme >= $vkFrei)) ? $vkSatz : 0.0;
$wkGesamt = $wkSumme + $versand;

include 'includes/header.php';

$katName = fn($id) => $id ? ShopKatalog::kategorieName($katalog, (int)$id) : 'Sonstiges';
$preisAbFn = fn($a) => !empty($a['varianten']) ? min(array_map(fn($v) => (float)$v['preis'], $a['varianten'])) : (float)$a['preis'];
// normaler Preis (ohne Aktion) – zum Durchstreichen; ältere Kataloge ohne preis_regulaer zeigen nichts Durchgestrichenes
$preisAbRegFn = fn($a) => !empty($a['varianten']) ? min(array_map(fn($v) => (float)($v['preis_regulaer'] ?? $v['preis']), $a['varianten'])) : (float)($a['preis_regulaer'] ?? $a['preis']);
$altPreis = fn($a) => ($r = $preisAbRegFn($a)) > $preisAbFn($a) + 0.001 ? $r : null;
$einleitung = trim((string)($katalog['info']['einleitung'] ?? ''));

// Kategorie-Baum (eine Ebene Unterkategorien)
$kats = $katalog['kategorien'] ?? [];
$katGueltig = array_flip(array_map(fn($k) => (int)$k['id'], $kats));
$katParent = []; $katKinder = []; $katHaupt = [];
foreach ($kats as $k) {
    $kid = (int)$k['id']; $p = (int)($k['parent_id'] ?? 0);
    if ($p && isset($katGueltig[$p])) { $katParent[$kid] = $p; $katKinder[$p][] = $kid; }
    else $katHaupt[] = $kid;
}
$angebote = $katalog['angebote'] ?? [];
// Artikel je Kategorie; Hauptkategorien zählen ihre Unterkategorien mit
$anzahlJeKat = [];
foreach ($katalog['artikel'] ?? [] as $a) {
    $kk = (int)($a['kategorie_id'] ?? 0);
    if ($kk && !isset($katGueltig[$kk])) $kk = 0;
    $anzahlJeKat[$kk] = ($anzahlJeKat[$kk] ?? 0) + 1;
    if (isset($katParent[$kk])) $anzahlJeKat['g' . $katParent[$kk]] = ($anzahlJeKat['g' . $katParent[$kk]] ?? 0) + 1;
}
$katGesamt = fn($kid) => ($anzahlJeKat[$kid] ?? 0) + ($anzahlJeKat['g' . $kid] ?? 0);
?>
<style>
/* ===== Vereins-Shop – modernes Layout in Syncopa-Farben (nutzt die Theme-Variablen, dadurch hell & dunkel) ===== */
.sx{--sx-p:var(--c-primary,#4471A3);--sx-p2:var(--c-primary-light,#5496cb);--sx-dark:#2d4a6a;--sx-gold:var(--c-warning,#D19A3E);
  --sx-card:var(--bg-card,#fff);--sx-line:var(--border,#e0e4e8);--sx-ink:var(--text-primary,#2c3e50);--sx-sub:var(--text-secondary,#5a6c7d);
  --sx-mut:var(--text-muted,#8699ac);--sx-soft:rgba(68,113,163,.09);--sx-r:14px;color:var(--sx-ink)}
.sx a{text-decoration:none}

/* Hero */
.sx-hero{position:relative;overflow:hidden;border-radius:18px;color:#fff;padding:30px 32px;margin-bottom:18px;
  background:linear-gradient(120deg,var(--sx-dark) 0%,var(--sx-p) 62%,var(--sx-p2) 100%);box-shadow:0 14px 34px rgba(45,74,106,.28)}
.sx-hero::before,.sx-hero::after{content:"";position:absolute;border-radius:50%;pointer-events:none}
.sx-hero::before{width:340px;height:340px;right:-90px;top:-150px;background:rgba(255,255,255,.09)}
.sx-hero::after{width:220px;height:220px;right:160px;bottom:-140px;background:rgba(209,154,62,.22)}
.sx-hero > *{position:relative;z-index:1}
.sx-eyebrow{font-size:.72rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;opacity:.8}
.sx-hero h1{font-size:2rem;font-weight:800;letter-spacing:-.02em;margin:.25rem 0 .35rem;color:#fff}
.sx-hero p{margin:0;max-width:560px;color:rgba(255,255,255,.85)}
.sx-hero.compact{padding:18px 24px}
.sx-hero.compact h1{font-size:1.35rem;margin:.1rem 0 0}
.sx-cart{display:inline-flex;align-items:center;gap:8px;border-radius:999px;padding:9px 16px;font-weight:600;color:#fff;
  background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.28);backdrop-filter:blur(6px);transition:.15s;white-space:nowrap}
.sx-cart:hover{background:rgba(255,255,255,.26);color:#fff}
.sx-cart .n{background:var(--sx-gold);color:#fff;border-radius:999px;font-size:.72rem;font-weight:800;padding:1px 8px}
.sx-search{position:relative;margin-top:20px;max-width:520px}
.sx-search i{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:var(--sx-mut)}
.sx-search input{width:100%;border:none;border-radius:999px;padding:12px 18px 12px 42px;font-size:.95rem;color:#2c3e50;
  background:#fff;box-shadow:0 6px 18px rgba(0,0,0,.15);outline:none}
.sx-crumb{font-size:.8rem;color:rgba(255,255,255,.8)}
.sx-crumb a{color:#fff;opacity:.9}.sx-crumb a:hover{opacity:1;text-decoration:underline}

/* Vorteile */
.sx-trust{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:22px}
.sx-trust div{display:flex;align-items:center;gap:12px;background:var(--sx-card);border:1px solid var(--sx-line);border-radius:12px;padding:12px 14px}
.sx-trust i{flex:0 0 auto;width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;
  background:var(--sx-soft);color:var(--sx-p);font-size:1.1rem}
.sx-trust b{display:block;font-size:.88rem}.sx-trust span{font-size:.78rem;color:var(--sx-mut)}
@media (max-width:767px){.sx-trust{grid-template-columns:1fr}}

/* Kopf-Navigation */
.sx-headwrap{position:relative;margin-bottom:18px}
.sx-headwrap .sx-hero{margin-bottom:0;padding-top:16px}
.sx-topnav{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-bottom:14px;margin-bottom:22px;
  border-bottom:1px solid rgba(255,255,255,.18)}
.sx-brand{display:inline-flex;align-items:center;gap:10px;color:#fff;font-weight:800;font-size:1rem;min-width:0}
.sx-brand:hover{color:#fff}
.sx-brand .ic{flex:0 0 auto;width:34px;height:34px;border-radius:10px;background:rgba(255,255,255,.16);display:flex;align-items:center;justify-content:center}
.sx-menu{display:flex;align-items:center;gap:2px}
.sx-mi{position:relative;display:inline-flex;align-items:center;gap:6px;border:none;background:transparent;color:#fff;
  font-weight:800;font-size:.84rem;letter-spacing:.08em;text-transform:uppercase;padding:8px 12px;border-radius:8px;cursor:pointer;transition:.15s}
.sx-mi:hover,.sx-mi.on{color:var(--sx-gold);background:rgba(255,255,255,.08)}
.sx-mi .bi-chevron-down{font-size:.7rem;transition:transform .2s}
.sx-mi.on .bi-chevron-down{transform:rotate(180deg)}
.sx-mi-cart{font-size:1.15rem;padding:6px 10px}
.sx-mi-cart .n{position:absolute;top:0;right:1px;background:var(--c-danger,#F44336);color:#fff;border-radius:999px;font-size:.62rem;
  font-weight:800;letter-spacing:0;padding:1px 5px;line-height:1.3}

/* Mega-Menü: helle Spalten-Karten auf dunklem Grund */
.sx-mega{display:none;position:absolute;left:0;right:0;top:66px;z-index:40;padding:22px;border-radius:16px;
  background:repeating-linear-gradient(45deg,rgba(255,255,255,.035) 0 2px,transparent 2px 9px),linear-gradient(135deg,#1a2a40 0%,#2d4a6a 60%,#35577d 100%);
  box-shadow:0 24px 50px rgba(15,25,40,.45);animation:sxAuf .18s ease-out}
.sx-mega.offen{display:block}
@keyframes sxAuf{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}
.sx-mega-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:20px}
.sx-mcard{background:var(--sx-card);border-radius:4px;padding:24px 26px 18px;box-shadow:0 8px 20px rgba(0,0,0,.25)}
.sx-mcard h3{margin:0;text-align:center;font-family:Georgia,"Times New Roman",serif;font-weight:700;font-size:1.3rem;
  letter-spacing:.04em;text-transform:uppercase;line-height:1.15}
.sx-mcard h3 a{color:var(--sx-ink);cursor:pointer}
.sx-mcard h3 a:hover{color:var(--sx-p)}
.sx-orn{display:flex;align-items:center;gap:12px;margin:14px 0 10px;color:var(--sx-p);font-size:1rem}
.sx-orn::before,.sx-orn::after{content:"";flex:1;height:1.5px;background:var(--sx-ink);opacity:.75}
.sx-mcard ul{list-style:none;margin:0;padding:0}
.sx-mcard li a{display:flex;align-items:flex-start;gap:10px;padding:9px 2px;color:var(--sx-sub);font-size:.93rem;cursor:pointer;transition:color .15s}
.sx-mcard li a i{font-size:.75rem;margin-top:5px;color:var(--sx-mut);transition:.15s}
.sx-mcard li a span{flex:1}
.sx-mcard li a em{font-style:normal;font-size:.72rem;color:var(--sx-mut);background:var(--sx-soft);border-radius:999px;padding:1px 7px;margin-top:2px}
.sx-mcard li a:hover{color:var(--sx-p)}
.sx-mcard li a:hover i{color:var(--sx-p);transform:translateX(2px)}
.sx-mcard li a.alle{color:var(--sx-p);font-weight:600}
.sx-mega-fuss{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:16px;font-size:.85rem}
.sx-mega-fuss a{color:#fff;font-weight:700;cursor:pointer}
.sx-mega-fuss a:hover{color:var(--sx-gold)}
.sx-mega-fuss button{border:none;background:rgba(255,255,255,.12);color:#fff;border-radius:999px;padding:5px 12px;font-size:.8rem}
.sx-mega-fuss button:hover{background:rgba(255,255,255,.2)}
@media (max-width:575px){.sx-mega{padding:14px}.sx-mcard{padding:18px 18px 12px}.sx-mi{padding:6px 8px;font-size:.74rem}.sx-brand{font-size:.9rem}}

/* Unterkategorien-Leiste */
.sx-subs{display:none;gap:6px;flex-wrap:wrap;margin:-6px 0 16px}
.sx-subs.offen{display:flex}
.sx-sub{border:none;background:var(--sx-soft);color:var(--sx-p);border-radius:999px;padding:4px 12px;font-size:.8rem;font-weight:600;cursor:pointer}
.sx-sub.on{background:var(--sx-p);color:#fff}
.sx-sub .z{opacity:.65;font-weight:500;margin-left:3px}

/* Angebote */
.sx-offers{position:relative;overflow:hidden;border-radius:18px;padding:26px 24px 28px;margin-bottom:26px;color:#fff;
  background:linear-gradient(135deg,#1f3550 0%,var(--sx-dark) 55%,#35577d 100%)}
.sx-offers::before{content:"";position:absolute;inset:0;pointer-events:none;opacity:.5;
  background:radial-gradient(circle at 12% 20%,rgba(209,154,62,.22),transparent 40%),radial-gradient(circle at 90% 85%,rgba(84,150,203,.25),transparent 45%)}
.sx-offers > *{position:relative}
.sx-offers h2{font-size:1.5rem;font-weight:800;letter-spacing:-.01em;margin:0 0 4px;color:#fff}
.sx-offers .lead-s{color:rgba(255,255,255,.7);font-size:.9rem;margin-bottom:18px}
.sx-off-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:18px}
.sx-off{display:flex;flex-direction:column;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.13);border-radius:14px;
  padding:12px;backdrop-filter:blur(4px);transition:transform .18s,background .18s}
.sx-off:hover{transform:translateY(-3px);background:rgba(255,255,255,.11)}
.sx-off .bild{aspect-ratio:16/10;border-radius:10px;overflow:hidden;background:rgba(255,255,255,.1)}
.sx-off .bild img{width:100%;height:100%;object-fit:cover;transition:transform .4s}
.sx-off:hover .bild img{transform:scale(1.04)}
.sx-off .bild .ph{display:flex;height:100%;align-items:center;justify-content:center;font-size:34px;color:rgba(255,255,255,.35)}
.sx-off h3{font-size:1.2rem;font-weight:800;margin:14px 4px 6px;color:#fff;line-height:1.25}
.sx-off p{color:rgba(255,255,255,.78);font-size:.86rem;margin:0 4px 10px;white-space:pre-line}
.sx-off ul{list-style:none;padding:0;margin:0 4px 6px}
.sx-off li{display:flex;gap:7px;font-size:.84rem;color:rgba(255,255,255,.9);padding:3px 0}
.sx-off li i{color:var(--sx-gold);font-size:.75rem;margin-top:3px}
.sx-off li a{color:#fff;text-decoration:underline;text-decoration-color:rgba(209,154,62,.7);text-underline-offset:3px}
.sx-off .mehr{margin:auto 4px 2px;padding-top:10px}
.sx-off .mehr button{border:none;background:var(--sx-gold);color:#fff;border-radius:999px;padding:6px 14px;font-size:.8rem;font-weight:700}
.sx-off .mehr button:hover{filter:brightness(1.08)}

/* Werkzeugleiste */
.sx-bar{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:16px}
.sx-pills{display:flex;gap:6px;overflow-x:auto;scrollbar-width:none;flex:1 1 auto;min-width:0;padding-bottom:2px}
.sx-pills::-webkit-scrollbar{display:none}
.sx-pill{flex:0 0 auto;border:1px solid var(--sx-line);background:var(--sx-card);color:var(--sx-sub);border-radius:999px;
  padding:6px 14px;font-size:.84rem;font-weight:600;cursor:pointer;transition:.15s;white-space:nowrap}
.sx-pill:hover{border-color:var(--sx-p);color:var(--sx-p)}
.sx-pill.on{background:var(--sx-p);border-color:var(--sx-p);color:#fff}
.sx-pill .z{opacity:.65;font-weight:500;margin-left:3px}
.sx-tools{display:flex;align-items:center;gap:8px;margin-left:auto}
.sx-count{font-size:.8rem;color:var(--sx-mut);white-space:nowrap}
.sx-select{border:1px solid var(--sx-line);background:var(--sx-card);color:var(--sx-ink);border-radius:999px;padding:6px 30px 6px 12px;font-size:.82rem}
.sx-favbtn{border:1px solid var(--sx-line);background:var(--sx-card);color:var(--sx-sub);border-radius:999px;padding:6px 12px;font-size:.82rem;font-weight:600;cursor:pointer}
.sx-favbtn.on{border-color:#e05656;color:#e05656;background:rgba(224,86,86,.08)}

/* Raster & Karten */
.sx-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(176px,1fr));gap:16px}
@media (max-width:575px){.sx-grid{grid-template-columns:repeat(2,1fr);gap:10px}}
.sx-card{position:relative;display:flex;flex-direction:column;background:var(--sx-card);border:1px solid var(--sx-line);
  border-radius:var(--sx-r);overflow:hidden;transition:transform .18s,box-shadow .18s,border-color .18s}
.sx-card:hover{transform:translateY(-3px);box-shadow:0 12px 28px rgba(45,74,106,.14);border-color:rgba(68,113,163,.35)}
.sx-media{position:relative;aspect-ratio:1/1;background:#fff;overflow:hidden}
.sx-media .lnk{position:absolute;inset:0;z-index:1}
.sx-media img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity .3s,transform .4s}
.sx-media img.on{opacity:1}
.sx-card:hover .sx-media img.on{transform:scale(1.04)}
.sx-media .ph{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#c5ccd4;font-size:32px}
.sx-badges{position:absolute;left:8px;top:8px;z-index:3;display:flex;flex-direction:column;gap:4px;align-items:flex-start}
.sx-badge{font-size:.68rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase;border-radius:2px;padding:4px 9px;color:#fff;
  background:#d32f2f;box-shadow:0 2px 6px rgba(211,47,47,.3)}
.sx-fav{position:absolute;right:8px;top:8px;z-index:3;width:30px;height:30px;border-radius:50%;border:none;padding:0;
  background:rgba(255,255,255,.92);color:#7b8794;display:flex;align-items:center;justify-content:center;font-size:.85rem;
  box-shadow:0 2px 8px rgba(0,0,0,.14);cursor:pointer;transition:.15s}
.sx-fav:hover{color:#e05656;transform:scale(1.08)}
.sx-fav.on{color:#e05656}
.sx-nav{position:absolute;top:50%;transform:translateY(-50%);z-index:3;width:26px;height:26px;border-radius:50%;border:none;padding:0;
  background:rgba(255,255,255,.92);color:#2c3e50;display:flex;align-items:center;justify-content:center;font-size:.75rem;
  opacity:0;transition:opacity .15s;box-shadow:0 2px 7px rgba(0,0,0,.16);cursor:pointer}
.sx-nav.prev{left:6px}.sx-nav.next{right:6px}
.sx-media:hover .sx-nav{opacity:1}
@media (hover:none){.sx-nav{opacity:.9}}
.sx-dots{position:absolute;left:0;right:0;bottom:7px;z-index:3;display:flex;gap:4px;justify-content:center}
.sx-dot{width:6px;height:6px;border-radius:999px;border:none;padding:0;background:rgba(44,62,80,.25);cursor:pointer;transition:.2s}
.sx-dot.on{width:16px;background:var(--sx-p)}
.sx-sold{position:absolute;inset:0;z-index:2;display:flex;align-items:center;justify-content:center;
  background:rgba(255,255,255,.55);backdrop-filter:grayscale(1)}
.sx-sold span{background:#2c3e50;color:#fff;font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;border-radius:999px;padding:6px 12px}
.sx-body{display:flex;flex-direction:column;flex:1 1 auto;padding:10px 12px 12px}
.sx-kat{font-size:.66rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--sx-mut)}
.sx-title{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-top:2px;
  font-size:.86rem;font-weight:700;line-height:1.3;color:var(--sx-ink)}
.sx-title:hover{color:var(--sx-p)}
.sx-foot{display:flex;align-items:center;justify-content:space-between;gap:6px;margin-top:auto;padding-top:8px}
.sx-price{font-weight:800;font-size:.95rem;color:var(--sx-ink)}
.sx-aktionspreis{color:#d32f2f}
.sx-altpreis{color:var(--sx-mut);font-weight:500;font-size:.78em;margin-left:2px}
.sx-price small{font-weight:500;color:var(--sx-mut);font-size:.7rem;margin-right:2px}
.sx-go{flex:0 0 auto;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  background:var(--sx-soft);color:var(--sx-p);transition:.15s}
.sx-card:hover .sx-go{background:var(--sx-p);color:#fff}
.sx-empty{display:none;text-align:center;padding:48px 16px;color:var(--sx-mut);border:1px dashed var(--sx-line);border-radius:var(--sx-r)}
.sx-empty i{font-size:2rem;display:block;margin-bottom:6px}

/* Detailseite */
.sx-panel{background:var(--sx-card);border:1px solid var(--sx-line);border-radius:18px;padding:22px}
.sx-gal-main{position:relative;aspect-ratio:1/1;background:#fff;border:1px solid var(--sx-line);border-radius:14px;overflow:hidden}
.sx-gal-main img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity .25s}
.sx-gal-main img.on{opacity:1}
.sx-gal-main .sx-nav{width:36px;height:36px;font-size:.95rem;opacity:.95}
.sx-thumbs{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.sx-thumb{width:62px;height:62px;border-radius:10px;overflow:hidden;border:2px solid transparent;padding:0;background:#fff;cursor:pointer;opacity:.75;transition:.15s}
.sx-thumb img{width:100%;height:100%;object-fit:cover}
.sx-thumb.on{border-color:var(--sx-p);opacity:1}
.sx-d-title{font-size:1.6rem;font-weight:800;letter-spacing:-.01em;margin:.2rem 0 .5rem}
.sx-d-price{font-size:1.7rem;font-weight:800;color:var(--sx-p)}
.sx-d-price small{font-size:.85rem;color:var(--sx-mut);font-weight:500}
.sx-label{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--sx-mut);margin:18px 0 8px}
.sx-sizes{display:flex;flex-wrap:wrap;gap:8px}
.sx-size{min-width:52px;text-align:center;border:1.5px solid var(--sx-line);border-radius:10px;padding:7px 12px;font-weight:600;font-size:.88rem;
  cursor:pointer;transition:.15s;background:var(--sx-card);color:var(--sx-ink);margin:0}
.sx-size:hover{border-color:var(--sx-p)}
.btn-check:checked + .sx-size{background:var(--sx-p);border-color:var(--sx-p);color:#fff}
.sx-qty{display:inline-flex;align-items:center;border:1.5px solid var(--sx-line);border-radius:12px;overflow:hidden;background:var(--sx-card)}
.sx-qty button{border:none;background:transparent;width:38px;height:44px;font-size:1.1rem;color:var(--sx-ink);cursor:pointer}
.sx-qty button:hover{background:var(--sx-soft)}
.sx-qty input{width:46px;border:none;text-align:center;font-weight:700;background:transparent;color:var(--sx-ink);-moz-appearance:textfield}
.sx-qty input::-webkit-inner-spin-button{-webkit-appearance:none}
.sx-qty.klein{border-radius:10px}
.sx-qty.klein button{width:32px;height:34px;font-size:1rem}
.sx-qty.klein input{width:40px}
/* Mengentabelle je Größe */
.sx-mtab{border:1px solid var(--sx-line);border-radius:12px;overflow:hidden;max-width:520px}
.sx-mtab-kopf,.sx-mtab-row{display:grid;grid-template-columns:minmax(0,1fr) auto 90px;align-items:center;column-gap:14px;padding:8px 14px}
.sx-mtab.mit-preis .sx-mtab-kopf,.sx-mtab.mit-preis .sx-mtab-row{grid-template-columns:minmax(0,1fr) 84px auto 90px}
.sx-mtab-kopf{background:rgba(68,113,163,.05);font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--sx-mut)}
.sx-mtab-row{border-top:1px solid var(--sx-line);transition:background .15s}
.sx-mtab-row.hat{background:rgba(68,113,163,.07)}
.sx-mtab-row .gr{font-weight:700}
.sx-mtab-row .pr{text-align:right;color:var(--sx-sub);font-size:.88rem}
.sx-mtab-row .su{text-align:right;font-weight:700;color:var(--sx-mut);font-size:.9rem}
.sx-mtab-row.hat .su{color:var(--sx-ink)}
.sx-mtab-fuss{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-top:1px solid var(--sx-line);
  background:rgba(68,113,163,.05);font-size:.88rem;color:var(--sx-sub)}
.sx-mtab-fuss b{color:var(--sx-ink)}
/* Veredelung */
.sx-ved{border:1px solid var(--sx-line);border-radius:12px;overflow:hidden;max-width:640px}
.sx-ved-gruppe{padding:12px 14px;border-bottom:1px solid var(--sx-line)}
.sx-ved-art{display:flex;align-items:center;gap:7px;font-weight:800;font-size:.86rem;margin-bottom:8px}
.sx-ved-art i{color:var(--sx-p)}
.sx-ved-liste{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px}
.sx-ved-opt{border:1.5px solid var(--sx-line);border-radius:10px;background:var(--sx-card);transition:border-color .15s,background .15s}
.sx-ved-opt.an{border-color:var(--sx-p);background:rgba(68,113,163,.05)}
.sx-ved-kopf{display:flex;align-items:center;gap:8px;padding:8px 10px;margin:0;cursor:pointer}
.sx-ved-kopf .form-check-input{margin:0;flex:0 0 auto}
.sx-ved-kopf .nm{flex:1;font-weight:600;font-size:.88rem}
.sx-ved-kopf .pr{font-size:.78rem;color:var(--sx-mut);white-space:nowrap}
.sx-ved-opt.an .pr{color:var(--sx-p);font-weight:700}
.sx-ved-detail{display:none;padding:0 10px 10px}
.sx-ved-opt.an .sx-ved-detail{display:block}
.sx-ved-fuss{display:flex;flex-wrap:wrap;justify-content:space-between;gap:6px 14px;padding:10px 14px;background:rgba(68,113,163,.05);font-size:.78rem;color:var(--sx-mut)}
.sx-ved-fuss b{color:var(--sx-ink)}
/* Meine Bestellungen */
.sx-orders{display:flex;flex-direction:column;gap:16px}
.sx-orders{gap:10px}
.sx-order{background:var(--sx-card);border:1px solid var(--sx-line);border-radius:16px;padding:0;transition:box-shadow .15s}
.sx-order[open]{box-shadow:0 8px 22px rgba(45,74,106,.08)}
.sx-order-kopf{display:flex;align-items:center;gap:12px;padding:14px 20px;cursor:pointer;list-style:none;user-select:none;border-radius:16px}
.sx-order-kopf::-webkit-details-marker{display:none}
.sx-order-kopf:hover{background:rgba(68,113,163,.04)}
.sx-order-pfeil{color:var(--sx-mut);transition:transform .15s}
.sx-order[open] > .sx-order-kopf .sx-order-pfeil{transform:rotate(90deg)}
.sx-order[open] > .sx-order-kopf{border-bottom:1px solid var(--sx-line);border-radius:16px 16px 0 0}
.sx-order-inhalt{padding:16px 22px 18px}
.sx-order-kopf .nr{font-weight:800;font-size:1rem}
.sx-order-kopf .dt{font-size:.82rem;color:var(--sx-mut)}
.sx-status{border-radius:999px;padding:4px 12px;font-size:.75rem;font-weight:800;white-space:nowrap;background:var(--sx-soft);color:var(--sx-p)}
.sx-status.st-neu{background:rgba(209,154,62,.15);color:#a0721f}
.sx-status.st-versendet{background:rgba(68,113,163,.15);color:var(--sx-p)}
.sx-status.st-geliefert,.sx-status.st-verrechnet{background:rgba(91,138,114,.15);color:var(--c-success,#5b8a72)}
.sx-status.st-storniert{background:rgba(0,0,0,.06);color:var(--sx-mut)}
.sx-fortschritt{display:flex;list-style:none;margin:4px 0 14px;padding:0;counter-reset:s}
.sx-fortschritt li{position:relative;flex:1;display:flex;flex-direction:column;align-items:center;text-align:center;min-width:0}
.sx-fortschritt li::before{content:"";position:absolute;top:13px;left:-50%;right:50%;height:3px;background:var(--sx-line);z-index:0}
.sx-fortschritt li:first-child::before{display:none}
.sx-fortschritt li.erledigt::before,.sx-fortschritt li.aktuell::before{background:var(--sx-p)}
.sx-fortschritt .punkt{position:relative;z-index:1;width:28px;height:28px;border-radius:50%;background:var(--sx-card);border:3px solid var(--sx-line);
  display:flex;align-items:center;justify-content:center;font-size:.8rem;color:#fff}
.sx-fortschritt li.erledigt .punkt{background:var(--sx-p);border-color:var(--sx-p)}
.sx-fortschritt li.aktuell .punkt{background:var(--sx-p);border-color:var(--sx-p);box-shadow:0 0 0 5px rgba(68,113,163,.18)}
.sx-fortschritt .txt{margin-top:6px;font-size:.76rem;font-weight:600;color:var(--sx-mut);line-height:1.2}
.sx-fortschritt li.erledigt .txt,.sx-fortschritt li.aktuell .txt{color:var(--sx-ink)}
.sx-fortschritt li.aktuell .txt{font-weight:800}
.sx-fortschritt .zeit{font-size:.7rem;color:var(--sx-mut);min-height:1em}
.sx-storno{background:rgba(0,0,0,.04);border-radius:10px;padding:10px 14px;color:var(--sx-sub);margin-bottom:12px}
.sx-track{display:flex;align-items:center;gap:12px;flex-wrap:wrap;background:rgba(68,113,163,.07);border:1px solid rgba(68,113,163,.2);border-radius:12px;padding:12px 14px;margin-bottom:10px}
.sx-track > i{font-size:1.4rem;color:var(--sx-p)}
.sx-track .sx-btn{padding:8px 14px;font-size:.85rem}
.sx-msg{display:flex;gap:10px;background:rgba(209,154,62,.1);border-radius:12px;padding:10px 14px;margin-bottom:10px;font-size:.88rem}
.sx-msg > i{color:#a0721f;margin-top:2px}
.sx-order-det summary{cursor:pointer;font-size:.85rem;font-weight:600;color:var(--sx-p);padding:4px 0}
.sx-order-det[open] summary{margin-bottom:8px}
@media (max-width:575px){.sx-fortschritt .txt{font-size:.66rem}.sx-order{padding:14px}}
/* Veredelung im Warenkorb / an der Kasse */
.c-ved{list-style:none;margin:8px 0 0;padding:0;font-size:.78rem;color:var(--sx-sub)}
.c-ved li{display:flex;gap:6px;align-items:baseline;padding:1px 0}
.c-ved li i{color:var(--sx-p);font-size:.72rem}
.c-ved .dt{color:var(--sx-mut)}
.sx-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:none;border-radius:12px;padding:12px 22px;
  font-weight:700;background:var(--sx-p);color:#fff;transition:.15s;box-shadow:0 6px 16px rgba(68,113,163,.3)}
.sx-btn:hover{background:var(--sx-dark);color:#fff}
.sx-btn:disabled{background:var(--sx-mut);box-shadow:none}
.sx-btn.ghost{background:transparent;color:var(--sx-sub);border:1.5px solid var(--sx-line);box-shadow:none}
.sx-btn.ghost:hover{border-color:var(--sx-p);color:var(--sx-p)}
.sx-facts{display:flex;flex-wrap:wrap;gap:8px;margin-top:18px}
.sx-fact{display:inline-flex;align-items:center;gap:6px;font-size:.8rem;color:var(--sx-sub);background:var(--sx-soft);border-radius:999px;padding:5px 11px}
.sx-desc{white-space:pre-line;color:var(--sx-sub);line-height:1.65;margin-top:18px}

/* Warenkorb & Kasse */
.sx-steps{display:flex;gap:6px;align-items:center;font-size:.8rem;font-weight:600;color:rgba(255,255,255,.7);margin-top:10px}
.sx-steps .on{color:#fff}.sx-steps i{font-size:.65rem;opacity:.6}
/* Warenkorb: Spalten Artikel · Preis · Menge · Summe */
.sx-cartbox{background:var(--sx-card);border:1px solid var(--sx-line);border-radius:18px;overflow:hidden}
.sx-cart-kopf{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:18px 24px;border-bottom:1px solid var(--sx-line)}
.sx-cart-kopf h5{margin:0;font-weight:800;font-size:1.05rem}
.sx-cart-kopf h5 i{color:var(--sx-p);margin-right:4px}
.sx-link-btn{border:none;background:transparent;color:var(--sx-mut);font-size:.82rem;padding:4px 6px;border-radius:8px}
.sx-link-btn:hover{color:#e05656;background:rgba(224,86,86,.08)}
.sx-cart-cols,.sx-cart-row{display:grid;grid-template-columns:96px minmax(0,1fr) 100px 132px 100px 34px;align-items:center;column-gap:18px;padding-left:24px;padding-right:24px}
.sx-cart-cols{padding-top:10px;padding-bottom:10px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:var(--sx-mut);
  background:rgba(68,113,163,.04);border-bottom:1px solid var(--sx-line)}
.sx-cart-cols span:first-child{grid-column:1 / span 2}
.sx-cart-row{padding-top:20px;padding-bottom:20px;border-bottom:1px solid var(--sx-line);transition:background .15s}
.sx-cart-row:last-child{border-bottom:none}
.sx-cart-row:hover{background:rgba(68,113,163,.025)}
.c-bild{display:flex;align-items:center;justify-content:center;width:96px;height:96px;border-radius:12px;overflow:hidden;background:#fff;
  border:1px solid var(--sx-line);color:#c5ccd4;font-size:1.6rem}
.c-bild img{width:100%;height:100%;object-fit:cover;transition:transform .3s}
.c-bild:hover img{transform:scale(1.05)}
.c-info{min-width:0}
.c-name{display:block;font-weight:700;font-size:.98rem;line-height:1.3;color:var(--sx-ink)}
.c-name:hover{color:var(--sx-p)}
.c-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.c-tag{font-size:.72rem;font-weight:600;background:var(--sx-soft);color:var(--sx-p);border-radius:999px;padding:3px 10px}
.c-tag.hell{background:transparent;border:1px solid var(--sx-line);color:var(--sx-mut)}
.c-einzel{display:none;font-size:.8rem;color:var(--sx-mut);margin-top:6px}
.c-preis{color:var(--sx-sub);font-size:.92rem}
.c-menge{justify-self:center;display:inline-flex;align-items:center;margin:0;border:1.5px solid var(--sx-line);border-radius:12px;overflow:hidden;background:var(--sx-card)}
.c-menge button{border:none;background:transparent;width:36px;height:38px;font-size:1.05rem;color:var(--sx-ink);display:flex;align-items:center;justify-content:center}
.c-menge button:hover{background:var(--sx-soft);color:var(--sx-p)}
.c-menge span{min-width:34px;text-align:center;font-weight:700}
.c-summe{text-align:right;font-weight:800;font-size:1rem;white-space:nowrap}
.c-weg{margin:0;justify-self:end}
.c-weg button{border:none;background:transparent;color:var(--sx-mut);width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center}
.c-weg button:hover{background:rgba(224,86,86,.1);color:#e05656}
.sx-zurueck{display:inline-flex;align-items:center;gap:6px;margin-top:14px;color:var(--sx-sub);font-weight:600;font-size:.88rem}
.sx-zurueck:hover{color:var(--sx-p)}
.sx-frei{margin:8px 0 4px;font-size:.8rem;color:var(--sx-sub)}
.sx-frei .bar{height:6px;border-radius:999px;background:var(--sx-line);overflow:hidden;margin-top:7px}
.sx-frei .bar span{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,var(--sx-p),var(--sx-p2))}
.sx-sum-info{display:flex;flex-direction:column;gap:7px;margin-top:18px;padding-top:14px;border-top:1px solid var(--sx-line);font-size:.8rem;color:var(--sx-mut)}
.sx-sum-info i{color:var(--sx-p);margin-right:4px}
@media (max-width:767px){
  .sx-cart-cols{display:none}
  .sx-cart-kopf{padding:14px 16px}
  .sx-cart-row{grid-template-columns:76px minmax(0,1fr) auto;grid-template-areas:"bild info weg" "bild menge summe";row-gap:12px;column-gap:14px;padding:16px}
  .c-bild{grid-area:bild;width:76px;height:76px;align-self:start}
  .c-info{grid-area:info}.c-weg{grid-area:weg;align-self:start}
  .c-preis{display:none}.c-einzel{display:block}
  .c-menge{grid-area:menge;justify-self:start}
  .c-summe{grid-area:summe;align-self:center}
}
.sx-sum{position:sticky;top:80px}
.sx-sum .row-l{display:flex;justify-content:space-between;font-size:.9rem;margin-bottom:6px;color:var(--sx-sub)}
.sx-sum .tot{display:flex;justify-content:space-between;font-size:1.15rem;font-weight:800;border-top:1px solid var(--sx-line);padding-top:12px;margin-top:10px}
.sx .form-control,.sx .form-select{border-radius:10px}
.sx-done{text-align:center;padding:48px 20px}
.sx-done .ic{width:76px;height:76px;border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;
  background:rgba(91,138,114,.14);color:var(--c-success,#5b8a72);font-size:2.2rem}
</style>

<div class="sx">

<?php /* ---------------------------------------------------------------- Kopfbereich */ ?>
<?php if ($ansicht === 'liste'): ?>
<div class="sx-headwrap" id="sx-headwrap">
<div class="sx-hero">
    <nav class="sx-topnav" aria-label="Shop-Navigation">
        <a class="sx-brand" href="shop.php"><span class="ic"><i class="bi bi-music-note-beamed"></i></span><?php echo $h($titel); ?></a>
        <div class="sx-menu">
            <?php if ($kats): ?><button type="button" class="sx-mi" id="sx-megabtn" aria-expanded="false" aria-controls="sx-mega">Shop <i class="bi bi-chevron-down"></i></button><?php endif; ?>
            <?php if ($angebote): ?><a class="sx-mi" href="#sx-angebote" data-megazu>Angebote</a><?php endif; ?>
            <a class="sx-mi" href="shop.php?ansicht=bestellungen">Bestellungen</a>
            <a class="sx-mi sx-mi-cart" href="shop.php?ansicht=warenkorb" aria-label="Warenkorb"><i class="bi bi-bag"></i><?php if ($wkAnzahl): ?><span class="n"><?php echo (int)$wkAnzahl; ?></span><?php endif; ?></a>
        </div>
    </nav>
    <div class="sx-hero-body">
        <div class="sx-eyebrow"><i class="bi bi-stars"></i> Vereinsbekleidung &amp; Zubehör</div>
        <h1><?php echo $h($titel); ?></h1>
        <p><?php echo $h($einleitung !== '' ? $einleitung : 'Einheitliche Kleidung und Zubehör für euren Musikverein – einfach auswählen, Größe wählen und bequem per Rechnung bestellen.'); ?></p>
        <div class="sx-search"><i class="bi bi-search"></i><input type="search" id="sx-suche" placeholder="Artikel suchen …" autocomplete="off"></div>
    </div>
</div>
<?php if ($kats): ?>
<div class="sx-mega" id="sx-mega">
    <div class="sx-mega-grid">
        <?php foreach (array_merge($katHaupt, !empty($anzahlJeKat[0]) ? [0] : []) as $hid): $n = $hid ? $katGesamt($hid) : (int)($anzahlJeKat[0] ?? 0); if (!$n) continue;
            $subs = $hid ? array_filter($katKinder[$hid] ?? [], fn($c) => !empty($anzahlJeKat[$c])) : []; ?>
        <div class="sx-mcard">
            <h3><a data-filter="<?php echo $hid; ?>"><?php echo $h($katName($hid)); ?></a></h3>
            <div class="sx-orn" aria-hidden="true"><i class="bi bi-music-note-beamed"></i></div>
            <ul>
                <?php foreach ($subs as $cid): ?>
                <li><a data-filter="<?php echo $cid; ?>"><i class="bi bi-chevron-right"></i><span><?php echo $h($katName($cid)); ?></span><em><?php echo (int)$anzahlJeKat[$cid]; ?></em></a></li>
                <?php endforeach; ?>
                <li><a data-filter="<?php echo $hid; ?>" class="alle"><i class="bi bi-chevron-right"></i><span><?php echo $subs ? 'Alles aus ' . $h($katName($hid)) : 'Alle Artikel anzeigen'; ?></span><em><?php echo $n; ?></em></a></li>
            </ul>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="sx-mega-fuss">
        <a data-filter="alle"><i class="bi bi-grid"></i> Gesamtes Sortiment anzeigen</a>
        <button type="button" data-megazu><i class="bi bi-x-lg"></i> Schließen</button>
    </div>
</div>
<?php endif; ?>
</div>
<div class="sx-trust">
    <div><i class="bi bi-rulers"></i><p class="m-0"><b>Größen für alle</b><span>einheitlich im ganzen Verein</span></p></div>
    <div><i class="bi bi-arrow-repeat"></i><p class="m-0"><b>Nachlieferbar</b><span>auch bei Zuwachs über Jahre</span></p></div>
    <div><i class="bi bi-receipt"></i><p class="m-0"><b>Kauf auf Rechnung</b><span>keine Online-Zahlung nötig</span></p></div>
</div>
<?php if ($angebote): ?>
<section class="sx-offers" id="sx-angebote">
    <h2>Unsere Angebote</h2>
    <div class="lead-s">Mehr als Kleidung von der Stange – sprecht uns an.</div>
    <div class="sx-off-grid">
        <?php foreach ($angebote as $g): $gk = (int)($g['kategorie_id'] ?? 0); ?>
        <article class="sx-off">
            <div class="bild"><?php if (!empty($g['bild'])): ?><img src="<?php echo $h($g['bild']); ?>" alt="<?php echo $h($g['titel']); ?>" loading="lazy"><?php else: ?><span class="ph"><i class="bi bi-image"></i></span><?php endif; ?></div>
            <h3><?php echo $h($g['titel']); ?></h3>
            <?php if (!empty($g['beschreibung'])): ?><p><?php echo $h($g['beschreibung']); ?></p><?php endif; ?>
            <?php if (!empty($g['punkte'])): ?>
            <ul>
                <?php foreach ($g['punkte'] as $pk): ?>
                <li><i class="bi bi-chevron-right"></i><span><?php if (!empty($pk['url']) && preg_match('#^https?://#i', $pk['url'])): ?><a href="<?php echo $h($pk['url']); ?>" target="_blank" rel="noopener"><?php echo $h($pk['text']); ?></a><?php else: ?><?php echo $h($pk['text']); ?><?php endif; ?></span></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php if ($gk && $katGesamt($gk)): ?><div class="mehr"><button type="button" data-filter="<?php echo $gk; ?>">Zu den Artikeln <i class="bi bi-arrow-right"></i></button></div><?php endif; ?>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php else: ?>
<div class="sx-hero compact">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="sx-crumb"><a href="shop.php"><i class="bi bi-shop"></i> <?php echo $h($titel); ?></a>
                <?php if ($ansicht === 'artikel' && ($ak = ShopKatalog::artikel($katalog, (int)($_GET['artikel'] ?? 0)))): $akk = (int)($ak['kategorie_id'] ?? 0); ?>
                <?php if (isset($katParent[$akk])): ?> <i class="bi bi-chevron-right small"></i> <?php echo $h($katName($katParent[$akk])); ?><?php endif; ?>
                <i class="bi bi-chevron-right small"></i> <?php echo $h($katName($akk)); ?><?php endif; ?></div>
            <h1><?php echo ['artikel' => 'Artikel', 'warenkorb' => 'Warenkorb', 'kasse' => 'Bestellung abschließen', 'bestellungen' => 'Meine Bestellungen'][$ansicht] ?? 'Vielen Dank!'; ?></h1>
            <?php if (in_array($ansicht, ['warenkorb', 'kasse', 'fertig'], true)): ?>
            <div class="sx-steps"><span class="<?php echo $ansicht === 'warenkorb' ? 'on' : ''; ?>">1 Warenkorb</span><i class="bi bi-chevron-right"></i>
                <span class="<?php echo $ansicht === 'kasse' ? 'on' : ''; ?>">2 Daten</span><i class="bi bi-chevron-right"></i>
                <span class="<?php echo $ansicht === 'fertig' ? 'on' : ''; ?>">3 Fertig</span></div>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <a href="shop.php" class="sx-cart"><i class="bi bi-grid"></i> Katalog</a>
            <?php if ($ansicht !== 'bestellungen'): ?><a href="shop.php?ansicht=bestellungen" class="sx-cart" title="Meine Bestellungen"><i class="bi bi-box-seam"></i> Bestellungen</a><?php endif; ?>
            <?php if ($ansicht !== 'warenkorb'): ?><a href="shop.php?ansicht=warenkorb" class="sx-cart"><i class="bi bi-bag"></i><?php if ($wkAnzahl): ?> <span class="n"><?php echo (int)$wkAnzahl; ?></span><?php endif; ?></a><?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($ladeFehler): ?>
<div class="alert alert-warning alert-permanent"><i class="bi bi-exclamation-triangle"></i> <?php echo $h($ladeFehler); ?></div>
<?php endif; ?>

<?php /* ---------------------------------------------------------------- Bestellung fertig */ ?>
<?php if ($ansicht === 'fertig'): ?>
    <div class="sx-panel sx-done">
        <div class="ic"><i class="bi bi-check-lg"></i></div>
        <h2 class="fw-bold">Bestellung eingegangen</h2>
        <p class="text-muted mb-1">Deine Bestellnummer</p>
        <div class="fs-4 fw-bold mb-3" style="letter-spacing:.04em"><?php echo $h($_GET['nr'] ?? ''); ?></div>
        <p class="text-muted" style="max-width:520px;margin:0 auto">Die Bestellung wurde an den Shop-Betreiber übermittelt. Die Abrechnung erfolgt per Rechnung. Den Stand der Bestellung siehst du jederzeit unter „Bestellungen“.</p>
        <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
            <a href="shop.php?ansicht=bestellungen" class="sx-btn"><i class="bi bi-box-seam"></i> Bestellstatus ansehen</a>
            <a href="shop.php" class="sx-btn ghost"><i class="bi bi-grid"></i> Weiter einkaufen</a>
        </div>
    </div>

<?php /* ---------------------------------------------------------------- Meine Bestellungen (Status vom Katalog-Server) */ ?>
<?php elseif ($ansicht === 'bestellungen'):
    $meine = []; $meineFehler = null;
    try { $meine = ShopKatalog::meineBestellungen(); } catch (\Throwable $e) { $meineFehler = $e->getMessage(); }
    $schritte = Shop::STATUS_SCHRITTE; ?>
    <?php if ($meineFehler): ?>
    <div class="alert alert-warning alert-permanent"><i class="bi bi-exclamation-triangle"></i> <?php echo $h($meineFehler); ?></div>
    <?php elseif (!$meine): ?>
    <div class="sx-panel sx-done">
        <div class="ic" style="background:var(--sx-soft);color:var(--sx-p)"><i class="bi bi-box-seam"></i></div>
        <h4 class="fw-bold">Noch keine Bestellungen</h4>
        <p class="text-muted">Sobald ihr im Shop bestellt, seht ihr hier, wie weit die Bestellung ist.</p>
        <a href="shop.php" class="sx-btn"><i class="bi bi-grid"></i> Zum Katalog</a>
    </div>
    <?php else: ?>
    <div class="sx-orders">
        <?php foreach ($meine as $oi => $o):
            $st = (string)$o['status'];
            $idx = $st === 'verrechnet' ? count($schritte) - 1 : array_search($st, $schritte, true);
            $stueck = array_sum(array_map(fn($p) => (int)$p['menge'], $o['positionen'] ?? []));
            $link = (string)($o['tracking_url'] ?? '');
            $linkOk = $link !== '' && preg_match('#^https?://#i', $link); ?>
        <details class="sx-order" <?php echo $oi === 0 ? 'open' : ''; ?>>
            <summary class="sx-order-kopf">
                <i class="bi bi-chevron-right sx-order-pfeil"></i>
                <div class="flex-grow-1">
                    <div class="nr">Bestellung <?php echo $h($o['bestellnummer']); ?></div>
                    <div class="dt">vom <?php echo date('d.m.Y', strtotime($o['erstellt_am'])); ?> · <?php echo (int)$stueck; ?> Stück · <?php echo $h($euro($o['summe'])); ?></div>
                </div>
                <?php if ($linkOk): ?><i class="bi bi-truck text-primary" title="Sendung unterwegs"></i><?php endif; ?>
                <span class="sx-status st-<?php echo $h($st); ?>"><?php echo $h($o['status_name']); ?></span>
            </summary>
            <div class="sx-order-inhalt">

            <?php if ($st === 'storniert'): ?>
            <div class="sx-storno"><i class="bi bi-x-circle"></i> Diese Bestellung wurde storniert<?php echo !empty($o['status_geaendert_am']) ? ' (' . date('d.m.Y', strtotime($o['status_geaendert_am'])) . ')' : ''; ?>.</div>
            <?php else: ?>
            <ol class="sx-fortschritt">
                <?php foreach ($schritte as $i => $s): $zeit = $o['verlauf'][$s] ?? null; ?>
                <li class="<?php echo $idx !== false && $i < $idx ? 'erledigt' : ''; ?> <?php echo $idx === $i ? 'aktuell' : ''; ?>">
                    <span class="punkt"><?php echo $idx !== false && $i <= $idx ? '<i class="bi bi-check-lg"></i>' : ''; ?></span>
                    <span class="txt"><?php echo $h(Shop::STATUS[$s]); ?></span>
                    <span class="zeit"><?php echo $zeit && $idx !== false && $i <= $idx ? date('d.m.', strtotime($zeit)) : ''; ?></span>
                </li>
                <?php endforeach; ?>
            </ol>
            <?php if ($st === 'verrechnet'): ?><div class="small text-muted mb-2"><i class="bi bi-receipt"></i> Die Bestellung ist abgeschlossen und verrechnet.</div><?php endif; ?>
            <?php endif; ?>

            <?php if ($linkOk || !empty($o['sendungsnummer'])): ?>
            <div class="sx-track">
                <i class="bi bi-truck"></i>
                <div class="flex-grow-1"><b>Paket unterwegs</b><?php if (!empty($o['sendungsnummer'])): ?><br><span class="small text-muted">Sendungsnummer <?php echo $h($o['sendungsnummer']); ?></span><?php endif; ?></div>
                <?php if ($linkOk): ?><a class="sx-btn" href="<?php echo $h($link); ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-geo-alt"></i> Sendung verfolgen</a><?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($o['nachricht'])): ?>
            <div class="sx-msg"><i class="bi bi-chat-left-text"></i><div><b>Nachricht vom Shop</b><br><?php echo nl2br($h($o['nachricht'])); ?></div></div>
            <?php endif; ?>

            <details class="sx-order-det">
                <summary>Bestellte Artikel anzeigen</summary>
                <table class="table table-sm align-middle mb-2">
                    <thead><tr><th>Artikel</th><th>Größe</th><th class="text-end">Menge</th><th class="text-end">Summe</th></tr></thead>
                    <tbody>
                    <?php foreach ($o['positionen'] as $p): ?>
                    <tr><td><?php echo $h($p['artikel_name']); ?>
                            <?php foreach ($p['veredelung'] ?? [] as $v): ?><br><small class="text-muted"><i class="bi bi-stars"></i> <?php echo $h($v['bezeichnung']); ?><?php echo !empty($v['original']) ? ' · ' . $h($v['original']) : ''; ?></small><?php endforeach; ?></td>
                        <td><?php echo $h($p['variante'] ?: '–'); ?></td>
                        <td class="text-end"><?php echo (int)$p['menge']; ?></td>
                        <td class="text-end text-nowrap"><?php echo $h($euro($p['einzelpreis'] * $p['menge'])); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <?php if ((float)$o['versandkosten'] > 0): ?><tr><td colspan="3" class="text-end text-muted">Versand</td><td class="text-end"><?php echo $h($euro($o['versandkosten'])); ?></td></tr><?php endif; ?>
                    <tr><th colspan="3" class="text-end">Gesamt</th><th class="text-end text-nowrap"><?php echo $h($euro($o['summe'])); ?></th></tr>
                    </tfoot>
                </table>
                <div class="small text-muted"><?php if (!empty($o['besteller_name'])): ?>Bestellt von <?php echo $h($o['besteller_name']); ?> · <?php endif; ?>Lieferung an: <?php echo $h(str_replace(["\r\n", "\n"], ', ', (string)$o['lieferadresse'])); ?></div>
            </details>
            </div>
        </details>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php /* ---------------------------------------------------------------- Artikel-Detail */ ?>
<?php elseif ($ansicht === 'artikel' && ($a = ShopKatalog::artikel($katalog, (int)($_GET['artikel'] ?? 0)))):
    $soldout = !empty($a['ausverkauft']); $bilder = $a['bilder'] ?? []; ?>
    <div class="sx-panel">
        <div class="row g-4 g-lg-5">
            <div class="col-md-6 col-lg-5">
                <div class="sx-gal-main" id="sx-gal">
                    <div class="sx-badges">
                        <?php if (!empty($a['aktion'])): ?><span class="sx-badge"><?php echo $h($a['aktion_text'] ?: 'Aktion'); ?></span><?php endif; ?>
                    </div>
                    <?php if ($bilder): foreach ($bilder as $bi => $b): ?>
                    <img src="<?php echo $h($b); ?>" class="<?php echo $bi === 0 ? 'on' : ''; ?>" alt="<?php echo $h($a['name']); ?>">
                    <?php endforeach; else: ?>
                    <span class="ph d-flex h-100 align-items-center justify-content-center text-muted"><i class="bi bi-image" style="font-size:44px"></i></span>
                    <?php endif; ?>
                    <?php if (count($bilder) > 1): ?>
                    <button type="button" class="sx-nav prev" aria-label="Vorheriges Bild"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="sx-nav next" aria-label="Nächstes Bild"><i class="bi bi-chevron-right"></i></button>
                    <?php endif; ?>
                    <?php if ($soldout): ?><div class="sx-sold"><span>Ausverkauft</span></div><?php endif; ?>
                </div>
                <?php if (count($bilder) > 1): ?>
                <div class="sx-thumbs">
                    <?php foreach ($bilder as $bi => $b): ?><button type="button" class="sx-thumb <?php echo $bi === 0 ? 'on' : ''; ?>" aria-label="Bild <?php echo $bi + 1; ?>"><img src="<?php echo $h($b); ?>" alt=""></button><?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6 col-lg-7">
                <div class="sx-kat"><?php echo $h($katName($a['kategorie_id'])); ?><?php if ($a['artikelnummer']): ?> · Art.-Nr. <?php echo $h($a['artikelnummer']); ?><?php endif; ?></div>
                <h2 class="sx-d-title"><?php echo $h($a['name']); ?></h2>
                <div class="sx-d-price" id="sx-preis"><?php if (!empty($a['varianten']) && count(array_unique(array_map(fn($v) => (float)$v['preis'], $a['varianten']))) > 1): ?><small>ab </small><?php endif; ?><?php if ($altPreis($a) !== null): ?><span class="sx-aktionspreis"><?php echo $h($euro($preisAbFn($a))); ?></span> <s class="sx-altpreis"><?php echo $h($euro($altPreis($a))); ?></s><?php else: ?><?php echo $h($euro($preisAbFn($a))); ?><?php endif; ?></div>
                <div class="small text-muted"><?php echo $vkSatz > 0 ? 'zzgl. Versand · ' : ''; ?>Zahlung per Rechnung</div>

                <?php if ($soldout): ?>
                <div class="alert alert-secondary alert-permanent d-flex align-items-center gap-2 mt-4 mb-0"><i class="bi bi-slash-circle"></i> Dieser Artikel ist derzeit ausverkauft.</div>
                <?php else: ?>
                <form method="post" id="sx-bestellform" enctype="multipart/form-data">
                    <input type="hidden" name="aktion" value="hinzufuegen">
                    <input type="hidden" name="artikel_id" value="<?php echo (int)$a['id']; ?>">
                    <?php if (!empty($a['varianten'])):
                        $preisSpalte = count(array_unique(array_map(fn($v) => (float)$v['preis'], $a['varianten']))) > 1; ?>
                    <div class="sx-label">Mengen je Größe eintragen</div>
                    <div class="sx-mtab <?php echo $preisSpalte ? 'mit-preis' : ''; ?>">
                        <div class="sx-mtab-kopf"><span>Größe</span><?php if ($preisSpalte): ?><span class="text-end">Preis</span><?php endif; ?><span class="text-center">Menge</span><span class="text-end">Summe</span></div>
                        <?php foreach ($a['varianten'] as $v): ?>
                        <div class="sx-mtab-row" data-preis="<?php echo (float)$v['preis']; ?>">
                            <span class="gr"><?php echo $h($v['bezeichnung']); ?></span>
                            <?php if ($preisSpalte): ?><span class="pr"><?php if ((float)($v['preis_regulaer'] ?? $v['preis']) > (float)$v['preis'] + 0.001): ?><s class="sx-altpreis d-block"><?php echo $h($euro($v['preis_regulaer'])); ?></s><span class="sx-aktionspreis"><?php echo $h($euro($v['preis'])); ?></span><?php else: ?><?php echo $h($euro($v['preis'])); ?><?php endif; ?></span><?php endif; ?>
                            <div class="sx-qty klein"><button type="button" data-q="-1" aria-label="weniger">−</button><input type="number" name="mengen[<?php echo (int)$v['id']; ?>]" value="0" min="0" max="99" inputmode="numeric" aria-label="Menge Größe <?php echo $h($v['bezeichnung']); ?>"><button type="button" data-q="1" aria-label="mehr">+</button></div>
                            <span class="su">–</span>
                        </div>
                        <?php endforeach; ?>
                        <div class="sx-mtab-fuss"><span><b id="sx-stk">0</b> Stück</span><span>Zusammen <b id="sx-gesamt"><?php echo $h($euro(0)); ?></b></span></div>
                    </div>
                    <?php else: ?>
                    <div class="sx-label">Menge</div>
                    <div class="sx-qty"><button type="button" data-q="-1" aria-label="weniger">−</button><input type="number" name="menge" value="1" min="1" max="99" aria-label="Menge"><button type="button" data-q="1" aria-label="mehr">+</button></div>
                    <?php endif; ?>

                    <?php // ---- Veredelung: Stickerei/Druck je Position, mehrere möglich, je Position eigene Vorlage
                    $vedListe = !empty($a['veredelung']) ? ($katalog['veredelungen'] ?? []) : [];
                    if ($vedListe):
                        $vedNachArt = [];
                        foreach ($vedListe as $v) $vedNachArt[$v['art']][] = $v; ?>
                    <div class="sx-label">Veredelung <span class="text-lowercase fw-normal">– optional, mehrere möglich</span></div>
                    <div class="sx-ved">
                        <?php foreach ($vedNachArt as $art => $liste): ?>
                        <div class="sx-ved-gruppe">
                            <div class="sx-ved-art"><i class="bi bi-<?php echo $art === 'druck' ? 'printer' : 'scissors'; ?>"></i> <?php echo $h($liste[0]['art_name']); ?></div>
                            <div class="sx-ved-liste">
                                <?php foreach ($liste as $v): $vid = (int)$v['id']; ?>
                                <div class="sx-ved-opt">
                                    <label class="sx-ved-kopf">
                                        <input type="checkbox" class="form-check-input" name="veredelung[<?php echo $vid; ?>]" value="1" data-preis="<?php echo (float)$v['preis']; ?>">
                                        <span class="nm"><?php echo $h($v['position_name']); ?></span>
                                        <span class="pr"><?php echo (float)$v['preis'] > 0 ? '+ ' . $h($euro($v['preis'])) : 'ohne Aufpreis'; ?></span>
                                    </label>
                                    <div class="sx-ved-detail">
                                        <input type="file" class="form-control form-control-sm" name="ved_datei[<?php echo $vid; ?>]" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" aria-label="Logo oder Schriftzug für <?php echo $h($v['art_name'] . ' ' . $v['position_name']); ?>">
                                        <input type="text" class="form-control form-control-sm mt-2" name="ved_hinweis[<?php echo $vid; ?>]" maxlength="300" placeholder="Hinweis (optional): Text, Farbe, Größe …">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="sx-ved-fuss">
                            <span><i class="bi bi-upload"></i> Je Position ein Logo oder Schriftzug (JPG, PNG, WebP oder PDF, bis <?php echo round(Shop::dateiGrenzeBytes() / 1048576); ?> MB)</span>
                            <span>Aufpreis je Stück <b id="sx-vedpreis"><?php echo $h($euro(0)); ?></b></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <button class="sx-btn mt-3" id="sx-addbtn" <?php echo !empty($a['varianten']) ? 'disabled' : ''; ?>><i class="bi bi-bag-plus"></i> In den Warenkorb</button>
                </form>
                <?php endif; ?>

                <div class="sx-facts">
                    <?php if ($a['lieferzeit']): ?><span class="sx-fact"><i class="bi bi-truck"></i> Lieferzeit <?php echo $h($a['lieferzeit']); ?></span><?php endif; ?>
                    <span class="sx-fact"><i class="bi bi-receipt"></i> Kauf auf Rechnung</span>
                    <?php if ($vkFrei > 0): ?><span class="sx-fact"><i class="bi bi-box-seam"></i> Versandfrei ab <?php echo $h($euro($vkFrei)); ?></span><?php endif; ?>
                </div>
                <?php if ($a['beschreibung']): ?><div class="sx-desc"><?php echo $h($a['beschreibung']); ?></div><?php endif; ?>
            </div>
        </div>
    </div>

<?php /* ---------------------------------------------------------------- Warenkorb */ ?>
<?php elseif ($ansicht === 'warenkorb'): ?>
    <?php if (!$warenkorb): ?>
    <div class="sx-panel sx-done">
        <div class="ic" style="background:var(--sx-soft);color:var(--sx-p)"><i class="bi bi-bag"></i></div>
        <h4 class="fw-bold">Dein Warenkorb ist leer</h4>
        <p class="text-muted">Stöbere im Katalog und lege Artikel in den Warenkorb.</p>
        <a href="shop.php" class="sx-btn"><i class="bi bi-grid"></i> Zum Katalog</a>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="sx-cartbox">
                <div class="sx-cart-kopf">
                    <h5><i class="bi bi-bag"></i> Dein Warenkorb <span class="text-muted fw-normal">(<?php echo (int)$wkAnzahl; ?> Artikel)</span></h5>
                    <form method="post" class="m-0" onsubmit="return confirm('Warenkorb leeren?')"><input type="hidden" name="aktion" value="leeren">
                        <button class="sx-link-btn"><i class="bi bi-trash3"></i> Warenkorb leeren</button></form>
                </div>
                <div class="sx-cart-cols"><span>Artikel</span><span>Preis</span><span class="text-center">Menge</span><span class="text-end">Summe</span><span></span></div>
                <?php foreach ($warenkorb as $k => $z): $durl = 'shop.php?ansicht=artikel&artikel=' . (int)($z['artikel_id'] ?? 0); ?>
                <div class="sx-cart-row">
                    <a class="c-bild" href="<?php echo $durl; ?>"><?php if (!empty($z['bild'])): ?><img src="<?php echo $h($z['bild']); ?>" alt="<?php echo $h($z['artikel_name']); ?>"><?php else: ?><i class="bi bi-image"></i><?php endif; ?></a>
                    <div class="c-info">
                        <a class="c-name" href="<?php echo $durl; ?>"><?php echo $h($z['artikel_name']); ?></a>
                        <div class="c-tags">
                            <?php if ($z['variante']): ?><span class="c-tag">Größe <?php echo $h($z['variante']); ?></span><?php endif; ?>
                            <?php if (!empty($z['artikelnummer'])): ?><span class="c-tag hell">Art.-Nr. <?php echo $h($z['artikelnummer']); ?></span><?php endif; ?>
                        </div>
                        <?php if (!empty($z['veredelung'])): ?>
                        <ul class="c-ved">
                            <?php foreach ($z['veredelung'] as $v): ?>
                            <li><i class="bi bi-stars"></i><span><?php echo $h($v['bezeichnung']); ?><?php echo $v['preis'] > 0 ? ' (+' . $h($euro($v['preis'])) . ')' : ''; ?>
                                <span class="dt">· <?php echo $h($v['original']); ?><?php echo $v['hinweis'] !== '' ? ' · „' . $h($v['hinweis']) . '“' : ''; ?></span></span></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                        <div class="c-einzel">je <?php echo $h($euro($z['einzelpreis'])); ?></div>
                    </div>
                    <div class="c-preis"><?php echo $h($euro($z['einzelpreis'])); ?></div>
                    <form method="post" class="c-menge">
                        <input type="hidden" name="aktion" value="menge"><input type="hidden" name="schluessel" value="<?php echo $h($k); ?>">
                        <button name="menge" value="<?php echo (int)$z['menge'] - 1; ?>" aria-label="weniger" title="<?php echo (int)$z['menge'] <= 1 ? 'Entfernen' : 'weniger'; ?>"><?php echo (int)$z['menge'] <= 1 ? '<i class="bi bi-trash3" style="font-size:.85rem"></i>' : '−'; ?></button>
                        <span><?php echo (int)$z['menge']; ?></span>
                        <button name="menge" value="<?php echo min(99, (int)$z['menge'] + 1); ?>" aria-label="mehr">+</button>
                    </form>
                    <div class="c-summe"><?php echo $h($euro($z['einzelpreis'] * $z['menge'])); ?></div>
                    <form method="post" class="c-weg"><input type="hidden" name="aktion" value="entfernen"><input type="hidden" name="schluessel" value="<?php echo $h($k); ?>">
                        <button title="Entfernen" aria-label="Entfernen"><i class="bi bi-x-lg"></i></button></form>
                </div>
                <?php endforeach; ?>
            </div>
            <a href="shop.php" class="sx-zurueck"><i class="bi bi-arrow-left"></i> Weiter einkaufen</a>
        </div>
        <div class="col-xl-4">
            <div class="sx-panel sx-sum">
                <h5 class="fw-bold mb-3">Zusammenfassung</h5>
                <div class="row-l"><span>Zwischensumme</span><span><?php echo $h($euro($wkSumme)); ?></span></div>
                <?php if ($vkSatz > 0): ?>
                <div class="row-l"><span>Versand</span><span><?php echo $versand == 0 ? '<span class="text-success fw-semibold">kostenlos</span>' : $h($euro($versand)); ?></span></div>
                <?php if ($vkFrei > 0): $proz = (int)min(100, round($wkSumme / $vkFrei * 100)); ?>
                <div class="sx-frei">
                    <?php if ($versand > 0): ?>Noch <strong><?php echo $h($euro($vkFrei - $wkSumme)); ?></strong> bis zum versandfreien Einkauf<?php else: ?><i class="bi bi-check-circle-fill text-success"></i> Versandfrei ab <?php echo $h($euro($vkFrei)); ?> erreicht<?php endif; ?>
                    <div class="bar"><span style="width:<?php echo $proz; ?>%"></span></div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                <div class="tot"><span>Gesamt</span><span><?php echo $h($euro($wkGesamt)); ?></span></div>
                <a href="shop.php?ansicht=kasse" class="sx-btn w-100 mt-3">Zur Bestellung <i class="bi bi-arrow-right"></i></a>
                <div class="sx-sum-info">
                    <span><i class="bi bi-receipt"></i> Bezahlung bequem per Rechnung</span>
                    <span><i class="bi bi-shield-check"></i> Bestellung geht direkt an den Shop-Betreiber</span>
                    <?php if (!empty($katalog['info']['versand'])): ?><span><i class="bi bi-truck"></i> <?php echo $h($katalog['info']['versand']); ?></span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

<?php /* ---------------------------------------------------------------- Kasse */ ?>
<?php elseif ($ansicht === 'kasse'): ?>
    <?php
    if (!$warenkorb) { echo '<div class="alert alert-info alert-permanent">Der Warenkorb ist leer.</div>'; }
    else {
        $vereinsname = ShopKatalog::vereinsname();
        $meinBenutzer = $db->fetchOne("SELECT email FROM benutzer WHERE id = ?", [Session::getUserId()]);
    ?>
    <form method="post" class="row g-4">
        <input type="hidden" name="aktion" value="bestellen"><input type="hidden" name="zuruck" value="kasse">
        <div class="col-lg-8">
            <div class="sx-panel">
                <h5 class="fw-bold mb-3"><i class="bi bi-person-badge text-primary"></i> Bestelldaten</h5>
                <div class="row g-3">
                    <div class="col-12"><label class="form-label" for="verein_name">Verein</label>
                        <input class="form-control" id="verein_name" name="verein_name" required value="<?php echo $h($vereinsname); ?>"></div>
                    <div class="col-md-6"><label class="form-label" for="besteller_name">Ansprechpartner</label>
                        <input class="form-control" id="besteller_name" name="besteller_name" required value="<?php echo $h(Session::getUsername()); ?>"></div>
                    <div class="col-md-6"><label class="form-label" for="besteller_email">E-Mail</label>
                        <input type="email" class="form-control" id="besteller_email" name="besteller_email" value="<?php echo $h($meinBenutzer['email'] ?? ''); ?>"></div>
                    <div class="col-md-6"><label class="form-label" for="besteller_telefon">Telefon</label>
                        <input class="form-control" id="besteller_telefon" name="besteller_telefon"></div>
                    <div class="col-12"><label class="form-label" for="lieferadresse">Lieferadresse</label>
                        <textarea class="form-control" id="lieferadresse" name="lieferadresse" rows="3" required placeholder="Verein, Straße, PLZ Ort"></textarea></div>
                    <div class="col-12"><label class="form-label" for="bemerkung">Bemerkung (optional)</label>
                        <textarea class="form-control" id="bemerkung" name="bemerkung" rows="2"></textarea></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="sx-panel sx-sum">
                <h5 class="fw-bold mb-3">Deine Bestellung</h5>
                <?php foreach ($warenkorb as $z): ?>
                <div class="row-l"><span><?php echo (int)$z['menge']; ?>× <?php echo $h($z['artikel_name']); ?><?php echo $z['variante'] ? ' (' . $h($z['variante']) . ')' : ''; ?>
                    <?php if (!empty($z['veredelung'])): ?><br><small class="text-muted"><i class="bi bi-stars"></i> <?php echo $h(implode(', ', array_column($z['veredelung'], 'bezeichnung'))); ?></small><?php endif; ?></span>
                    <span class="text-nowrap ms-2"><?php echo $h($euro($z['einzelpreis'] * $z['menge'])); ?></span></div>
                <?php endforeach; ?>
                <?php if ($vkSatz > 0): ?>
                <div class="row-l mt-2"><span>Versand</span><span><?php echo $versand == 0 ? 'kostenlos' : $h($euro($versand)); ?></span></div>
                <?php endif; ?>
                <div class="tot"><span>Gesamt</span><span><?php echo $h($euro($wkGesamt)); ?></span></div>
                <div class="small text-muted mt-2"><i class="bi bi-receipt"></i> Die Abrechnung erfolgt per Rechnung.</div>
                <?php if (!empty($katalog['info']['versand'])): ?><div class="small text-muted"><i class="bi bi-truck"></i> <?php echo $h($katalog['info']['versand']); ?></div><?php endif; ?>
                <button class="sx-btn w-100 mt-3"><i class="bi bi-bag-check"></i> Verbindlich bestellen</button>
            </div>
        </div>
    </form>
    <?php } ?>

<?php /* ---------------------------------------------------------------- Katalog */ ?>
<?php else:
    $artikelListe = $katalog['artikel'] ?? [];
    $katIds = array_values(array_filter(array_merge($katHaupt, [0]), fn($kid) => $kid ? $katGesamt($kid) > 0 : !empty($anzahlJeKat[0])));
    ?>
    <?php if (!$artikelListe && !$ladeFehler): ?>
    <div class="sx-panel sx-done"><div class="ic" style="background:var(--sx-soft);color:var(--sx-p)"><i class="bi bi-box-seam"></i></div>
        <h4 class="fw-bold">Noch keine Artikel</h4><p class="text-muted mb-0">Im Shop sind derzeit keine Artikel verfügbar.</p></div>
    <?php else: ?>
    <div class="sx-bar">
        <div class="sx-pills" id="sx-pills">
            <button type="button" class="sx-pill on" data-kat="alle">Alle<span class="z"><?php echo count($artikelListe); ?></span></button>
            <?php foreach ($katIds as $kid): ?>
            <button type="button" class="sx-pill" data-kat="<?php echo (int)$kid; ?>"><?php echo $h($katName($kid)); ?><span class="z"><?php echo $kid ? $katGesamt($kid) : (int)$anzahlJeKat[0]; ?></span></button>
            <?php endforeach; ?>
        </div>
        <div class="sx-tools">
            <span class="sx-count" id="sx-count"></span>
            <button type="button" class="sx-favbtn" id="sx-favfilter" title="Nur gemerkte Artikel"><i class="bi bi-heart"></i> <span>0</span></button>
            <select class="sx-select" id="sx-sort" aria-label="Sortierung">
                <option value="std">Empfohlen</option>
                <option value="preis-auf">Preis aufsteigend</option>
                <option value="preis-ab">Preis absteigend</option>
                <option value="name">Name A–Z</option>
            </select>
        </div>
    </div>
    <?php foreach ($katHaupt as $hid): $subs = array_filter($katKinder[$hid] ?? [], fn($c) => !empty($anzahlJeKat[$c])); if (!$subs) continue; ?>
    <div class="sx-subs" data-fuer="<?php echo $hid; ?>">
        <button type="button" class="sx-sub on" data-kat="<?php echo $hid; ?>">Alle in <?php echo $h($katName($hid)); ?></button>
        <?php foreach ($subs as $cid): ?><button type="button" class="sx-sub" data-kat="<?php echo $cid; ?>"><?php echo $h($katName($cid)); ?><span class="z"><?php echo (int)$anzahlJeKat[$cid]; ?></span></button><?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <div class="sx-grid" id="sx-grid">
        <?php foreach ($artikelListe as $pos => $a):
            $soldout = !empty($a['ausverkauft']); $bilder = $a['bilder'] ?? []; $url = 'shop.php?ansicht=artikel&artikel=' . (int)$a['id'];
            $mehrPreise = !empty($a['varianten']) && count(array_unique(array_map(fn($v) => (float)$v['preis'], $a['varianten']))) > 1; ?>
        <?php $ck = (int)($a['kategorie_id'] ?? 0); if ($ck && !isset($katGueltig[$ck])) $ck = 0; ?>
        <div class="sx-card" data-id="<?php echo (int)$a['id']; ?>" data-kat="<?php echo $ck; ?>" data-parent="<?php echo (int)($katParent[$ck] ?? 0); ?>"
             data-name="<?php echo $h(mb_strtolower($a['name'] . ' ' . ($a['artikelnummer'] ?? '') . ' ' . $katName($a['kategorie_id']))); ?>"
             data-preis="<?php echo $preisAbFn($a); ?>" data-pos="<?php echo (int)$pos; ?>">
            <div class="sx-media">
                <a class="lnk" href="<?php echo $url; ?>" aria-label="<?php echo $h($a['name']); ?>"></a>
                <div class="sx-badges">
                    <?php if (!empty($a['aktion'])): ?><span class="sx-badge"><?php echo $h($a['aktion_text'] ?: 'Aktion'); ?></span><?php endif; ?>
                </div>
                <button type="button" class="sx-fav" title="Merken" aria-label="Merken"><i class="bi bi-heart"></i></button>
                <?php if ($bilder): foreach ($bilder as $bi => $b): ?>
                <img src="<?php echo $h($b); ?>" class="<?php echo $bi === 0 ? 'on' : ''; ?>" alt="<?php echo $h($a['name']); ?>" loading="lazy">
                <?php endforeach; else: ?>
                <span class="ph"><i class="bi bi-image"></i></span>
                <?php endif; ?>
                <?php if (count($bilder) > 1): ?>
                <button type="button" class="sx-nav prev" aria-label="Vorheriges Bild"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="sx-nav next" aria-label="Nächstes Bild"><i class="bi bi-chevron-right"></i></button>
                <div class="sx-dots"><?php foreach ($bilder as $bi => $b): ?><button type="button" class="sx-dot <?php echo $bi === 0 ? 'on' : ''; ?>" aria-label="Bild <?php echo $bi + 1; ?>"></button><?php endforeach; ?></div>
                <?php endif; ?>
                <?php if ($soldout): ?><div class="sx-sold"><span>Ausverkauft</span></div><?php endif; ?>
            </div>
            <div class="sx-body">
                <div class="sx-kat"><?php echo $h($katName($a['kategorie_id'])); ?></div>
                <a class="sx-title" href="<?php echo $url; ?>"><?php echo $h($a['name']); ?></a>
                <div class="sx-foot">
                    <div class="sx-price"><?php if ($mehrPreise): ?><small>ab</small><?php endif; ?><?php if ($altPreis($a) !== null): ?><span class="sx-aktionspreis"><?php echo $h($euro($preisAbFn($a))); ?></span> <s class="sx-altpreis"><?php echo $h($euro($altPreis($a))); ?></s><?php else: ?><?php echo $h($euro($preisAbFn($a))); ?><?php endif; ?></div>
                    <a class="sx-go" href="<?php echo $url; ?>" aria-label="Ansehen"><i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="sx-empty" id="sx-empty"><i class="bi bi-search"></i>Keine passenden Artikel gefunden.</div>
    <?php endif; ?>
<?php endif; ?>
</div><!-- /.sx -->

<script>
(function () {
    // Bildwechsel (Karten und Detail-Galerie): Pfeile, Punkte, Vorschaubilder, Wischen
    function galerie(box, dots) {
        var imgs = box.querySelectorAll('img'), akt = 0;
        if (imgs.length < 2) return;
        function zeige(i) {
            akt = (i + imgs.length) % imgs.length;
            imgs.forEach(function (x, j) { x.classList.toggle('on', j === akt); });
            dots.forEach(function (x, j) { x.classList.toggle('on', j === akt); });
        }
        dots.forEach(function (d, i) { d.addEventListener('click', function (e) { e.preventDefault(); zeige(i); }); });
        var p = box.querySelector('.sx-nav.prev'), n = box.querySelector('.sx-nav.next');
        if (p) p.addEventListener('click', function (e) { e.preventDefault(); zeige(akt - 1); });
        if (n) n.addEventListener('click', function (e) { e.preventDefault(); zeige(akt + 1); });
        var x0 = null;
        box.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
        box.addEventListener('touchend', function (e) {
            if (x0 === null) return;
            var dx = e.changedTouches[0].clientX - x0; x0 = null;
            if (Math.abs(dx) > 35) { e.preventDefault(); zeige(akt + (dx < 0 ? 1 : -1)); }
        });
    }
    document.querySelectorAll('.sx-media').forEach(function (m) { galerie(m, m.querySelectorAll('.sx-dot')); });
    var gal = document.getElementById('sx-gal');
    if (gal) galerie(gal, document.querySelectorAll('.sx-thumb'));

    // Detail: Mengen-Knöpfe (−/+) und Summen der Mengentabelle
    var euro = function (b) { return b.toLocaleString('de-AT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'; };
    function vedJeStueck() {
        var s = 0;
        document.querySelectorAll('.sx-ved-opt input[type="checkbox"]').forEach(function (c) { if (c.checked) s += parseFloat(c.dataset.preis) || 0; });
        return s;
    }
    function summieren() {
        var ved = vedJeStueck(), vp = document.getElementById('sx-vedpreis');
        if (vp) vp.textContent = euro(ved);
        var tab = document.querySelector('.sx-mtab');
        if (!tab) return;
        var stk = 0, sum = 0;
        tab.querySelectorAll('.sx-mtab-row').forEach(function (r) {
            var m = Math.max(0, parseInt(r.querySelector('input').value, 10) || 0), p = (parseFloat(r.dataset.preis) || 0) + ved;
            r.classList.toggle('hat', m > 0);
            r.querySelector('.su').textContent = m > 0 ? euro(m * p) : '–';
            stk += m; sum += m * p;
        });
        document.getElementById('sx-stk').textContent = stk;
        document.getElementById('sx-gesamt').textContent = euro(sum);
        var btn = document.getElementById('sx-addbtn');
        if (btn) {
            btn.disabled = stk === 0;
            btn.innerHTML = '<i class="bi bi-bag-plus"></i> ' + (stk ? stk + ' Stück in den Warenkorb' : 'In den Warenkorb');
        }
    }
    document.querySelectorAll('.sx-qty').forEach(function (q) {
        var inp = q.querySelector('input'), min = parseInt(inp.min, 10) || 0;
        q.querySelectorAll('button').forEach(function (b) {
            b.addEventListener('click', function () {
                inp.value = Math.max(min, Math.min(99, (parseInt(inp.value, 10) || 0) + parseInt(b.dataset.q, 10)));
                summieren();
            });
        });
        inp.addEventListener('input', summieren);
        inp.addEventListener('focus', function () { inp.select(); });   // Eintippen überschreibt die 0
    });
    // Veredelung: angehakte Position zeigt Datei-Feld (Pflicht) und Hinweis
    document.querySelectorAll('.sx-ved-opt').forEach(function (o) {
        var cb = o.querySelector('input[type="checkbox"]'), datei = o.querySelector('input[type="file"]');
        cb.addEventListener('change', function () {
            o.classList.toggle('an', cb.checked);
            datei.required = cb.checked;
            if (!cb.checked) datei.value = '';
            summieren();
        });
    });
    // Vor dem Absenden: Dateigrößen prüfen (sonst verwirft der Server die ganze Anfrage kommentarlos)
    var bf = document.getElementById('sx-bestellform');
    if (bf) bf.addEventListener('submit', function (e) {
        var GRENZE = <?php echo Shop::uploadGrenzeBytes(); ?>, EINZEL = <?php echo Shop::dateiGrenzeBytes(); ?>, summe = 0, zuGross = [];
        var mb = function (b) { return (b / 1048576).toFixed(1).replace('.', ',') + ' MB'; };
        bf.querySelectorAll('.sx-ved-opt').forEach(function (o) {
            if (!o.querySelector('input[type="checkbox"]').checked) return;
            Array.prototype.forEach.call(o.querySelector('input[type="file"]').files || [], function (f) { summe += f.size; if (f.size > EINZEL) zuGross.push(f.name); });
        });
        var text = '';
        if (zuGross.length) text = 'Diese Dateien sind größer als ' + mb(EINZEL) + ': ' + zuGross.join(', ');
        else if (GRENZE > 0 && summe > GRENZE - 256 * 1024) text = 'Die Dateien sind zusammen ' + mb(summe) + ' groß – höchstens ' + mb(GRENZE) + ' auf einmal möglich.';
        if (text) { e.preventDefault(); alert(text); }
    });
    summieren();

    // Katalog: Merkliste (im Browser gespeichert), Kategorie, Suche, Sortierung
    var grid = document.getElementById('sx-grid');
    if (!grid) return;
    var karten = Array.prototype.slice.call(grid.querySelectorAll('.sx-card'));
    var SPEICHER = 'syncopa_shop_merkliste', merk = [];
    try { merk = JSON.parse(localStorage.getItem(SPEICHER) || '[]'); } catch (e) { merk = []; }
    var kat = 'alle', nurMerk = false, suche = '';
    var favBtn = document.getElementById('sx-favfilter');

    function merkSpeichern() { try { localStorage.setItem(SPEICHER, JSON.stringify(merk)); } catch (e) {} }
    function merkAnzeigen() {
        karten.forEach(function (k) {
            var an = merk.indexOf(k.dataset.id) > -1, b = k.querySelector('.sx-fav'), i = b.querySelector('i');
            b.classList.toggle('on', an); i.className = an ? 'bi bi-heart-fill' : 'bi bi-heart';
        });
        favBtn.querySelector('span').textContent = merk.length;
    }
    function filtern() {
        var n = 0;
        karten.forEach(function (k) {
            var ok = (kat === 'alle' || k.dataset.kat === kat || k.dataset.parent === kat)
                && (!suche || k.dataset.name.indexOf(suche) > -1)
                && (!nurMerk || merk.indexOf(k.dataset.id) > -1);
            k.style.display = ok ? '' : 'none'; if (ok) n++;
        });
        document.getElementById('sx-count').textContent = n + (n === 1 ? ' Artikel' : ' Artikel');
        document.getElementById('sx-empty').style.display = n ? 'none' : 'block';
    }
    karten.forEach(function (k) {
        k.querySelector('.sx-fav').addEventListener('click', function (e) {
            e.preventDefault();
            var i = merk.indexOf(k.dataset.id);
            if (i > -1) merk.splice(i, 1); else merk.push(k.dataset.id);
            merkSpeichern(); merkAnzeigen(); filtern();
        });
    });
    // Kategorie setzen: Hauptkategorie-Leiste, Unterkategorien-Leiste und Raster abgleichen
    var ELTERN = <?php echo json_encode(array_map('strval', $katParent), JSON_FORCE_OBJECT); ?>;
    function setzeKat(k) {
        kat = String(k);
        var haupt = ELTERN[kat] || kat;
        document.querySelectorAll('.sx-pill').forEach(function (x) { x.classList.toggle('on', x.dataset.kat === haupt); });
        document.querySelectorAll('.sx-subs').forEach(function (r) {
            var offen = r.dataset.fuer === haupt; r.classList.toggle('offen', offen);
            if (offen) r.querySelectorAll('.sx-sub').forEach(function (b) { b.classList.toggle('on', b.dataset.kat === kat); });
        });
        filtern();
    }
    document.querySelectorAll('.sx-pill, .sx-sub').forEach(function (p) {
        p.addEventListener('click', function () { setzeKat(p.dataset.kat); });
    });
    // Kategorien-Menü im Kopfbereich + Verweise aus den Angeboten
    var mega = document.getElementById('sx-mega'), megaBtn = document.getElementById('sx-megabtn');
    var kopf = document.getElementById('sx-headwrap'), nav = document.querySelector('.sx-topnav'), zuTimer = null;
    function megaZu() { if (mega && megaBtn) { mega.classList.remove('offen'); megaBtn.classList.remove('on'); megaBtn.setAttribute('aria-expanded', 'false'); } }
    function megaAuf() {
        clearTimeout(zuTimer);
        if (nav) mega.style.top = (nav.offsetTop + nav.offsetHeight + 6) + 'px';   // direkt unter der Navigationsleiste
        mega.classList.add('offen'); megaBtn.classList.add('on'); megaBtn.setAttribute('aria-expanded', 'true');
    }
    if (mega && megaBtn) {
        megaBtn.addEventListener('click', function () { mega.classList.contains('offen') ? megaZu() : megaAuf(); });
        // Am Computer öffnet das Menü auch beim Darüberfahren
        if (window.matchMedia('(hover: hover)').matches) {
            megaBtn.addEventListener('mouseenter', megaAuf);
            mega.addEventListener('mouseenter', function () { clearTimeout(zuTimer); });
            [megaBtn, mega].forEach(function (el) { el.addEventListener('mouseleave', function () { zuTimer = setTimeout(megaZu, 280); }); });
        }
        document.querySelectorAll('[data-megazu]').forEach(function (b) { b.addEventListener('click', megaZu); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') megaZu(); });
        document.addEventListener('click', function (e) { if (!mega.contains(e.target) && !megaBtn.contains(e.target)) megaZu(); });
    }
    document.querySelectorAll('[data-filter]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            setzeKat(a.dataset.filter);
            megaZu();
            var bar = document.querySelector('.sx-bar');
            if (bar) bar.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
    favBtn.addEventListener('click', function () { nurMerk = !nurMerk; favBtn.classList.toggle('on', nurMerk); filtern(); });
    var such = document.getElementById('sx-suche');
    if (such) such.addEventListener('input', function () { suche = such.value.trim().toLowerCase(); filtern(); });
    document.getElementById('sx-sort').addEventListener('change', function () {
        var art = this.value;
        karten.sort(function (a, b) {
            if (art === 'preis-auf') return a.dataset.preis - b.dataset.preis;
            if (art === 'preis-ab') return b.dataset.preis - a.dataset.preis;
            if (art === 'name') return a.dataset.name.localeCompare(b.dataset.name, 'de');
            return a.dataset.pos - b.dataset.pos;
        });
        karten.forEach(function (k) { grid.appendChild(k); });
    });
    merkAnzeigen(); filtern();
})();
</script>

<?php include 'includes/footer.php'; ?>
