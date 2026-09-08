// Canvas Drawing Engine

class DrawingApp {
  constructor(canvasId) {
    this.canvas    = document.getElementById(canvasId);
    this.ctx       = this.canvas.getContext('2d');
    this.isDrawing = false;
    this.lastX     = 0;
    this.lastY     = 0;
    this.tool        = 'pen';
    this.color       = '#000000';
    this._clearPending = false;
    this._clearTimer   = null;

    // Stempel-Zustand
    this.stampSymbol    = 'f';   // aktuell gewähltes Symbol
    this.stampSize      = 32;    // Schriftgröße in Canvas-Pixeln (über Slider einstellbar)
    this.isStamping     = false; // Drag-to-position läuft gerade
    this._stampSnapshot = null;  // ImageData-Snapshot vor dem Drag (für Vorschau-Reset)
    this._stampPos      = { x: 0, y: 0 }; // aktuelle Drag-Position
    this.penWidth    = 2;   // Stift-Standardgröße
    this.markerWidth = 12;  // Textmarker-Standardgröße
    this.lineWidth   = 2;   // aktive Größe
    this.history     = [];
    this.saveTimer   = null;

    // Annotations-Cache: key `${stimme_id}_${page}` → base64-String oder null
    this._annCache = new Map();

    // Generationszähler: verhindert doppeltes Zeichnen bei parallelen loadAnnotations-Aufrufen
    this._loadGen = 0;

    // Laufende Fetch-Anfragen: verhindert doppelte Server-Abfragen für dieselbe Seite
    this._loadInflight = new Map();

    // Split-Modus Zustand
    this.splitMode    = false;
    this.splitTopPage = 1;
    this.splitBotPage = 1;
    this.splitHalfH   = 0;
    this.splitFullH   = 0;
    this.splitTopAnn  = null;  // gecachte Image für topPage (volle Seite)
    this.splitBotAnn  = null;  // gecachte Image für botPage (volle Seite)

    this.bindEvents();
  }

  bindEvents() {
    document.getElementById('btn-pen').addEventListener('click',         () => this.selectTool('pen'));
    document.getElementById('btn-highlighter').addEventListener('click', () => this.selectTool('highlighter'));
    document.getElementById('btn-eraser').addEventListener('click',      () => this.selectTool('eraser'));

    document.getElementById('width-slider').addEventListener('input', e => {
      const v = parseInt(e.target.value);
      if (this.tool === 'stamp') {
        // Im Stempel-Modus steuert der Slider die Schriftgröße
        this.stampSize = v;
        document.getElementById('width-display').innerText = v + 'px';
        return;
      }
      if (this.tool === 'highlighter') {
        this.markerWidth = v;
      } else {
        this.penWidth = v;
      }
      this.lineWidth = v;
      document.getElementById('width-display').innerText = v + 'px';
    });

    document.getElementById('btn-undo').addEventListener('click',  () => this.undo());
    document.getElementById('btn-clear').addEventListener('click', () => this.clearPage());
    document.getElementById('btn-save').addEventListener('click',  () => this.saveManual());

    document.getElementById('btn-next').addEventListener('click', () => {
      this.saveNow();
      if (window.pdfViewer) window.pdfViewer.nextPage();
    });
    document.getElementById('btn-prev').addEventListener('click', () => {
      this.saveNow();
      if (window.pdfViewer) window.pdfViewer.prevPage();
    });

    // Mouse
    this.canvas.addEventListener('mousedown',  e => this.startDraw(e));
    this.canvas.addEventListener('mousemove',  e => this.draw(e));
    this.canvas.addEventListener('mouseup',    () => this.stopDraw());
    this.canvas.addEventListener('mouseleave', () => this.stopDraw());

    // Touch (Finger/Stift)
    this.canvas.addEventListener('touchstart', e => { e.preventDefault(); this.startDraw(e); }, { passive: false });
    this.canvas.addEventListener('touchmove',  e => { e.preventDefault(); this.draw(e); },      { passive: false });
    this.canvas.addEventListener('touchend',   e => { e.preventDefault(); this.stopDraw(); },   { passive: false });
  }

  selectTool(tool) {
    this.tool = tool;
    ['pen', 'highlighter', 'eraser', 'stamp'].forEach(t => {
      document.getElementById('btn-' + t)?.classList.toggle('active', t === tool);
    });

    // Slider und Anzeige auf die gespeicherte Größe des Werkzeugs setzen
    const slider  = document.getElementById('width-slider');
    const display = document.getElementById('width-display');

    if (tool === 'stamp') {
      // Slider auf Stempel-Größenbereich umkonfigurieren
      if (slider)  { slider.min = 12; slider.max = 80; slider.value = this.stampSize; }
      if (display) display.innerText = this.stampSize + 'px';
      return;
    }

    if (tool === 'highlighter') {
      slider.value       = this.markerWidth;
      display.innerText  = this.markerWidth + 'px';
      this.lineWidth     = this.markerWidth;
      slider.min = 6; slider.max = 30;
    } else {
      slider.value       = this.penWidth;
      display.innerText  = this.penWidth + 'px';
      this.lineWidth     = this.penWidth;
      slider.min = 1; slider.max = 10;
    }
  }

