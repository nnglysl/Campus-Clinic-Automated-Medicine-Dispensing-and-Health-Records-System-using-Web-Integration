<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$userName = $_SESSION['fname'] . ' ' . $_SESSION['lname'];
$pdo = getDB();
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$searchTerm = $_GET['search'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Activity Logs</title>
  <link href="../admin/css/activity_logs.css" rel="stylesheet">
  <link href="/finalproject/css/nav.css" rel="stylesheet">
  <link href="../admin/css/responsive.css" rel="stylesheet">
  <link href="../admin/css/notifications.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  
  <style>
    /* Simplified Modal Styles */
    .detail-section { margin-bottom: 20px; }
    .detail-section h6 {
        font-weight: 700;
        color: #6b0d00;
        border-bottom: 2px solid #eee;
        padding-bottom: 8px;
        margin-bottom: 15px;
    }
    .info-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        border-bottom: 1px solid #f8f9fa;
        padding-bottom: 5px;
    }
    .info-label { font-weight: 600; color: #6c757d; }
    .info-value { font-weight: 500; color: #212529; }
    .notes-box {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 15px;
        min-height: 60px;
        margin-bottom: 15px;
    }
    .badge-medical { background-color: #198754; color: white; }
    .badge-consultation { background-color: #ffc107; color: white; }
    .badge-dental { background-color: #0d6efd; color: white; }
    .view-btn.btn-primary {
      background-color: #6b0d00;
      border-color: #6b0d00;
      color: white;
    }
    .view-btn.btn-primary:hover {
      background-color: #8b1a00;
      border-color: #8b1a00;
      color: white;
    }
    .notes-box table {
      margin-bottom: 0;
    }
    .notes-box table th,
    .notes-box table td {
      font-size: 13px;
      padding: 8px;
    }
  </style>
</head>

<body>
  <div class="header">
    <div class="logo-section"><div class="logo"><img src="../img/bsu-logo.png" /></div><div class="university-name"><h1>Batangas State</h1><h1>University</h1></div></div>
    <div class="header-icons">
      <!-- Mobile Menu Icon -->
      <button type="button" class="mobile-menu-icon" id="mobileMenuBtn" aria-label="Toggle navigation menu" aria-expanded="false">
        <i class="bi bi-list"></i>
      </button>
      <?php include 'notification_component.php'; ?>
      <div class="logout-icon" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></div>
    </div>
  </div>

  <div class="main-container">
    <div class="sidebar">
      <div>
        <a href="adminDashboard.php" class="menu-item">Dashboard</a>
        <a href="patients.php" class="menu-item">Patient</a>
        <a href="userManagement.php" class="menu-item">User Management</a>
        <a href="inventory.php" class="menu-item">Inventory</a>
        <a href="activity_logs.php" class="menu-item active">Activity Logs</a>
        <a href="reports.php" class="menu-item">Reports</a>
      </div>
      <div class="user-profile"><span><?php echo htmlspecialchars($userName); ?></span></div>
    </div>

    <main class="dashboard-content">
      <h2 class="welcome-text">Activity Logs</h2>
      
      <div class="activity-logs-container">
        <div class="filters-section">
          <form method="GET" id="filterForm">
            <div class="filter-row">
              <div class="filter-group"><label>From</label><input type="date" name="date_from" id="dateFrom" value="<?php echo $dateFrom; ?>" class="form-control"></div>
              <div class="filter-group"><label>To</label><input type="date" name="date_to" id="dateTo" value="<?php echo $dateTo; ?>" class="form-control"></div>
              <div class="filter-group"><label>Search</label><input type="text" id="searchInput" placeholder="Search..." value="<?php echo htmlspecialchars($searchTerm); ?>" class="form-control"></div>
              <div style="display:flex;gap:10px;"><button type="button" class="btn-filter" onclick="refreshTables()">Filter</button></div>
            </div>
          </form>
        </div>

        <ul class="nav nav-tabs" id="logsTabs" role="tablist">
          <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#userLogs">User Activity</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#medicineLogs">Medicine Dispensed</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#appointmentHistory">Appointment History</button></li>
        </ul>

        <div class="tab-content mt-3">
          <div class="tab-pane fade show active" id="userLogs">
            <table id="userLogsTable" class="table table-striped" width="100%">
              <thead><tr><th>#</th><th>Name</th><th>Action</th><th>Details</th><th>Timestamp</th></tr></thead><tbody></tbody>
            </table>
          </div>
          <div class="tab-pane fade" id="medicineLogs">
            <table id="medicineLogsTable" class="table table-striped" width="100%">
              <thead><tr><th>#</th><th>Medicine</th><th>Qty</th><th>Dispensed By</th><th>Patient</th><th>Stock</th><th>Time</th></tr></thead><tbody></tbody>
            </table>
          </div>
          <div class="tab-pane fade" id="appointmentHistory">
            <table id="appointmentHistoryTable" class="table table-striped" width="100%">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Appointment ID</th>
                  <th>Patient Name</th>
                  <th>Action</th>
                  <th>Appointment Type</th>
                  <th>Date/Time</th>
                  <th>Changed By</th>
                  <th>Timestamp</th>
                  <th>Details</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div class="modal fade" id="medicalDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header text-white" style="background: linear-gradient(135deg, #6b0d00 0%, #8b1a00 100%);">
          <h5 class="modal-title">Record Details</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
            <div id="modalBodyContent">
                <!-- Patient Information - Common for all types -->
                <div class="detail-section">
                    <h6>Patient Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-row"><span class="info-label">Name:</span><span class="info-value" id="dName">--</span></div>
                            <div class="info-row"><span class="info-label">SR Code:</span><span class="info-value" id="dID">--</span></div>
                            <div class="info-row"><span class="info-label">Program:</span><span class="info-value" id="dProg">--</span></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-row"><span class="info-label">Date:</span><span class="info-value" id="dDate">--</span></div>
                            <div class="info-row"><span class="info-label">Attending Physician:</span><span class="info-value" id="dDoc">--</span></div>
                        </div>
                    </div>
                </div>

                <!-- Medical Record Details - Only for Medical Records -->
                <div class="detail-section" id="medicalRecordSection" style="display: none;">
                    <h6>Medical Record Details</h6>
                    <div class="mb-3">
                        <label class="fw-bold small text-secondary">Doctor's Notes:</label>
                        <div class="notes-box" id="dDoctorNotes">--</div>
                    </div>
                </div>

                <!-- Consultation Details - Only for Consultations -->
                <div class="detail-section" id="consultationSection" style="display: none;">
                    <h6>Consultation Details</h6>
                    <div class="mb-3">
                        <label class="fw-bold small text-secondary">Nurse's Notes:</label>
                        <div class="notes-box" id="dNurseNotes">--</div>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold small text-secondary">Doctor's Notes:</label>
                        <div class="notes-box" id="dConsultationDoctorNotes">--</div>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold small text-secondary">Treatment Plan:</label>
                        <div id="dTreatmentPlan">--</div>
                    </div>
                    </div>

                <!-- Dental Record Details - Only for Dental Records -->
                <div class="detail-section" id="dentalSection" style="display: none;">
                    <h6>Dental Record Details</h6>
                    <div class="mb-3">
                        <label class="fw-bold small text-secondary">Treatment Record:</label>
                        <div id="dTreatmentRecord">--</div>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold small text-secondary">Remarks:</label>
                        <div class="notes-box" id="dRemarks">--</div>
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

  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="js/notifications.js"></script>
  <script src="../js/logout.js"></script>
  
  <script>
    let userLogsTable, medicineLogsTable, appointmentHistoryTable;
    
    function refreshTables() {
      const dF = $('#dateFrom').val();
      const dT = $('#dateTo').val();
      const s = $('#searchInput').val();
      
      if (userLogsTable) {
        userLogsTable.ajax.url('api/activity_logs_api.php?action=get_user_logs&date_from=' + dF + '&date_to=' + dT + '&search=' + encodeURIComponent(s)).load();
      }
      if (medicineLogsTable) {
        medicineLogsTable.ajax.url('api/activity_logs_api.php?action=get_medicine_logs&date_from=' + dF + '&date_to=' + dT + '&search=' + encodeURIComponent(s)).load();
      }
      if (appointmentHistoryTable) {
        appointmentHistoryTable.ajax.url('api/activity_logs_api.php?action=get_appointment_history&date_from=' + dF + '&date_to=' + dT + '&search=' + encodeURIComponent(s)).load();
      }
    }
    
    $(document).ready(function() {
      const dF = $('#dateFrom').val(), dT = $('#dateTo').val(), s = $('#searchInput').val();
      
      // User Logs
      userLogsTable = $('#userLogsTable').DataTable({
          ajax: { 
            url:'api/activity_logs_api.php', 
            type: 'GET',
            data:{action:'get_user_logs', date_from:dF, date_to:dT, search:s},
            dataSrc: function(json) {
              console.log('User Logs Response:', json);
              if (json.success && json.data) {
                return json.data;
              } else {
                console.error('User Logs Error:', json.error || 'Unknown error');
                return [];
              }
            },
            error: function(xhr, error, thrown) {
              console.error('User Logs AJAX Error:', error, thrown);
              console.error('Response:', xhr.responseText);
              console.error('Status:', xhr.status);
            }
          },
          columns: [
              {data:null, render:function(data, type, row, meta) { return meta.row + 1; }},
              {data:'user_name', defaultContent: ''},
              {data:'action', defaultContent: ''},
              {data:'details', defaultContent: ''},
              {data:'created_at', render: function(data) {
                if (!data) return 'N/A';
                const date = new Date(data);
                if (isNaN(date.getTime())) return 'N/A';
                return date.toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
              }, defaultContent: 'N/A'}
          ],
          processing: false,
          serverSide: false,
          language: {
            emptyTable: "No activity logs found"
          }
      });

      // Medicine Logs
      medicineLogsTable = $('#medicineLogsTable').DataTable({
          ajax: { 
            url:'api/activity_logs_api.php', 
            type: 'GET',
            data:{action:'get_medicine_logs', date_from:dF, date_to:dT, search:s},
            dataSrc: function(json) {
              console.log('Medicine Logs Response:', json);
              if (json.success && json.data) {
                return json.data;
              } else {
                console.error('Medicine Logs Error:', json.error || 'Unknown error');
                return [];
              }
            },
            error: function(xhr, error, thrown) {
              console.error('Medicine Logs AJAX Error:', error, thrown);
              console.error('Response:', xhr.responseText);
              console.error('Status:', xhr.status);
            }
          },
          columns: [
              {data:null, render:function(data, type, row, meta) { return meta.row + 1; }},
              {data:'medicine_name', defaultContent: ''},
              {data:'quantity', defaultContent: '0'},
              {data:'dispensed_by', defaultContent: ''},
              {data:'patient_name', defaultContent: ''},
              {data:'remaining_stock', defaultContent: '0'},
              {data:null, render: function(data, type, row) {
                if (!row.dispensed_date) return 'N/A';
                const dateStr = row.dispensed_date;
                const timeStr = row.dispensed_time || '00:00:00';
                const dateTimeStr = dateStr + ' ' + timeStr;
                const date = new Date(dateTimeStr);
                if (isNaN(date.getTime())) return 'N/A';
                return date.toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
              }, defaultContent: 'N/A'}
          ],
          processing: false,
          serverSide: false,
          language: {
            emptyTable: "No medicine logs found"
          }
      });

      // Appointment History Table
      appointmentHistoryTable = $('#appointmentHistoryTable').DataTable({
        ajax: { 
          url:'api/activity_logs_api.php', 
          type: 'GET',
          data:{action:'get_appointment_history', date_from:dF, date_to:dT, search:s},
          dataSrc: function(json) {
            console.log('Appointment History Response:', json);
            if (json.success && json.data) {
              return json.data;
            } else {
              console.error('Appointment History Error:', json.error || 'Unknown error');
              return [];
            }
          },
          error: function(xhr, error, thrown) {
            console.error('Appointment History AJAX Error:', error, thrown);
            console.error('Response:', xhr.responseText);
            console.error('Status:', xhr.status);
          }
        },
        columns: [
          {data:null, render:function(data, type, row, meta) { return meta.row + 1; }},
          {data:'appointment_id'},
          {data:'patient_name', className:'fw-bold'},
          {data:'action', render: function(data) {
            if (!data) return 'N/A';
            let badgeClass = 'secondary';
            switch(data.toLowerCase()) {
              case 'created': badgeClass = 'success'; break;
              case 'updated': badgeClass = 'info'; break;
              case 'cancelled': badgeClass = 'danger'; break;
              case 'completed': badgeClass = 'primary'; break;
              case 'rescheduled': badgeClass = 'warning'; break;
            }
            return `<span class="badge bg-${badgeClass}">${data.charAt(0).toUpperCase() + data.slice(1)}</span>`;
          }},
          {data:'appointment_type', render: function(data) {
            if (!data) return 'N/A';
            const lowerData = data.toLowerCase();
            if(lowerData === 'medical') return `<span class="badge badge-medical">${data.charAt(0).toUpperCase() + data.slice(1)}</span>`;
            if(lowerData === 'dental') return `<span class="badge badge-dental">${data.charAt(0).toUpperCase() + data.slice(1)}</span>`;
            if(lowerData === 'consultation') return `<span class="badge badge-consultation">${data.charAt(0).toUpperCase() + data.slice(1)}</span>`;
            return `<span class="badge bg-secondary">${data.charAt(0).toUpperCase() + data.slice(1)}</span>`;
          }},
          {data:null, render: function(data, type, row) {
            let date = row.new_date || row.old_date || 'N/A';
            let time = row.new_time || row.old_time || '';
            
            if (date === 'N/A') return 'N/A';
            
            let dateStr = '';
            if (date) {
              const d = new Date(date);
              dateStr = d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            }
            
            if (time && time !== '00:00:00') {
              // Format time (HH:MM:SS to HH:MM AM/PM)
              const [hours, minutes] = time.split(':');
              const hour24 = parseInt(hours);
              const hour12 = hour24 % 12 || 12;
              const ampm = hour24 >= 12 ? 'PM' : 'AM';
              dateStr += ` ${hour12}:${minutes} ${ampm}`;
            }
            
            // If rescheduled, show old -> new
            if (row.action === 'rescheduled' && row.old_date && row.new_date) {
              const oldD = new Date(row.old_date);
              const newD = new Date(row.new_date);
              let oldStr = oldD.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
              let newStr = newD.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
              if (row.old_time && row.old_time !== '00:00:00') {
                const [h, m] = row.old_time.split(':');
                const h12 = (parseInt(h) % 12) || 12;
                const ampm = parseInt(h) >= 12 ? 'PM' : 'AM';
                oldStr += ` ${h12}:${m} ${ampm}`;
              }
              if (row.new_time && row.new_time !== '00:00:00') {
                const [h, m] = row.new_time.split(':');
                const h12 = (parseInt(h) % 12) || 12;
                const ampm = parseInt(h) >= 12 ? 'PM' : 'AM';
                newStr += ` ${h12}:${m} ${ampm}`;
              }
              return `<div><small class="text-muted">${oldStr}</small> → <strong>${newStr}</strong></div>`;
            }
            
            return dateStr;
          }},
          {data:'changed_by_name', defaultContent: 'System'},
          {data:'created_at', render: function(data) {
            if (!data) return 'N/A';
            const date = new Date(data);
            return date.toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
          }},
          {data:'notes', render: function(data) {
            if (!data || data.trim() === '') return '<span class="text-muted">-</span>';
            return `<small>${data.length > 50 ? data.substring(0, 50) + '...' : data}</small>`;
          }}
        ],
        processing: false,
        serverSide: false,
        language: {
          emptyTable: "No appointment history found"
        }
      });
    });
  </script>
  <script src="../js/mobile-menu.js"></script>
</body>
</html>