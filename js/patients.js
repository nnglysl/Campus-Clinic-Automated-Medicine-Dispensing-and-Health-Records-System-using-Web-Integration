// Global variables
let currentPatientId = null;
let medicineCounter = 0;

// Patient card click handler
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded');
    console.log('Checking variables...');
    console.log('patientsData:', typeof patientsData !== 'undefined' ? patientsData : 'UNDEFINED');
    console.log('loggedInPhysician:', typeof loggedInPhysician !== 'undefined' ? loggedInPhysician : 'UNDEFINED');
    console.log('medicineInventory:', typeof medicineInventory !== 'undefined' ? medicineInventory : 'UNDEFINED');
    
    // Check if required variables are defined
    if (typeof patientsData === 'undefined') {
        console.error('CRITICAL: patientsData is not defined!');
        alert('Error: Patient data not loaded. Please refresh the page.');
        return;
    }
    
    if (typeof loggedInPhysician === 'undefined') {
        console.error('CRITICAL: loggedInPhysician is not defined!');
        alert('Error: Physician data not loaded. Please refresh the page.');
        return;
    }
    
    if (typeof medicineInventory === 'undefined') {
        console.error('CRITICAL: medicineInventory is not defined!');
        alert('Error: Medicine inventory not loaded. Please refresh the page.');
        return;
    }
    
    console.log('All variables loaded successfully');
    console.log('Patients count:', patientsData.length);
    
    // Add click handlers to all patient cards
    const patientCards = document.querySelectorAll('.patient-card');
    console.log('Patient cards found:', patientCards.length);
    
    patientCards.forEach(card => {
        card.addEventListener('click', function() {
            const patientId = this.getAttribute('data-patient-id');
            console.log('Patient card clicked, ID:', patientId);
            loadPatientDetail(patientId);
        });
    });

    // Search functionality
    const searchInput = document.getElementById('searchPatients');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterPatients(this.value);
        });
    }

    // BMI calculator
    const weightInput = document.getElementById('weightInput');
    const heightInput = document.getElementById('heightInput');
    if (weightInput && heightInput) {
        weightInput.addEventListener('input', calculateBMI);
        heightInput.addEventListener('input', calculateBMI);
    }

    // Medical record form submission
    const medicalRecordForm = document.getElementById('newMedicalRecordForm');
    if (medicalRecordForm) {
        medicalRecordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitMedicalRecord();
        });
    }
});

// Load patient details
function loadPatientDetail(patientId) {
    try {
        console.log('=== Loading Patient Detail ===');
        console.log('Patient ID:', patientId);
        
        currentPatientId = patientId;
        
        // Check if patientsData exists
        if (typeof patientsData === 'undefined') {
            throw new Error('patientsData is not defined');
        }
        
        console.log('Available patients:', patientsData);
        
        // Find patient in data
        const patient = patientsData.find(p => p.id == patientId);
        
        if (!patient) {
            console.error('Patient not found with ID:', patientId);
            alert('Patient not found');
            return;
        }

        console.log('Patient found:', patient);

        // Show detail section and hide patient list
        const patientsSection = document.getElementById('patientsSection');
        const patientDetailSection = document.getElementById('patientDetailSection');
        
        if (!patientsSection || !patientDetailSection) {
            console.error('Required sections not found');
            alert('Error: Page sections not found');
            return;
        }

        patientsSection.style.display = 'none';
        patientDetailSection.style.display = 'block';
        patientDetailSection.classList.add('active');

        // Update detail header
        document.getElementById('detailInitials').textContent = getInitials(patient.full_name);
        document.getElementById('detailName').textContent = patient.full_name;
<<<<<<< HEAD
        const srCode = patient.sr_code && patient.sr_code !== 'null' ? patient.sr_code : 'N/A';
        document.getElementById('detailSRCode').textContent = 'SR-Code: ' + srCode;
        const positionLabel = patient.position || patient.program || 'Student';
        document.getElementById('detailPosition').textContent = positionLabel;
=======
        document.getElementById('detailSRCode').textContent = 'SR-Code: ' + patient.sr_code;
        document.getElementById('detailPosition').textContent = patient.position;
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9

        // Load personal information
        loadPersonalInfo(patient);

        // Load medical records
        loadMedicalRecords(patientId);

        // Switch to info tab by default
        switchTab('info');
        
        console.log('Patient detail loaded successfully');
        
    } catch (error) {
        console.error('Error loading patient details:', error);
        console.error('Error stack:', error.stack);
        alert('Error loading patient details: ' + error.message);
    }
}

