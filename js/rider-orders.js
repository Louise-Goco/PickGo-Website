import { getAll, queryWhere, updateRecord } from './db.js';
import { getSession, setSession } from './session.js';

const REJECTED_KEY = 'pickgo_rejected_orders';

export function getRejectedOrders() {
  try {
    return JSON.parse(localStorage.getItem(REJECTED_KEY) || '[]');
  } catch {
    return [];
  }
}

export function addRejectedOrder(grpId) {
  const list = getRejectedOrders();
  if (!list.includes(grpId)) {
    list.push(grpId);
    localStorage.setItem(REJECTED_KEY, JSON.stringify(list));
  }
}

async function loadLookups() {
  const [sellers, merchants] = await Promise.all([getAll('sellers'), getAll('merchants')]);
  const sellerMap = {};
  for (const s of sellers) {
    sellerMap[s._id] = s;
    if (s.Seller_Id) sellerMap[s.Seller_Id] = s;
  }
  const merchMap = {};
  for (const m of merchants) {
    merchMap[m._id] = m;
    if (m.Merch_Id) merchMap[m.Merch_Id] = m;
  }
  return { sellerMap, merchMap, sellers, merchants };
}

export function resolveMerchantForOrder(order, sellerMap, merchMap) {
  const sid = order.Seller_Id;
  const seller = sellerMap[sid] || sellerMap[String(sid)];
  if (!seller) return null;
  return merchMap[seller.Merch_Id] || merchMap[String(seller.Merch_Id)] || null;
}

export function orderMatchesStation(order, sellerMap, merchMap, stationCity) {
  if (!stationCity) return false;
  const merch = resolveMerchantForOrder(order, sellerMap, merchMap);
  if (!merch || merch.Merch_Status !== 'active') return false;
  const seller = sellerMap[order.Seller_Id] || sellerMap[String(order.Seller_Id)];
  if (!seller || seller.Sellr_Status !== 'active') return false;
  const city = (merch.Merch_City || '').trim().toLowerCase();
  return city === String(stationCity).trim().toLowerCase();
}

/** Cities where at least one active merchant has an active seller. */
export async function getActiveMerchantCities() {
  const { sellers, merchants } = await loadLookups();
  const merchMap = {};
  for (const m of merchants) {
    merchMap[m._id] = m;
    if (m.Merch_Id) merchMap[String(m.Merch_Id)] = m;
  }
  const cities = new Set();
  for (const s of sellers) {
    if (s.Sellr_Status !== 'active') continue;
    const merch = merchMap[s.Merch_Id] || merchMap[String(s.Merch_Id)];
    if (merch?.Merch_Status === 'active' && merch.Merch_City) {
      cities.add(merch.Merch_City.trim());
    }
  }
  return [...cities].sort((a, b) => a.localeCompare(b));
}

export async function enrichOrder(order, lookups = null) {
  const { sellerMap, merchMap } = lookups || (await loadLookups());
  const merch = resolveMerchantForOrder(order, sellerMap, merchMap);
  return {
    ...order,
    Order_Id: order.Order_Id || order._id,
    Merch_Name: merch?.Merch_Name || 'Store',
    Store_Address: merch?.Merch_Address || '',
    Store_City: merch?.Merch_City || '',
  };
}

export async function getRiderOrders(riderId) {
  const all = await getAll('orders');
  return all.filter((o) => String(o.Rider_Id) === String(riderId));
}

export async function getTodayStats(riderId) {
  const orders = await getRiderOrders(riderId);
  const today = new Date().toISOString().slice(0, 10);
  const delivered = orders.filter((o) => {
    if (o.Order_Status !== 'delivered') return false;
    const d = (o.Order_Date || '').slice(0, 10);
    return d === today;
  });
  const trips = delivered.length;
  const earnings = delivered.reduce((s, o) => s + Number(o.Order_Total || 0) * 0.1, 0);
  return { trips, earnings };
}

export async function getActiveDeliveries(riderId) {
  const orders = await getRiderOrders(riderId);
  const active = orders.filter((o) =>
    ['ready_for_pickup', 'on_the_way'].includes(o.Order_Status)
  );
  const lookups = await loadLookups();
  return Promise.all(active.map((o) => enrichOrder(o, lookups)));
}

function batchReady(allOrders, batchId) {
  if (!batchId) return true;
  const batch = allOrders.filter((o) => o.Batch_Id === batchId);
  return batch.length > 0 && batch.every((o) => o.Order_Status === 'ready_for_pickup');
}

