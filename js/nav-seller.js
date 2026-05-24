import { resolvePath } from './auth.js';

export function renderSellerNav(activePage = 'dashboard') {
  const root = document.getElementById('seller-nav-root');
  if (!root) return;
  const items = [
    ['dashboard', 'dashboard.html', 'Dashboard'],
    ['orders', 'manage_orders.html', 'Orders'],
    ['products', 'manage_items.html', 'Products'],
    ['analytics', 'analytics.html', 'Analytics'],
    ['payouts', 'payouts.html', 'Payouts'],
    ['reviews', 'reviews.html', 'Reviews'],
    ['profile', 'store_profile.html', 'Store Profile'],
  ];
  const links = items
    .map(
      ([key, href, label]) =>
        `<a href="${href}" class="nav-item ${activePage === key ? 'active' : ''}">${label}</a>`
    )
    .join('');

  root.innerHTML = `
    <style>
      .sidebar { background:#0f172a;color:#fff;padding:40px 24px;position:fixed;top:0;left:0;height:100vh;width:260px;z-index:1000;box-sizing:border-box; }
      .sidebar-brand { font-size:26px;font-weight:800;color:#f97316;margin-bottom:48px;padding-left:12px; }
      .sidebar-nav { display:flex;flex-direction:column;gap:8px;height:calc(100% - 100px); }
      .nav-item { padding:14px 20px;border-radius:12px;color:#94a3b8;text-decoration:none;font-weight:600;display:flex;align-items:center; }
      .nav-item.active { background:#f97316;color:#fff; }
      .logout-item { margin-top:auto;color:#ef4444!important;padding-top:24px;border-top:1px solid rgba(255,255,255,0.05); }
      .main-content { margin-left:260px;padding:40px;background:#f8fafc;min-height:100vh; }
    </style>
    <aside class="sidebar">
      <div class="sidebar-brand">PickGo</div>
      <nav class="sidebar-nav">${links}
        <a href="#" class="nav-item logout-item" id="seller-logout-link">Sign Out</a>
      </nav>
    </aside>`;
  document.getElementById('seller-logout-link')?.addEventListener('click', async (e) => {
    e.preventDefault();
    const { logout } = await import('./auth.js');
    await logout();
  });
}
