// PDF Viewer – zeigt nur den Seitenbereich der gewählten Stimme

pdfjsLib.GlobalWorkerOptions.workerSrc = 'assets/js/libs/pdf.worker.min.js';

class PDFViewer {
  constructor(canvasId, pdfPath, pageFrom, pageTo) {
    this.canvas    = document.getElementById(canvasId);
    this.ctx       = this.canvas.getContext('2d');
    this.pdfPath   = pdfPath;
    this.pdfDoc    = null;

    this.pageFrom   = pageFrom || 1;
    this.pageTo     = pageTo   || 1;
    this.totalPages = this.pageTo - this.pageFrom + 1;

    this.currentRelPage = 1;

    this.splitPageMode = false;
    this.topRelPage    = 1;
    this.botRelPage    = 1;

    // Render-Cache: absPage → { canvas, scale }  (max. 6 Seiten)
    this._pageCache = new Map();

    // Bei Orientierungswechsel / Resize: neu rendern (debounced)
    let _resizeTimer;
    window.addEventListener('resize', () => {
      this._pageCache.clear();
      clearTimeout(_resizeTimer);
      _resizeTimer = setTimeout(() => {
        if (!this.pdfDoc) return;
        if (this.splitPageMode) this.renderSplit();
        else this.renderPage(this.currentRelPage);
      }, 120);
    });
  }

  calcScale(page) {
    const container = document.querySelector('.pdf-viewer');
    if (!container) return 1.0;
    const rect     = container.getBoundingClientRect();
    const availW   = rect.width;
    const availH   = rect.height;
    const unscaled = page.getViewport({ scale: 1 });
    // Exaktes Fit: eine ganze Seite füllt den verfügbaren Bereich
    return Math.max(0.1, Math.min(availW / unscaled.width, availH / unscaled.height));
  }

  // Seite rendern und cachen – gibt Offscreen-Canvas zurück
  async _renderToOffscreen(absPage, scale) {
    const cached = this._pageCache.get(absPage);
    if (cached && Math.abs(cached.scale - scale) < 0.001) return cached.canvas;

    const page     = await this.pdfDoc.getPage(absPage);
    const viewport = page.getViewport({ scale });
    const off      = document.createElement('canvas');
    off.width      = viewport.width;
    off.height     = viewport.height;
    await page.render({ canvasContext: off.getContext('2d'), viewport }).promise;

    if (this._pageCache.size >= 6) {
      this._pageCache.delete(this._pageCache.keys().next().value);
    }
    this._pageCache.set(absPage, { canvas: off, scale });
    return off;
  }

  // Nachbar-Seiten im Hintergrund pre-rendern
  _prefetchPages(relPages) {
    relPages.forEach(async rel => {
      if (rel < 1 || rel > this.totalPages) return;
      const abs  = this.pageFrom + rel - 1;
      if (this._pageCache.has(abs)) return;
      try {
        const page  = await this.pdfDoc.getPage(abs);
        const scale = this.calcScale(page);
        await this._renderToOffscreen(abs, scale);
      } catch {}
    });
  }

  async init() {
    if (!this.pdfPath) return;
    try {
      this.pdfDoc = await pdfjsLib.getDocument(this.pdfPath).promise;

      const maxPage   = this.pdfDoc.numPages;
      this.pageFrom   = Math.min(this.pageFrom, maxPage);
      this.pageTo     = Math.min(this.pageTo,   maxPage);
      this.totalPages = this.pageTo - this.pageFrom + 1;

      document.getElementById('page-total').innerText = this.totalPages;

      const startRel = (typeof userData !== 'undefined' && userData.initial_page)
        ? Math.min(userData.initial_page, this.totalPages) : 1;

      await this.renderPage(startRel);
    } catch (e) {
      console.error('PDF laden fehlgeschlagen:', e);
    }
  }

  async renderPage(pageRel) {
    if (!this.pdfDoc) return;
    if (this.splitPageMode) return this.renderSplit();

    const absPage = this.pageFrom + pageRel - 1;
    try {
      const page  = await this.pdfDoc.getPage(absPage);
      const scale = this.calcScale(page);
      const off   = await this._renderToOffscreen(absPage, scale);

      this.canvas.width  = off.width;
      this.canvas.height = off.height;
      this.ctx.drawImage(off, 0, 0);

      this.currentRelPage = pageRel;
      this._resetClip();

      const dc = document.getElementById('drawing-canvas');
      if (dc) { dc.width = off.width; dc.height = off.height; }

      var pn = document.getElementById('page-num');   if (pn) pn.innerText   = pageRel;
      var pt = document.getElementById('page-total'); if (pt) pt.innerText = this.totalPages;

      if (window.drawingApp) {
        window.drawingApp.splitMode = false;
        window.drawingApp.loadAnnotations(pageRel);
      }

      // Nachbarseiten im Hintergrund vorladen
      this._prefetchPages([pageRel - 1, pageRel + 1, pageRel + 2]);
      if (window.drawingApp) window.drawingApp.prefetchAnnotations(
        [pageRel - 1, pageRel + 1, pageRel + 2].filter(p => p >= 1 && p <= this.totalPages)
      );
    } catch (e) {
      console.error('Seite rendern fehlgeschlagen:', e);
    }
  }

