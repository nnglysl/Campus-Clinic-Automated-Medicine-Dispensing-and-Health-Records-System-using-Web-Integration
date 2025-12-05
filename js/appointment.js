const APPOINTMENT_API = '../crud/appointment_handler.php';
const CALENDAR_API = '../api/calendar_api.php';
const DOCTOR_SCHEDULE_API = '../api/doctor_schedule_api.php';

let doctorAvailableDays = [];
let appointments = [];
let calendarEvents = [];
let currentDate = new Date();
let activeTab = 'today';
let isLoading = false;

// Enhanced logging function
function logDebug(category, message, data = null) {
  const timestamp = new Date().toISOString();
  const logMessage = `[${timestamp}] [${category}] ${message}`;
  
  if (data) {
    console.log(logMessage, data);
  } else {
    console.log(logMessage);
  }
  
  // Store logs for debugging
  if (!window.debugLogs) window.debugLogs = [];
  window.debugLogs.push({ timestamp, category, message, data });
}

// Error handler with detailed logging
function handleError(context, error, showUser = true) {
  logDebug('ERROR', `${context}:`, {
    message: error.message,
    stack: error.stack,
    error: error
  });
  
  if (showUser) {
    showNotification(`Error: ${error.message || 'Something went wrong'}`, true);
  }
}

document.addEventListener('DOMContentLoaded', async () => {
  logDebug('INIT', '=== Initializing Appointment Management ===');

  const calendar = document.getElementById('calendar');
  const appointmentsContent = document.getElementById('appointmentsContent');

  if (!calendar || !appointmentsContent) {
    logDebug('ERROR', 'Missing DOM elements', {
      calendar: !!calendar,
      appointmentsContent: !!appointmentsContent
    });
    return;
  }

  logDebug('INIT', 'DOM elements found');

  try {
    // Load doctor's schedule first
    await loadDoctorSchedule();

    // Then load everything else
    initializeEventListeners();
    await loadAllData();

    // Set up periodic refresh
    setInterval(async () => {
      logDebug('REFRESH', 'Auto-refreshing data');
      await loadAllData();
    }, 30000);
    
    logDebug('INIT', 'Initialization complete');
  } catch (error) {
    handleError('Initialization', error);
  }
});

async function loadDoctorSchedule() {
  logDebug('SCHEDULE', 'Loading doctor schedule from:', DOCTOR_SCHEDULE_API);
  
  try {
    const res = await fetch(DOCTOR_SCHEDULE_API);
    logDebug('SCHEDULE', 'Response status:', res.status);
    
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    }
    
    const data = await res.json();
    logDebug('SCHEDULE', 'Response data:', data);

    if (data.success && Array.isArray(data.availableDays)) {
      doctorAvailableDays = data.availableDays;
      logDebug('SCHEDULE', 'Doctor schedule loaded:', doctorAvailableDays);
    } else {
      logDebug('SCHEDULE', 'No schedule data, using default (all days)');
      doctorAvailableDays = [0, 1, 2, 3, 4, 5, 6];
    }
  } catch (error) {
    handleError('Load Doctor Schedule', error, false);
    logDebug('SCHEDULE', 'Fallback to all days available');
    doctorAvailableDays = [0, 1, 2, 3, 4, 5, 6];
  }
}

function initializeEventListeners() {
  logDebug('EVENTS', 'Setting up event listeners');
  
  // Tab switching
  document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', handleTabSwitch);
  });

  // Calendar navigation
  const prevBtn = document.getElementById('prevMonth');
  const nextBtn = document.getElementById('nextMonth');
  
  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      currentDate.setMonth(currentDate.getMonth() - 1);
      logDebug('NAV', 'Previous month:', currentDate.toISOString());
      renderCalendar();
    });
  } else {
    logDebug('ERROR', 'Previous month button not found');
  }
  
  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      currentDate.setMonth(currentDate.getMonth() + 1);
      logDebug('NAV', 'Next month:', currentDate.toISOString());
      renderCalendar();
    });
  } else {
    logDebug('ERROR', 'Next month button not found');
  }

  // Search
  const searchInput = document.getElementById('searchInput');
  if (searchInput) {
    searchInput.addEventListener('input', handleSearch);
  } else {
    logDebug('WARN', 'Search input not found');
  }
  
  logDebug('EVENTS', 'Event listeners attached');
}

