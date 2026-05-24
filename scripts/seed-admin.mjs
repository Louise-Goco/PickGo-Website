/**
 * One-time script: create admin user in Firestore users collection.
 * Run: node scripts/seed-admin.mjs
 */
import bcrypt from 'bcryptjs';
import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const config = JSON.parse(
  readFileSync(join(__dirname, '../js/firebase-config.js'), 'utf8')
    .replace(/export const firebaseConfig = /, '')
    .replace(/;$/, '')
);

const EMAIL = 'admin@gmail.com';
const PASSWORD = 'admin123';

async function getAccessToken() {
  const saPath = join(__dirname, '../firebase-service-account.json');
  let sa;
  try {
    sa = JSON.parse(readFileSync(saPath, 'utf8'));
  } catch {
    console.error('Missing firebase-service-account.json in project root.');
    console.error('Download from Firebase Console → Project Settings → Service Accounts.');
    process.exit(1);
  }

  const now = Math.floor(Date.now() / 1000);
  const header = Buffer.from(JSON.stringify({ alg: 'RS256', typ: 'JWT' })).toString('base64url');
  const payload = Buffer.from(
    JSON.stringify({
      iss: sa.client_email,
      scope: 'https://www.googleapis.com/auth/datastore',
      aud: 'https://oauth2.googleapis.com/token',
      iat: now,
      exp: now + 3600,
    })
  ).toString('base64url');

  const crypto = await import('crypto');
  const sign = crypto.createSign('RSA-SHA256');
  sign.update(`${header}.${payload}`);
  const sig = sign.sign(sa.private_key).toString('base64url');
  const jwt = `${header}.${payload}.${sig}`;

  const res = await fetch('https://oauth2.googleapis.com/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      grant_type: 'urn:ietf:params:oauth:grant-type:jwt-bearer',
      assertion: jwt,
    }),
  });
  const data = await res.json();
  if (!data.access_token) throw new Error('Token failed: ' + JSON.stringify(data));
  return { token: data.access_token, projectId: sa.project_id };
}

function encodeValue(v) {
  if (v === null) return { nullValue: null };
  if (typeof v === 'boolean') return { booleanValue: v };
  if (typeof v === 'number') return { integerValue: String(v) };
  if (typeof v === 'string') return { stringValue: v };
  return { stringValue: String(v) };
}

function encodeFields(obj) {
  const fields = {};
  for (const [k, v] of Object.entries(obj)) fields[k] = encodeValue(v);
  return fields;
}

async function queryUserByEmail(token, projectId, email) {
  const url = `https://firestore.googleapis.com/v1/projects/${projectId}/databases/(default)/documents:runQuery`;
  const body = {
    structuredQuery: {
      from: [{ collectionId: 'users' }],
      where: {
        fieldFilter: {
          field: { fieldPath: 'email' },
          op: 'EQUAL',
          value: { stringValue: email },
        },
      },
      limit: 1,
    },
  };
  const res = await fetch(url, {
    method: 'POST',
    headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  const data = await res.json();
  const doc = data.find((x) => x.document)?.document;
  if (!doc) return null;
  const id = doc.name.split('/').pop();
  const fields = {};
  for (const [k, v] of Object.entries(doc.fields || {})) {
    fields[k] = v.stringValue ?? v.booleanValue ?? v.integerValue ?? null;
  }
  return { id, fields };
}

async function createUser(token, projectId, fields) {
  const url = `https://firestore.googleapis.com/v1/projects/${projectId}/databases/(default)/documents/users`;
  const res = await fetch(url, {
    method: 'POST',
    headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({ fields: encodeFields(fields) }),
  });
  const data = await res.json();
  if (data.error) throw new Error(data.error.message);
  return data.name.split('/').pop();
}

async function updateUser(token, projectId, docId, fields) {
  const mask = Object.keys(fields).map((k) => `updateMask.fieldPaths=${k}`).join('&');
  const url = `https://firestore.googleapis.com/v1/projects/${projectId}/databases/(default)/documents/users/${docId}?${mask}`;
  const res = await fetch(url, {
    method: 'PATCH',
    headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({ fields: encodeFields(fields) }),
  });
  const data = await res.json();
  if (data.error) throw new Error(data.error.message);
}

async function main() {
  const salt = await bcrypt.genSalt(10);
  const hashed = await bcrypt.hash(PASSWORD, salt);
  const userData = {
    first_name: 'Admin',
    last_name: 'User',
    email: EMAIL,
    phone_number: '09000000000',
    password: hashed,
    user_type: 'admin',
    account_status: 'active',
    is_verified: true,
    profile_photo: '',
    created_at: new Date().toISOString(),
  };

  const { token, projectId } = await getAccessToken();
  const existing = await queryUserByEmail(token, projectId, EMAIL);

  if (existing) {
    await updateUser(token, projectId, existing.id, userData);
    console.log(`Updated existing user ${EMAIL} (doc: ${existing.id}) as admin.`);
  } else {
    const id = await createUser(token, projectId, userData);
    console.log(`Created admin user ${EMAIL} (doc: ${id}).`);
  }
  console.log('Login at login.html with the credentials above.');
}

main().catch((e) => {
  console.error(e.message || e);
  process.exit(1);
});
