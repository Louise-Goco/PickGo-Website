import { addRecord, queryOne, getById } from './db.js';
import { getSession } from './session.js';
import { nowIso } from './utils.js';
import { hashPassword } from './bcrypt-util.js';
import { readFileForFirestore, maxBytesPerDocFile } from './file-upload.js';

const locationData = {
  cities: {
    'Cebu City': { zip: '6000', barangays: ['Mambaling', 'Mabolo', 'Apas', 'Tejero', 'Lahug', 'Kasambagan', 'Guadalupe', 'Talamban', 'Banilad', 'Capitol Site', 'Kamputhaw'] },
    'Mandaue City': { zip: '6014', barangays: ['Bakilid', 'Banilad', 'Cabancalan', 'Casuntingan', 'Centro', 'Guizo', 'Subangdaku', 'Tipolo'] },
    'Lapu-Lapu City': { zip: '6015', barangays: ['Basak', 'Gun-ob', 'Maribago', 'Mactan', 'Pajo', 'Pusok'] },
    'Talisay City': { zip: '6045', barangays: ['Bulacao', 'Cansojong', 'Dumlog', 'Jaclupan', 'Lawaan', 'Linao', 'Poblacion', 'Tabunok'] },
    Consolacion: { zip: '6001', barangays: ['Casili', 'Danglag', 'Garing', 'Nangka', 'Poblacion Occidental', 'Poblacion Oriental', 'Tayud', 'Tugbongan'] },
    Liloan: { zip: '6002', barangays: ['Catarman', 'Jubay', 'Poblacion', 'San Vicente', 'Santa Cruz', 'Yati'] },
    Cordova: { zip: '6017', barangays: ['Catarman', 'Day-as', 'Gabi', 'Poblacion', 'San Miguel'] },
    Minglanilla: { zip: '6046', barangays: ['Calajo-an', 'Lipaata', 'Poblacion Ward I', 'Poblacion Ward II', 'Tungkop', 'Tungha-an'] },
  },
  buildings: {
    'SM Seaside City Cebu': { unit_floor: 'Upper Ground Floor', street_no: '', street_name: 'South Road Properties', barangay: 'Mambaling', city: 'Cebu City', province: 'Cebu', zip: '6000' },
    'SM City Cebu': { unit_floor: 'Lower Ground Floor', street_no: '', street_name: 'Juan Luna Avenue', barangay: 'Mabolo', city: 'Cebu City', province: 'Cebu', zip: '6000' },
    'Ayala Center Cebu': { unit_floor: 'Level 1', street_no: '', street_name: 'Cardinal Rosales Avenue', barangay: 'Mabolo', city: 'Cebu City', province: 'Cebu', zip: '6000' },
    'Ayala Malls Central Bloc': { unit_floor: 'Ground Floor', street_no: '', street_name: 'V. Padriga Street', barangay: 'Apas', city: 'Cebu City', province: 'Cebu', zip: '6000' },
    'Robinsons Galleria Cebu': { unit_floor: 'Ground Level', street_no: '', street_name: 'General Maxilom Avenue', barangay: 'Tejero', city: 'Cebu City', province: 'Cebu', zip: '6000' },
    'IT Park - The Walk': { unit_floor: 'Ground Floor', street_no: '', street_name: 'Abad Santos Street', barangay: 'Apas', city: 'Cebu City', province: 'Cebu', zip: '6000' },
    'J Centre Mall': { unit_floor: 'Ground Floor', street_no: '165', street_name: 'A.S. Fortuna Street', barangay: 'Bakilid', city: 'Mandaue City', province: 'Cebu', zip: '6014' },
    'Oakridge Business Park': { unit_floor: 'Block 88', street_no: '880', street_name: 'A.S. Fortuna Street', barangay: 'Banilad', city: 'Mandaue City', province: 'Cebu', zip: '6014' },
    'Gaisano Grand Mall Mactan': { unit_floor: 'Ground Floor', street_no: '', street_name: 'Basak-Marigondon Road', barangay: 'Basak', city: 'Lapu-Lapu City', province: 'Cebu', zip: '6015' },
    'Mactan Marina Mall': { unit_floor: 'Ground Floor', street_no: '', street_name: 'M.L. Quezon National Highway', barangay: 'Pusok', city: 'Lapu-Lapu City', province: 'Cebu', zip: '6015' },
  },
};

let currentStep = 0;
const totalSteps = 4;

function $(id) { return document.getElementById(id); }

function showError(msg) {
  const box = $('errorBox');
  $('errorText').textContent = msg;
  box.style.display = msg ? 'flex' : 'none';
}

function val(id) { return $(id).value.trim(); }

function onCityChange(cityVal, selectedBarangay = '') {
  const barangaySelect = $('barangay');
  const zipSelect = $('zip');
  barangaySelect.innerHTML = '<option value="">-- Select Barangay --</option>';
  zipSelect.innerHTML = '<option value="">-- Select ZIP Code --</option>';
  if (!cityVal || !locationData.cities[cityVal]) return;
  const cityDetails = locationData.cities[cityVal];
  cityDetails.barangays.forEach((bg) => {
    const opt = document.createElement('option');
    opt.value = bg;
    opt.textContent = bg;
    if (bg === selectedBarangay) opt.selected = true;
    barangaySelect.appendChild(opt);
  });
  const zipOpt = document.createElement('option');
  zipOpt.value = cityDetails.zip;
  zipOpt.textContent = cityDetails.zip;
  zipOpt.selected = true;
  zipSelect.appendChild(zipOpt);
}

