<?php
session_start();
require_once '../db.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    try {
        switch($action) {
            case 'getMedicines':
                $stmt = $pdo->query("SELECT * FROM inventory ORDER BY batchId ASC");
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                break;
                
            case 'addMedicine':
                $batchId = $_POST['batchId'];
                $code = $_POST['code'];
                $name = $_POST['name'];
                $quantity = $_POST['quantity'];
                $expiry = $_POST['expiry'];
                $description = $_POST['description'];
                
                $stmt = $pdo->prepare("INSERT INTO inventory (batchId, code, name, quantity, dispensed, expiry, description, status) VALUES (?, ?, ?, ?, 0, ?, ?, 'active')");
                $stmt->execute([$batchId, $code, $name, $quantity, $expiry, $description]);
                
                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
                break;
                
            case 'updateMedicine':
                $id = $_POST['id'];
                $name = $_POST['name'];
                $quantity = $_POST['quantity'];
                $expiry = $_POST['expiry'];
                $description = $_POST['description'];
                
                $stmt = $pdo->prepare("UPDATE inventory SET name = ?, quantity = ?, expiry = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $quantity, $expiry, $description, $id]);
                
                echo json_encode(['success' => true]);
                break;
                
            case 'getNextBatchId':
                $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(batchId, 6) AS UNSIGNED)) as maxBatch FROM inventory");
                $result = $stmt->fetch();
                $nextNum = ($result['maxBatch'] ?? 0) + 1;
                echo json_encode(['success' => true, 'batchId' => 'BATCH' . str_pad($nextNum, 3, '0', STR_PAD_LEFT)]);
                break;
                
            case 'getItemCode':
                $name = $_POST['name'];
                $stmt = $pdo->prepare("SELECT code FROM inventory WHERE name = ? LIMIT 1");
                $stmt->execute([$name]);
                $result = $stmt->fetch();
                
                if ($result) {
                    echo json_encode(['success' => true, 'code' => $result['code']]);
                } else {
                    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(code, 4) AS UNSIGNED)) as maxCode FROM inventory");
                    $result = $stmt->fetch();
                    $nextNum = ($result['maxCode'] ?? 0) + 1;
                    echo json_encode(['success' => true, 'code' => 'MED' . str_pad($nextNum, 3, '0', STR_PAD_LEFT)]);
                }
                break;

            case 'archiveExpired':
                $stmt = $pdo->prepare("UPDATE inventory SET status = 'archive' WHERE expiry < CURDATE() AND status != 'archive'");
                $stmt->execute();
                echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inventory</title>

  <!-- Stylesheets -->
  <link href="../admin/inventory.css" rel="stylesheet" />
  <link href="../admin/nav.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Text:ital@0;1&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" />
</head>

