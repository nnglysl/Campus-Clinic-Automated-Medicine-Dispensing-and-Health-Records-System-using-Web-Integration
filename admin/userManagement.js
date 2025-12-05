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

// Enhanced search function with filters
function searchSchedule() {
  const searchBtn = document.querySelector('.btn-primary');
  if (!searchBtn) return;
  const originalText = searchBtn.innerHTML;
  searchBtn.innerHTML = '<i class="bi bi-search spinner-border spinner-border-sm"></i> Searching...';
  searchBtn.disabled = true;
  
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
  
  let url = '../crud/refresh_schedule.php?';
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
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    })
    .then(data => {
      if (data.success) {
        console.log('Schedule data received:', data);
        console.log('Schedules count:', data.schedules?.length);
        if (data.schedules && data.schedules.length > 0) {
          console.log('Sample schedule entry:', data.schedules[0]);
          // Log nurse entries specifically
          const nurses = data.schedules.filter(s => s.role === 'nurse');
          console.log('Nurse entries:', nurses);
          if (nurses.length > 0) {
            console.log('First nurse data:', nurses[0]);
            console.log('Nurse attendance fields:', {
              time_in: nurses[0].attendance_time_in,
              time_out: nurses[0].attendance_time_out,
              time_in_raw: nurses[0].attendance_time_in_raw,
              time_out_raw: nurses[0].attendance_time_out_raw,
              attendance_date: nurses[0].attendance_date
            });
          }
        }
        updateScheduleHeader(data);
        updateScheduleDisplay(data.schedules, data.isWeekend || false);
      } else {
        console.error('Schedule fetch failed:', data);
      }
      searchBtn.innerHTML = originalText;
      searchBtn.disabled = false;
    })
    .catch(error => {
      console.error('Error fetching schedule:', error);
      alert('Error loading schedule data. Please try again or contact support if the problem persists.');
      searchBtn.innerHTML = originalText;
      searchBtn.disabled = false;
    });
}

// Alias for backward compatibility
function refreshSchedule() {
  searchSchedule();
}

// Update schedule header with count and date info
function updateScheduleHeader(data) {
  document.getElementById('scheduleDateDisplay').innerHTML = 
    `<i class="bi bi-calendar-week"></i> ${data.dateRangeInfo}`;
  document.getElementById('scheduleCount').textContent = 
    `${data.count} Employee(s)`;
}

// Update schedule display
function updateScheduleDisplay(schedules, isWeekend = false) {
  const container = document.getElementById('scheduleContainer');
  if (!container) return;
  
  if (schedules.length === 0) {
    container.innerHTML = `
      <div class="text-center py-5">
        <i class="bi bi-calendar-x" style="font-size: 3rem; color: #ccc;"></i>
        <p class="text-muted mt-3">No employees found matching your search criteria</p>
      </div>
    `;
    return;
  }
  
  // Remove duplicates by user_id (in case multiple schedules exist)
  const uniqueEmployees = [];
  const seenIds = new Set();
  schedules.forEach(emp => {
    if (!seenIds.has(emp.user_id)) {
      seenIds.add(emp.user_id);
      uniqueEmployees.push(emp);
    }
  });
  
  // Group schedules by date for multi-day views
  const viewType = document.getElementById('scheduleViewType').value;
  
  if (viewType === 'today') {
    // Single day view
    container.innerHTML = uniqueEmployees.map(emp => createEmployeeScheduleCard(emp, isWeekend)).join('');
  } else {
    // Multi-day view - group by date
    const groupedByDate = {};
    uniqueEmployees.forEach(emp => {
      const date = emp.schedule_date || 'No Date';
      if (!groupedByDate[date]) {
        groupedByDate[date] = [];
      }
      groupedByDate[date].push(emp);
    });
    
    const sortedDates = Object.keys(groupedByDate).sort();
    
    let html = '';
    sortedDates.forEach(date => {
      if (date !== 'No Date') {
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
      } else {
        html += `
          <div class="mb-4">
            <div class="date-group-header">
              <h6 class="text-muted mb-3">
                <i class="bi bi-calendar3"></i> No Schedule Date
                <span class="badge bg-secondary ms-2">${groupedByDate[date].length}</span>
              </h6>
            </div>
        `;
      }
      
      groupedByDate[date].forEach(emp => {
        // Check if this date is a weekend
        const dateObj = new Date(date + 'T00:00:00');
        const dayOfWeek = dateObj.getDay(); // 0 = Sunday, 6 = Saturday
        const isDateWeekend = (dayOfWeek === 0 || dayOfWeek === 6);
        html += createEmployeeScheduleCard(emp, isDateWeekend);
      });
      
      html += '</div>';
    });
    
    container.innerHTML = html;
  }
}