function autoFillFromBuilding(buildingVal) {
  if (!buildingVal || !locationData.buildings[buildingVal]) return;
  const details = locationData.buildings[buildingVal];
  $('unit_floor').value = details.unit_floor;
  $('street_no').value = details.street_no;
  $('street_name').value = details.street_name;
  $('city').value = details.city;
  $('province').value = details.province;
  onCityChange(details.city, details.barangay);
}

function clearStoreLocation() {
  $('building').value = '';
  $('unit_floor').value = '';
  $('street_no').value = '';
  $('street_name').value = '';
  $('city').value = '';
  $('province').value = 'Cebu';
  $('barangay').innerHTML = '<option value="">-- Select Barangay --</option>';
  $('zip').innerHTML = '<option value="">-- Select ZIP Code --</option>';
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
    if (!val('fname') || !val('lname') || !val('email') || !val('phone') || !$('password').value) {
      showError('Please fill in all owner fields.');
      return false;
    }
    if ($('password').value !== $('confirm_password').value) {
      showError('Passwords do not match.');
      return false;
    }
  }
  if (step === 1) {
    if (!val('merch_name') || !val('merch_type')) {
      showError('Please fill in store name and type.');
      return false;
    }
  }
  if (step === 2) {
    if (!val('city') || !val('barangay') || !val('zip')) {
      showError('Please complete the store location.');
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
      $('fname').value = user.first_name || '';
      $('lname').value = user.last_name || '';
      $('email').value = user.email || '';
      $('phone').value = user.phone_number || '';
    }
    const seller = await queryOne('sellers', 'Sellr_Email', session.user);
    if (seller) {
      if (seller.Sellr_Status === 'active') {
        window.location.href = 'seller/dashboard.html';
        return;
      }
      if (seller.Sellr_Status === 'pending') {
        const merchant = seller.Merch_Id ? await getById('merchants', seller.Merch_Id) : null;
        $('pendingStoreName').textContent = merchant?.Merch_Name || 'your store';
        $('pendingView').style.display = 'block';
        $('sellerForm').style.display = 'none';
        return;
      }
    }
  }
  $('sellerForm').style.display = 'block';
  updateStepUI();
}

async function submitApplication(e) {
  e.preventDefault();
  if (!validateStep(3)) return;
  if (!$('gov_id').files.length || !$('bir_cert').files.length) {
    showError('Please upload required documents.');
    return;
  }

  $('submitBtn').disabled = true;
  try {
    const email = val('email').toLowerCase();
    const existing = await queryOne('sellers', 'Sellr_Email', email);
    if (existing) {
      showError('This email is already registered as a seller.');
      $('submitBtn').disabled = false;
      return;
    }

    const phone = val('phone');
    const unit_floor = val('unit_floor');
    const building = val('building');
    const street_no = val('street_no');
    const street_name = val('street_name');
    const barangay = val('barangay');
    const city = val('city');
    const province = val('province');
    const zip = val('zip');
    const landmark = val('landmark');
    const addressParts = [unit_floor, building, street_no, street_name, barangay, city, province, zip].filter(Boolean);
    let full_address = addressParts.join(', ');
    if (landmark) full_address += ` (Landmark: ${landmark})`;

    const [gov_id_path, bir_cert_path, hashed] = await Promise.all([
      readFileForFirestore($('gov_id'), maxBytesPerDocFile(2, 25000)),
      readFileForFirestore($('bir_cert'), maxBytesPerDocFile(2, 25000)),
      hashPassword($('password').value),
    ]);

    const merch_id = await addRecord('merchants', {
      Merch_Name: val('merch_name'),
      Merch_Type: val('merch_type'),
      Merch_Address: full_address,
      Merch_UnitFloor: unit_floor,
      Merch_Building: building,
      Merch_StreetNo: street_no,
      Merch_StreetName: street_name,
      Merch_Barangay: barangay,
      Merch_City: city,
      Merch_Province: province,
      Merch_ZIP: zip,
      Merch_Landmark: landmark,
      Merch_ContactNumber: val('merch_phone') || phone,
      Merch_Email: val('merch_email') || email,
      Merch_GovID: gov_id_path,
      Merch_BIRCert: bir_cert_path,
      Merch_Status: 'pending',
      Merch_DeliveryRange: 5,
      Merch_Logo: null,
      Merch_Banner: null,
      created_at: nowIso(),
    });

    await addRecord('sellers', {
      Sellr_Fname: val('fname'),
      Sellr_Lname: val('lname'),
      Sellr_Email: email,
      Sellr_Password: hashed,
      Sellr_PhoneNumber: phone,
      Merch_Id: merch_id,
      Sellr_Status: 'pending',
      Sellr_Rating: 0.0,
      Sellr_Bio: null,
      created_at: nowIso(),
    });

    $('sellerForm').style.display = 'none';
    $('successView').style.display = 'block';
    showError('');
  } catch (err) {
    showError('Registration failed: ' + (err.message || 'Please try again.'));
    $('submitBtn').disabled = false;
  }
}

$('building').addEventListener('change', (e) => autoFillFromBuilding(e.target.value));
$('city').addEventListener('change', (e) => onCityChange(e.target.value));
$('clearLocationBtn').addEventListener('click', clearStoreLocation);
$('prevBtn').addEventListener('click', () => { if (currentStep > 0) { currentStep--; updateStepUI(); } });
$('nextBtn').addEventListener('click', () => { if (validateStep(currentStep) && currentStep < totalSteps - 1) { currentStep++; updateStepUI(); } });
$('sellerForm').addEventListener('submit', submitApplication);

initPage();