// ==========================================
// DATA LOADING
// ==========================================

async function loadAllData() {
  if (isLoading) {
    logDebug('LOAD', 'Already loading data, skipping...');
    return;
  }
  
  isLoading = true;
  logDebug('LOAD', '=== Loading All Data ===');
  
  try {
    showLoadingState();
    
    // Load appointments first (primary data)
    logDebug('LOAD', 'Step 1: Loading appointments');
    await loadAppointments();
    
    // Load calendar events (secondary data - optional)
    logDebug('LOAD', 'Step 2: Loading calendar events');
    await loadCalendarEvents().catch(err => {
      logDebug('WARN', 'Calendar events loading failed (non-critical):', err);
    });
    
    // Merge and render
    logDebug('LOAD', 'Step 3: Merging and rendering');
    mergeCalendarData();
    renderCalendar();
    renderAppointments();
    
    logDebug('LOAD', '✓ All data loaded successfully');
  } catch (error) {
    handleError('Load All Data', error);
    
    // Still try to render with whatever data we have
    renderCalendar();
    renderAppointments();
  } finally {
    isLoading = false;
  }
}

async function loadAppointments() {
  const url = `${APPOINTMENT_API}?action=list`;
  logDebug('APPOINTMENTS', 'Loading appointments from:', url);
  
  try {
    const response = await fetch(url);
    logDebug('APPOINTMENTS', 'Response status:', response.status);
    logDebug('APPOINTMENTS', 'Response headers:', {
      contentType: response.headers.get('content-type'),
      status: response.status,
      statusText: response.statusText
    });
    
    if (!response.ok) {
      const errorText = await response.text();
      logDebug('APPOINTMENTS', 'Error response body:', errorText);
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }
    
    const responseText = await response.text();
    logDebug('APPOINTMENTS', 'Raw response:', responseText.substring(0, 500));
    
    let result;
    try {
      result = JSON.parse(responseText);
    } catch (parseError) {
      logDebug('ERROR', 'JSON parse error:', parseError);
      logDebug('ERROR', 'Response was:', responseText);
      throw new Error('Invalid JSON response from server');
    }
    
    logDebug('APPOINTMENTS', 'Parsed result:', result);

    if (result.success && Array.isArray(result.data)) {
      appointments = result.data.map(apt => ({
        id: apt.id,
        date: apt.appointment_date,
        time: convertFrom24Hour(apt.appointment_time),
        name: `${apt.fname} ${apt.lname}`,
        email: apt.email,
        phone: apt.phone,
        patientId: apt.patient_id,
        status: determineStatus(apt.appointment_date, apt.status),
        type: apt.appointment_type,
        notes: apt.notes || '',
        calendarEventId: apt.calendar_event_id
      }));
      
      logDebug('APPOINTMENTS', `✓ Loaded ${appointments.length} appointments`, {
        count: appointments.length,
        sample: appointments[0]
      });
    } else {
      throw new Error(result.error || 'Invalid response format');
    }
  } catch (error) {
    handleError('Load Appointments', error);
    appointments = [];
    throw error;
  }
}

async function loadCalendarEvents() {
  logDebug('CALENDAR', 'Loading calendar events');
  
  try {
    // Get events for current month ±1 month
    const startDate = new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1);
    const endDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + 2, 0);
    
    const timeMin = startDate.toISOString();
    const timeMax = endDate.toISOString();
    
    const url = `${CALENDAR_API}?action=list&timeMin=${encodeURIComponent(timeMin)}&timeMax=${encodeURIComponent(timeMax)}`;
    logDebug('CALENDAR', 'Calendar events URL:', url);
    
    const response = await fetch(url);
    logDebug('CALENDAR', 'Response status:', response.status);
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }
    
    const result = await response.json();
    logDebug('CALENDAR', 'Calendar events result:', result);

    if (result.success && result.data?.items) {
      calendarEvents = result.data.items;
      logDebug('CALENDAR', `✓ Loaded ${calendarEvents.length} calendar events`);
    } else {
      calendarEvents = [];
      logDebug('CALENDAR', 'No calendar events loaded');
    }
  } catch (error) {
    handleError('Load Calendar Events', error, false);
    calendarEvents = [];
  }
}

