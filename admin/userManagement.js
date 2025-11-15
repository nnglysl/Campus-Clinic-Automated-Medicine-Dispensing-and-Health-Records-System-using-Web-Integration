let scheduleRefreshInterval;
let searchTimeout;

function startScheduleRefresh() {
  scheduleRefreshInterval = setInterval(() => {
    const scheduleTab = document.getElementById('schedule');
    if (scheduleTab && scheduleTab.classList.contains('active')) {
      refreshSchedule();
    }
  }, 30000);
}

function stopScheduleRefresh() {
  if (scheduleRefreshInterval) {
    clearInterval(scheduleRefreshInterval);
  }
}

// Enhanced refresh function with filters
function refreshSchedule() {
  const refreshBtn = document.querySelector('.btn-outline-success');
  const originalText = refreshBtn.innerHTML;
  refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise spinner-border spinner-border-sm"></i> Refreshing...';
  refreshBtn.disabled = true;
  
  const viewType = document.getElementById('scheduleViewType').value;
  const datePicker = document.getElementById('scheduleDatePicker');
  
  // Use date picker value, or fallback to today's date
  let date = datePicker.value;
  if (!date || date === '') {
    const today = new Date().toISOString().split('T')[0];
    datePicker.value = today;
    date = today;
  }
  
  const search = document.getElementById('scheduleSearch').value;
  const department = document.getElementById('scheduleDepartment')?.value || 'all';
  
  console.log('Refreshing with:', { viewType, date, search, department });
  
  let url = 'refresh_schedule.php?';
  const params = new URLSearchParams();
  
  if (viewType === 'today') {
    params.append('view', 'today');
    params.append('date', date);
  } else {
    params.append('view', viewType);
  }
  
  if (search) {
    params.append('search', search);
  }
  
  if (department !== 'all') {
    params.append('department', department);
  }
  
  console.log('Fetch URL:', url + params.toString());
  
  // Actually fetch the data
  fetch(url + params.toString())
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        updateScheduleHeader(data);
        updateScheduleDisplay(data.schedules);
      }
      refreshBtn.innerHTML = originalText;
      refreshBtn.disabled = false;
    })
    .catch(error => {
      console.error('Error:', error);
      refreshBtn.innerHTML = originalText;
      refreshBtn.disabled = false;
    });
}

// Update schedule header with count and date info
function updateScheduleHeader(data) {
  document.getElementById('scheduleDateDisplay').innerHTML = 
    `<i class="bi bi-calendar-week"></i> ${data.dateRangeInfo}`;
  document.getElementById('scheduleCount').textContent = 
    `${data.count} Employee(s)`;
}

// Update schedule display
function updateScheduleDisplay(schedules) {
  const container = document.getElementById('scheduleContainer');
  if (!container) return;
  
  if (schedules.length === 0) {
    container.innerHTML = `
      <div class="text-center py-5">
        <i class="bi bi-calendar-x" style="font-size: 3rem; color: #ccc;"></i>
        <p class="text-muted mt-3">No schedules found</p>
      </div>
    `;
    return;
  }
  
  // Group schedules by date for multi-day views
  const viewType = document.getElementById('scheduleViewType').value;
  
  if (viewType === 'today') {
    // Single day view
    container.innerHTML = schedules.map(emp => createEmployeeScheduleCard(emp)).join('');
  } else {
    // Multi-day view - group by date
    const groupedByDate = {};
    schedules.forEach(emp => {
      if (emp.schedule_date) {
        if (!groupedByDate[emp.schedule_date]) {
          groupedByDate[emp.schedule_date] = [];
        }
        groupedByDate[emp.schedule_date].push(emp);
      }
    });
    
    const sortedDates = Object.keys(groupedByDate).sort();
    
    let html = '';
    sortedDates.forEach(date => {
      const dateObj = new Date(date + 'T00:00:00');
      const options = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' };
      const formattedDate = dateObj.toLocaleDateString('en-US', options);
      
      html += `
        <div class="mb-4">
          <div class="date-group-header">
            <h6 class="text-muted mb-3">
              <i class="bi bi-calendar3"></i> ${formattedDate}
              <span class="badge bg-secondary ms-2">${groupedByDate[date].length}</span>
            </h6>
          </div>
      `;
      
      groupedByDate[date].forEach(emp => {
        html += createEmployeeScheduleCard(emp);
      });
      
      html += '</div>';
    });
    
    container.innerHTML = html;
  }
}

