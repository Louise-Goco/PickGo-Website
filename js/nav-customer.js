import { getSession } from './session.js';
import { queryOne } from './db.js';
import { resolvePath } from './auth.js';

export async function renderCustomerNav() {
  const root = document.getElementById('customer-nav-root');
  if (!root) return;
  const session = getSession();
  const isGuest = !session?.user;
  let isSeller = false;
  let isRider = false;
  if (session?.user) {
    const [s, r] = await Promise.all([
      queryOne('sellers', 'Sellr_Email', session.user),
      queryOne('riders', 'Rider_Email', session.user),
    ]);
    isSeller = !!s;
    isRider = !!r;
  }
  const cartCount = (session?.cart || []).reduce((n, i) => n + (i.quantity || 0), 0);
  const badge = cartCount > 0
    ? `<span style="position:absolute;top:-8px;right:-12px;background:#ef4444;color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:10px;">${cartCount}</span>`
    : '';

  const guestLinks = `
      <div class="nav-links">
        <a href="browse_items.html">Foods</a>
        <a href="browse_stores.html">Stores</a>
        <a href="${resolvePath('seller_register.html')}" style="color:#f97316;">Start Selling</a>
        <a href="${resolvePath('rider_register.html')}" style="color:#10b981;">Drive with us</a>
      </div>
      <div class="nav-actions">
        <a href="${resolvePath('login.html')}" style="padding:10px 20px;border-radius:10px;border:1px solid #e2e8f0;">Login</a>
        <a href="${resolvePath('register.html')}" style="padding:10px 20px;border-radius:10px;background:#f97316;color:#fff !important;">Sign Up</a>
      </div>`;

  const memberLinks = `
      <div class="nav-links">
        <a href="dashboard.html">Dashboard</a>
        <a href="browse_items.html">Foods</a>
        <a href="browse_stores.html">Stores</a>
        ${!isSeller ? `<a href="${resolvePath('seller_register.html')}" style="color:#f97316;">Start Selling</a>` : ''}
        ${!isRider ? `<a href="${resolvePath('rider_register.html')}" style="color:#10b981;">Drive with us</a>` : ''}
      </div>
      <div class="nav-actions">
        <a href="cart.html" style="position:relative;display:flex;align-items:center;gap:6px;">Cart ${badge}</a>
        <a href="profile.html">Profile</a>
        <a href="#" id="customer-logout-link">Logout</a>
      </div>`;

  root.innerHTML = `
    <style>
      .nav-bar { width:100%;display:flex;justify-content:space-between;align-items:center;gap:20px;background:#fff;
        padding:15px 40px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);border-bottom:1px solid rgba(0,0,0,0.05);
        position:fixed;top:0;left:0;z-index:1000;box-sizing:border-box; }
      .nav-brand { font-size:24px;font-weight:800;color:#f97316;text-decoration:none; }
      .nav-links { display:flex;gap:40px; }
      .nav-actions { display:flex;gap:30px;align-items:center; }
      .nav-links a,.nav-actions a { color:#0f172a;text-decoration:none;font-weight:600;font-size:15px; }
      .nav-spacer { height:75px; }
    </style>
    <nav class="nav-bar">
      <a href="${isGuest ? resolvePath('index.html') : 'dashboard.html'}" class="nav-brand">PickGo</a>
      ${isGuest ? guestLinks : memberLinks}
    </nav>
    <div class="nav-spacer"></div>`;
  document.getElementById('customer-logout-link')?.addEventListener('click', async (e) => {
    e.preventDefault();
    const { logout } = await import('./auth.js');
    await logout();
  });
}
