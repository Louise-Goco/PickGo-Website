import {
  collection,
  doc,
  getDoc,
  getDocs,
  addDoc,
  setDoc,
  updateDoc,
  deleteDoc,
  query,
  where,
  orderBy,
  limit,
  writeBatch,
} from 'https://www.gstatic.com/firebasejs/11.6.0/firebase-firestore.js';
import { db } from './firebase-init.js';

export { db };

export function col(name) {
  return collection(db, name);
}

export async function getById(collectionName, id) {
  const snap = await getDoc(doc(db, collectionName, id));
  if (!snap.exists()) return null;
  return { _id: snap.id, ...snap.data() };
}

export async function queryWhere(collectionName, field, op, value) {
  const q = query(col(collectionName), where(field, op, value));
  const snap = await getDocs(q);
  return snap.docs.map((d) => ({ _id: d.id, ...d.data() }));
}

export async function queryOne(collectionName, field, value) {
  const rows = await queryWhere(collectionName, field, '==', value);
  return rows[0] || null;
}

export async function getAll(collectionName, orderField = null, dir = 'asc') {
  let q = col(collectionName);
  if (orderField) {
    q = query(col(collectionName), orderBy(orderField, dir));
  }
  const snap = await getDocs(q);
  return snap.docs.map((d) => ({ _id: d.id, ...d.data() }));
}

export async function addRecord(collectionName, data) {
  const ref = await addDoc(col(collectionName), data);
  return ref.id;
}

export async function setRecord(collectionName, id, data) {
  await setDoc(doc(db, collectionName, id), data);
}

export async function updateRecord(collectionName, id, data) {
  await updateDoc(doc(db, collectionName, id), data);
}

export async function deleteRecord(collectionName, id) {
  await deleteDoc(doc(db, collectionName, id));
}

export async function getSetting(key, defaultValue = '') {
  const row = await queryOne('settings', 'Setting_Key', key);
  return row?.Setting_Value ?? defaultValue;
}

export async function getSettingsMap() {
  const rows = await getAll('settings');
  const map = {};
  for (const r of rows) {
    map[r.Setting_Key] = r.Setting_Value;
  }
  return map;
}

/** Resolve seller + merchant for logged-in store email */
export async function getSellerByStoreEmail(email) {
  const merchant = await queryOne('merchants', 'Merch_Email', email);
  if (!merchant) return null;
  const sellers = await queryWhere('sellers', 'Merch_Id', '==', merchant._id);
  if (!sellers.length) return null;
  return { seller: sellers[0], merchant };
}

/** Resolve a seller reference to the Firestore document id. */
export async function resolveSellerDocId(sellerRef) {
  if (!sellerRef) return null;
  const key = String(sellerRef);
  let seller = await getById('sellers', key);
  if (!seller) {
    const rows = await queryWhere('sellers', 'Seller_Id', '==', sellerRef);
    seller = rows[0];
  }
  return seller?._id || key;
}

/** Collect seller id variants used across legacy and current records. */
export function sellerIdKeys(seller) {
  const keys = new Set();
  if (seller?._id) keys.add(String(seller._id));
  if (seller?.Seller_Id != null) keys.add(String(seller.Seller_Id));
  return keys;
}

export function recordMatchesSeller(record, seller) {
  if (!record || !seller) return false;
  const keys = sellerIdKeys(seller);
  return keys.has(String(record.Seller_Id));
}

/** Fetch orders for a seller regardless of Seller_Id format stored on the order. */
export async function getOrdersForSeller(seller) {
  const all = await getAll('orders');
  return all.filter((o) => recordMatchesSeller(o, seller));
}

/** Look up a customer user from an order Customer_Id (Firestore doc id). */
export async function getUserByRef(userRef) {
  if (!userRef) return null;
  const key = String(userRef);
  let user = await getById('users', key);
  if (!user) user = await queryOne('users', 'email', key);
  return user;
}

export function orderItemsForOrder(allItems, order) {
  const docId = String(order._id || '');
  const displayId = order.Order_Id != null ? String(order.Order_Id) : '';
  return allItems.filter((item) => {
    const ref = String(item.Order_Id || '');
    return ref === docId || (displayId && ref === displayId);
  });
}

export async function getItemsWithMerchants(filters = {}) {
  const [items, sellers, merchants] = await Promise.all([
    getAll('items'),
    getAll('sellers'),
    getAll('merchants'),
  ]);
  const sellerMap = {};
  sellers.forEach((s) => {
    sellerMap[s._id] = s;
    if (s.Seller_Id != null) sellerMap[String(s.Seller_Id)] = s;
  });
  const merchMap = {};
  merchants.forEach((m) => {
    merchMap[m._id] = m;
    if (m.Merch_Id != null) merchMap[String(m.Merch_Id)] = m;
  });

  return items
    .map((item) => {
      const seller = sellerMap[item.Seller_Id] || sellerMap[item.Seller_Id?.toString()];
      const merchant = seller ? merchMap[seller.Merch_Id] || merchMap[seller.Merch_Id?.toString()] : null;
      return {
        ...item,
        Item_Id: item._id,
        Merch_Name: merchant?.Merch_Name || '',
        seller,
        merchant,
      };
    })
    .filter((item) => {
      if (filters.status && item.Item_Status !== filters.status) return false;
      if (filters.sellerActive && item.seller?.Sellr_Status !== 'active') return false;
      if (filters.merchActive && item.merchant?.Merch_Status !== 'active') return false;
      if (filters.categoryId && String(item.Item_Category) !== String(filters.categoryId)) return false;
      if (filters.search) {
        const s = filters.search.toLowerCase();
        if (!item.Item_Name?.toLowerCase().includes(s) && !item.Merch_Name?.toLowerCase().includes(s)) return false;
      }
      return true;
    });
}

export { writeBatch, doc, limit };