// Create employee schedule card HTML
function createEmployeeScheduleCard(emp) {
  const photoHtml = emp.photo 
    ? `<img src="${emp.photo}" class="employee-photo-small" alt="${emp.first_name}">`
    : `<div class="employee-photo-small bg-secondary d-flex align-items-center justify-content-center">
         <i class="bi bi-person-fill text-white" style="font-size: 1rem;"></i>
       </div>`;
  
  let scheduleContent;
  if (emp.time_in && emp.is_available) {
    const statusBadges = {
      'in': '<span class="availability-badge availability-in"><i class="bi bi-check-circle-fill"></i> Checked In</span>',
      'out': '<span class="availability-badge availability-out"><i class="bi bi-x-circle-fill"></i> Not Checked In</span>',
      'break': '<span class="availability-badge availability-break"><i class="bi bi-pause-circle-fill"></i> On Break</span>'
    };
    
    scheduleContent = `
      <div class="schedule-table d-flex text-center">
        <div class="flex-fill border-end p-2">
          <small class="text-muted d-block">Schedule</small>
          <strong>${emp.time_in_formatted || 'N/A'} - ${emp.time_out_formatted || 'N/A'}</strong>
        </div>
        <div class="flex-fill border-end p-2">
          <small class="text-muted d-block">Duration</small>
          <strong>${emp.duration || 'N/A'}</strong>
        </div>
        <div class="flex-fill border-end p-2">
          <small class="text-muted d-block">Attendance</small>
          ${statusBadges[emp.availability_status] || statusBadges['out']}
        </div>
        <div class="flex-fill p-2">
          <small class="text-muted d-block">Last Check-in</small>
          <strong style="font-size: 0.85rem;">${emp.last_check_in || 'N/A'}</strong>
        </div>
      </div>
    `;
  } else {
    scheduleContent = `
      <div class="text-center">
        <span class="availability-badge availability-unavailable">
          <i class="bi bi-calendar-x"></i> Unavailable
        </span>
      </div>
    `;
  }
  
  return `
    <div class="employee-schedule-row">
      <div class="employee-schedule-info">
        ${photoHtml}
        <div>
          <div><strong>${emp.first_name} ${emp.middle_name ? emp.middle_name + ' ' : ''}${emp.last_name}</strong></div>
          <div class="text-muted" style="font-size: 0.85rem;">
            <span class="role-badge role-${emp.role}">
              <i class="bi bi-person-badge-fill"></i> ${emp.role.charAt(0).toUpperCase() + emp.role.slice(1)}
            </span>
          </div>
        </div>
      </div>
      <div class="employee-schedule-details">
        ${scheduleContent}
      </div>
    </div>
  `;
}

// Function to open edit modal and populate data
function openEditModal(employeeId) {
  const employee = employees.find(e => e.id === employeeId);
  if (!employee) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Employee not found'
    });
    return;
  }
  
  // Populate form fields
  document.getElementById('edit_employee_id').value = employee.id;
  document.getElementById('edit_first_name').value = employee.first_name;
  document.getElementById('edit_middle_name').value = employee.middle_name || '';
  document.getElementById('edit_last_name').value = employee.last_name;
  document.getElementById('edit_birth_date').value = employee.birth_date;
  document.getElementById('edit_gender').value = employee.gender;
  document.getElementById('edit_email').value = employee.email;
  document.getElementById('edit_phone').value = employee.phone;
  document.getElementById('edit_address').value = employee.address;
  document.getElementById('edit_role').value = employee.role;
  document.getElementById('edit_password').value = '';
  
  // Show modal
  const modal = new bootstrap.Modal(document.getElementById('editEmployeeModal'));
  modal.show();
}

// Function to submit edit employee form
function submitEditEmployee() {
  const form = document.getElementById('editEmployeeForm');
  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }
  
  const formData = new FormData(form);
  formData.append('action', 'edit_employee');
  
  fetch('userManagement.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: data.message,
        confirmButtonText: 'OK'
      }).then(() => {
        location.reload();
      });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: data.message,
        confirmButtonText: 'OK'
      });
    }
  })
  .catch(error => {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Error updating employee: ' + error,
      confirmButtonText: 'OK'
    });
  });
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
  // Initialize date picker with today's date
  const datePicker = document.getElementById('scheduleDatePicker');
  const today = new Date().toISOString().split('T')[0];
  
  if (!datePicker.value || datePicker.value === '') {
    datePicker.value = today;
  }
  
  console.log('Initial date picker value:', datePicker.value);
  
  // View type change
  document.getElementById('scheduleViewType').addEventListener('change', function() {
    const datePicker = document.getElementById('scheduleDatePicker');
    if (this.value === 'today') {
      datePicker.disabled = false;
    } else {
      datePicker.disabled = true;
    }
    refreshSchedule();
  });
  
  // Date picker change
  document.getElementById('scheduleDatePicker').addEventListener('change', function() {
    const selectedDate = this.value;
    console.log('Date picker changed to:', selectedDate);
    
    // Make sure view type is set to 'today' when using date picker
    document.getElementById('scheduleViewType').value = 'today';
    
    // Force refresh with the selected date
    refreshSchedule();
  });
  
  // Search with debounce
  document.getElementById('scheduleSearch').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      refreshSchedule();
    }, 500);
  });
});

// Start auto-refresh when page loads
startScheduleRefresh();

// Stop refresh when navigating away
window.addEventListener('beforeunload', stopScheduleRefresh);