<body>
  <!-- HEADER -->
  <div class="header">
    <div class="logo-section">
      <div class="logo">
        <img src="../img/bsu-logo.png" alt="University Logo" />
      </div>
      <div class="university-name">
        <h1>Batangas State</h1>
        <h1>University</h1>
      </div>
    </div>
    <div class="header-icons">
      <div class="notification-icon"><i class="bi bi-bell-fill"></i></div>
      <div class="logout-icon"><i class="bi bi-box-arrow-right"></i></div>
    </div>
  </div>

  <!-- SIDEBAR + CONTENT -->
  <div class="main-container">
    <div class="sidebar">
      <div>
        <a href="../admin/adminDashboard.php" class="menu-item">Dashboard</a>
        <a href="../admin/patients.php" class="menu-item">Patient</a>
        <a href="../admin/userManagement.php" class="menu-item">User Management</a>

        <!-- INVENTORY DROPDOWN -->
        <div class="dropdown-menu-container">
          <a href="../admin/inventory.php" id="inventoryMain">Inventory</a>
          <div class="submenu" id="inventorySubmenu">
            <a href="#" class="submenu-item" id="medicineTab">Medicines</a>
            <a href="#" class="submenu-item" id="stockTab">Stocks</a>
          </div>
        </div>

        <a href="../admin/appointmentManagement.php" class="menu-item">Appointments</a>
        <a href="../admin/reports.html" class="menu-item">Reports & Analytics</a>
      </div>
      <div class="user-profile">
        <div class="avatar"></div>
        <span><?php echo htmlspecialchars($userName); ?></span>
      </div>
    </div>


    <div class="main-content">
      <!-- Inventory Overview -->
      <div id="inventoryOverviewSection">
        <h2>Inventory Dashboard</h2>

        <div class="row mt-4">
          <div class="col-md-6">
            <div class="alert-section">
              <div class="alert-header">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <h5>Low Stock Alert</h5>
              </div>
              <div id="lowStockCards" class="alert-cards-container"></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="alert-section expired-section">
              <div class="alert-header">
                <i class="bi bi-calendar-x-fill"></i>
                <h5>Nearing Expiration Alert</h5>
              </div>
              <div id="expiredCards" class="alert-cards-container"></div>
            </div>
          </div>
        </div>

        <div class="mt-4">
          <button class="btn btn-outline-secondary" id="toggleDetailedView">
            <i class="bi bi-table"></i> View Detailed Tables
          </button>
        </div>

        <div id="detailedTablesSection" style="display:none;" class="mt-4">
          <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#lowstockDashboard">Low Stock Items</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#expiredDashboard">Nearing Expiration</button>
            </li>
          </ul>

          <div class="tab-content">
            <div class="tab-pane fade show active" id="lowstockDashboard">
              <div class="table-container">
                <table id="lowStockDashboardTable" class="display table table-bordered table-striped">
                  <thead>
                    <tr>
                      <th>Batch ID</th>
                      <th>Item Code</th>
                      <th>Item Name</th>
                      <th>Quantity</th>
                      <th>Dispensed</th>
                      <th>Expiry Date</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>

            <div class="tab-pane fade" id="expiredDashboard">
              <div class="table-container">
                <table id="expiredDashboardTable" class="display table table-bordered table-striped">
                  <thead>
                    <tr>
                      <th>Batch ID</th>
                      <th>Item Code</th>
                      <th>Item Name</th>
                      <th>Quantity</th>
                      <th>Dispensed</th>
                      <th>Expiry Date</th>
                      <th>Days Until Expiry</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Medicines Section -->
      <div id="medicinesSection" style="display:none;">
        <h2 class="mb-4">Medicines</h2>
        <div class="mb-3">
          <label for="sortBy" class="form-label me-2">Sort by:</label>
          <select id="sortBy" class="form-select d-inline-block" style="width: auto;">
            <option value="batch">Batch ID</option>
            <option value="code">Item Code</option>
            <option value="name">Item Name</option>
            <option value="quantity">Quantity</option>
            <option value="expiry">Expiry Date</option>
          </select>
        </div>
        <ul class="nav nav-tabs mb-3">
          <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#active">Active</button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#bod">BOD</button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#archive">Archive/Disposal</button>
          </li>
        </ul>
        <div class="tab-content table-container">
          <div class="tab-pane fade show active" id="active">
            <table id="activeTable" class="display table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Batch ID</th>
                  <th>Item Code</th>
                  <th>Item Name</th>
                  <th>Quantity</th>
                  <th>Dispensed</th>
                  <th>Expiry Date</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
          <div class="tab-pane fade" id="bod">
            <table id="bodTable" class="display table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Batch ID</th>
                  <th>Item Code</th>
                  <th>Item Name</th>
                  <th>Quantity</th>
                  <th>Dispensed</th>
                  <th>Expiry Date</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
          <div class="tab-pane fade" id="archive">
            <table id="archiveTable" class="display table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Batch ID</th>
                  <th>Item Code</th>
                  <th>Item Name</th>
                  <th>Quantity</th>
                  <th>Dispensed</th>
                  <th>Expiry Date</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Stocks Section -->
      <div id="stocksSection" style="display:none;">
        <h2 class="mb-4">Stocks</h2>
        <button class="btn btn-primary mb-3" id="addStockBtn">
          <i class="bi bi-plus-circle"></i> Add New Stock Batch
        </button>
        <div class="table-container">
          <table id="stocksTable" class="display table table-bordered table-striped">
            <thead>
              <tr>
                <th>Batch ID</th>
                <th>Item Code</th>
                <th>Item Name</th>
                <th>Quantity</th>
                <th>Expiry Date</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Add/Edit Stock Modal -->
  <div class="modal fade" id="addStockModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
          <h5 class="modal-title" id="addStockModalLabel">
            <i class="bi bi-plus-circle-fill me-2"></i>Add New Stock Batch
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form id="addStockForm">
            <input type="hidden" id="stockId">
            <input type="hidden" id="stockBatchId">
            <input type="hidden" id="stockItemCode">
            
            <div class="auto-generated-info">
              <strong><i class="bi bi-info-circle-fill"></i> Auto-Generated IDs</strong>
              <div class="info-details">
                <div><strong>Batch ID:</strong> <span id="displayBatchId"></span></div>
                <div><strong>Item Code:</strong> <span id="displayItemCode"></span></div>
              </div>
            </div>

            <div class="form-section">
              <div class="form-section-title">
                <i class="bi bi-box-seam-fill"></i> Item Information
              </div>
              <div class="mb-3">
                <label for="stockName" class="form-label">
                  Medicine/Item Name <span class="required">*</span>
                </label>
                <input type="text" class="form-control" id="stockName" placeholder="e.g., Paracetamol, Amoxicillin" required>
              </div>
              <div class="mb-3">
                <label for="stockDescription" class="form-label">
                  Description <span class="required">*</span>
                </label>
                <textarea class="form-control" id="stockDescription" rows="3" placeholder="Enter item description, usage, or notes..." required></textarea>
              </div>
            </div>

            <div class="form-section">
              <div class="form-section-title">
                <i class="bi bi-calculator-fill"></i> Quantity Management
              </div>
              <div class="quantity-section">
                <div class="quantity-inputs">
                  <div class="quantity-input-group">
                    <label>
                      <i class="bi bi-box"></i> Current Stock
                    </label>
                    <input type="number" class="form-control" id="stockCurrentQty" min="0" value="0" readonly>
                  </div>
                  <div class="quantity-input-group">
                    <label>
                      <i class="bi bi-plus-circle"></i> New Arrivals
                    </label>
                    <input type="number" class="form-control" id="stockNewQty" min="0" value="0" placeholder="0">
                  </div>
                  <div class="quantity-input-group">
                    <label>
                      <i class="bi bi-check-circle"></i> Total Quantity
                    </label>
                    <input type="number" class="form-control quantity-total-input" id="stockTotalQty" min="0" readonly>
                  </div>
                </div>
              </div>
            </div>

            <div class="form-section">
              <div class="form-section-title">
                <i class="bi bi-calendar-event-fill"></i> Expiry Information
              </div>
              <div class="mb-3">
                <label for="stockExpiry" class="form-label">
                  Expiry Date <span class="required">*</span>
                </label>
                <input type="date" class="form-control" id="stockExpiry" required>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="bi bi-x-circle"></i> Cancel
          </button>
          <button type="button" class="btn btn-primary" id="saveStockBtn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
            <i class="bi bi-check-circle"></i> Save Stock
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- View Modal -->
  <div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Item Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="viewModalContent"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script>
    let medicines = [];
    let activeTable, bodTable, archiveTable, stocksTable, lowStockDashTable, expiredDashTable;

    function ajaxRequest(action, data = {}) {
      return $.ajax({
        url: '',
        method: 'POST',
        data: { action, ...data },
        dataType: 'json'
      });
    }

    function loadMedicines() {
      ajaxRequest('getMedicines').done(function(response) {
        if (response.success) {
          medicines = response.data;
          renderMedicines();
          renderStocks();
          updateOverview();
          updateDashboardTables();
          updateDashboardCards();
        }
      });
    }

    function getDaysUntilExpiry(expiryDate) {
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const expiry = new Date(expiryDate);
      expiry.setHours(0, 0, 0, 0);
      return Math.floor((expiry - today) / (1000 * 60 * 60 * 24));
    }

    function isExpiredOrNearing(expiryDate) {
      const daysLeft = getDaysUntilExpiry(expiryDate);
      return daysLeft > 0 && daysLeft <= 30;
    }

    function checkAndArchiveExpired() {
      ajaxRequest('archiveExpired').done(function(response) {
        if (response.success && response.affected > 0) {
          console.log(`${response.affected} expired item(s) automatically moved to archive`);
          loadMedicines();
        }
      });
    }

    function updateDashboardCards() {
      const lowStockContainer = $('#lowStockCards');
      const expiredContainer = $('#expiredCards');
      
      lowStockContainer.empty();
      expiredContainer.empty();

      const lowStockItems = medicines.filter(m => m.quantity <= 10 && m.status === 'active');
      if (lowStockItems.length === 0) {
        lowStockContainer.html(`
          <div class="empty-state">
            <i class="bi bi-check-circle"></i>
            <p>All items are well stocked!</p>
          </div>
        `);
      } else {
        lowStockItems.forEach(medicine => {
          const cardClass = medicine.quantity === 0 ? 'critical' : '';
          lowStockContainer.append(`
            <div class="alert-item-card ${cardClass}">
              <div class="alert-item-info">
                <div>
                  <div class="alert-item-name">${medicine.name}</div>
                  <div class="alert-item-code">${medicine.batchId} - ${medicine.code}</div>
                </div>
                <span class="alert-item-badge ${medicine.quantity === 0 ? 'danger' : 'warning'}">
                  ${medicine.quantity} left
                </span>
              </div>
              <div class="alert-item-details">
                <span><i class="bi bi-box"></i> Dispensed: ${medicine.dispensed}</span>
                <span><i class="bi bi-calendar"></i> Exp: ${medicine.expiry}</span>
              </div>
            </div>
          `);
        });
      }

      const nearingExpirationItems = medicines.filter(m => {
        const daysLeft = getDaysUntilExpiry(m.expiry);
        return daysLeft > 0 && daysLeft <= 30 && m.status !== 'archive';
      });
      
      if (nearingExpirationItems.length === 0) {
        expiredContainer.html(`
          <div class="empty-state">
            <i class="bi bi-check-circle"></i>
            <p>No items expiring soon!</p>
          </div>
        `);
      } else {
        nearingExpirationItems.sort((a, b) => getDaysUntilExpiry(a.expiry) - getDaysUntilExpiry(b.expiry));
        nearingExpirationItems.forEach(medicine => {
          const daysLeft = getDaysUntilExpiry(medicine.expiry);
          const daysText = `${daysLeft} days left`;
          
          expiredContainer.append(`
            <div class="alert-item-card">
              <div class="alert-item-info">
                <div>
                  <div class="alert-item-name">${medicine.name}</div>
                  <div class="alert-item-code">${medicine.batchId} - ${medicine.code}</div>
                </div>
                <span class="alert-item-badge warning">
                  ${daysText}
                </span>
              </div>
              <div class="alert-item-details">
                <span><i class="bi bi-box"></i> Qty: ${medicine.quantity}</span>
                <span><i class="bi bi-calendar"></i> ${medicine.expiry}</span>
              </div>
            </div>
          `);
        });
      }
    }

    function updateDashboardTables() {
      lowStockDashTable.clear();
      expiredDashTable.clear();

      medicines.filter(m => m.quantity <= 10 && m.status === 'active').forEach(medicine => {
        lowStockDashTable.row.add($(`
          <tr>
            <td>${medicine.batchId}</td>
            <td>${medicine.code}</td>
            <td>${medicine.name}</td>
            <td>${medicine.quantity}</td>
            <td>${medicine.dispensed}</td>
            <td>${medicine.expiry}</td>
            <td><span class="status-badge status-low">Low Stock</span></td>
          </tr>
        `)[0]);
      });

      medicines.filter(m => {
        const daysLeft = getDaysUntilExpiry(m.expiry);
        return daysLeft > 0 && daysLeft <= 30 && m.status !== 'archive';
      }).forEach(medicine => {
        const daysLeft = getDaysUntilExpiry(medicine.expiry);
        const statusClass = 'status-low';
        const statusText = 'Nearing Expiration';
        const daysText = `${daysLeft} days`;

        expiredDashTable.row.add($(`
          <tr>
            <td>${medicine.batchId}</td>
            <td>${medicine.code}</td>
            <td>${medicine.name}</td>
            <td>${medicine.quantity}</td>
            <td>${medicine.dispensed}</td>
            <td>${medicine.expiry}</td>
            <td>${daysText}</td>
            <td><span class="status-badge ${statusClass}">${statusText}</span></td>
          </tr>
        `)[0]);
      });

      lowStockDashTable.draw();
      expiredDashTable.draw();
    }

    $(document).ready(function() {
      activeTable = $('#activeTable').DataTable({ pageLength: 5, order: [[0, 'asc']] });
      bodTable = $('#bodTable').DataTable({ pageLength: 5, order: [[0, 'asc']] });
      archiveTable = $('#archiveTable').DataTable({ pageLength: 5, order: [[0, 'asc']] });
      stocksTable = $('#stocksTable').DataTable({ pageLength: 5, order: [[0, 'asc']] });
      lowStockDashTable = $('#lowStockDashboardTable').DataTable({ pageLength: 5, order: [[0, 'asc']] });
      expiredDashTable = $('#expiredDashboardTable').DataTable({ pageLength: 5, order: [[5, 'asc']] });

      loadMedicines();
      checkAndArchiveExpired();

      $('#stockNewQty').on('input', function() {
        const current = parseInt($('#stockCurrentQty').val()) || 0;
        const newQty = parseInt($(this).val()) || 0;
        $('#stockTotalQty').val(current + newQty);
      });

      $('#stockName').on('blur', function() {
        const medicineName = $(this).val().trim();
        if (medicineName && !$('#stockId').val()) {
          ajaxRequest('getItemCode', { name: medicineName }).done(function(response) {
            if (response.success) {
              $('#stockItemCode').val(response.code);
              $('#displayItemCode').text(response.code);
            }
          });
        }
      });

      $("#inventoryMain").on("click", function(e) {
        e.preventDefault();
        $("#inventorySubmenu").toggleClass("show");
        $("#inventoryOverviewSection").show();
        $("#medicinesSection").hide();
        $("#stocksSection").hide();
        checkAndArchiveExpired();
      });

      $("#medicineTab").on("click", function(e) {
        e.preventDefault();
        $("#inventoryOverviewSection").hide();
        $("#medicinesSection").show();
        $("#stocksSection").hide();
      });

      $("#stockTab").on("click", function(e) {
        e.preventDefault();
        $("#inventoryOverviewSection").hide();
        $("#medicinesSection").hide();
        $("#stocksSection").show();
      });

      $('#addStockBtn').on('click', function(e) {
        e.preventDefault();
        $('#addStockModalLabel').html('<i class="bi bi-plus-circle-fill me-2"></i>Add New Stock Batch');
        $('#addStockForm')[0].reset();
        $('#stockId').val('');
        $('#stockCurrentQty').val(0);
        $('#stockNewQty').val(0);
        $('#stockTotalQty').val(0);
        
        ajaxRequest('getNextBatchId').done(function(response) {
          if (response.success) {
            $('#stockBatchId').val(response.batchId);
            $('#displayBatchId').text(response.batchId);
            $('#displayItemCode').text('Will be assigned based on medicine name');
          }
        });
        
        new bootstrap.Modal(document.getElementById('addStockModal')).show();
      });

      $('#saveStockBtn').on('click', function() {
        const id = $('#stockId').val();
        const batchId = $('#stockBatchId').val();
        const code = $('#stockItemCode').val();
        const name = $('#stockName').val().trim();
        const quantity = parseInt($('#stockTotalQty').val()) || 0;
        const expiry = $('#stockExpiry').val();
        const description = $('#stockDescription').val();

        if (!name || quantity < 0 || !expiry || !description) {
          alert('Please fill all required fields');
          return;
        }

        const action = id ? 'updateMedicine' : 'addMedicine';
        const data = id ? { id, name, quantity, expiry, description } : { batchId, code, name, quantity, expiry, description };
        
        ajaxRequest(action, data).done(function(response) {
          if (response.success) {
            $('#addStockForm')[0].reset();
            bootstrap.Modal.getInstance(document.getElementById('addStockModal')).hide();
            loadMedicines();
            checkAndArchiveExpired();
          } else {
            alert('Error: ' + response.error);
          }
        });
      });

      $('#sortBy').on('change', function() { sortMedicines(); });

      $(document).on('click', '.view-btn', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        const medicine = medicines.find(m => m.id == id);
        if (medicine) {
          const statusClass = getStatusClass(medicine);
          const statusText = getStatusText(medicine);
          $('#viewModalContent').html(`
            <div class="row">
              <div class="col-md-6 mb-3"><strong>Batch ID:</strong><br>${medicine.batchId}</div>
              <div class="col-md-6 mb-3"><strong>Item Code:</strong><br>${medicine.code}</div>
              <div class="col-md-6 mb-3"><strong>Item Name:</strong><br>${medicine.name}</div>
              <div class="col-md-6 mb-3"><strong>Quantity:</strong><br>${medicine.quantity}</div>
              <div class="col-md-6 mb-3"><strong>Dispensed:</strong><br>${medicine.dispensed}</div>
              <div class="col-md-6 mb-3"><strong>Expiry Date:</strong><br>${medicine.expiry}</div>
              <div class="col-md-6 mb-3"><strong>Status:</strong><br><span class="status-badge ${statusClass}">${statusText}</span></div>
              <div class="col-12 mb-3"><strong>Description:</strong><br>${medicine.description}</div>
            </div>
          `);
          new bootstrap.Modal(document.getElementById('viewModal')).show();
        }
      });

      $(document).on('click', '.edit-stock-btn', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        const medicine = medicines.find(m => m.id == id);
        if (medicine) {
          $('#addStockModalLabel').html('<i class="bi bi-pencil-square me-2"></i>Edit Stock');
          $('#stockId').val(medicine.id);
          $('#stockBatchId').val(medicine.batchId);
          $('#stockItemCode').val(medicine.code);
          $('#stockName').val(medicine.name);
          $('#stockCurrentQty').val(medicine.quantity);
          $('#stockNewQty').val(0);
          $('#stockTotalQty').val(medicine.quantity);
          $('#stockExpiry').val(medicine.expiry);
          $('#stockDescription').val(medicine.description);
          
          $('#displayBatchId').text(medicine.batchId);
          $('#displayItemCode').text(medicine.code);
          
          new bootstrap.Modal(document.getElementById('addStockModal')).show();
        }
      });

      $('#toggleDetailedView').on('click', function() {
        const detailedSection = $('#detailedTablesSection');
        const isVisible = detailedSection.is(':visible');
        detailedSection.slideToggle();
        $(this).html(isVisible 
          ? '<i class="bi bi-table"></i> View Detailed Tables' 
          : '<i class="bi bi-x-lg"></i> Hide Detailed Tables'
        );
      });

      $("#inventoryMain").click();
    });

    function getStatusClass(medicine) {
      if (medicine.status === 'archive') return 'status-inactive';
      if (medicine.status === 'bod') return 'status-bod';
      if (medicine.quantity <= 10) return 'status-low';
      return 'status-active';
    }

    function getStatusText(medicine) {
      if (medicine.status === 'archive') return 'Archived';
      if (medicine.status === 'bod') return 'BOD';
      if (medicine.quantity <= 10) return 'Low Stock';
      return 'In Stock';
    }

    function renderMedicines() {
      activeTable.clear();
      bodTable.clear();
      archiveTable.clear();

      medicines.forEach(medicine => {
        const statusClass = getStatusClass(medicine);
        const statusText = getStatusText(medicine);
        const row = `
          <tr>
            <td>${medicine.batchId}</td>
            <td>${medicine.code}</td>
            <td>${medicine.name}</td>
            <td>${medicine.quantity}</td>
            <td>${medicine.dispensed}</td>
            <td>${medicine.expiry}</td>
            <td><span class="status-badge ${statusClass}">${statusText}</span></td>
            <td>
              <button class="btn btn-secondary btn-sm view-btn" data-id="${medicine.id}">
                <i class="bi bi-eye"></i> View
              </button>
            </td>
          </tr>
        `;
        
        if (medicine.status === 'active') {
          activeTable.row.add($(row)[0]);
        } else if (medicine.status === 'bod') {
          bodTable.row.add($(row)[0]);
        } else if (medicine.status === 'archive') {
          archiveTable.row.add($(row)[0]);
        }
      });

      activeTable.draw();
      bodTable.draw();
      archiveTable.draw();
    }

    function renderStocks() {
      stocksTable.clear();

      medicines.forEach(medicine => {
        const statusClass = getStatusClass(medicine);
        const statusText = getStatusText(medicine);
        const row = `
          <tr>
            <td>${medicine.batchId}</td>
            <td>${medicine.code}</td>
            <td>${medicine.name}</td>
            <td>${medicine.quantity}</td>
            <td>${medicine.expiry}</td>
            <td><span class="status-badge ${statusClass}">${statusText}</span></td>
            <td>
              <button class="btn btn-secondary btn-sm view-btn" data-id="${medicine.id}">
                <i class="bi bi-eye"></i> View
              </button>
            </td>
          </tr>
        `;
        stocksTable.row.add($(row)[0]);
      });

      stocksTable.draw();
    }

    function sortMedicines() {
      const sortBy = $('#sortBy').val();
      medicines.sort((a, b) => {
        let compareA, compareB;
        switch(sortBy) {
          case 'batch': compareA = a.batchId; compareB = b.batchId; break;
          case 'code': compareA = a.code; compareB = b.code; break;
          case 'name': compareA = a.name; compareB = b.name; break;
          case 'quantity': compareA = a.quantity; compareB = b.quantity; break;
          case 'expiry': compareA = new Date(a.expiry); compareB = new Date(b.expiry); break;
          default: compareA = a.batchId; compareB = b.batchId;
        }
        if (compareA < compareB) return -1;
        if (compareA > compareB) return 1;
        return 0;
      });
      renderMedicines();
    }

    function updateOverview() {
      const total = medicines.length;
      const lowStock = medicines.filter(m => m.quantity <= 10 && m.status === 'active').length;
      const nearingExpiration = medicines.filter(m => isExpiredOrNearing(m.expiry) && m.status !== 'archive').length;
      $("#totalMedicinesCount").text(total);
      $("#lowStockCount").text(lowStock);
      $("#expiredCount").text(nearingExpiration);
    }
  </script>
</body>
</html>