// Create employee schedule card HTML
function createEmployeeScheduleCard(emp, isWeekend = false) {
  const photoHtml = `<div class="employee-photo-small bg-secondary d-flex align-items-center justify-content-center">
         <i class="bi bi-person-fill text-white" style="font-size: 1rem;"></i>
       </div>`;
  
  let scheduleContent;
  const role = emp.role?.toLowerCase() || '';
  const isDoctorOrDentist = role === 'doctor' || role === 'dentist';
  const isNurse = role === 'nurse';
  
  // For nurses: always show attendance time in/out (they don't have schedules)
  if (isNurse) {
    // Check if there's attendance data for the selected date
    const hasAttendanceData = emp.attendance_date || 
                              (emp.attendance_time_in_raw !== null && emp.attendance_time_in_raw !== undefined) ||
                              (emp.attendance_time_out_raw !== null && emp.attendance_time_out_raw !== undefined) ||
                              (emp.attendance_time_in && emp.attendance_time_in !== null && emp.attendance_time_in !== '') ||
                              (emp.attendance_time_out && emp.attendance_time_out !== null && emp.attendance_time_out !== '');
    
    if (hasAttendanceData) {
      const statusBadges = {
        'in': '<span class="availability-badge availability-in"><i class="bi bi-check-circle-fill"></i> Checked In</span>',
        'out': '<span class="availability-badge availability-out"><i class="bi bi-x-circle-fill"></i> Checked Out</span>',
        'break': '<span class="availability-badge availability-break"><i class="bi bi-pause-circle-fill"></i> On Break</span>'
      };
      
      // Format time in/out - use formatted version if available, otherwise format raw
      let timeInDisplay = emp.attendance_time_in || '--:--';
      let timeOutDisplay = emp.attendance_time_out || '--:--';
      
      // If we have raw time data but no formatted, format it manually
      if (!emp.attendance_time_in && emp.attendance_time_in_raw) {
        const timeInParts = emp.attendance_time_in_raw.split(':');
        if (timeInParts.length >= 2) {
          const hour = parseInt(timeInParts[0]);
          const minute = timeInParts[1];
          const ampm = hour >= 12 ? 'PM' : 'AM';
          const displayHour = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
          timeInDisplay = `${displayHour}:${minute} ${ampm}`;
        }
      }
      
      if (!emp.attendance_time_out && emp.attendance_time_out_raw) {
        const timeOutParts = emp.attendance_time_out_raw.split(':');
        if (timeOutParts.length >= 2) {
          const hour = parseInt(timeOutParts[0]);
          const minute = timeOutParts[1];
          const ampm = hour >= 12 ? 'PM' : 'AM';
          const displayHour = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
          timeOutDisplay = `${displayHour}:${minute} ${ampm}`;
        }
      } else if (!emp.attendance_time_out && !emp.attendance_time_out_raw && emp.attendance_time_in) {
        // Clocked in but not out yet
        timeOutDisplay = '--';
      }
      
      scheduleContent = `
        <div class="schedule-table d-flex text-center">
          <div class="flex-fill border-end p-2">
            <small class="text-muted d-block">Time In</small>
            <strong>${timeInDisplay}</strong>
          </div>
          <div class="flex-fill border-end p-2">
            <small class="text-muted d-block">Time Out</small>
            <strong>${timeOutDisplay}</strong>
          </div>
          <div class="flex-fill border-end p-2">
            <small class="text-muted d-block">Status</small>
            ${statusBadges[emp.availability_status] || (emp.attendance_time_in ? statusBadges['in'] : statusBadges['out'])}
          </div>
          <div class="flex-fill p-2">
            <small class="text-muted d-block">Date</small>
            <strong style="font-size: 0.85rem;">${emp.attendance_date ? new Date(emp.attendance_date + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : (emp.schedule_date ? new Date(emp.schedule_date + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'Today')}</strong>
          </div>
        </div>
      `;
    } else {
      scheduleContent = `
        <div class="text-center">
          <span class="availability-badge availability-unavailable">
            <i class="bi bi-clock-history"></i> No Attendance Record
          </span>
        </div>
      `;
    }
  }
  // For doctors and dentists: show appointment schedule slots (weekdays only)
  else if (isDoctorOrDentist) {
    // Check if weekend - show "No Schedule" for weekends
    if (isWeekend) {
      scheduleContent = `
        <div class="text-center">
          <span class="availability-badge availability-unavailable">
            <i class="bi bi-calendar-x"></i> No Schedule
          </span>
        </div>
      `;
    } else if (emp.time_in && emp.is_available && emp.schedule_type !== 'unavailable') {
      const statusBadges = {
        'in': '<span class="availability-badge availability-in"><i class="bi bi-check-circle-fill"></i> Checked In</span>',
        'out': '<span class="availability-badge availability-out"><i class="bi bi-x-circle-fill"></i> Not Checked In</span>',
        'break': '<span class="availability-badge availability-break"><i class="bi bi-pause-circle-fill"></i> On Break</span>'
      };
      
      scheduleContent = `
        <div class="schedule-table d-flex text-center">
          <div class="flex-fill border-end p-2">
            <small class="text-muted d-block">Appointment Schedule</small>
            <strong>${emp.time_in_formatted || 'N/A'} - ${emp.time_out_formatted || 'N/A'}</strong>
          </div>
          <div class="flex-fill border-end p-2">
            <small class="text-muted d-block">Duration</small>
            <strong>${emp.duration || 'N/A'}</strong>
          </div>
          <div class="flex-fill border-end p-2">
            <small class="text-muted d-block">Status</small>
            ${statusBadges[emp.availability_status] || statusBadges['out']}
          </div>
          <div class="flex-fill p-2">
            <small class="text-muted d-block">Last Check-in</small>
            <strong style="font-size: 0.85rem;">${emp.attendance_time_in || 'N/A'}</strong>
          </div>
        </div>
      `;
    } else if (emp.schedule_type === 'unavailable' && emp.reason) {
      scheduleContent = `
        <div class="text-center">
          <span class="availability-badge availability-unavailable">
            <i class="bi bi-calendar-x"></i> Unavailable
            <small class="d-block mt-1" style="font-size: 0.75rem; opacity: 0.8;">
              ${emp.reason || ''}
            </small>
          </span>
        </div>
      `;
    } else {
      scheduleContent = `
        <div class="text-center">
          <span class="availability-badge availability-unavailable">
            <i class="bi bi-calendar-x"></i> No Schedule
          </span>
        </div>
      `;
    }
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
  // Initialize date picker - check URL parameter first, then use existing value, then default to today
  const datePicker = document.getElementById('scheduleDatePicker');
  const urlParams = new URLSearchParams(window.location.search);
  const urlDate = urlParams.get('date');
  const today = new Date().toISOString().split('T')[0];
  
  // Use URL date parameter if available, otherwise use the date picker's current value, otherwise use today
  if (urlDate) {
    datePicker.value = urlDate;
  } else if (!datePicker.value || datePicker.value === '') {
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
    searchSchedule();
  });
  
  // Department filter change
  document.getElementById('scheduleDepartment')?.addEventListener('change', function() {
    searchSchedule();
  });
  
  // Date picker change
  document.getElementById('scheduleDatePicker').addEventListener('change', function() {
    const selectedDate = this.value;
    console.log('Date picker changed to:', selectedDate);
    
    // Make sure view type is set to 'today' when using date picker
    document.getElementById('scheduleViewType').value = 'today';
    
    // Force search with the selected date
    searchSchedule();
  });
  
  // Search with debounce
  document.getElementById('scheduleSearch').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      searchSchedule();
    }, 500);
  });
  
  // Initial load of schedules
  searchSchedule();
});

