<div class="modal fade" id="addPatientModal" tabindex="-1" aria-labelledby="addPatientModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addPatientModalLabel">
          <i class="bi bi-person-plus-fill"></i> Add New Patient
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="addPatientForm">
          <!-- Personal Information -->
          <div class="form-section">
            <h6 class="section-title">Personal Information</h6>
            
            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">First Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="first_name" required>
              </div>

              <div class="col-md-4 mb-3">
                <label class="form-label">Middle Name</label>
                <input type="text" class="form-control" name="middle_name">
              </div>

              <div class="col-md-4 mb-3">
                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="last_name" required>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">SR-Code <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="sr_code" placeholder="e.g., 23-12345" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Position <span class="text-danger">*</span></label>
                <select class="form-select" name="position" required>
                  <option value="">Select Position</option>
                  <option value="Student">Student</option>
                  <option value="Faculty">Faculty</option>
                  <option value="Staff">Staff</option>
                </select>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="date_of_birth" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Gender <span class="text-danger">*</span></label>
                <select class="form-select" name="gender" required>
                  <option value="">Select Gender</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" name="email" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                <input type="tel" class="form-control" name="phone" placeholder="e.g., 09123456789" required>
              </div>
            </div>

            <div class="row">
              <div class="col-md-12 mb-3">
                <label class="form-label">Address <span class="text-danger">*</span></label>
                <textarea class="form-control" name="address" rows="2" required></textarea>
              </div>
            </div>
          </div>

          <!-- Emergency Contact -->
          <div class="form-section">
            <h6 class="section-title">Emergency Contact</h6>
            
            <div class="row">
              <div class="col-md-12 mb-3">
                <label class="form-label">Contact Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="emergency_contact_name" required>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Relationship <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="emergency_contact_relationship" placeholder="e.g., Mother, Father, Spouse" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Contact Number <span class="text-danger">*</span></label>
                <input type="tel" class="form-control" name="emergency_contact_phone" placeholder="e.g., 09123456789" required>
              </div>
            </div>
          </div>

          <!-- Medical Information -->
          <div class="form-section">
            <h6 class="section-title">Medical Information</h6>
            
            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">Blood Type</label>
                <select class="form-select" name="blood_type">
                  <option value="">Select Blood Type</option>
                  <option value="A+">A+</option>
                  <option value="A-">A-</option>
                  <option value="B+">B+</option>
                  <option value="B-">B-</option>
                  <option value="AB+">AB+</option>
                  <option value="AB-">AB-</option>
                  <option value="O+">O+</option>
                  <option value="O-">O-</option>
                </select>
              </div>
            </div>

            <div class="row">
              <div class="col-md-12 mb-3">
                <label class="form-label">Allergies</label>
                <textarea class="form-control" name="allergies" rows="2" placeholder="List any known allergies (e.g., Penicillin, Peanuts, etc.) or type 'None'"></textarea>
              </div>
            </div>

            <div class="row">
              <div class="col-md-12 mb-3">
                <label class="form-label">Medical Conditions</label>
                <textarea class="form-control" name="medical_conditions" rows="2" placeholder="List any existing medical conditions (e.g., Asthma, Diabetes, etc.) or type 'None'"></textarea>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="submitAddPatient()">
          <i class="bi bi-check-circle"></i> Add Patient
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function submitAddPatient() {
  const form = document.getElementById('addPatientForm');
  
  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }

  const formData = new FormData(form);
  
  // Concatenate the full name from first, middle, and last name
  const firstName = formData.get('first_name').trim();
  const middleName = formData.get('middle_name').trim();
  const lastName = formData.get('last_name').trim();
  
  // Build full name properly
  let fullName = firstName;
  if (middleName) {
    fullName += ' ' + middleName;
  }
  fullName += ' ' + lastName;
  
  formData.append('full_name', fullName);
  formData.append('action', 'add_patient');

  fetch('../api/patients.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Patient added successfully!');
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while adding the patient.');
  });
}
</script>