function mergeCalendarData() {
  logDebug('MERGE', 'Merging calendar data');
  
  let syncedCount = 0;
  appointments.forEach(apt => {
    if (apt.calendarEventId) {
      const calEvent = calendarEvents.find(e => e.id === apt.calendarEventId);
      if (calEvent) {
        apt.calendarSynced = true;
        apt.calendarLink = calEvent.htmlLink;
        syncedCount++;
      }
    }
  });
  
  logDebug('MERGE', `✓ Merged data: ${syncedCount}/${appointments.length} appointments synced with calendar`);
}

// ==========================================
// CALENDAR RENDERING
// ==========================================

function renderCalendar() {
  logDebug('RENDER', '=== Rendering Calendar ===');
  
  const calendar = document.getElementById('calendar');
  if (!calendar) {
    logDebug('ERROR', 'Calendar element not found!');
    return;
  }
  
  const year = currentDate.getFullYear();
  const month = currentDate.getMonth();
  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  logDebug('RENDER', 'Calendar details:', {
    year,
    month: month + 1,
    firstDay: firstDay.toISOString(),
    lastDay: lastDay.toISOString(),
    daysInMonth: lastDay.getDate()
  });

  // Update month/year display
  const monthYearEl = document.getElementById('monthYear');
  if (monthYearEl) {
    monthYearEl.textContent = `${firstDay.toLocaleString('default', { month: 'long' })} ${year}`;
  }
  
  // Clear calendar
  calendar.innerHTML = '';

  // Weekday headers
  const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  weekdays.forEach(day => {
    const weekday = document.createElement('div');
    weekday.className = 'weekday';
    weekday.textContent = day;
    calendar.appendChild(weekday);
  });

  // Empty cells before first day
  const startDay = firstDay.getDay();
  logDebug('RENDER', `Adding ${startDay} empty cells before first day`);
  for (let i = 0; i < startDay; i++) {
    const empty = document.createElement('div');
    empty.className = 'day-cell other-month';
    calendar.appendChild(empty);
  }

  // Days of month
  const totalDays = lastDay.getDate();
  logDebug('RENDER', `Rendering ${totalDays} days`);
  
  for (let day = 1; day <= totalDays; day++) {
    const date = new Date(year, month, day);
    const dateString = date.toISOString().split('T')[0];
    const dayAppointments = appointments.filter(
      a => a.date === dateString && a.status !== 'cancelled'
    );
    const isAvailable = doctorAvailableDays.includes(date.getDay());
    const isPast = date < today;

    const dayCell = document.createElement('div');
    dayCell.className = 'day-cell';
    dayCell.dataset.date = dateString;

    // Apply styling
    if (isPast) {
      dayCell.classList.add('other-month');
    } else if (!isAvailable) {
      dayCell.classList.add('unavailable');
    } else if (dayAppointments.length > 0) {
      dayCell.classList.add('has-bookings');
    } else {
      dayCell.classList.add('available');
    }

    // Day number
    const dayNumber = document.createElement('div');
    dayNumber.className = 'day-number';
    dayNumber.textContent = day;
    dayCell.appendChild(dayNumber);

    // Badge
    if (!isPast) {
      const badge = document.createElement('div');
      badge.className = 'day-badge';

      if (!isAvailable) {
        badge.classList.add('badge-unavailable');
        badge.textContent = 'Not Available';
      } else if (dayAppointments.length > 0) {
        badge.classList.add('badge-bookings');
        badge.textContent = `${dayAppointments.length} Booking${dayAppointments.length > 1 ? 's' : ''}`;
      } else {
        badge.classList.add('badge-available');
        badge.textContent = 'Available';
      }

      dayCell.appendChild(badge);
    }

    // Click handler for available days
    if (!isPast && isAvailable) {
      dayCell.style.cursor = 'pointer';
      dayCell.addEventListener('click', () => handleDayClick(dateString, dayAppointments));
    }

    calendar.appendChild(dayCell);
  }
  
  logDebug('RENDER', '✓ Calendar rendered successfully');
}

function handleDayClick(date, appointments) {
  logDebug('CLICK', 'Day clicked:', { date, appointmentCount: appointments.length });
  
  if (appointments.length === 0) {
    showNotification('No appointments for this date', false);
    return;
  }
  
  activeTab = 'today';
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.querySelector('.tab[data-tab="today"]')?.classList.add('active');
  
  renderAppointments(appointments);
}