// Load personal information
function loadPersonalInfo(patient) {
    try {
        console.log('Loading personal info for:', patient.full_name);
        
        const infoTab = document.getElementById('infoTab');
        if (!infoTab) {
            console.error('infoTab element not found');
            return;
        }
        
        const age = calculateAge(patient.date_of_birth);
<<<<<<< HEAD
        const ageDisplay = patient.age || age || 'N/A';
        const phoneNumber = patient.phone || patient.contact_number || 'N/A';
        const guardianName = patient.guardian_name || patient.emergency_contact_name || 'N/A';
        const guardianRelationship = patient.guardian_relationship || patient.emergency_contact_relationship || 'N/A';
        const guardianPhone = patient.guardian_contact || patient.emergency_contact_phone || 'N/A';
        const gender = patient.sex || patient.gender || 'N/A';
        const firstName = patient.fname || '';
        const middleName = patient.mname || '';
        const lastName = patient.lname || '';
        
        infoTab.innerHTML = `
            <div class="form-section">
                <h4 class="section-title">Personal Information</h4>
                <div class="info-grid">
                    <div class="info-item">
                        <label>First Name</label>
                        <p>${firstName || 'N/A'}</p>
                    </div>
                    <div class="info-item">
                        <label>Middle Name</label>
                        <p>${middleName || 'N/A'}</p>
                    </div>
                    <div class="info-item">
                        <label>Last Name</label>
                        <p>${lastName || 'N/A'}</p>
                    </div>
                    <div class="info-item">
                        <label>Date of Birth</label>
                        <p>${patient.date_of_birth ? formatDate(patient.date_of_birth) : 'N/A'}</p>
                    </div>
                    <div class="info-item">
                        <label>Age</label>
                        <p>${ageDisplay}</p>
                    </div>
                    <div class="info-item">
                        <label>Gender</label>
                        <p>${gender}</p>
                    </div>
                    <div class="info-item">
                        <label>Contact Number</label>
                        <p>${phoneNumber}</p>
                    </div>
                    <div class="info-item">
                        <label>SR-Code</label>
                        <p>${patient.sr_code || 'N/A'}</p>
                    </div>
                    <div class="info-item">
                        <label>Blood Type</label>
                        <p>${patient.blood_type || 'N/A'}</p>
                    </div>
                    <div class="info-item full-width">
                        <label>Address</label>
                        <p>${patient.address || 'N/A'}</p>
                    </div>
                    <div class="info-item full-width">
                        <label>Email Address</label>
                        <p>${patient.email || 'N/A'}</p>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h4 class="section-title">Emergency Contact Information</h4>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Guardian Name</label>
                        <p>${guardianName}</p>
                    </div>
                    <div class="info-item">
                        <label>Relationship</label>
                        <p>${guardianRelationship}</p>
                    </div>
                    <div class="info-item">
                        <label>Contact Number</label>
                        <p>${guardianPhone}</p>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h4 class="section-title">Medical Information</h4>
                <div class="info-grid two-columns">
                    <div class="info-item">
                        <label>Allergies</label>
                        <p>${patient.allergies || 'None'}</p>
                    </div>
                    <div class="info-item">
                        <label>Medical Conditions</label>
                        <p>${patient.medical_condition || 'None'}</p>
                    </div>
                </div>
            </div>
=======
        
        infoTab.innerHTML = `
            <form id="updatePatientForm">
                <div class="form-section">
                    <h4 class="section-title">Personal Information</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" class="form-control" value="${patient.full_name || ''}" readonly>
                        </div>
                        <div class="form-group">
                            <label>SR-Code</label>
                            <input type="text" class="form-control" value="${patient.sr_code || ''}" readonly>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Position</label>
                            <input type="text" class="form-control" value="${patient.position || ''}" readonly>
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="text" class="form-control" value="${formatDate(patient.date_of_birth)}" readonly>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Age</label>
                            <input type="text" class="form-control" value="${age} years old" readonly>
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <input type="text" class="form-control" value="${patient.gender || ''}" readonly>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h4 class="section-title">Contact Information</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" class="form-control" value="${patient.email || ''}" readonly>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" class="form-control" value="${patient.phone || ''}" readonly>
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label>Address</label>
                        <textarea class="form-control" rows="2" readonly>${patient.address || ''}</textarea>
                    </div>
                </div>

                <div class="form-section">
                    <h4 class="section-title">Emergency Contact</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Contact Name</label>
                            <input type="text" class="form-control" value="${patient.emergency_contact_name || ''}" readonly>
                        </div>
                        <div class="form-group">
                            <label>Relationship</label>
                            <input type="text" class="form-control" value="${patient.emergency_contact_relationship || ''}" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" class="form-control" value="${patient.emergency_contact_phone || ''}" readonly>
                    </div>
                </div>

                <div class="form-section">
                    <h4 class="section-title">Medical Information</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Blood Type</label>
                            <input type="text" class="form-control" value="${patient.blood_type || 'N/A'}" readonly>
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label>Allergies</label>
                        <textarea class="form-control" rows="2" readonly>${patient.allergies || 'None'}</textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>Medical Conditions</label>
                        <textarea class="form-control" rows="2" readonly>${patient.medical_conditions || 'None'}</textarea>
                    </div>
                </div>
            </form>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
        `;
        
        console.log('Personal info loaded successfully');
        
    } catch (error) {
        console.error('Error in loadPersonalInfo:', error);
    }
}

// Load medical records
function loadMedicalRecords(patientId) {
    console.log('Loading medical records for patient:', patientId);
    
    // FIXED: Correct path - going up from /admin/ to root, then into /api/
    fetch(`../api/patients.php?action=get_medical_records&patient_id=${patientId}`)
        .then(response => {
            console.log('Medical records response status:', response.status);
            console.log('Medical records response URL:', response.url);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Medical records data:', data);
            const container = document.getElementById('medicalRecordsContainer');
            
            if (!container) {
                console.error('medicalRecordsContainer not found');
                return;
            }
            
            if (data.success && data.records && data.records.length > 0) {
                container.innerHTML = data.records.map(record => {
                    // Format vital signs
                    let vitalSigns = '';
                    if (record.blood_pressure) vitalSigns += `BP: ${record.blood_pressure}, `;
                    if (record.heart_rate) vitalSigns += `HR: ${record.heart_rate} bpm, `;
                    if (record.temperature) vitalSigns += `Temp: ${record.temperature}°C, `;
                    if (record.weight) vitalSigns += `Weight: ${record.weight} kg, `;
                    if (record.height) vitalSigns += `Height: ${record.height} cm, `;
                    if (record.bmi) vitalSigns += `BMI: ${record.bmi}`;
                    vitalSigns = vitalSigns.replace(/,\s*$/, ''); // Remove trailing comma
                    
                    // Format medications
<<<<<<< HEAD
                    // Note: Prescriptions table has been removed - medicines are now in medicine_dispensed table
                    // This code is kept for backward compatibility but will show empty if prescriptions array is empty
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                    let medications = '';
                    if (record.prescriptions && record.prescriptions.length > 0) {
                        medications = record.prescriptions.map(p => 
                            `${p.medicine_name} (${p.quantity}) - ${p.dosage_instructions}`
                        ).join('; ');
                    }
                    
                    return `
                        <div class="medical-record-card">
                            <div class="record-header">
                                <div>
                                    <strong>${formatDate(record.visit_date)}</strong>
                                    <span class="record-time">${record.visit_time || ''}</span>
                                </div>
                                <div class="record-physician">
                                    <i class="bi bi-person-badge"></i> ${record.physician_name}
                                </div>
                            </div>
                            <div class="record-body">
                                <div class="record-section">
                                    <strong>Chief Complaint:</strong>
                                    <p>${record.chief_complaint}</p>
                                </div>
                                <div class="record-section">
                                    <strong>Diagnosis:</strong>
                                    <p>${record.diagnosis}</p>
                                </div>
                                ${vitalSigns ? `
                                <div class="record-section">
                                    <strong>Vital Signs:</strong>
                                    <p>${vitalSigns}</p>
                                </div>
                                ` : ''}
                                ${medications ? `
                                <div class="record-section">
                                    <strong>Medications:</strong>
                                    <p>${medications}</p>
                                </div>
                                ` : ''}
                                ${record.clinical_notes ? `
                                <div class="record-section">
                                    <strong>Clinical Notes:</strong>
                                    <p>${record.clinical_notes}</p>
                                </div>
                                ` : ''}
                                ${record.treatment_instructions ? `
                                <div class="record-section">
                                    <strong>Treatment Instructions:</strong>
                                    <p>${record.treatment_instructions}</p>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                container.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">No medical records found.</p>';
            }
        })
        .catch(error => {
            console.error('Error loading medical records:', error);
            const container = document.getElementById('medicalRecordsContainer');
            if (container) {
                container.innerHTML = `<p style="text-align: center; color: #dc3545; padding: 40px;">Error loading medical records: ${error.message}</p>`;
            }
        });
}

// Close patient detail
function closePatientDetail() {
    console.log('Closing patient detail');
    const patientsSection = document.getElementById('patientsSection');
    const patientDetailSection = document.getElementById('patientDetailSection');
    
    if (patientsSection) patientsSection.style.display = 'block';
    if (patientDetailSection) {
        patientDetailSection.style.display = 'none';
        patientDetailSection.classList.remove('active');
    }
    
    currentPatientId = null;
}

// Switch tabs
function switchTab(tabName) {
    console.log('Switching to tab:', tabName);
    
    // Remove active class from all tabs
    document.querySelectorAll('.tab-item').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-content-inner').forEach(content => {
        content.classList.remove('active');
    });

    // Add active class to selected tab
    const tabs = document.querySelectorAll('.tab-item');
    if (tabName === 'info') {
        if (tabs[0]) tabs[0].classList.add('active');
        const infoTab = document.getElementById('infoTab');
        if (infoTab) infoTab.classList.add('active');
    } else if (tabName === 'medical') {
        if (tabs[1]) tabs[1].classList.add('active');
        const medicalTab = document.getElementById('medicalTab');
        if (medicalTab) medicalTab.classList.add('active');
<<<<<<< HEAD
    }
    // Removed the 'new-record' tab handling
=======
    } else if (tabName === 'new-record') {
        if (tabs[2]) tabs[2].classList.add('active');
        const newRecordTab = document.getElementById('newRecordTab');
        if (newRecordTab) newRecordTab.classList.add('active');
        
        // Populate patient information
        if (currentPatientId) {
            const patient = patientsData.find(p => p.id == currentPatientId);
            if (patient) {
                // Split full name into parts
                const nameParts = patient.full_name.split(' ');
                const firstName = nameParts[0] || '';
                const lastName = nameParts.length > 1 ? nameParts[nameParts.length - 1] : '';
                const middleName = nameParts.length > 2 ? nameParts.slice(1, -1).join(' ') : '';
                
                // Populate patient info fields
                document.getElementById('patientFirstName').value = firstName;
                document.getElementById('patientMiddleName').value = middleName;
                document.getElementById('patientLastName').value = lastName;
                document.getElementById('patientAge').value = calculateAge(patient.date_of_birth) + ' years old';
                document.getElementById('patientGender').value = patient.gender;
                document.getElementById('patientSRCode').value = patient.sr_code;
            }
        }
        
        // Set current date and time
        const visitDate = document.getElementById('visitDate');
        const visitTime = document.getElementById('visitTime');
        
        if (visitDate) {
            visitDate.valueAsDate = new Date();
        }
        if (visitTime) {
            visitTime.value = new Date().toTimeString().slice(0, 5);
        }
    }
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
}

// Filter patients
function filterPatients(searchTerm) {
    const cards = document.querySelectorAll('.patient-card');
    const term = searchTerm.toLowerCase();

    cards.forEach(card => {
        const name = card.querySelector('.patient-name');
        const srCode = card.querySelector('.patient-meta');
        
        if (name && srCode) {
            const nameText = name.textContent.toLowerCase();
            const srCodeText = srCode.textContent.toLowerCase();
            
            if (nameText.includes(term) || srCodeText.includes(term)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        }
    });
}

// Calculate BMI
function calculateBMI() {
    const weight = parseFloat(document.getElementById('weightInput').value);
    const height = parseFloat(document.getElementById('heightInput').value);
    const bmiInput = document.getElementById('bmiInput');
    
    if (weight && height && height > 0 && bmiInput) {
        const heightInMeters = height / 100;
        const bmi = (weight / (heightInMeters * heightInMeters)).toFixed(2);
        bmiInput.value = bmi;
    } else if (bmiInput) {
        bmiInput.value = '';
    }
}

// Submit medical record
function submitMedicalRecord() {
    console.log('Submitting medical record...');
    
    if (!currentPatientId) {
        alert('No patient selected');
        return;
    }

    // Get form data
    const medicineSelect = document.getElementById('medicineSelect');
    const quantity = document.getElementById('medicineQuantity').value;
    const dosage = document.getElementById('medicineDosage').value;
    
    const medicines = [];
    if (medicineSelect.value && quantity && dosage) {
        medicines.push({ 
            inventory_id: medicineSelect.value, 
            quantity: quantity, 
            dosage: dosage 
        });
    }

    console.log('Medicines collected:', medicines);

    const formData = new FormData();
    formData.append('action', 'add_medical_record');
    formData.append('patient_id', currentPatientId);
    formData.append('physician_id', loggedInPhysician.id);
    formData.append('physician_name', loggedInPhysician.name);
    formData.append('visit_date', document.getElementById('visitDate').value);
    formData.append('visit_time', document.getElementById('visitTime').value);
    formData.append('blood_pressure', document.getElementById('bloodPressure').value || '');
    formData.append('heart_rate', document.getElementById('heartRate').value || '');
    formData.append('temperature', document.getElementById('temperature').value || '');
    formData.append('weight', document.getElementById('weightInput').value || '');
    formData.append('height', document.getElementById('heightInput').value || '');
    formData.append('bmi', document.getElementById('bmiInput').value || '');
    formData.append('chief_complaint', document.getElementById('chiefComplaint').value);
    formData.append('diagnosis', document.getElementById('diagnosis').value);
    formData.append('clinical_notes', '');
    formData.append('treatment_instructions', document.getElementById('treatmentInstructions').value || '');
    formData.append('medicines', JSON.stringify(medicines));

    // FIXED: Correct path - going up from /admin/ to root, then into /api/
    fetch('../api/patients.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Medical record response:', data);
        if (data.success) {
            alert('Medical record saved successfully!');
            clearMedicalForm();
            loadMedicalRecords(currentPatientId);
            switchTab('medical');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error saving medical record:', error);
        alert('An error occurred while saving the medical record.');
    });
}

// Clear medical form
function clearMedicalForm() {
    const form = document.getElementById('newMedicalRecordForm');
    if (form) {
        form.reset();
    }
    
    console.log('Medical form cleared');
}

// Helper functions
function getInitials(name) {
    if (!name) return '??';
    const words = name.split(' ');
    if (words.length >= 2) {
        return (words[0][0] + words[words.length - 1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
}

function calculateAge(dateOfBirth) {
    if (!dateOfBirth) return 0;
    
    const today = new Date();
    const birthDate = new Date(dateOfBirth);
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    
    return age;
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    } catch (error) {
        console.error('Error formatting date:', error);
        return dateString;
    }
}