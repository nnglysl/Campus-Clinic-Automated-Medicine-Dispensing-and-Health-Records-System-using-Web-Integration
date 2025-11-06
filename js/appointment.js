// ==========================================
// APPOINTMENTS MANAGEMENT WITH CALENDAR API
// ==========================================

// API Endpoints - FIXED PATHS
const APPOINTMENT_API = '../crud/appointment_handler.php';
const CALENDAR_API = '../api/calendar_api.php';



// Doctor availability (Monday-Wednesday)
const DOCTOR_AVAILABLE_DAYS = [1, 2, 3];

// State
let appointments = [];
let calendarEvents = [];
let currentDate = new Date();
let activeTab = 'today';
let isLoading = false;

// ==========================================
// INITIALIZATION
// ==========================================

document.addEventListener('DOMContentLoaded', () => {
  console.log('=== Initializing Appointment Management ===');
  console.log('Current date:', currentDate);
  console.log('API Endpoints:', { APPOINTMENT_API, CALENDAR_API });
  
  // Check if required elements exist
  const calendar = document.getElementById('calendar');
  const appointmentsContent = document.getElementById('appointmentsContent');
  
  if (!calendar) {
    console.error('ERROR: Calendar element not found!');
    return;
  }
  
  if (!appointmentsContent) {
    console.error('ERROR: Appointments content element not found!');
    return;
  }
  
  console.log('✓ Required DOM elements found');
  
  initializeEventListeners();
  loadAllData();
  
  // Auto-refresh every 30 seconds
  setInterval(loadAllData, 30000);
});

function initializeEventListeners() {
  console.log('Setting up event listeners...');
  
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
      console.log('Previous month:', currentDate);
      renderCalendar();
    });
  }
  
  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      currentDate.setMonth(currentDate.getMonth() + 1);
      console.log('Next month:', currentDate);
      renderCalendar();
    });
  }

  // Search
  const searchInput = document.getElementById('searchInput');
  if (searchInput) {
    searchInput.addEventListener('input', handleSearch);
  }
  
  console.log('✓ Event listeners attached');
}

// ==========================================
// DATA LOADING
// ==========================================

async function loadAllData() {
  if (isLoading) {
    console.log('Already loading data, skipping...');
    return;
  }
  
  isLoading = true;
  console.log('=== Loading All Data ===');
  
  try {
    // Show loading state
    showLoadingState();
    
    // Load appointments first (primary data)
    await loadAppointments();
    
    // Load calendar events (secondary data - optional)
    await loadCalendarEvents().catch(err => {
      console.warn('Calendar events loading failed (non-critical):', err);
    });
    
    // Merge and render
    mergeCalendarData();
    renderCalendar();
    renderAppointments();
    
    console.log('✓ All data loaded successfully');
  } catch (error) {
    console.error('❌ Error loading data:', error);
    showNotification('Failed to load appointment data', true);
    
    // Still try to render with whatever data we have
    renderCalendar();
    renderAppointments();
  } finally {
    isLoading = false;
  }
}

async function loadAppointments() {
  console.log('Loading appointments from:', APPOINTMENT_API);
  
  try {
    const response = await fetch(`${APPOINTMENT_API}?action=list`);
    console.log('Appointments response status:', response.status);
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }
    
    const result = await response.json();
    console.log('Appointments result:', result);

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
      
      console.log(`✓ Loaded ${appointments.length} appointments`);
    } else {
      throw new Error(result.error || 'Invalid response format');
    }
  } catch (error) {
    console.error('❌ Error loading appointments:', error);
    appointments = [];
    throw error;
  }
}

async function loadCalendarEvents() {
  console.log('Loading calendar events from:', CALENDAR_API);
  
  try {
    // Get events for current month ±1 month
    const startDate = new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1);
    const endDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + 2, 0);
    
    const timeMin = startDate.toISOString();
    const timeMax = endDate.toISOString();
    
    const url = `${CALENDAR_API}?action=list&timeMin=${encodeURIComponent(timeMin)}&timeMax=${encodeURIComponent(timeMax)}`;
    console.log('Calendar events URL:', url);
    
    const response = await fetch(url);
    console.log('Calendar events response status:', response.status);
    
    const result = await response.json();
    console.log('Calendar events result:', result);

    if (result.success && result.data?.items) {
      calendarEvents = result.data.items;
      console.log(`✓ Loaded ${calendarEvents.length} calendar events`);
    } else {
      calendarEvents = [];
      console.log('No calendar events loaded');
    }
  } catch (error) {
    console.error('❌ Error loading calendar events:', error);
    calendarEvents = [];
  }
}

function mergeCalendarData() {
  console.log('Merging calendar data...');
  
  // Match appointments with calendar events
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
  
  console.log(`✓ Merged data: ${syncedCount} appointments synced with calendar`);
}

