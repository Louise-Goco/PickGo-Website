import { queryOne, queryWhere, updateRecord, addRecord } from './db.js';
import { getSession, setSession, clearSession } from './session.js';
import { nowIso } from './utils.js';
import { hashPassword, verifyPassword } from './bcrypt-util.js';

export { verifyPassword };

export async function login(email, password) {
  const emailNorm = email.trim().toLowerCase();

  const users = await queryWhere('users', 'email', '==', emailNorm);
  if (users.length) {
    const user = users[0];
    if (await verifyPassword(password, user.password)) {
      if (user.account_status === 'suspended') return { ok: false, error: 'Your account has been suspended.' };
      const cartItems = await queryWhere('cart', 'user_id', '==', user._id);
      setSession({
        user: emailNorm,
        user_type: user.user_type || 'customer',
        user_id: user._id,
        cart: cartItems.map((c) => ({ item_id: c.item_id, quantity: Number(c.quantity) || 1 })),
      });
      return { ok: true, user_type: user.user_type || 'customer', redirect: dashboardFor(user.user_type || 'customer') };
    }
  }

  const merchants = await queryWhere('merchants', 'Merch_Email', '==', emailNorm);
  if (merchants.length) {
    const merchant = merchants[0];
    const sellers = await queryWhere('sellers', 'Merch_Id', '==', merchant._id);
    if (sellers.length) {
      const seller = sellers[0];
      if (await verifyPassword(password, seller.Sellr_Password)) {
        const st = seller.Sellr_Status || 'pending';
        if (st === 'pending') return { ok: true, redirect: 'seller_register.html' };
        if (st === 'rejected') return { ok: false, error: 'Your seller application was rejected.' };
        if (st === 'suspended') return { ok: false, error: 'Your seller account is suspended.' };
        setSession({ user: emailNorm, user_type: 'seller', seller_id: seller._id });
        return { ok: true, user_type: 'seller', redirect: 'seller/dashboard.html' };
      }
    }
  }

  const riders = await queryWhere('riders', 'Rider_Email', '==', emailNorm);
  if (riders.length) {
    const rider = riders[0];
    if (await verifyPassword(password, rider.Rider_Password)) {
      const st = rider.Rider_Status || 'pending';
      if (st === 'pending') return { ok: true, redirect: 'rider_register.html' };
      if (st === 'rejected') return { ok: false, error: 'Your rider application was rejected.' };
      if (st === 'suspended') return { ok: false, error: 'Your rider account is suspended.' };
      setSession({ user: emailNorm, user_type: 'rider', rider_id: rider._id });
      await updateRecord('riders', rider._id, { Rider_Status: 'offline' });
      return { ok: true, user_type: 'rider', redirect: 'rider/dashboard.html' };
    }
  }

  return { ok: false, error: 'Invalid email or password.' };
}

function dashboardFor(type) {
  if (type === 'admin') return 'admin/dashboard.html';
  if (type === 'seller') return 'seller/dashboard.html';
  if (type === 'rider') return 'rider/dashboard.html';
  return 'customer/dashboard.html';
}

export async function registerCustomer(data) {
  const existing = await queryOne('users', 'email', data.email.trim().toLowerCase());
  if (existing) return { ok: false, error: 'Email already taken. Please choose another or sign in.' };

  const hashed = await hashPassword(data.password);
  await addRecord('users', {
    first_name: data.first_name.trim(),
    last_name: data.last_name.trim(),
    email: data.email.trim().toLowerCase(),
    phone_number: data.phone_number.trim(),
    password: hashed,
    user_type: 'customer',
    account_status: 'active',
    is_verified: false,
    profile_photo: null,
    created_at: nowIso(),
  });
  return { ok: true };
}

export async function logout() {
  const session = getSession();
  if (session?.user_type === 'rider' && session.user) {
    const rider = await queryOne('riders', 'Rider_Email', session.user);
    if (rider) {
      try {
        await updateRecord('riders', rider._id, { Rider_Status: 'offline' });
      } catch (_) {}
    }
  }
  clearSession();
  window.location.href = resolvePath('login.html');
}

/** Resolve relative path from nested folders */
export function resolvePath(target) {
  const depth = (window.location.pathname.match(/\//g) || []).length;
  const inSub = /\/(admin|customer|seller|rider)\//.test(window.location.pathname);
  if (!inSub) return target;
  if (target.startsWith('../')) return target;
  return '../' + target;
}

export async function loadCurrentUser() {
  const session = getSession();
  if (!session?.user) return null;
  const user = await queryOne('users', 'email', session.user);
  if (!user || user.account_status !== 'active') {
    clearSession();
    window.location.href = resolvePath('login.html');
    return null;
  }
  return user;
}