// ==========================================
// APPOINTMENTS RENDERING
// ==========================================

function renderAppointments(customList = null) {
  logDebug('RENDER', '=== Rendering Appointments ===');
  
  const content = document.getElementById('appointmentsContent');
  if (!content) {
    logDebug('ERROR', 'Appointments content element not found!');
    return;
  }

  const filtered = customList || appointments.filter(a => a.status === activeTab);
  logDebug('RENDER', `Showing ${filtered.length} appointments`, {
    tab: activeTab,
    totalAppointments: appointments.length,
    filtered: filtered.length
  });

  if (filtered.length === 0) {
    content.innerHTML = `
      <div class="empty-state">
        <i class="bi bi-calendar-x" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
        <p>No ${customList ? '' : activeTab} appointments</p>
      </div>
    `;
    return;
  }

  content.innerHTML = filtered.map(apt => `
    <div class="appointment-item ${apt.type}">
      <div class="appointment-info">
        <div class="appointment-name">
          ${apt.name}
          ${apt.calendarSynced ? 
            '<span class="calendar-synced-badge"><i class="bi bi-calendar-check-fill"></i> Synced</span>' : 
            '<span class="calendar-synced-badge" style="background: #ff9800;"><i class="bi bi-exclamation-triangle-fill"></i> Not Synced</span>'
          }
        </div>
        
        <div class="appointment-details">
          <span><i class="bi bi-calendar-event"></i> ${formatDate(apt.date)}</span>
          <span><i class="bi bi-clock"></i> ${apt.time}</span>
          <span class="appointment-type type-${apt.type}">
            <i class="bi ${apt.type === 'medical' ? 'bi-heart-pulse' : 'bi-tooth'}"></i>
            ${apt.type}
          </span>
        </div>

        ${apt.phone ? `
          <div class="appointment-details">
            <span><i class="bi bi-telephone"></i> ${apt.phone}</span>
            <span><i class="bi bi-envelope"></i> ${apt.email}</span>
          </div>
        ` : ''}
        
        ${apt.notes ? `
          <div class="appointment-notes">
            <i class="bi bi-chat-left-text"></i>
            <span>${apt.notes}</span>
          </div>
        ` : ''}
      </div>
      
      <div class="appointment-actions">
        ${apt.calendarSynced && apt.calendarLink ? `
          <button class="btn-calendar btn-view" onclick="window.open('${apt.calendarLink}', '_blank')">
            <i class="bi bi-google"></i> View in Calendar
          </button>
        ` : `
          <button class="btn-calendar btn-sync" onclick="syncAppointment(${apt.id})">
            <i class="bi bi-arrow-repeat"></i> Sync to Calendar
          </button>
        `}
        
        ${apt.status !== 'cancelled' ? `
          <button class="btn-calendar btn-cancel" onclick="cancelAppointment(${apt.id})">
            <i class="bi bi-x-circle"></i> Cancel
          </button>
        ` : ''}
      </div>
    </div>
  `).join('');
  
  logDebug('RENDER', '✓ Appointments rendered');
}

function showLoadingState() {
  const content = document.getElementById('appointmentsContent');
  if (content) {
    content.innerHTML = `
      <div class="text-center py-4">
        <div class="spinner-border" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Loading appointments...</p>
      </div>
    `;
  }
}

// ==========================================
// CALENDAR SYNC OPERATIONS
// ==========================================

async function syncAppointment(appointmentId) {
  logDebug('SYNC', 'Syncing appointment:', appointmentId);
  
  try {
    showNotification('Syncing to Google Calendar...');
    
    const response = await fetch(`${APPOINTMENT_API}?action=sync&id=${appointmentId}`, {
      method: 'POST'
    });
    
    logDebug('SYNC', 'Sync response status:', response.status);
    const result = await response.json();
    logDebug('SYNC', 'Sync result:', result);

    if (result.success) {
      showNotification('✓ Successfully synced to Google Calendar');
      await loadAllData();
    } else {
      throw new Error(result.error || 'Sync failed');
    }
  } catch (error) {
    handleError('Sync Appointment', error);
  }
}

