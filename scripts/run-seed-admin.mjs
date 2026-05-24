import { initializeApp } from 'firebase/app';
import { getFirestore, collection, addDoc, query, where, getDocs, updateDoc, doc } from 'firebase/firestore';
import bcrypt from 'bcryptjs';

const firebaseConfig = {
  apiKey: 'AIzaSyAGxUHsRjL0PD4xGzLasrVS5BcGCRRWXOI',
  authDomain: 'pickup-3f95d.firebaseapp.com',
  projectId: 'pickup-3f95d',
  storageBucket: 'pickup-3f95d.firebasestorage.app',
  messagingSenderId: '229110399876',
  appId: '1:229110399876:web:c766076d6408ee564e93d1',
};

const EMAIL = 'admin@gmail.com';
const PASSWORD = 'admin123';

const app = initializeApp(firebaseConfig);
const db = getFirestore(app);

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
  profile_photo: null,
  created_at: new Date().toISOString(),
};

const q = query(collection(db, 'users'), where('email', '==', EMAIL));
const snap = await getDocs(q);

if (!snap.empty) {
  const ref = snap.docs[0].ref;
  await updateDoc(ref, userData);
  console.log(`Updated admin user ${EMAIL} (id: ${snap.docs[0].id})`);
} else {
  const ref = await addDoc(collection(db, 'users'), userData);
  console.log(`Created admin user ${EMAIL} (id: ${ref.id})`);
}

console.log('Done. Login at login.html with admin@gmail.com / admin123');
process.exit(0);