// ==========================================
// CALENDAR RENDERING
// ==========================================

function renderCalendar() {
  console.log('=== Rendering Calendar ===');
  
  const calendar = document.getElementById('calendar');
  if (!calendar) {
    console.error('❌ Calendar element not found!');
    return;
  }
  
  const year = currentDate.getFullYear();
  const month = currentDate.getMonth();
  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  console.log('Calendar month:', { year, month, firstDay, lastDay });

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
  console.log(`Adding ${startDay} empty cells before first day`);
  for (let i = 0; i < startDay; i++) {
    const empty = document.createElement('div');
    empty.className = 'day-cell other-month';
    calendar.appendChild(empty);
  }

  // Days of month
  const totalDays = lastDay.getDate();
  console.log(`Rendering ${totalDays} days`);
  
  for (let day = 1; day <= totalDays; day++) {
    const date = new Date(year, month, day);
    const dateString = date.toISOString().split('T')[0];
    const dayAppointments = appointments.filter(
      a => a.date === dateString && a.status !== 'cancelled'
    );
    const isAvailable = DOCTOR_AVAILABLE_DAYS.includes(date.getDay());
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
  
  console.log('✓ Calendar rendered successfully');
}

function handleDayClick(date, appointments) {
  console.log('Day clicked:', date, 'Appointments:', appointments.length);
  
  if (appointments.length === 0) {
    showNotification('No appointments for this date', false);
    return;
  }
  
  // Filter to show appointments for this date
  activeTab = 'today';
  
  // Update active tab UI
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.querySelector('.tab[data-tab="today"]')?.classList.add('active');
  
  renderAppointments(appointments);
}

// ==========================================
// APPOINTMENTS RENDERING
// ==========================================

function renderAppointments(customList = null) {
  console.log('=== Rendering Appointments ===');
  
  const content = document.getElementById('appointmentsContent');
  if (!content) {
    console.error('❌ Appointments content element not found!');
    return;
  }

  const filtered = customList || appointments.filter(a => a.status === activeTab);
  console.log(`Showing ${filtered.length} appointments for tab: ${activeTab}`);

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
  
  console.log('✓ Appointments rendered');
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
  console.log('Syncing appointment:', appointmentId);
  
  try {
    showNotification('Syncing to Google Calendar...');
    
    const response = await fetch(`${APPOINTMENT_API}?action=sync&id=${appointmentId}`, {
      method: 'POST'
    });
    
    const result = await response.json();
    console.log('Sync result:', result);

    if (result.success) {
      showNotification('✓ Successfully synced to Google Calendar');
      await loadAllData();
    } else {
      throw new Error(result.error || 'Sync failed');
    }
  } catch (error) {
    console.error('❌ Sync error:', error);
    showNotification('✗ Failed to sync: ' + error.message, true);
  }
}

async function cancelAppointment(appointmentId) {
  console.log('Cancelling appointment:', appointmentId);
  
  if (!confirm('Are you sure you want to cancel this appointment?')) {
    return;
  }

  try {
    showNotification('Cancelling appointment...');
    
    const response = await fetch(`${APPOINTMENT_API}?action=cancel&id=${appointmentId}`, {
      method: 'POST'
    });
    
    const result = await response.json();
    console.log('Cancel result:', result);

    if (result.success) {
      showNotification('✓ Appointment cancelled');
      await loadAllData();
    } else {
      throw new Error(result.error || 'Failed to cancel');
    }
  } catch (error) {
    console.error('❌ Cancel error:', error);
    showNotification('✗ Failed to cancel: ' + error.message, true);
  }
}

// ==========================================
// EVENT HANDLERS
// ==========================================

function handleTabSwitch(e) {
  console.log('Tab switched to:', e.target.dataset.tab);
  
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  e.target.classList.add('active');
  activeTab = e.target.dataset.tab;
  renderAppointments();
}

function handleSearch(e) {
  const searchTerm = e.target.value.toLowerCase().trim();
  console.log('Search:', searchTerm);
  
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
  console.log(`Notification [${isError ? 'ERROR' : 'INFO'}]:`, message);
  
  const status = document.getElementById('syncStatus');
  const messageEl = document.getElementById('syncMessage');

  if (!status || !messageEl) return;

  messageEl.textContent = message;
  status.className = 'sync-status show' + (isError ? ' error' : '');

  setTimeout(() => {
    status.classList.remove('show');
  }, 3000);
}

// ==========================================
// EXPORT FOR INLINE ONCLICK HANDLERS
// ==========================================

window.syncAppointment = syncAppointment;
window.cancelAppointment = cancelAppointment;

console.log('✓ appointments.js loaded successfully');