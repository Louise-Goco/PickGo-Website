const SESSION_KEY = 'pickgo_session';

/** @typedef {{ user: string, user_type: string, user_id?: string, seller_id?: string, rider_id?: string, cart?: Array<{item_id: string, quantity: number}>, applied_promo?: object }} Session */

/** @returns {Session|null} */
export function getSession() {
  try {
    const raw = localStorage.getItem(SESSION_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

/** @param {Partial<Session>} data */
export function setSession(data) {
  const current = getSession() || {};
  localStorage.setItem(SESSION_KEY, JSON.stringify({ ...current, ...data }));
}

export function clearSession() {
  localStorage.removeItem(SESSION_KEY);
}

export function isLoggedIn() {
  return !!getSession()?.user;
}