async function cancelAppointment(appointmentId) {
  logDebug('CANCEL', 'Cancelling appointment:', appointmentId);
  
  if (!confirm('Are you sure you want to cancel this appointment?')) {
    return;
  }

  try {
    showNotification('Cancelling appointment...');
    
    const response = await fetch(`${APPOINTMENT_API}?action=cancel&id=${appointmentId}`, {
      method: 'POST'
    });
    
    logDebug('CANCEL', 'Cancel response status:', response.status);
    const result = await response.json();
    logDebug('CANCEL', 'Cancel result:', result);

    if (result.success) {
      showNotification('✓ Appointment cancelled');
      await loadAllData();
    } else {
      throw new Error(result.error || 'Failed to cancel');
    }
  } catch (error) {
    handleError('Cancel Appointment', error);
  }
}

// ==========================================
// EVENT HANDLERS
// ==========================================

function handleTabSwitch(e) {
  const tab = e.target.dataset.tab;
  logDebug('TAB', 'Tab switched to:', tab);
  
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  e.target.classList.add('active');
  activeTab = tab;
  renderAppointments();
}

function handleSearch(e) {
  const searchTerm = e.target.value.toLowerCase().trim();
  logDebug('SEARCH', 'Search term:', searchTerm);
  
  if (!searchTerm) {
    renderAppointments();
    return;
  }

  const filtered = appointments.filter(a => 
    a.name.toLowerCase().includes(searchTerm) ||
    a.type.toLowerCase().includes(searchTerm) ||
    a.date.includes(searchTerm) ||
    (a.notes && a.notes.toLowerCase().includes(searchTerm))
  );
  
  logDebug('SEARCH', `Found ${filtered.length} matching appointments`);
  renderAppointments(filtered);
}

// ==========================================
// UTILITY FUNCTIONS
// ==========================================

function determineStatus(date, dbStatus) {
  if (dbStatus === 'cancelled') return 'cancelled';
  
  const today = new Date().toISOString().split('T')[0];
  if (date === today) return 'today';
  if (date > today) return 'upcoming';
  return 'past';
}

function convertFrom24Hour(time24) {
  if (!time24) return '';
  
  const [hours, minutes] = time24.split(':');
  const hour = parseInt(hours);
  const ampm = hour >= 12 ? 'PM' : 'AM';
  const hour12 = hour % 12 || 12;
  
  return `${hour12}:${minutes} ${ampm}`;
}

function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { 
    month: 'short', 
    day: 'numeric', 
    year: 'numeric' 
  });
}

function showNotification(message, isError = false) {
  logDebug('NOTIFY', `${isError ? 'ERROR' : 'INFO'}: ${message}`);
  
  const status = document.getElementById('syncStatus');
  const messageEl = document.getElementById('syncMessage');

  if (!status || !messageEl) {
    console.warn('Notification elements not found, using alert');
    if (isError) alert(message);
    return;
  }

  messageEl.textContent = message;
  status.className = 'sync-status show' + (isError ? ' error' : '');

  setTimeout(() => {
    status.classList.remove('show');
  }, 3000);
}

// ==========================================
// DEBUG HELPERS
// ==========================================

// Export debug functions to window
window.getDebugLogs = function() {
  return window.debugLogs || [];
};

window.exportDebugLogs = function() {
  const logs = window.getDebugLogs();
  const dataStr = JSON.stringify(logs, null, 2);
  const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
  
  const exportFileDefaultName = `debug-logs-${new Date().toISOString()}.json`;
  
  const linkElement = document.createElement('a');
  linkElement.setAttribute('href', dataUri);
  linkElement.setAttribute('download', exportFileDefaultName);
  linkElement.click();
};

window.clearDebugLogs = function() {
  window.debugLogs = [];
  logDebug('DEBUG', 'Debug logs cleared');
};

// ==========================================
// EXPORT FOR INLINE ONCLICK HANDLERS
// ==========================================

window.syncAppointment = syncAppointment;
window.cancelAppointment = cancelAppointment;

logDebug('INIT', '✓ appointments.js loaded successfully');

// Log initial state
logDebug('CONFIG', 'API Configuration:', {
  APPOINTMENT_API,
  CALENDAR_API,
  DOCTOR_SCHEDULE_API
});

// Listen for schedule updates triggered from other dashboards (e.g., Medical profile tab)
window.addEventListener('scheduleUpdated', () => {
  logDebug('SCHEDULE', 'Schedule update received, refreshing doctor availability');
  loadDoctorSchedule()
    .then(() => loadAllData())
    .catch(error => handleError('Schedule Refresh', error, false));
});