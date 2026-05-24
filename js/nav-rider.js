import { resolvePath } from './auth.js';

export function renderRiderNav(activePage = 'dashboard') {
  const root = document.getElementById('rider-nav-root');
  if (!root) return;
  const items = [
    ['dashboard', 'dashboard.html', 'Dashboard'],
    ['requests', 'check_requests.html', 'Delivery Requests'],
    ['earnings', 'earnings.html', 'Earnings'],
    ['reviews', 'reviews.html', 'Reviews'],
    ['profile', 'profile.html', 'Profile'],
  ];
  const links = items
    .map(([key, href, label]) => `<a href="${href}" class="nav-item ${activePage === key ? 'active' : ''}">${label}</a>`)
    .join('');

  root.innerHTML = `
    <style>
      .sidebar { background:#0f172a;color:#fff;padding:40px 24px;position:fixed;top:0;left:0;height:100vh;width:260px;z-index:1000;box-sizing:border-box; }
      .sidebar-brand { font-size:26px;font-weight:800;color:#10b981;margin-bottom:48px; }
      .sidebar-nav { display:flex;flex-direction:column;gap:8px; }
      .nav-item { padding:14px 20px;border-radius:12px;color:#94a3b8;text-decoration:none;font-weight:600; }
      .nav-item.active { background:#10b981;color:#fff; }
      .main-content { margin-left:260px;padding:40px;background:#f8fafc;min-height:100vh; }
    </style>
    <aside class="sidebar">
      <div class="sidebar-brand">PickGo Rider</div>
      <nav class="sidebar-nav">${links}
        <a href="#" class="nav-item" style="margin-top:auto;color:#ef4444;" id="rider-logout-link">Sign Out</a>
      </nav>
    </aside>`;
  document.getElementById('rider-logout-link')?.addEventListener('click', async (e) => {
    e.preventDefault();
    const { logout } = await import('./auth.js');
    await logout();
  });
}
