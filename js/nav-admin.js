import { resolvePath } from './auth.js';

export function renderAdminNav() {
  const root = document.getElementById('admin-nav-root');
  if (!root) return;
  const p = (f) => resolvePath(`admin/${f}`);
  root.innerHTML = `
    <style>
      .nav-bar { width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 20px;
        background: #ffffff; padding: 15px 40px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border-bottom: 1px solid rgba(0,0,0,0.05); position: fixed; top: 0; left: 0; z-index: 1000; box-sizing: border-box; }
      .nav-brand { font-size: 24px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; }
      .nav-links { display: flex; gap: 25px; flex-wrap: wrap; }
      .nav-actions { display: flex; gap: 25px; align-items: center; }
      .nav-links a, .nav-actions a { color: #0f172a; text-decoration: none; font-weight: 600; font-size: 14px; }
      .nav-links a:hover, .nav-actions a:hover { color: #f97316; }
      .nav-spacer { height: 75px; }
    </style>
    <nav class="nav-bar">
      <div class="nav-brand">PickGo Admin</div>
      <div class="nav-links">
        <a href="${p('dashboard.html')}">Overview</a>
        <a href="${p('manage_customers.html')}">Manage Users</a>
        <a href="${p('manage_sellers.html')}">Manage Sellers</a>
        <a href="${p('manage_riders.html')}">Manage Riders</a>
        <a href="${p('manage_categories.html')}">Manage Categories</a>
        <a href="${p('manage_payouts.html')}">Manage Payouts</a>
        <a href="${p('settings.html')}">System Settings</a>
        <a href="${p('manage_orders.html')}">Manage Orders</a>
      </div>
      <div class="nav-actions">
        <a href="#" id="admin-logout-link">Logout</a>
      </div>
    </nav>
    <div class="nav-spacer"></div>`;
  document.getElementById('admin-logout-link')?.addEventListener('click', async (e) => {
    e.preventDefault();
    const { logout } = await import('./auth.js');
    await logout();
  });
}
