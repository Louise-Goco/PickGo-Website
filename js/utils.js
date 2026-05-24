export function escapeHtml(str) {
  if (str == null) return '';
  const div = document.createElement('div');
  div.textContent = String(str);
  return div.innerHTML;
}

export function formatMoney(n) {
  return '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export function showAlert(containerId, message, type = 'error') {
  const el = document.getElementById(containerId);
  if (!el) return;
  el.innerHTML = `<div class="alert alert-${type}">${escapeHtml(message)}</div>`;
  el.style.display = message ? 'block' : 'none';
}

export function getQueryParam(name) {
  return new URLSearchParams(window.location.search).get(name);
}

export function shuffle(arr) {
  const a = [...arr];
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

export function nowIso() {
  return new Date().toISOString();
}

export function imgUrl(path, fallback) {
  if (!path) return fallback;
  if (path.startsWith('data:') || path.startsWith('blob:')) return path;
  if (path.startsWith('http://') || path.startsWith('https://')) return path;
  if (path.startsWith('../') || path.startsWith('./')) return path;
  const inSubfolder = /\/(customer|seller|admin|rider)\//.test(window.location.pathname);
  return inSubfolder ? `../${path.replace(/^\.\.\//, '')}` : path;
}

export function docViewButton(path, label) {
  if (!path) return '';
  const url = imgUrl(path);
  const safeLabel = escapeHtml(label);
  const safeUrl = escapeHtml(url);
  return `<button type="button" class="doc-view-btn" data-doc-src="${safeUrl}" data-doc-label="${safeLabel}" style="display:inline-flex;align-items:center;gap:4px;font-size:12px;color:#0ea5e9;background:none;border:none;padding:0;cursor:pointer;text-decoration:underline;margin-bottom:4px;">${safeLabel}</button>`;
}

export function initDocViewer() {
  if (document.getElementById('doc-viewer-modal')) return;

  const modal = document.createElement('div');
  modal.id = 'doc-viewer-modal';
  modal.innerHTML = `
    <style>
      #doc-viewer-modal { display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.85);
        align-items:center; justify-content:center; padding:24px; box-sizing:border-box; }
      #doc-viewer-modal.open { display:flex; }
      #doc-viewer-panel { background:#fff; border-radius:16px; max-width:900px; width:100%; max-height:90vh;
        overflow:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35); }
      #doc-viewer-header { display:flex; justify-content:space-between; align-items:center; padding:16px 20px;
        border-bottom:1px solid #e2e8f0; }
      #doc-viewer-header h3 { margin:0; font-size:16px; color:#0f172a; }
      #doc-viewer-close { background:#f1f5f9; border:none; border-radius:8px; padding:8px 14px; cursor:pointer; font-weight:600; }
      #doc-viewer-body { padding:20px; text-align:center; }
      #doc-viewer-body img { max-width:100%; max-height:70vh; border-radius:8px; }
      #doc-viewer-body iframe { width:100%; height:70vh; border:none; border-radius:8px; }
    </style>
    <div id="doc-viewer-panel">
      <div id="doc-viewer-header">
        <h3 id="doc-viewer-title">Document</h3>
        <button type="button" id="doc-viewer-close">Close</button>
      </div>
      <div id="doc-viewer-body"></div>
    </div>`;
  document.body.appendChild(modal);

  const close = () => {
    modal.classList.remove('open');
    document.getElementById('doc-viewer-body').innerHTML = '';
  };
  modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
  document.getElementById('doc-viewer-close').addEventListener('click', close);

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.doc-view-btn');
    if (!btn) return;
    e.preventDefault();
    const src = btn.dataset.docSrc;
    const label = btn.dataset.docLabel || 'Document';
    document.getElementById('doc-viewer-title').textContent = label;
    const body = document.getElementById('doc-viewer-body');
  const isPdf = src.startsWith('data:application/pdf') || /\.pdf($|\?)/i.test(src);
    body.innerHTML = isPdf
      ? `<iframe src="${src}" title="${escapeHtml(label)}"></iframe>`
      : `<img src="${src}" alt="${escapeHtml(label)}">`;
    modal.classList.add('open');
  });
}

export async function promptLoginForCart() {
  if (confirm('Please sign in to add items to your cart. Go to login?')) {
    const { resolvePath } = await import('./auth.js');
    window.location.href = resolvePath('login.html');
  }
}

export function formatDate(iso, opts = {}) {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return String(iso);
  return d.toLocaleString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    ...opts,
  });
}

export function formatTime(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return String(iso);
  return d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
}

/** Human-readable order number for UI (PG-XXXXXXXX). */
export function displayOrderId(order) {
  if (!order) return '—';
  if (order.Order_Id) return String(order.Order_Id);
  if (order._id) return 'PG-' + String(order._id).slice(-8).toUpperCase();
  return '—';
}