  startDraw(e) {
    if (this.tool === 'stamp') {
      // Drag-to-position: Canvas-Zustand sichern, Vorschau anzeigen
      this.isStamping     = true;
      this._stampSnapshot = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
      const pos = this.getPos(e);
      this._stampPos = pos;
      this._renderStampAt(pos, true);
      return;
    }
    this.isDrawing = true;
    this.saveHistory();
    const pos  = this.getPos(e);
    this.lastX = pos.x;
    this.lastY = pos.y;
  }

  draw(e) {
    if (this.tool === 'stamp') {
      if (!this.isStamping) return;
      // Snapshot zurücksetzen, Vorschau an neuer Position zeichnen
      const pos = this.getPos(e);
      this._stampPos = pos;
      this.ctx.putImageData(this._stampSnapshot, 0, 0);
      this._renderStampAt(pos, true);
      return;
    }
    if (!this.isDrawing) return;
    const pos = this.getPos(e);

    this.ctx.beginPath();
    this.ctx.moveTo(this.lastX, this.lastY);
    this.ctx.lineTo(pos.x, pos.y);
    this.ctx.lineCap  = 'round';
    this.ctx.lineJoin = 'round';

    if (this.tool === 'eraser') {
      this.ctx.globalCompositeOperation = 'destination-out';
      this.ctx.strokeStyle = 'rgba(0,0,0,1)';
      this.ctx.globalAlpha = 1;
      this.ctx.lineWidth   = this.penWidth * 4;
      this.ctx.lineCap     = 'round';
      this.ctx.lineJoin    = 'round';
    } else if (this.tool === 'highlighter') {
      this.ctx.globalCompositeOperation = 'source-over';
      this.ctx.strokeStyle = this.color;
      this.ctx.globalAlpha = 0.22;        // stärker transparent
      this.ctx.lineWidth   = this.markerWidth;
      this.ctx.lineCap     = 'square';    // eckiger Marker
      this.ctx.lineJoin    = 'miter';
    } else {
      this.ctx.globalCompositeOperation = 'source-over';
      this.ctx.strokeStyle = this.color;
      this.ctx.globalAlpha = 1;
      this.ctx.lineWidth   = this.penWidth;
      this.ctx.lineCap     = 'round';
      this.ctx.lineJoin    = 'round';
    }

    this.ctx.stroke();
    this.lastX = pos.x;
    this.lastY = pos.y;
  }

  // Symbol an Position zeichnen – preview=true: halb-transparent (Vorschau beim Ziehen)
  _renderStampAt(pos, preview) {
    const sym   = this.stampSymbol;
    const isDyn = /^(ppp|pp|p|mp|mf|f|ff|fff|sf|sfz|fp|fz)$/.test(sym);
    this.ctx.save();
    this.ctx.globalAlpha              = preview ? 0.5 : 1.0;
    this.ctx.globalCompositeOperation = 'source-over';
    this.ctx.fillStyle   = this.color;
    this.ctx.font        = isDyn
      ? `italic bold ${this.stampSize}px 'Times New Roman', serif`
      : `bold ${this.stampSize}px sans-serif`;
    this.ctx.textAlign    = 'center';
    this.ctx.textBaseline = 'middle';
    this.ctx.fillText(sym, pos.x, pos.y);
    this.ctx.restore();
  }

  // Symbol wählen und Stempel-Modus aktivieren
  selectStamp(symbol) {
    this.stampSymbol = symbol;
    this.selectTool('stamp');
  }

  stopDraw() {
    if (this.tool === 'stamp') {
      if (!this.isStamping) return;
      this.isStamping = false;
      // Snapshot wiederherstellen, History sichern, Symbol endgültig platzieren
      this.ctx.putImageData(this._stampSnapshot, 0, 0);
      this._stampSnapshot = null;
      this.saveHistory();
      this._renderStampAt(this._stampPos, false);
      clearTimeout(this.saveTimer);
      this.saveTimer = setTimeout(() => this.saveNow(), 1500);
      return;
    }
    if (!this.isDrawing) return;
    this.isDrawing = false;
    this.ctx.globalAlpha              = 1;
    this.ctx.globalCompositeOperation = 'source-over';

    // Auto-Save: 1.5 Sek nach letztem Strich
    clearTimeout(this.saveTimer);
    this.saveTimer = setTimeout(() => this.saveNow(), 1500);
  }

