import { addRecord, queryOne } from './db.js';
import { getSession } from './session.js';
import { nowIso } from './utils.js';
import { hashPassword } from './bcrypt-util.js';
import { readFilesForFirestore } from './file-upload.js';

let currentStep = 0;
const totalSteps = 3;

function $(id) { return document.getElementById(id); }

function showError(msg) {
  $('errorBox').style.display = msg ? 'flex' : 'none';
  $('errorText').textContent = msg || '';
}

function val(name) {
  const el = document.querySelector(`[name="${name}"]`);
  return el ? el.value.trim() : '';
}

function updateStepUI() {
  document.querySelectorAll('.step-panel').forEach((p) => p.classList.toggle('active', Number(p.dataset.step) === currentStep));
  document.querySelectorAll('.step-dot').forEach((d) => {
    const step = Number(d.dataset.step);
    d.classList.toggle('active', step === currentStep);
    d.classList.toggle('done', step < currentStep);
  });
  $('prevBtn').style.display = currentStep > 0 ? 'inline-block' : 'none';
  $('nextBtn').style.display = currentStep < totalSteps - 1 ? 'inline-flex' : 'none';
  $('submitBtn').style.display = currentStep === totalSteps - 1 ? 'inline-flex' : 'none';
}

function validateStep(step) {
  if (step === 0) {
    if (!val('fname') || !val('lname') || !val('email') || !val('phone')) {
      showError('All personal fields are required.');
      return false;
    }
  }
  if (step === 1) {
    if (!val('vehicle_type') || !val('plate_number') || !val('license_number')) {
      showError('All vehicle and license fields are required.');
      return false;
    }
    const pw = document.querySelector('[name="password"]').value;
    const confirm = document.querySelector('[name="confirm_password"]').value;
    if (!pw) {
      showError('Password is required.');
      return false;
    }
    if (pw !== confirm) {
      showError('Passwords do not match.');
      return false;
    }
  }
  showError('');
  return true;
}

async function initPage() {
  const session = getSession();
  if (session?.user) {
    const user = await queryOne('users', 'email', session.user);
    if (user) {
      document.querySelector('[name="fname"]').value = user.first_name || '';
      document.querySelector('[name="lname"]').value = user.last_name || '';
      document.querySelector('[name="email"]').value = user.email || '';
      document.querySelector('[name="phone"]').value = user.phone_number || '';
    }
    const rider = await queryOne('riders', 'Rider_Email', session.user);
    if (rider) {
      if (rider.Rider_Status === 'active') {
        window.location.href = 'rider/dashboard.html';
        return;
      }
      if (rider.Rider_Status === 'pending') {
        $('pendingView').style.display = 'block';
        $('riderForm').style.display = 'none';
        return;
      }
    }
  }
  $('riderForm').style.display = 'block';
  updateStepUI();
}

async function submitApplication(e) {
  e.preventDefault();
  const fileFields = ['license_photo', 'nbi', 'or', 'cr'];
  for (const name of fileFields) {
    const input = document.querySelector(`[name="${name}"]`);
    if (!input?.files?.length) {
      showError('Please upload all required documents.');
      return;
    }
  }

  $('submitBtn').disabled = true;
  try {
    const email = val('email').toLowerCase();
    const existing = await queryOne('riders', 'Rider_Email', email);
    if (existing) {
      showError('Email already registered.');
      $('submitBtn').disabled = false;
      return;
    }

    const fileInputs = fileFields.map((name) => document.querySelector(`[name="${name}"]`));
    const password = document.querySelector('[name="password"]').value;
    const [hashed, paths] = await Promise.all([
      hashPassword(password),
      readFilesForFirestore(fileInputs, { fileCountInDoc: 4, reserveBytes: 15000 }),
    ]);
    const [license_photo_path, nbi_path, or_path, cr_path] = paths;

    await addRecord('riders', {
      Rider_Fname: val('fname'),
      Rider_Lname: val('lname'),
      Rider_Email: email,
      Rider_Password: hashed,
      Rider_Phone: val('phone'),
      Rider_VehicleType: val('vehicle_type'),
      Rider_PlateNumber: val('plate_number'),
      Rider_LicenseNumber: val('license_number'),
      Rider_LicensePhoto: license_photo_path,
      Rider_NBI: nbi_path,
      Rider_OR: or_path,
      Rider_CR: cr_path,
      Rider_Status: 'pending',
      Rider_Verified: false,
      Rider_Rating: 0.0,
      Rider_TotalDeliveries: 0,
      Rider_SuccessRate: 100.0,
      Rider_Photo: null,
      Rider_BankName: null,
      Rider_BankAccNo: null,
      Rider_BankAccName: null,
      Rider_StationCity: null,
      created_at: nowIso(),
    });

    $('riderForm').style.display = 'none';
    $('successView').style.display = 'block';
    showError('');
  } catch (err) {
    showError('Database error: ' + (err.message || 'Please try again.'));
    $('submitBtn').disabled = false;
  }
}

$('prevBtn').addEventListener('click', () => { if (currentStep > 0) { currentStep--; updateStepUI(); } });
$('nextBtn').addEventListener('click', () => { if (validateStep(currentStep) && currentStep < totalSteps - 1) { currentStep++; updateStepUI(); } });
$('riderForm').addEventListener('submit', submitApplication);

initPage();
