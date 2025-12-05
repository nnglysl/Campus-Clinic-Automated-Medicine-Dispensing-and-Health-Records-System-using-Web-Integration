<div class="alert alert-info">
    <i class="bi bi-info-circle-fill"></i>
    <strong>Note:</strong> Once you complete this consultation, it will be automatically saved to the patient's medical records.
</div>
 <!-- Physician Information -->
    <div class="physician-display">
        <i class="bi bi-person-badge-fill"></i>
        <div class="info">
            <div class="label">Attending Physician</div>
            <div class="name" id="physicianName"><?php echo htmlspecialchars($loggedInPhysician['name']); ?></div>
        </div>
    </div>

<form id="newMedicalRecordForm">
    <!-- Patient Information Section -->
    <div class="form-section">
        <h4 class="section-title">Patient Information</h4>
        <div class="form-row">
            <div class="form-group">
                <label>First Name <span style="color: red;">*</span></label>
                <input type="text" id="patientFirstName" class="form-control" readonly>
            </div>
            <div class="form-group">
                <label>Middle Name</label>
                <input type="text" id="patientMiddleName" class="form-control" readonly>
            </div>
            <div class="form-group">
                <label>Last Name <span style="color: red;">*</span></label>
                <input type="text" id="patientLastName" class="form-control" readonly>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Age</label>
                <input type="text" id="patientAge" class="form-control" readonly>
            </div>
            <div class="form-group">
                <label>Gender</label>
                <input type="text" id="patientGender" class="form-control" readonly>
            </div>
            <div class="form-group">
                <label>SR-Code</label>
                <input type="text" id="patientSRCode" class="form-control" readonly>
            </div>
        </div>
    </div>

   

    <div class="form-section">
        <h4 class="section-title">Visit Information</h4>
        <div class="form-row">
            <div class="form-group">
                <label>Date of Visit <span style="color: red;">*</span></label>
                <input type="date" id="visitDate" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Time of Visit <span style="color: red;">*</span></label>
                <input type="time" id="visitTime" class="form-control" required>
            </div>
        </div>
    </div>

    <div class="form-section">
        <h4 class="section-title">Vital Signs</h4>
        <div class="form-row">
            <div class="form-group">
                <label>Blood Pressure (mmHg)</label>
                <input type="text" id="bloodPressure" class="form-control" placeholder="e.g., 120/80">
            </div>
            <div class="form-group">
                <label>Heart Rate (bpm)</label>
                <input type="number" id="heartRate" class="form-control" placeholder="e.g., 72">
            </div>
            <div class="form-group">
                <label>Temperature (°C)</label>
                <input type="number" step="0.1" id="temperature" class="form-control" placeholder="e.g., 36.5">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Weight (kg)</label>
                <input type="number" step="0.1" id="weightInput" class="form-control" placeholder="e.g., 60.5">
            </div>
            <div class="form-group">
                <label>Height (cm)</label>
                <input type="number" id="heightInput" class="form-control" placeholder="e.g., 165">
            </div>
            <div class="form-group">
                <label>BMI</label>
                <input type="text" id="bmiInput" class="form-control" placeholder="Auto-calculated" readonly>
            </div>
        </div>
    </div>

    <div class="form-section">
        <h4 class="section-title">Chief Complaint</h4>
        <div class="form-group full-width">
            <label>Reason for Visit / Main Complaint <span style="color: red;">*</span></label>
            <textarea id="chiefComplaint" class="form-control" placeholder="e.g., Headache, fever, difficulty breathing..." required></textarea>
        </div>
    </div>

    <div class="form-section">
        <h4 class="section-title">Assessment</h4>
        <div class="form-group full-width">
            <label>Diagnosis <span style="color: red;">*</span></label>
            <textarea id="diagnosis" class="form-control" placeholder="Enter diagnosis..." required></textarea>
        </div>
    </div>

    <div class="form-section">
    <h4 class="section-title">Treatment Plan</h4>

    <div class="form-row">
        <div class="form-group">
            <label>Medicine</label>
            <select id="medicineSelect" class="form-control">
                <option selected disabled>Select Medicine</option>
                <!-- Add options dynamically from database if needed -->
            </select>
        </div>

        <div class="form-group">
            <label>Quantity</label>
            <input type="number" id="medicineQuantity" class="form-control" placeholder="Enter quantity">
        </div>

        <div class="form-group">
            <label>Dosage/Instructions</label>
            <input type="text" id="medicineDosage" class="form-control" placeholder="e.g., 1 tablet 3x daily">
        </div>
         <div class="form-group full-width mt-3">
        <label>Treatment Instructions</label>
        <textarea id="treatmentInstructions" class="form-control" placeholder="e.g., Rest for 24 hours, drink plenty of fluids..."></textarea>
    </div>
    </div>
</div>


    <div class="action-buttons">
        <button type="button" class="btn-secondary" onclick="clearMedicalForm()">Clear Form</button>
        <button type="submit" class="btn-primary">
            <i class="bi bi-check-circle"></i> Submit Record
        </button>
    </div>
</form>