  getPos(e) {
    const rect    = this.canvas.getBoundingClientRect();
    const scaleX  = this.canvas.width  / rect.width;
    const scaleY  = this.canvas.height / rect.height;
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    return {
      x: (clientX - rect.left) * scaleX,
      y: (clientY - rect.top)  * scaleY
    };
  }

  saveHistory() {
    if (this.history.length >= 30) this.history.shift();
    this.history.push(this.canvas.toDataURL());
  }

  undo() {
    if (!this.history.length) return;
    const img    = new Image();
    img.onload   = () => {
      this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
      this.ctx.drawImage(img, 0, 0);
      // Auto-Save nach Undo
      clearTimeout(this.saveTimer);
      this.saveTimer = setTimeout(() => this.saveNow(), 1500);
    };
    img.src = this.history.pop();
  }

  clearPage() {
    const btn = document.getElementById('btn-clear');
    if (!this._clearPending) {
      this._clearPending = true;
      if (btn) { btn.style.color = '#ff4444'; btn.title = 'Nochmals tippen zum Löschen'; }
      this._clearTimer = setTimeout(() => {
        this._clearPending = false;
        if (btn) { btn.style.color = ''; btn.title = 'Seite löschen'; }
      }, 2000);
      return;
    }
    clearTimeout(this._clearTimer);
    this._clearPending = false;
    if (btn) { btn.style.color = ''; btn.title = 'Seite löschen'; }
    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    this.history = [];
    this.saveNow();
  }

  async saveManual() {
    await this.saveNow();
    this.showStatus('✅ Gespeichert');
  }

  async saveNow() {
    if (this.splitMode) return this.saveSplitAnnotations();
    if (!userData?.stimme_id) return;
    clearTimeout(this.saveTimer);

    const pageNum = window.pdfViewer ? window.pdfViewer.getCurrentPage() : 1;
    const imgData = this.canvas.toDataURL('image/png');

    this.showStatus('💾 …', false);
    try {
      const res = await this._saveAnnotationData(pageNum, imgData);
      if (res.ok) this.showStatus('✅ Gespeichert');
      else        this.showStatus('⚠️ Fehler');
    } catch {
      this.showStatus('⚠️ Offline');
    }
  }

