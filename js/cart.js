import {
  queryWhere,
  addRecord,
  updateRecord,
  deleteRecord,
  queryOne,
} from './db.js';
import { getSession, setSession } from './session.js';

export function getCart() {
  return getSession()?.cart || [];
}

function setCart(cart) {
  setSession({ cart });
}

async function findCartDoc(userId, itemId) {
  const rows = await queryWhere('cart', 'user_id', '==', userId);
  return rows.find((r) => String(r.item_id) === String(itemId)) || null;
}

export async function addToCart(itemId, quantity = 1) {
  const session = getSession();
  if (!session?.user_id) return false;
  const qty = Math.max(1, Number(quantity) || 1);
  const cart = [...getCart()];
  const idx = cart.findIndex((i) => String(i.item_id) === String(itemId));
  if (idx >= 0) cart[idx].quantity += qty;
  else cart.push({ item_id: String(itemId), quantity: qty });
  setCart(cart);

  const existing = await findCartDoc(session.user_id, itemId);
  if (existing) {
    await updateRecord('cart', existing._id, {
      quantity: (Number(existing.quantity) || 0) + qty,
    });
  } else {
    await addRecord('cart', {
      user_id: session.user_id,
      item_id: String(itemId),
      quantity: qty,
    });
  }
  return true;
}

export async function updateCartItem(itemId, change) {
  const session = getSession();
  if (!session?.user_id) return false;
  const delta = Number(change) || 0;
  const cart = [...getCart()];
  const idx = cart.findIndex((i) => String(i.item_id) === String(itemId));
  if (idx < 0) return false;

  cart[idx].quantity += delta;
  if (cart[idx].quantity <= 0) cart.splice(idx, 1);
  setCart(cart);

  const existing = await findCartDoc(session.user_id, itemId);
  if (!existing) return true;

  const newQty = (Number(existing.quantity) || 0) + delta;
  if (newQty <= 0) await deleteRecord('cart', existing._id);
  else await updateRecord('cart', existing._id, { quantity: newQty });
  return true;
}

export async function removeFromCart(itemId) {
  const session = getSession();
  if (!session?.user_id) return false;
  setCart(getCart().filter((i) => String(i.item_id) !== String(itemId)));
  const existing = await findCartDoc(session.user_id, itemId);
  if (existing) await deleteRecord('cart', existing._id);
  return true;
}

export async function clearCartFirestore(userId) {
  const rows = await queryWhere('cart', 'user_id', '==', userId);
  await Promise.all(rows.map((r) => deleteRecord('cart', r._id)));
  setSession({ cart: [] });
}

export async function applyPromo(promoCode) {
  const code = String(promoCode || '').trim().toUpperCase();
  if (!code) return { ok: false, error: 'Enter a promo code.' };

  const promos = await queryWhere('promo_codes', 'Code', '==', code);
  const promo = promos.find((p) => p.Is_Active === true || p.Is_Active === 1);
  if (!promo) return { ok: false, error: 'Invalid or expired promo code.' };

  const expiry = promo.Expiry_Date ? new Date(promo.Expiry_Date) : null;
  if (expiry && expiry < new Date(new Date().toDateString())) {
    return { ok: false, error: 'Invalid or expired promo code.' };
  }

  const limit = Number(promo.Usage_Limit) || 0;
  const usage = Number(promo.Current_Usage) || 0;
  if (limit > 0 && usage >= limit) {
    return { ok: false, error: 'Invalid or expired promo code.' };
  }

  setSession({
    applied_promo: {
      id: promo._id || promo.Promo_Id,
      code: promo.Code,
      type: promo.Discount_Type,
      value: Number(promo.Discount_Value) || 0,
    },
    promo_msg: 'Promo code applied successfully!',
    promo_err: null,
  });
  return { ok: true, message: 'Promo code applied successfully!' };
}

export async function removePromo() {
  setSession({
    applied_promo: null,
    promo_msg: 'Promo code removed.',
    promo_err: null,
  });
}

export function calcPromoDiscount(subtotal, appliedPromo) {
  if (!appliedPromo || subtotal <= 0) return 0;
  if (appliedPromo.type === 'percentage') {
    return Math.round((subtotal * appliedPromo.value) / 100 * 100) / 100;
  }
  return Math.min(subtotal, appliedPromo.value);
}

export async function getItemDetails(itemId) {
  const { getById } = await import('./db.js');
  let item = await getById('items', String(itemId));
  if (!item) {
    const rows = await queryWhere('items', 'Item_Id', '==', itemId);
    item = rows[0];
  }
  return item ? enrichItem(item) : null;
}

async function enrichItem(item) {
  const { getById } = await import('./db.js');
  const sellerId = item.Seller_Id;
  let seller = await getById('sellers', String(sellerId));
  if (!seller) {
    const sellers = await queryWhere('sellers', 'Seller_Id', '==', sellerId);
    seller = sellers[0];
  }
  let merchant = null;
  if (seller) {
    merchant = await getById('merchants', String(seller.Merch_Id));
    if (!merchant) {
      const merchs = await queryWhere('merchants', 'Merch_Id', '==', seller.Merch_Id);
      merchant = merchs[0];
    }
  }
  return {
    ...item,
    Item_Id: item.Item_Id || item._id,
    Merch_Name: merchant?.Merch_Name || '',
    Merch_Id: merchant?._id || merchant?.Merch_Id,
    Seller_Id: seller?._id || String(seller?.Seller_Id || sellerId),
  };
}

export async function loadCartDetails() {
  const cart = getCart();
  const rows = [];
  for (const entry of cart) {
    const item = await getItemDetails(entry.item_id);
    if (item) rows.push({ ...item, quantity: entry.quantity });
  }
  return rows;
}
