import { getSession, clearSession } from './session.js';
import { queryOne } from './db.js';
import { resolvePath } from './auth.js';

export async function requireAuth(allowedTypes = null) {
  const session = getSession();
  if (!session?.user) {
    window.location.href = resolvePath('login.html');
    return null;
  }

  if (allowedTypes && !allowedTypes.includes(session.user_type)) {
    window.location.href = resolvePath('login.html');
    return null;
  }

  if (session.user_type === 'admin' || session.user_type === 'customer') {
    const user = await queryOne('users', 'email', session.user);
    if (!user || user.account_status !== 'active') {
      clearSession();
      window.location.href = resolvePath('login.html');
      return null;
    }
    return { session, user };
  }

  return { session, user: null };
}

export async function requireAdmin() {
  return requireAuth(['admin']);
}

export async function requireCustomer() {
  return requireAuth(['customer', 'admin']);
}

export async function requireSeller() {
  const session = getSession();
  if (!session?.user || session.user_type !== 'seller') {
    window.location.href = resolvePath('login.html');
    return null;
  }
  return { session };
}

export async function requireRider() {
  const session = getSession();
  if (!session?.user || session.user_type !== 'rider') {
    window.location.href = resolvePath('login.html');
    return null;
  }
  return { session };
}

export function redirectIfLoggedIn() {
  const session = getSession();
  if (!session?.user) return;
  const t = session.user_type;
  if (t === 'admin') window.location.href = resolvePath('admin/dashboard.html');
  else if (t === 'seller') window.location.href = resolvePath('seller/dashboard.html');
  else if (t === 'rider') window.location.href = resolvePath('rider/dashboard.html');
  else window.location.href = resolvePath('customer/dashboard.html');
}