// Start auto-refresh when page loads
startScheduleRefresh();

// Stop refresh when navigating away
window.addEventListener('beforeunload', stopScheduleRefresh);

// Initialize DataTables
$(document).ready(function() {
  // Ensure employees is defined
  if (typeof employees === 'undefined') {
    console.error('Employees data not loaded');
    window.employees = [];
  }
  
  console.log('Total employees loaded:', employees.length);
  console.log('Employees data:', employees);
  
  // Filter active employees (case-insensitive)
  const activeEmployees = employees.filter(e => {
    const status = (e.status || '').toLowerCase();
    return status === 'active';
  });
  
  console.log('Active employees count:', activeEmployees.length);
  console.log('Active employees:', activeEmployees);
  
  // Active Users Table
  const activeTable = $('#activeUsersTable').DataTable({
    data: activeEmployees,
    columns: [
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
            <div class="btn-group" role="group">
              <button class="btn btn-sm btn-info" onclick="openEditModal(${data})" title="Edit">
                <i class="bi bi-pencil-square"></i> Edit
              </button>
              <button class="btn btn-sm btn-warning" onclick="updateStatus(${data}, 'inactive')" title="Deactivate">
                <i class="bi bi-pause-circle"></i> Deactivate
              </button>
              <button class="btn btn-sm btn-danger" onclick="deleteEmployee(${data})" title="Delete">
                <i class="bi bi-trash"></i> Delete
              </button>
            </div>
          `;
        }
      }
    ],
    pageLength: 10,
    order: [[1, 'asc']]
  });
  
  // Filter inactive employees (case-insensitive)
  const inactiveEmployees = employees.filter(e => {
    const status = (e.status || '').toLowerCase();
    return status === 'inactive';
  });
  
  console.log('Inactive employees count:', inactiveEmployees.length);
  
  // Inactive Users Table
  const inactiveTable = $('#inactiveUsersTable').DataTable({
    data: inactiveEmployees,
    columns: [
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
            <div class="btn-group" role="group">
              <button class="btn btn-sm btn-success" onclick="updateStatus(${data}, 'active')" title="Activate">
                <i class="bi bi-check-circle"></i> Activate
              </button>
              <button class="btn btn-sm btn-danger" onclick="deleteEmployee(${data})" title="Delete">
                <i class="bi bi-trash"></i> Delete
              </button>
            </div>
          `;
        }
      }
    ],
    pageLength: 10,
    order: [[1, 'asc']]
  });
  
  // Reload tables when status is updated
  window.reloadEmployeeTables = function() {
    const active = employees.filter(e => (e.status || '').toLowerCase() === 'active');
    const inactive = employees.filter(e => (e.status || '').toLowerCase() === 'inactive');
    
    activeTable.clear();
    activeTable.rows.add(active);
    activeTable.draw();
    
    inactiveTable.clear();
    inactiveTable.rows.add(inactive);
    inactiveTable.draw();
  };
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
            // Update the employee in the local array
            const empIndex = employees.findIndex(e => e.id == employeeId);
            if (empIndex !== -1) {
              employees[empIndex].status = status;
            }
            // Reload tables if function exists, otherwise reload page
            if (typeof window.reloadEmployeeTables === 'function') {
              window.reloadEmployeeTables();
            } else {
              location.reload();
            }
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