export function groupAvailableTrips(orders, rejected = []) {
  const open = orders.filter(
    (o) =>
      !o.Rider_Id &&
      o.Order_Status === 'ready_for_pickup' &&
      batchReady(orders, o.Batch_Id)
  );

  const groups = new Map();
  for (const o of open) {
    const grpId = o.Batch_Id || o._id;
    if (rejected.includes(grpId) || rejected.includes(String(grpId))) continue;
    const key = `${grpId}::${o.Delivery_Address}`;
    if (!groups.has(key)) {
      groups.set(key, {
        Grp_Id: grpId,
        Batch_Id: o.Batch_Id || null,
        Delivery_Address: o.Delivery_Address,
        Total_Amount: 0,
        Order_Ids: [],
        _orders: [],
      });
    }
    const g = groups.get(key);
    g.Total_Amount += Number(o.Order_Total || 0);
    g.Order_Ids.push(o._id);
    g._orders.push(o);
  }
  return [...groups.values()];
}

export async function enrichTripGroups(groups) {
  const lookups = await loadLookups();
  for (const g of groups) {
    const names = new Set();
    const addrs = new Set();
    for (const o of g._orders) {
      const e = await enrichOrder(o, lookups);
      names.add(e.Merch_Name);
      if (e.Store_Address) addrs.add(e.Store_Address);
    }
    g.Merch_Name = [...names].join(', ');
    g.Store_Address = [...addrs].join('; ');
    const cities = new Set();
    for (const o of g._orders) {
      const merch = resolveMerchantForOrder(o, lookups.sellerMap, lookups.merchMap);
      if (merch?.Merch_City) cities.add(merch.Merch_City);
    }
    g.Store_City = [...cities].join(', ');
    delete g._orders;
  }
  return groups;
}

export async function getAvailableTrips(rejected = getRejectedOrders(), stationCity = null) {
  const all = await getAll('orders');
  const lookups = await loadLookups();
  let orders = all;
  if (stationCity) {
    orders = all.filter((o) => orderMatchesStation(o, lookups.sellerMap, lookups.merchMap, stationCity));
  }
  const groups = groupAvailableTrips(orders, rejected);
  return enrichTripGroups(groups);
}

export async function fetchNextRequest(riderId, rejected = getRejectedOrders(), stationCity = null) {
  const riderOrders = await getRiderOrders(riderId);
  const busy = riderOrders.some((o) =>
    ['ready_for_pickup', 'on_the_way'].includes(o.Order_Status)
  );
  if (busy) return null;
  if (!stationCity) return null;

  const trips = await getAvailableTrips(rejected, stationCity);
  if (!trips.length) return null;
  const t = trips[0];
  return {
    id: t.Grp_Id,
    pickup: t.Merch_Name,
    pickup_address: t.Store_Address,
    delivery_address: t.Delivery_Address,
    earnings: (t.Total_Amount * 0.1).toFixed(2),
    total: t.Total_Amount.toFixed(2),
  };
}

export async function getOrderById(orderDocId, riderId) {
  const all = await getAll('orders');
  const order = all.find(
    (o) =>
      o._id === orderDocId ||
      String(o.Order_Id) === String(orderDocId)
  );
  if (!order || String(order.Rider_Id) !== String(riderId)) return null;
  const enriched = await enrichOrder(order);
  const merchants = await getAll('merchants');
  const sellers = await getAll('sellers');
  const seller = sellers.find((s) => s._id === order.Seller_Id || s.Seller_Id === order.Seller_Id);
  const merch = seller
    ? merchants.find((m) => m._id === seller.Merch_Id || m.Merch_Id === seller.Merch_Id)
    : null;
  const users = await getAll('users');
  const customer =
    users.find((u) => u._id === order.Customer_Id || String(u.id) === String(order.Customer_Id)) ||
    users.find((u) => u.email === order.Customer_Id);
  return {
    ...enriched,
    Cust_Fname: customer?.first_name || '',
    Cust_Lname: customer?.last_name || '',
    Cust_Phone: customer?.phone_number || '',
    Merch_ContactNumber: merch?.Merch_ContactNumber || '',
  };
}

export async function getOrderItems(orderDocId) {
  const all = await getAll('order_items');
  return all.filter(
    (i) =>
      i.Order_Id === orderDocId ||
      String(i.Order_Id) === String(orderDocId)
  );
}

export async function updateOrderStatus(orderDocId, status, extra = {}) {
  await updateRecord('orders', orderDocId, { Order_Status: status, ...extra });
}

export async function cancelDelivery(orderDocId) {
  await updateRecord('orders', orderDocId, {
    Rider_Id: null,
    Order_Status: 'pending',
  });
}

export async function markDelivered(orderDocId, riderId, proofPhoto = null) {
  const all = await getAll('orders');
  const order = all.find((o) => o._id === orderDocId);
  if (!order) return;
  const earnings = Number(order.Order_Total || 0) * 0.1;
  const patch = { Order_Status: 'delivered', Rider_Earnings: earnings };
  if (proofPhoto) patch.Order_ProofPhoto = proofPhoto;
  await updateRecord('orders', orderDocId, patch);
  const riders = await getAll('riders');
  const r = riders.find((x) => x._id === riderId);
  if (r) {
    await updateRecord('riders', riderId, {
      Rider_TotalDeliveries: (Number(r.Rider_TotalDeliveries) || 0) + 1,
    });
  }
}