// Initialize DataTables
$(document).ready(function() {
  // Active Users Table
  $('#activeUsersTable').DataTable({
    data: employees.filter(e => e.status === 'active'),
    columns: [
      { 
        data: 'photo',
        render: function(data, type, row) {
          if (data) {
            return `<img src="${data}" class="employee-photo-small" alt="${row.first_name}">`;
          }
          return '<div class="employee-photo-small bg-secondary d-flex align-items-center justify-content-center"><i class="bi bi-person-fill text-white"></i></div>';
        }
      },
      { 
        data: null,
        render: function(data, type, row) {
          return `${row.first_name} ${row.middle_name || ''} ${row.last_name}`.trim();
        }
      },
      { data: 'email' },
      { data: 'phone' },
      { 
        data: 'role',
        render: function(data) {
          const roleDisplay = data === 'doctor' ? 'Medical Doctor' : 
                            data === 'dentist' ? 'Dentist' : 
                            data.charAt(0).toUpperCase() + data.slice(1);
          return `<span class="role-badge role-${data}">${roleDisplay}</span>`;
        }
      },
      { 
        data: 'id',
        render: function(data, type, row) {
          return `
            <button class="btn btn-sm btn-info me-1" onclick="openEditModal(${data})" title="Edit">
              <i class="bi bi-pencil-square"></i> Edit
            </button>
            <button class="btn btn-sm btn-warning me-1" onclick="updateStatus(${data}, 'inactive')" title="Deactivate">
              <i class="bi bi-pause-circle"></i> Deactivate
            </button>
            <button class="btn btn-sm btn-danger" onclick="deleteEmployee(${data})" title="Delete">
              <i class="bi bi-trash"></i> Delete
            </button>
          `;
        }
      }
    ]
  });
  
  // Inactive Users Table
  $('#inactiveUsersTable').DataTable({
    data: employees.filter(e => e.status === 'inactive'),
    columns: [
      { 
        data: 'photo',
        render: function(data, type, row) {
          if (data) {
            return `<img src="${data}" class="employee-photo-small" alt="${row.first_name}">`;
          }
          return '<div class="employee-photo-small bg-secondary d-flex align-items-center justify-content-center"><i class="bi bi-person-fill text-white"></i></div>';
        }
      },
      { 
        data: null,
        render: function(data, type, row) {
          return `${row.first_name} ${row.middle_name || ''} ${row.last_name}`.trim();
        }
      },
      { data: 'email' },
      { data: 'phone' },
      { 
        data: 'role',
        render: function(data) {
          const roleDisplay = data === 'doctor' ? 'Medical Doctor' : 
                            data === 'dentist' ? 'Dentist' : 
                            data.charAt(0).toUpperCase() + data.slice(1);
          return `<span class="role-badge role-${data}">${roleDisplay}</span>`;
        }
      },
      { 
        data: 'id',
        render: function(data) {
          return `
            <button class="btn btn-sm btn-success me-1" onclick="updateStatus(${data}, 'active')">
              <i class="bi bi-check-circle"></i> Activate
            </button>
            <button class="btn btn-sm btn-danger" onclick="deleteEmployee(${data})">
              <i class="bi bi-trash"></i> Delete
            </button>
          `;
        }
      }
    ]
  });
});

// Submit Add Employee Form
function submitAddEmployee() {
  const form = document.getElementById('addEmployeeForm');
  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }
  
  const formData = new FormData(form);
  formData.append('action', 'add_employee');
  
  fetch('userManagement.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: data.message,
        confirmButtonText: 'OK'
      }).then(() => {
        location.reload();
      });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: data.message,
        confirmButtonText: 'OK'
      });
    }
  })
  .catch(error => {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Error adding employee: ' + error,
      confirmButtonText: 'OK'
    });
  });
}

// Update Employee Status
function updateStatus(employeeId, status) {
  Swal.fire({
    title: 'Are you sure?',
    text: `Do you want to ${status === 'active' ? 'activate' : 'deactivate'} this employee?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, proceed!'
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('action', 'update_status');
      formData.append('employee_id', employeeId);
      formData.append('status', status);
      
      fetch('userManagement.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire('Updated!', data.message, 'success').then(() => {
            location.reload();
          });
        } else {
          Swal.fire('Error', data.message, 'error');
        }
      })
      .catch(error => {
        Swal.fire('Error', 'Error updating status: ' + error, 'error');
      });
    }
  });
}

// Delete Employee
function deleteEmployee(employeeId) {
  Swal.fire({
    title: 'Are you sure?',
    text: 'This action cannot be undone!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('action', 'delete_employee');
      formData.append('employee_id', employeeId);
      
      fetch('userManagement.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire('Deleted!', data.message, 'success').then(() => {
            location.reload();
          });
        } else {
          Swal.fire('Error', data.message, 'error');
        }
      })
      .catch(error => {
        Swal.fire('Error', 'Error deleting employee: ' + error, 'error');
      });
    }
  });
}