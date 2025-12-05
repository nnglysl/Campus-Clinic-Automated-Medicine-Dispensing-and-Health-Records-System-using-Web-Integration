<!-- Medical Record View Modal -->
<div class="modal fade" id="medicalRecordModal" tabindex="-1" aria-labelledby="medicalRecordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg, #8B0000 0%, #A52A2A 100%); color: white;">
        <h5 class="modal-title" id="medicalRecordModalLabel">
          <i class="bi bi-file-medical me-2"></i><span id="modalHeaderTitle">Medical Consultation Record</span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="medicalRecordPrintContent">
        <!-- Consultation Form View (Read-only) -->
        <div class="consultation-form-container">
          <div class="document-header">
            <div class="header-top">
              <div class="header-logo">
                <img src="../img/bsu-logo.png" alt="BSU Logo" style="width: 100%; height: 100%; object-fit: contain; image-rendering: -webkit-optimize-contrast; image-rendering: crisp-edges; image-rendering: high-quality; display: block;">
              </div>
              <div class="header-info-boxes">
                <div class="info-box">
                  <label>Reference No.:</label>
                  <span id="modalReferenceNo">BatStateU-FO-HSD-12</span>
                </div>
                <div class="info-box">
                  <label>Effectivity Date:</label>
                  <span>May 18, 2022</span>
                </div>
                <div class="info-box">
                  <label>Revision No.:</label>
                  <span>01</span>
                </div>
              </div>
            </div>
            <div class="header-title">
              <h2 id="modalRecordTitle">MEDICAL CONSULTATION RECORD</h2>
            </div>
          </div>

          <div class="medical-form">
            <!-- Patient Information Section -->
            <div class="consultation-section">
              <h4 class="consultation-section-title">Patient Information</h4>
              <div class="form-row">
                <div class="form-group">
                  <label>Full Name <span class="required">*</span></label>
                  <input type="text" id="viewFullName" readonly class="readonly-field">
                </div>
                <div class="form-group">
                  <label>SR Code <span class="required">*</span></label>
                  <input type="text" id="viewSRCode" readonly class="readonly-field">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>Address</label>
                  <input type="text" id="viewAddress" readonly class="readonly-field">
                </div>
                <div class="form-group">
                  <label>Program</label>
                  <input type="text" id="viewProgram" readonly class="readonly-field">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>Age</label>
                  <input type="number" id="viewAge" readonly class="readonly-field">
                </div>
                <div class="form-group">
                  <label>Gender</label>
                  <input type="text" id="viewSex" readonly class="readonly-field">
                </div>
              </div>
            </div>

            <!-- Consultation Form Fields -->
            <div id="consultationFormFields" style="display: none;">
              <!-- Nurse's Assessment Section -->
              <div class="consultation-section">
                <h4 class="consultation-section-title">Nurse's Assessment</h4>
                <div class="form-row">
                  <div class="form-group">
                    <label>Date <span class="required">*</span></label>
                    <input type="date" id="viewAssessmentDate" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>Control Number</label>
                    <input type="text" id="viewControlNumber" readonly class="readonly-field">
                  </div>
                </div>
                <div class="form-row full">
                  <div class="form-group">
                    <label>Purpose of Consultation <span class="required">*</span></label>
                    <input type="text" id="viewPurpose" readonly class="readonly-field">
                  </div>
                </div>
              </div>

              <!-- Vital Signs Section -->
              <div class="consultation-section">
                <h4 class="consultation-section-title">Vital Signs</h4>
                <div class="vitals-grid">
                  <div class="form-group">
                    <label>Blood Pressure (BP)</label>
                    <input type="text" id="viewBloodPressure" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>Pulse Rate (PR)</label>
                    <input type="text" id="viewPulseRate" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>SpO2</label>
                    <input type="text" id="viewSpo2" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>Respiratory Rate (RR)</label>
                    <input type="text" id="viewRespiratoryRate" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>Temperature (T)</label>
                    <input type="text" id="viewTemperature" readonly class="readonly-field">
                  </div>
                </div>
              </div>

              <!-- Measurements Section -->
              <div class="consultation-section">
                <h4 class="consultation-section-title">Measurements</h4>
                <div class="measurements-grid">
                  <div class="form-group">
                    <label>Height (HT)</label>
                    <input type="text" id="viewHeight" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>Weight (WT)</label>
                    <input type="text" id="viewWeight" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>BMI</label>
                    <input type="text" id="viewBmi" readonly class="readonly-field">
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Last Menstrual Period</label>
                    <input type="date" id="viewLastMenstrualPeriod" readonly class="readonly-field">
                  </div>
                  <div class="form-group"></div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Vision - Right (R)</label>
                    <input type="text" id="viewVisionRight" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>Vision - Left (L)</label>
                    <input type="text" id="viewVisionLeft" readonly class="readonly-field">
                  </div>
                </div>
              </div>

              <div class="section-divider"></div>

              <!-- Treatment Plan Section -->
              <div class="consultation-section" id="viewTreatmentPlanSection">
                <h4 class="consultation-section-title">Treatment Plan</h4>
                <div id="viewMedicinesContainer">
                  <!-- Medicines will be populated here -->
                </div>
                <div class="form-row full" style="margin-top: 20px;">
                  <div class="form-group">
                    <label>Treatment Instructions</label>
                    <textarea id="viewTreatmentInstructions" rows="4" readonly class="readonly-field"></textarea>
                  </div>
                </div>
              </div>

              <!-- Notes Section -->
              <div class="consultation-section">
                <h4 class="consultation-section-title">Clinical Notes</h4>
                <div class="notes-container">
                  <div class="notes-section">
                    <h5>Nurse's Notes</h5>
                    <div class="form-group">
                      <textarea id="viewNurseNotes" rows="6" readonly class="readonly-field"></textarea>
                    </div>
                  </div>
                  <div class="notes-section">
                    <h5>Doctor's Notes</h5>
                    <div class="form-group">
                      <label>Date</label>
                      <input type="date" id="viewDoctorDate" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                      <textarea id="viewDoctorNotes" rows="6" readonly class="readonly-field"></textarea>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Medical Record Form Fields (for appointment-based records) -->
            <div id="appointmentFormFields" style="display: none;">
              <!-- Appointment Information Section -->
              <div class="consultation-section">
                <h4 class="consultation-section-title">Appointment Information</h4>
                <div class="form-row">
                  <div class="form-group">
                    <label>Appointment Date <span class="required">*</span></label>
                    <input type="date" id="viewAppointmentDate" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>Appointment Time</label>
                    <input type="time" id="viewAppointmentTime" readonly class="readonly-field">
                  </div>
                </div>
              </div>

              <!-- Vital Signs Section -->
              <div class="consultation-section">
                <h4 class="consultation-section-title">Vital Signs</h4>
                <div class="vitals-grid">
                  <div class="form-group">
                    <label>Blood Pressure (BP)</label>
                    <input type="text" id="viewAppointmentBP" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>Pulse Rate (PR)</label>
                    <input type="text" id="viewAppointmentPR" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>SpO2</label>
                    <input type="text" id="viewAppointmentSpO2" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>Respiratory Rate (RR)</label>
                    <input type="text" id="viewAppointmentRR" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>Temperature (T)</label>
                    <input type="text" id="viewAppointmentTemp" readonly class="readonly-field">
                  </div>
                </div>
              </div>

              <!-- Measurements Section -->
              <div class="consultation-section">
                <h4 class="consultation-section-title">Measurements</h4>
                <div class="measurements-grid">
                  <div class="form-group">
                    <label>Height (HT)</label>
                    <input type="text" id="viewAppointmentHeight" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>Weight (WT)</label>
                    <input type="text" id="viewAppointmentWeight" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>BMI</label>
                    <input type="text" id="viewAppointmentBMI" readonly class="readonly-field">
                  </div>
                </div>
              </div>

              <!-- Doctor's Notes Section -->
              <div class="consultation-section">
                <h4 class="consultation-section-title">Doctor's Notes</h4>
                <div class="form-group">
                  <label>Diagnosis <span class="required">*</span></label>
                  <textarea id="viewDiagnosis" rows="2" readonly class="readonly-field" placeholder="Enter diagnosis..."></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-1"></i>Close
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Dental Record View Modal -->
<div class="modal fade" id="dentalRecordModal" tabindex="-1" aria-labelledby="dentalRecordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content consultation-form-container" style="border: none; border-radius: 8px; overflow: hidden;">
      <!-- Red Header Bar with Close Button -->
      <div class="modal-header" style="background: linear-gradient(135deg, #8B0000 0%, #A52A2A 100%); color: white; border: none; padding: 12px 20px;">
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="dentalRecordPrintContent" style="padding: 0;">
        <div class="document-header">
            <div class="header-top">
              <div class="header-logo">
                <img src="../img/bsu-logo.png" alt="BSU Logo" style="width: 100%; height: 100%; object-fit: contain; image-rendering: -webkit-optimize-contrast; image-rendering: crisp-edges; image-rendering: high-quality; display: block;">
              </div>
              <div class="header-info-boxes">
                <div class="info-box">
                  <label>Reference No.:</label>
                  <span>BatStateU-FO-HSD-11</span>
                </div>
                <div class="info-box">
                  <label>Effectivity Date:</label>
                  <span>May 18, 2022</span>
                </div>
                <div class="info-box">
                  <label>Revision No.:</label>
                  <span>01</span>
                </div>
              </div>
            </div>
            <div class="header-title">
              <h2>SCHOOL DENTAL EXAMINATION RECORD</h2>
            </div>
          </div>

          <div class="dental-form" style="padding: 20px;">
            <!-- Patient Information Section -->
            <div class="consultation-section">
              <h4 class="consultation-section-title">Patient Information</h4>
              <div class="form-row">
                <div class="form-group">
                  <label>Full Name <span class="required">*</span></label>
                  <input type="text" id="viewDentalFullName" readonly class="readonly-field">
                </div>
                <div class="form-group">
                  <label>SR Code <span class="required">*</span></label>
                  <input type="text" id="viewDentalSRCode" readonly class="readonly-field">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>Address</label>
                  <input type="text" id="viewDentalAddress" readonly class="readonly-field">
                </div>
                <div class="form-group">
                  <label>Program</label>
                  <input type="text" id="viewDentalProgram" readonly class="readonly-field">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>Date</label>
                  <input type="date" id="viewDentalDate" readonly class="readonly-field">
                </div>
                <div class="form-group">
                  <label>Dentist</label>
                  <input type="text" id="viewDentalDentist" readonly class="readonly-field">
                </div>
              </div>
            </div>

            <!-- Dentition Status Section -->
            <div class="consultation-section">
              <h4 class="consultation-section-title">Dentition Status and Treatment Needs</h4>
              <div class="dental-chart-wrapper" id="viewDentalChartWrapper">
                <div class="teeth-section-standalone">
                  <div>
                    <div class="teeth-label">Temporary Teeth - Right <span style="float: right;">Left</span></div>
                    <div class="teeth-row" id="viewUpperTempTeeth"></div>
                  </div>
                  <div style="margin-top: 20px;">
                    <div class="teeth-row" id="viewUpperPermTeeth"></div>
                  </div>
                  <div style="margin-top: 30px;">
                    <div class="teeth-row" id="viewLowerPermTeeth"></div>
                  </div>
                  <div style="margin-top: 20px;">
                    <div class="teeth-row" id="viewLowerTempTeeth"></div>
                    <div class="teeth-label">Temporary Teeth</div>
                  </div>
                </div>
              </div>
              
              <!-- Index Tables -->
              <div class="index-tables-container">
                <!-- Temporary Teeth Index -->
                <div class="index-table-wrapper">
                  <h5 class="index-table-title">Temporary Teeth</h5>
                  <table class="index-table">
                    <thead>
                      <tr>
                        <th rowspan="2">Index d.f.t</th>
                        <th colspan="6">Date of Visits</th>
                      </tr>
                      <tr>
                        <th>1st</th>
                        <th>2nd</th>
                        <th>3rd</th>
                        <th>4th</th>
                        <th>5th</th>
                        <th>6th</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td class="label-cell">No. T/Decayed</td>
                        <td><input type="text" id="viewTempDecayed1" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewTempDecayed2" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewTempDecayed3" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewTempDecayed4" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewTempDecayed5" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewTempDecayed6" readonly class="readonly-field"></td>
                      </tr>
                      <tr>
                        <td class="label-cell">No. T/Filled</td>
                        <td><input type="text" id="viewTempFilled1" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewTempFilled2" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewTempFilled3" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewTempFilled4" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewTempFilled5" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewTempFilled6" readonly class="readonly-field"></td>
                      </tr>
                      <tr>
                        <td class="label-cell">Total d.f.t.</td>
                        <td><input type="text" id="viewTempTotal1" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewTempTotal2" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewTempTotal3" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewTempTotal4" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewTempTotal5" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewTempTotal6" readonly class="readonly-field"></td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <!-- Permanent Teeth Index -->
                <div class="index-table-wrapper">
                  <h5 class="index-table-title">Permanent Teeth</h5>
                  <table class="index-table">
                    <thead>
                      <tr>
                        <th rowspan="2">Index d.f.t</th>
                        <th colspan="4">Date if Visits</th>
                      </tr>
                      <tr>
                        <th>1st</th>
                        <th>2nd</th>
                        <th>3rd</th>
                        <th>4th</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td class="label-cell">D</td>
                        <td><input type="text" id="viewPermD1" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewPermD2" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewPermD3" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewPermD4" readonly class="readonly-field"></td>
                      </tr>
                      <tr>
                        <td class="label-cell">M</td>
                        <td><input type="text" id="viewPermM1" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewPermM2" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewPermM3" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewPermM4" readonly class="readonly-field"></td>
                      </tr>
                      <tr>
                        <td class="label-cell">F</td>
                        <td><input type="text" id="viewPermF1" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewPermF2" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewPermF3" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewPermF4" readonly class="readonly-field"></td>
                      </tr>
                      <tr>
                        <td class="label-cell">Total DMF</td>
                        <td><input type="text" id="viewPermTotal1" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewPermTotal2" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewPermTotal3" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewPermTotal4" readonly class="readonly-field"></td>
                      </tr>
                      <tr>
                        <td class="label-cell">Total no of Teeth</td>
                        <td><input type="text" id="viewPermTeeth1" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewPermTeeth2" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewPermTeeth3" readonly class="readonly-field"></td>
                        <td><input type="text" id="viewPermTeeth4" readonly class="readonly-field"></td>
                      </tr>
                    </tbody>
                  </table>
                  
                  <div class="index-legend">
                    <strong>Legend:</strong>
                    <span class="legend-abbr">X = Carious tooth indicated for extraction</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Treatment Record Section -->
            <div class="consultation-section">
              <h4 class="consultation-section-title">Treatment Record</h4>
              <div class="treatment-record-wrapper">
                <table class="treatment-table-standalone">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Tooth No.</th>
                      <th>Operation</th>
                      <th>Dentist</th>
                    </tr>
                  </thead>
                  <tbody id="viewDentalTreatmentBody">
                    <tr><td colspan="4" style="text-align: center; color: #999;">No treatments recorded</td></tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Legend Section with Checkboxes -->
            <div class="legend-section">
              <div class="legend-title">Legend:</div>
              <div class="legend-grid">
                <div class="legend-column">
                  <div class="legend-item"><strong>X</strong> - For extraction</div>
                  <div class="legend-item"><strong>C</strong> - For filling</div>
                  <div class="legend-item"><strong>RF</strong> - Root Fragment</div>
                  <div class="legend-item"><strong>M</strong> - Missing</div>
                  <div class="legend-item"><strong>Im</strong> - Impacted</div>
                  <div class="legend-item"><strong>Un</strong> - Unerupted</div>
                  <div class="legend-item"><strong>√</strong> - Present</div>
                  <div class="legend-item"><strong>Cm</strong> - Cong. Missing</div>
                  <div class="legend-item"><strong>Sp</strong> - Supernumerary</div>
                  
                  <h5 style="margin-top: 15px;">Periodontal Screening:</h5>
                  <div class="legend-item">
                    <input type="checkbox" id="viewDentalGingivitis" disabled> Gingivitis
                  </div>
                  <div class="legend-item">
                    <input type="checkbox" id="viewDentalEarlyPeriodontitis" disabled> Early Periodontitis
                  </div>
                </div>

                <div class="legend-column">
                  <div class="legend-item"><strong>JC</strong> - Jacket Crown</div>
                  <div class="legend-item"><strong>Am</strong> - Amalgam</div>
                  <div class="legend-item"><strong>Comp</strong> - Composite</div>
                  <div class="legend-item"><strong>TF</strong> - Temporary Filling</div>
                  <div class="legend-item"><strong>S</strong> - Sealant</div>
                  <div class="legend-item"><strong>In</strong> - Inlay</div>

                  <h5 style="margin-top: 15px;">Occlusion:</h5>
                  <div class="legend-item">
                    <input type="checkbox" id="viewDentalClassMolar" disabled> Class (Molar)
                  </div>
                  <div class="legend-item">
                    <input type="checkbox" id="viewDentalOverjet" disabled> Overjet
                  </div>
                  <div class="legend-item">
                    <input type="checkbox" id="viewDentalOverbite" disabled> Overbite
                  </div>
                </div>

                <div class="legend-column">
                  <div class="legend-item"><strong>AB</strong> - Abutment</div>
                  <div class="legend-item"><strong>P</strong> - Pontic</div>
                  <div class="legend-item"><strong>RPD</strong> - Partial Denture</div>
                  <div class="legend-item"><strong>CD</strong> - Complete Denture</div>
                  <div class="legend-item"><strong>FB</strong> - Fixed Bridge</div>

                  <h5 style="margin-top: 15px;">Appliances:</h5>
                  <div class="legend-item">
                    <input type="checkbox" id="viewDentalOrthodontic" disabled> Orthodontic
                  </div>
                  <div class="legend-item">
                    <input type="checkbox" id="viewDentalStayplate" disabled> Stayplate
                  </div>

                  <h5 style="margin-top: 15px;">TMD:</h5>
                  <div class="legend-item">
                    <input type="checkbox" id="viewDentalClenching" disabled> Clenching
                  </div>
                  <div class="legend-item">
                    <input type="checkbox" id="viewDentalClicking" disabled> Clicking
                  </div>
                </div>
              </div>
            </div>

            <!-- Remarks Section -->
            <div class="remarks-section">
              <label for="viewDentalRemarks">Remarks:</label>
              <textarea id="viewDentalRemarks" readonly class="readonly-field" style="min-height: 80px;"></textarea>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
  // Dental Chart Initialization Function (same as dental_patients.php)
  function initializeDentalChartForView(toothStatus = {}) {
    const dentalOptions = [
      { value: '', text: '' },
      { value: 'X', text: 'X' },
      { value: 'C', text: 'C' },
      { value: 'RF', text: 'RF' },
      { value: 'M', text: 'M' },
      { value: 'Im', text: 'Im' },
      { value: 'Un', text: 'Un' },
      { value: '√', text: '√' },
      { value: 'Cm', text: 'Cm' },
      { value: 'Sp', text: 'Sp' },
      { value: 'JC', text: 'JC' },
      { value: 'Am', text: 'Am' },
      { value: 'Comp', text: 'Comp' },
      { value: 'TF', text: 'TF' },
      { value: 'S', text: 'S' },
      { value: 'In', text: 'In' },
      { value: 'AB', text: 'AB' },
      { value: 'P', text: 'P' },
      { value: 'RPD', text: 'RPD' },
      { value: 'CD', text: 'CD' },
      { value: 'FB', text: 'FB' }
    ];
    
    function createToothView(number, showNumberTop = true) {
      const container = document.createElement('div');
      container.className = 'tooth-container';
      
      if (showNumberTop) {
        const numDiv = document.createElement('div');
        numDiv.className = 'tooth-number';
        numDiv.textContent = number;
        container.appendChild(numDiv);
      }
      
      const diagram = document.createElement('div');
      diagram.className = 'tooth-diagram';
      
      const statusValue = toothStatus[number] || '';
      
      // Mark the tooth diagram if it has any status (not just 'X')
      if (statusValue && statusValue !== '') {
        diagram.classList.add('marked');
        // Add 'status-x' class specifically for 'X' status to show X mark
        if (statusValue === 'X') {
          diagram.classList.add('status-x');
        }
      }
      
      container.appendChild(diagram);
      
      const select = document.createElement('select');
      select.className = 'tooth-select';
      select.id = `viewTooth_${number}_status`;
      select.name = `tooth_${number}_status`;
      select.disabled = true;
      
      dentalOptions.forEach(option => {
        const opt = document.createElement('option');
        opt.value = option.value;
        opt.textContent = option.text;
        opt.selected = opt.value === statusValue;
        select.appendChild(opt);
      });
      
      container.appendChild(select);
      
      if (!showNumberTop) {
        const numDiv = document.createElement('div');
        numDiv.className = 'tooth-number';
        numDiv.textContent = number;
        container.appendChild(numDiv);
      }
      
      return container;
    }
    
    // Clear and populate upper temporary teeth
    const viewUpperTemp = document.getElementById('viewUpperTempTeeth');
    if (viewUpperTemp) {
      viewUpperTemp.innerHTML = '';
      [55, 54, 53, 52, 51, 61, 62, 63, 64, 65].forEach(num => {
        viewUpperTemp.appendChild(createToothView(num, true));
      });
    }
    
    // Clear and populate upper permanent teeth
    const viewUpperPerm = document.getElementById('viewUpperPermTeeth');
    if (viewUpperPerm) {
      viewUpperPerm.innerHTML = '';
      [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28].forEach(num => {
        viewUpperPerm.appendChild(createToothView(num, true));
      });
    }
    
    // Clear and populate lower permanent teeth
    const viewLowerPerm = document.getElementById('viewLowerPermTeeth');
    if (viewLowerPerm) {
      viewLowerPerm.innerHTML = '';
      [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38].forEach(num => {
        viewLowerPerm.appendChild(createToothView(num, false));
      });
    }
    
    // Clear and populate lower temporary teeth
    const viewLowerTemp = document.getElementById('viewLowerTempTeeth');
    if (viewLowerTemp) {
      viewLowerTemp.innerHTML = '';
      [85, 84, 83, 82, 81, 71, 72, 73, 74, 75].forEach(num => {
        viewLowerTemp.appendChild(createToothView(num, false));
      });
    }
  }

  // Print and Export PDF functions are disabled for patients
  // Only medical/dental staff have access to print and export functionality
</script>