export async function getRiderEarningsData(riderId) {
  const orders = await getRiderOrders(riderId);
  const delivered = orders.filter((o) => o.Order_Status === 'delivered');
  const lookups = await loadLookups();
  const trips = await Promise.all(
    delivered.map(async (o) => {
      const e = await enrichOrder(o, lookups);
      return {
        ...e,
        Rider_Earnings: o.Rider_Earnings ?? Number(o.Order_Total || 0) * 0.1,
      };
    })
  );
  trips.sort((a, b) => (b.Order_Date || '').localeCompare(a.Order_Date || ''));

  const allPayouts = await getAll('payouts');
  const payouts = allPayouts
    .filter(
      (p) =>
        p.User_Type === 'rider' &&
        (String(p.User_Id) === String(riderId) || p.User_Id === riderId)
    )
    .sort((a, b) => (b.Request_Date || '').localeCompare(a.Request_Date || ''));

  const totalEarned = delivered.reduce(
    (s, o) => s + Number(o.Rider_Earnings ?? Number(o.Order_Total || 0) * 0.1),
    0
  );
  const totalPaid = payouts
    .filter((p) => ['approved', 'processed'].includes(p.Payout_Status))
    .reduce((s, p) => s + Number(p.Amount || 0), 0);

  return { trips, payouts, balance: totalEarned - totalPaid, totalEarned };
}

export async function requestPayout(riderId, rider, amount) {
  const { addRecord } = await import('./db.js');
  const { nowIso } = await import('./utils.js');
  if (amount < 100) return { ok: false, error: 'Minimum payout balance is ₱100.00' };
  if (!rider.Rider_BankName || !rider.Rider_BankAccNo || !rider.Rider_BankAccName) {
    return { ok: false, error: 'Please complete bank info in profile first.' };
  }
  await addRecord('payouts', {
    User_Type: 'rider',
    User_Id: riderId,
    Amount: amount,
    Bank_Name: rider.Rider_BankName,
    Account_Number: rider.Rider_BankAccNo,
    Account_Name: rider.Rider_BankAccName,
    Payout_Status: 'pending',
    Request_Date: nowIso(),
  });
  return { ok: true };
}

export async function getRiderReviews(riderId) {
  const all = await getAll('reviews');
  const riderReviews = all.filter(
    (r) => String(r.Rider_Id) === String(riderId)
  );
  const users = await getAll('users');
  const userMap = Object.fromEntries(users.map((u) => [u._id, u]));

  const enriched = riderReviews.map((r) => {
    const u = userMap[r.Customer_Id] || userMap[String(r.Customer_Id)];
    return {
      ...r,
      first_name: u?.first_name || 'Customer',
      last_name: u?.last_name || '',
    };
  });
  enriched.sort((a, b) => (b.created_at || '').localeCompare(a.created_at || ''));

  const breakdown = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 };
  let sum = 0;
  for (const r of enriched) {
    const rating = Number(r.Rating) || 0;
    if (breakdown[rating] !== undefined) breakdown[rating]++;
    sum += rating;
  }
  const avg = enriched.length ? Math.round((sum / enriched.length) * 10) / 10 : 0;
  return { reviews: enriched, breakdown, avg_rating: avg, total: enriched.length };
}

export async function acceptOrder(riderId, orderId, stationCity = null) {
  const all = await getAll('orders');
  const lookups = await loadLookups();
  const idStr = String(orderId);

  let targets;
  if (idStr.startsWith('BATCH-')) {
    targets = all.filter((o) => o.Batch_Id === orderId && !o.Rider_Id);
  } else {
    targets = all.filter(
      (o) =>
        (o._id === orderId || String(o.Order_Id) === idStr || o.Batch_Id === orderId) &&
        !o.Rider_Id
    );
    if (!targets.length) {
      targets = all.filter((o) => o.Batch_Id === orderId && !o.Rider_Id);
    }
  }

  if (!targets.length) {
    return { success: false, message: 'Order already taken or unavailable' };
  }

  if (stationCity) {
    const allMatch = targets.every((o) =>
      orderMatchesStation(o, lookups.sellerMap, lookups.merchMap, stationCity)
    );
    if (!allMatch) {
      return { success: false, message: 'This order is outside your waiting station city.' };
    }
  }

  for (const o of targets) {
    await updateRecord('orders', o._id, {
      Rider_Id: riderId,
      Order_Status: 'ready_for_pickup',
    });
  }
  return { success: true };
}
