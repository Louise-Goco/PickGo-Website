// Import the functions you need from the SDKs you need
import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js";
import { getAnalytics } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-analytics.js";
// You can also import other Firebase services here as needed, for example:
// import { getAuth } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-auth.js";
// import { getFirestore } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-firestore.js";
// import { getDatabase } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-database.js";
// import { getStorage } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-storage.js";

// Your web app's Firebase configuration
// For Firebase JS SDK v7.20.0 and later, measurementId is optional
const firebaseConfig = {
  apiKey: "AIzaSyAGxUHsRjL0PD4xGzLasrVS5BcGCRRWXOI",
  authDomain: "pickup-3f95d.firebaseapp.com",
  projectId: "pickup-3f95d",
  storageBucket: "pickup-3f95d.firebasestorage.app",
  messagingSenderId: "229110399876",
  appId: "1:229110399876:web:c766076d6408ee564e93d1",
  measurementId: "G-JKYDDTSRFH"
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);
const analytics = getAnalytics(app);

// Initialize other services if you uncommented their imports above:
// const auth = getAuth(app);
// const db = getFirestore(app);
// const rtdb = getDatabase(app);
// const storage = getStorage(app);

export { app, analytics };