  async renderSplit() {
    if (!this.pdfDoc) return;
    try {
      const absTop = this.pageFrom + this.topRelPage - 1;
      const absBot = this.pageFrom + this.botRelPage - 1;

      // Scale anhand der ersten Seite berechnen
      const refPage = await this.pdfDoc.getPage(absTop);
      const scale   = this.calcScale(refPage);

      // Beide Hälften aus Cache oder neu rendern (parallel)
      const [offTop, offBot] = await Promise.all([
        this._renderToOffscreen(absTop, scale),
        this._renderToOffscreen(absBot, scale)
      ]);

      const halfH = Math.ceil(offTop.height / 2);
      const w     = offTop.width;

      this.canvas.width  = w;
      this.canvas.height = halfH * 2;
      this.ctx.drawImage(offTop, 0, 0,     w, halfH, 0, 0,     w, halfH);
      this.ctx.drawImage(offBot, 0, halfH, w, halfH, 0, halfH, w, halfH);

      // Trennlinie
      this.ctx.save();
      this.ctx.strokeStyle = 'rgba(100,100,100,0.3)';
      this.ctx.lineWidth   = 1;
      this.ctx.setLineDash([6, 4]);
      this.ctx.beginPath();
      this.ctx.moveTo(0, halfH); this.ctx.lineTo(w, halfH);
      this.ctx.stroke();
      this.ctx.restore();

      this.currentRelPage = this.topRelPage;
      this._resetClip();

      const dc = document.getElementById('drawing-canvas');
      if (dc) { dc.width = w; dc.height = halfH * 2; }

      this._updateSplitIndicator();
      var pts = document.getElementById('page-total'); if (pts) pts.innerText = this.totalPages;

      if (window.drawingApp) {
        window.drawingApp.loadSplitAnnotations(
          this.topRelPage, this.botRelPage, halfH, offTop.height
        );
      }

      // Nächste Seiten prefetchen
      this._prefetchPages([this.topRelPage + 1, this.topRelPage + 2, this.botRelPage - 1]);
      if (window.drawingApp) window.drawingApp.prefetchAnnotations(
        [this.topRelPage + 1, this.topRelPage + 2, this.botRelPage - 1]
          .filter(p => p >= 1 && p <= this.totalPages)
      );
    } catch (e) {
      console.error('Split-Ansicht fehlgeschlagen:', e);
    }
  }

  _resetClip() {
    const inner     = document.getElementById('canvas-inner');
    const container = inner?.parentElement;
    if (inner)     inner.style.transform = '';
    if (container) { container.style.width = ''; container.style.height = ''; container.style.overflow = ''; }
  }

  _updateSplitIndicator() {
    const el = document.getElementById('page-num');
    if (el) el.innerText = this.topRelPage === this.botRelPage
      ? this.topRelPage
      : `${this.botRelPage}↓ ${this.topRelPage}↑`;
  }

  nextPage() {
    if (this.splitPageMode) {
      if (this.topRelPage === this.botRelPage) {
        if (this.topRelPage < this.totalPages) { this.topRelPage++; this.renderSplit(); }
      } else {
        this.botRelPage = this.topRelPage; this.renderSplit();
      }
    } else {
      if (this.currentRelPage < this.totalPages) this.renderPage(this.currentRelPage + 1);
    }
  }

  prevPage() {
    if (this.splitPageMode) {
      if (this.topRelPage === this.botRelPage) {
        if (this.botRelPage > 1) { this.botRelPage--; this.renderSplit(); }
      } else {
        this.topRelPage = this.botRelPage; this.renderSplit();
      }
    } else {
      if (this.currentRelPage > 1) this.renderPage(this.currentRelPage - 1);
    }
  }

  toggleSplitPageMode() {
    this.splitPageMode = !this.splitPageMode;
    if (this.splitPageMode) {
      this.topRelPage = this.currentRelPage;
      this.botRelPage = this.currentRelPage;
    }
    this.renderPage(this.currentRelPage);
    return this.splitPageMode;
  }

  getCurrentPage() { return this.currentRelPage; }
}
