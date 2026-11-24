<?php
// includes/syncomail_titel.php – Titelleiste von SyncoMail (gemeinsam für E-Mail und Kontakte).
// Erwartet $syncoAktiv = 'mail' | 'kontakte'. Die Postfach-Anzeige rechts (#titelKonto) füllt mail.php per Skript.
$syncoAktiv = $syncoAktiv ?? 'mail';
?>
<header class="mail-titel">
    <a class="raster" href="index.php" title="Zur Syncopa-Startseite"><i class="bi bi-grid-3x3-gap-fill"></i></a>
    <div class="logo">
        <div class="logo-kachel"><i class="bi bi-envelope-fill"></i><b>S</b></div>
        <div class="name"><?php echo htmlspecialchars(MailKonto::APP_NAME); ?><?php if ($syncoAktiv === 'kontakte'): ?> <span>· Kontakte</span><?php endif; ?></div>
    </div>
    <div class="rechts">
        <span class="konto-anzeige" id="titelKonto"></span>
        <a class="knopf <?php echo $syncoAktiv === 'mail' ? 'aktiv' : ''; ?>" href="mail.php" title="E-Mail"<?php echo $syncoAktiv === 'mail' ? ' aria-current="page"' : ''; ?>><i class="bi bi-envelope"></i></a>
        <a class="knopf <?php echo $syncoAktiv === 'kontakte' ? 'aktiv' : ''; ?>" href="mail_kontakte.php" title="Kontakte"<?php echo $syncoAktiv === 'kontakte' ? ' aria-current="page"' : ''; ?>><i class="bi bi-people"></i></a>
        <span class="trenn"></span>
        <a class="knopf" href="konto.php#postfaecher" title="Postfach-Einstellungen"><i class="bi bi-gear"></i></a>
    </div>
</header>