  async _saveAnnotationData(pageNum, imgData) {
    const key = `${userData.stimme_id}_${pageNum}`;

    // Cache SOFORT mit neuen Daten befüllen (synchron, vor dem await-fetch).
    // Dadurch sieht ein gleichzeitiger loadAnnotations-Aufruf immer die aktuelle Version,
    // auch wenn der Server-POST noch läuft.
    this._annCache.set(key, imgData);

    const res = await fetch('api/probe_annotations_save.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        musiker_id:  userData.musiker_id,
        stimme_id:   userData.stimme_id,
        page_number: pageNum,
        zeichnung:   imgData
      })
    });

    // Bei Fehler Cache leeren damit der nächste Ladeversuch fresh vom Server holt
    if (!res.ok) {
      this._annCache.delete(key);
    }

    return res;
  }

  async saveSplitAnnotations() {
    if (!userData?.stimme_id) return;
    clearTimeout(this.saveTimer);

    const { splitTopPage: top, splitBotPage: bot, splitHalfH: halfH, splitFullH: fullH } = this;
    const w = this.canvas.width;

    const buildFullAnn = (isTop, cachedAnn) => {
      const fc = document.createElement('canvas');
      fc.width = w; fc.height = fullH;
      const c  = fc.getContext('2d');
      // Unsichtbare Hälfte aus Cache übernehmen (der Teil, den der Nutzer gerade nicht sieht)
      if (cachedAnn) c.drawImage(cachedAnn, 0, 0, fc.width, fc.height);
      // Sichtbaren Bereich explizit leeren, damit radierte Pixel (transparent) nicht durch
      // den darunter liegenden cachedAnn durchscheinen (source-over würde sie sonst behalten)
      if (isTop) {
        c.clearRect(0, 0, w, halfH);
        c.drawImage(this.canvas, 0, 0,     w, halfH, 0, 0,     w, halfH);
      } else {
        c.clearRect(0, halfH, w, halfH);
        c.drawImage(this.canvas, 0, halfH, w, halfH, 0, halfH, w, halfH);
      }
      return fc.toDataURL('image/png');
    };

    this.showStatus('💾 …', false);
    try {
      if (top === bot) {
        // Gleiche Seite: Canvas enthält bereits beide Hälften im aktuellen Zustand.
        // Kein splitTopAnn als Basis nötig – radierte Pixel würden sonst wieder auftauchen.
        const fc = document.createElement('canvas');
        fc.width = w; fc.height = fullH;
        fc.getContext('2d').drawImage(this.canvas, 0, 0, fc.width, fc.height);
        await this._saveAnnotationData(top, fc.toDataURL('image/png'));
      } else {
        await Promise.all([
          this._saveAnnotationData(top, buildFullAnn(true,  this.splitTopAnn)),
          this._saveAnnotationData(bot, buildFullAnn(false, this.splitBotAnn))
        ]);
      }
      this.showStatus('✅ Gespeichert');
    } catch {
      this.showStatus('⚠️ Fehler');
    }
  }

  showStatus(text, fade = true) {
    const el = document.getElementById('save-status');
    if (!el) return;
    el.textContent = text;
    el.style.opacity = '1';
    if (fade) {
      clearTimeout(this._fadeTimer);
      this._fadeTimer = setTimeout(() => { el.style.opacity = '0'; }, 2000);
    }
  }

  // Annotation aus Cache oder Server laden
  async _loadAnnCached(pageNum) {
    const key = `${userData.stimme_id}_${pageNum}`;
    if (this._annCache.has(key)) return this._annCache.get(key);

    // Bereits laufende Anfrage für diese Seite wiederverwenden (verhindert Race-Condition durch Prefetch)
    if (this._loadInflight.has(key)) return this._loadInflight.get(key);

    const promise = (async () => {
      try {
        const res  = await fetch(`api/probe_annotations_load.php?benutzer_id=${userData.musiker_id}&stimme_id=${userData.stimme_id}&page_number=${pageNum}`);
        const data = await res.json();
        const val  = (data.success && data.zeichnung) ? data.zeichnung : null;
        // Nur in Cache schreiben wenn kein neuerer saveNow() den Eintrag bereits gesetzt hat
        if (!this._annCache.has(key)) this._annCache.set(key, val);
        return this._annCache.get(key);
      } catch {
        return null;
      } finally {
        this._loadInflight.delete(key);
      }
    })();

    this._loadInflight.set(key, promise);
    return promise;
  }

  // Seiten im Hintergrund vorladen
  prefetchAnnotations(relPages) {
    relPages.forEach(p => this._loadAnnCached(p));
  }

  async loadAnnotations(pageRel) {
    if (!userData?.stimme_id) return;
    this.splitMode = false;

    // Generationszähler erhöhen: ältere, noch laufende Ladevorgänge werden verworfen
    const gen = ++this._loadGen;

    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    this.history = [];

    const zeichnung = await this._loadAnnCached(pageRel);

    // Abbrechen falls zwischenzeitlich ein neuerer Ladevorgang gestartet wurde
    if (gen !== this._loadGen) return;

    if (zeichnung) {
      const img  = new Image();
      img.onload = () => {
        if (gen !== this._loadGen) return;
        this.ctx.drawImage(img, 0, 0, this.canvas.width, this.canvas.height);
      };
      img.src = zeichnung;
    }
  }

  async loadSplitAnnotations(topPage, botPage, halfH, fullH) {
    if (!userData?.stimme_id) return;

    // Generationszähler für Split-Modus
    const gen = ++this._loadGen;

    this.splitMode    = true;
    this.splitTopPage = topPage;
    this.splitBotPage = botPage;
    this.splitHalfH   = halfH;
    this.splitFullH   = fullH;
    this.splitTopAnn  = null;
    this.splitBotAnn  = null;

    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    this.history = [];

    const loadImg = async (pageNum) => {
      const src = await this._loadAnnCached(pageNum);
      if (!src) return null;
      return new Promise(resolve => {
        const img = new Image();
        img.onload  = () => resolve(img);
        img.onerror = () => resolve(null);
        img.src = src;
      });
    };

    const w = this.canvas.width;

    if (topPage === botPage) {
      const img = await loadImg(topPage);
      if (gen !== this._loadGen) return;
      if (img) {
        this.splitTopAnn = img;
        this.splitBotAnn = img;
        const srcHalfH = Math.ceil(img.height / 2);
        this.ctx.drawImage(img, 0, 0,        img.width, srcHalfH,              0, 0,     w, halfH);
        this.ctx.drawImage(img, 0, srcHalfH, img.width, img.height - srcHalfH, 0, halfH, w, halfH);
      }
    } else {
      const [topImg, botImg] = await Promise.all([loadImg(topPage), loadImg(botPage)]);
      if (gen !== this._loadGen) return;
      if (topImg) {
        this.splitTopAnn = topImg;
        const srcHalfH = Math.ceil(topImg.height / 2);
        this.ctx.drawImage(topImg, 0, 0, topImg.width, srcHalfH, 0, 0, w, halfH);
      }
      if (botImg) {
        this.splitBotAnn = botImg;
        const srcHalfH = Math.ceil(botImg.height / 2);
        this.ctx.drawImage(botImg, 0, srcHalfH, botImg.width, botImg.height - srcHalfH, 0, halfH, w, halfH);
      }
    }
  }
}

window.drawingApp = null;
