/**
 * Real-time Calendar Synchronization System
 * Handles automatic updates across Admin, Employee, and Student dashboards
 */

class AppointmentSync {
    constructor() {
        this.pollInterval = 3000; // Poll every 3 seconds
        this.lastUpdate = 0;
        this.isPolling = false;
        this.currentMonth = new Date().getMonth() + 1;
        this.currentYear = new Date().getFullYear();
        this.selectedDate = null;
        this.pollTimer = null;
        
        this.init();
    }
    
    init() {
        // Load initial calendar data
        this.loadCalendar();
        
        // Start polling for updates
        this.startPolling();
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopPolling();
            } else {
                this.startPolling();
                this.loadCalendar(); // Refresh when coming back
            }
        });
    }
    
    setupEventListeners() {
        // Month navigation
        document.getElementById('prevMonth')?.addEventListener('click', () => {
            this.currentMonth--;
            if (this.currentMonth < 1) {
                this.currentMonth = 12;
                this.currentYear--;
            }
            this.loadCalendar();
        });
        
        document.getElementById('nextMonth')?.addEventListener('click', () => {
            this.currentMonth++;
            if (this.currentMonth > 12) {
                this.currentMonth = 1;
                this.currentYear++;
            }
            this.loadCalendar();
        });
        
        // Tab switching for appointments
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                e.target.classList.add('active');
                this.loadAppointments(e.target.dataset.tab);
            });
        });
        
        // Search functionality
        document.getElementById('searchInput')?.addEventListener('input', (e) => {
            this.filterAppointments(e.target.value);
        });
    }
    
    startPolling() {
        if (this.isPolling) return;
        this.isPolling = true;
        this.poll();
    }
    
    stopPolling() {
        this.isPolling = false;
        if (this.pollTimer) {
            clearTimeout(this.pollTimer);
            this.pollTimer = null;
        }
    }
    
    async poll() {
        if (!this.isPolling) return;
        
        try {
            const response = await fetch(`appointment_sync.php?action=poll&last_update=${this.lastUpdate}`);
            const data = await response.json();
            
            if (data.success && data.has_updates) {
                console.log('Updates detected, refreshing calendar...');
                await this.loadCalendar(false); // Silent refresh
                this.showNotification('Calendar updated', 'info');
            }
            
            this.lastUpdate = data.timestamp || this.lastUpdate;
            
        } catch (error) {
            console.error('Poll error:', error);
        }
        
        // Schedule next poll
        if (this.isPolling) {
            this.pollTimer = setTimeout(() => this.poll(), this.pollInterval);
        }
    }
    
    async loadCalendar(showLoading = true) {
        try {
            if (showLoading) {
                this.showLoading();
            }
            
            const response = await fetch(
                `appointment_sync.php?action=get_calendar&month=${this.currentMonth}&year=${this.currentYear}`
            );
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Failed to load calendar');
            }
            
            this.lastUpdate = data.timestamp;
            this.renderCalendar(data);
            
            // Load today's appointments by default
            const activeTab = document.querySelector('.tab.active')?.dataset.tab || 'today';
            this.loadAppointments(activeTab);
            
        } catch (error) {
            console.error('Load calendar error:', error);
            this.showNotification('Failed to load calendar', 'error');
        } finally {
            this.hideLoading();
        }
    }
    
    renderCalendar(data) {
        const calendar = document.getElementById('calendar');
        if (!calendar) return;
        
        // Update month/year display
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                           'July', 'August', 'September', 'October', 'November', 'December'];
        document.getElementById('monthYear').textContent = 
            `${monthNames[this.currentMonth - 1]} ${this.currentYear}`;
        
        // Build calendar grid
        const firstDay = new Date(this.currentYear, this.currentMonth - 1, 1);
        const lastDay = new Date(this.currentYear, this.currentMonth, 0);
        const daysInMonth = lastDay.getDate();
        const startingDayOfWeek = firstDay.getDay();
        
        // Create schedules and appointments maps
        const schedulesMap = {};
        const appointmentsMap = {};
        const unavailableMap = {};
        
        data.schedules.forEach(s => {
            schedulesMap[s.schedule_date] = s;
        });
        
        data.appointments.forEach(apt => {
            if (!appointmentsMap[apt.appointment_date]) {
                appointmentsMap[apt.appointment_date] = [];
            }
            appointmentsMap[apt.appointment_date].push(apt);
        });
        
        data.unavailable.forEach(u => {
            unavailableMap[u.schedule_date] = u.reason;
        });
        
        // Build calendar HTML
        let html = `
            <div class="calendar-header-row">
                <div class="calendar-day-header">Sun</div>
                <div class="calendar-day-header">Mon</div>
                <div class="calendar-day-header">Tue</div>
                <div class="calendar-day-header">Wed</div>
                <div class="calendar-day-header">Thu</div>
                <div class="calendar-day-header">Fri</div>
                <div class="calendar-day-header">Sat</div>
            </div>
            <div class="calendar-grid">
        `;
        
        // Add empty cells for days before month starts
        for (let i = 0; i < startingDayOfWeek; i++) {
            html += '<div class="calendar-day other-month"></div>';
        }
        
        // Add days of month
        const today = new Date();
        const todayStr = today.toISOString().split('T')[0];
        
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${this.currentYear}-${String(this.currentMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const isToday = dateStr === todayStr;
            const isPast = new Date(dateStr) < new Date(todayStr);
            
            let dayClass = 'calendar-day';
            let statusClass = '';
            let statusText = '';
            let indicator = '';
            
            if (isToday) dayClass += ' today';
            if (isPast) dayClass += ' past';
            
            // Determine day status
            if (unavailableMap[dateStr]) {
                statusClass = 'unavailable';
                statusText = 'Unavailable';
                indicator = '<div class="day-indicator unavailable"></div>';
            } else if (appointmentsMap[dateStr]) {
                const count = appointmentsMap[dateStr].length;
                statusClass = 'has-bookings';
                statusText = `${count} booking${count > 1 ? 's' : ''}`;
                indicator = `<div class="day-indicator booked">${count}</div>`;
            } else if (schedulesMap[dateStr]) {
                statusClass = 'available';
                statusText = 'Available';
                indicator = '<div class="day-indicator available"></div>';
            }
            
            html += `
                <div class="${dayClass} ${statusClass}" data-date="${dateStr}">
                    <div class="day-number">${day}</div>
                    ${indicator}
                    ${statusText ? `<div class="day-status">${statusText}</div>` : ''}
                </div>
            `;
        }
        
        html += '</div>';
        calendar.innerHTML = html;
        
        // Add click handlers for days
        calendar.querySelectorAll('.calendar-day').forEach(day => {
            day.addEventListener('click', (e) => {
                const date = e.currentTarget.dataset.date;
                if (date) {
                    this.selectDate(date);
                }
            });
        });
    }
    
    async selectDate(date) {
        this.selectedDate = date;
        
        // Highlight selected date
        document.querySelectorAll('.calendar-day').forEach(d => d.classList.remove('selected'));
        document.querySelector(`[data-date="${date}"]`)?.classList.add('selected');
        
        // Load appointments for this date
        this.loadAppointments('date', date);
        
        // For students, also load available slots
        if (this.isStudentView()) {
            await this.loadAvailableSlots(date);
        }
    }
    
    async loadAvailableSlots(date) {
        try {
            const response = await fetch(`appointment_sync.php?action=get_slots&date=${date}`);
            const data = await response.json();
            
            if (data.success) {
                this.displayAvailableSlots(data.slots, date);
            }
        } catch (error) {
            console.error('Load slots error:', error);
        }
    }
    
    displayAvailableSlots(slots, date) {
        // This method should be customized based on your UI
        const modal = document.getElementById('bookingModal');
        if (!modal) return;
        
        const slotsContainer = modal.querySelector('#availableSlots');
        if (!slotsContainer) return;
        
        if (slots.length === 0) {
            slotsContainer.innerHTML = '<p class="text-muted">No available slots for this date</p>';
            return;
        }
        
        let html = '<div class="time-slots">';
        slots.forEach(slot => {
            html += `
                <button class="time-slot-btn" data-time="${slot.time24}" data-time-display="${slot.time}">
                    ${slot.time}
                </button>
            `;
        });
        html += '</div>';
        
        slotsContainer.innerHTML = html;
        
        // Add click handlers
        slotsContainer.querySelectorAll('.time-slot-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.selectTimeSlot(date, e.target.dataset.time, e.target.dataset.timeDisplay);
            });
        });
    }
    
    selectTimeSlot(date, time, timeDisplay) {
        document.querySelectorAll('.time-slot-btn').forEach(b => b.classList.remove('selected'));
        event.target.classList.add('selected');
        
        // Store selected slot
        this.selectedSlot = { date, time, timeDisplay };
    }
    
    async bookAppointment(appointmentData) {
        try {
            this.showLoading();
            
            // Check availability one more time before booking
            const checkResponse = await fetch(
                `appointment_sync.php?action=check_availability&date=${appointmentData.date}&time=${appointmentData.time}`
            );
            const checkData = await checkResponse.json();
            
            if (!checkData.available) {
                this.showNotification('This slot is no longer available', 'error');
                // Refresh slots
                await this.loadAvailableSlots(appointmentData.date);
                return;
            }
            
            // Book the appointment
            const formData = new FormData();
            formData.append('action', 'book');
            formData.append('date', appointmentData.date);
            formData.append('time', appointmentData.time);
            formData.append('type', appointmentData.type);
            formData.append('notes', appointmentData.notes || '');
            
            const response = await fetch('appointment_sync.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Appointment booked successfully!', 'success');
                this.lastUpdate = data.timestamp;
                await this.loadCalendar(false);
                this.closeBookingModal();
            } else {
                if (data.error_type === 'slot_taken') {
                    this.showNotification(data.error, 'warning');
                    await this.loadAvailableSlots(appointmentData.date);
                } else {
                    throw new Error(data.error);
                }
            }
            
        } catch (error) {
            console.error('Book appointment error:', error);
            this.showNotification(error.message || 'Failed to book appointment', 'error');
        } finally {
            this.hideLoading();
        }
    }
    
    async cancelAppointment(appointmentId) {
        if (!confirm('Are you sure you want to cancel this appointment?')) {
            return;
        }
        
        try {
            this.showLoading();
            
            const formData = new FormData();
            formData.append('action', 'cancel');
            formData.append('id', appointmentId);
            
            const response = await fetch('../crud/appointment_sync.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Appointment cancelled successfully', 'success');
                this.lastUpdate = data.timestamp;
                await this.loadCalendar(false);
            } else {
                throw new Error(data.error);
            }
            
        } catch (error) {
            console.error('Cancel appointment error:', error);
            this.showNotification(error.message || 'Failed to cancel appointment', 'error');
        } finally {
            this.hideLoading();
        }
    }
    
    async loadAppointments(filter, date = null) {
        try {
            let url = `../crud/appointment_sync.php?action=get_appointments&filter=${filter}`;
            if (date) {
                url += `&date=${date}`;
            }
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                this.displayAppointments(data.appointments, filter);
            }
            
        } catch (error) {
            console.error('Load appointments error:', error);
        }
    }
    
    displayAppointments(appointments, filter) {
        const container = document.getElementById('appointmentsContent');
        if (!container) return;
        
        if (appointments.length === 0) {
            container.innerHTML = `
                <div class="no-appointments">
                    <i class="bi bi-calendar-x"></i>
                    <p>No appointments found</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        appointments.forEach(apt => {
            const date = new Date(apt.appointment_date + 'T' + apt.appointment_time);
            const timeStr = date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            
            const statusClass = apt.status === 'cancelled' ? 'text-danger' :
                               apt.status === 'completed' ? 'text-success' : 'text-primary';
            
            html += `
                <div class="appointment-card" data-id="${apt.id}">
                    <div class="appointment-header">
                        <div class="appointment-type ${apt.appointment_type}">
                            <i class="bi bi-${apt.appointment_type === 'medical' ? 'heart-pulse' : 'tooth'}"></i>
                            ${apt.appointment_type}
                        </div>
                        <span class="badge ${statusClass}">${apt.status}</span>
                    </div>
                    <div class="appointment-body">
                        ${this.isStudentView() ? '' : `<h5>${apt.fname} ${apt.lname}</h5>`}
                        <p><i class="bi bi-calendar"></i> ${apt.appointment_date}</p>
                        <p><i class="bi bi-clock"></i> ${timeStr}</p>
                        ${apt.notes ? `<p><i class="bi bi-note"></i> ${apt.notes}</p>` : ''}
                    </div>
                    <div class="appointment-actions">
                        ${apt.status === 'scheduled' ? `
                            <button class="btn btn-sm btn-danger cancel-apt" data-id="${apt.id}">
                                Cancel
                            </button>
                        ` : ''}
                        ${!this.isStudentView() && apt.status === 'scheduled' ? `
                            <button class="btn btn-sm btn-success confirm-apt" data-id="${apt.id}">
                                Confirm
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        
        // Add event listeners
        container.querySelectorAll('.cancel-apt').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.cancelAppointment(e.target.dataset.id);
            });
        });
        
        container.querySelectorAll('.confirm-apt').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.updateAppointmentStatus(e.target.dataset.id, 'confirmed');
            });
        });
    }
    
    async updateAppointmentStatus(appointmentId, status) {
        try {
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('id', appointmentId);
            formData.append('status', status);
            
            const response = await fetch('appointment_sync.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification(`Appointment ${status} successfully`, 'success');
                this.lastUpdate = data.timestamp;
                await this.loadCalendar(false);
            } else {
                throw new Error(data.error);
            }
            
        } catch (error) {
            console.error('Update status error:', error);
            this.showNotification(error.message || 'Failed to update appointment', 'error');
        }
    }
    
    filterAppointments(searchTerm) {
        const cards = document.querySelectorAll('.appointment-card');
        const term = searchTerm.toLowerCase();
        
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            card.style.display = text.includes(term) ? 'block' : 'none';
        });
    }
    
    isStudentView() {
        // Detect if current user is a student based on page or session
        return window.location.pathname.includes('/student/');
    }
    
    showNotification(message, type = 'info') {
        // Use existing notification system or create one
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: type === 'error' ? 'error' : type === 'warning' ? 'warning' : 'success',
                title: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        } else {
            const syncStatus = document.getElementById('syncStatus');
            if (syncStatus) {
                syncStatus.querySelector('#syncMessage').textContent = message;
                syncStatus.className = `sync-status ${type}`;
                syncStatus.style.display = 'block';
                setTimeout(() => {
                    syncStatus.style.display = 'none';
                }, 3000);
            } else {
                alert(message);
            }
        }
    }
    
    showLoading() {
        document.body.classList.add('loading');
    }
    
    hideLoading() {
        document.body.classList.remove('loading');
    }
    
    closeBookingModal() {
        const modal = document.getElementById('bookingModal');
        if (modal) {
            bootstrap.Modal.getInstance(modal)?.hide();
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.appointmentSync = new AppointmentSync();
});

// Expose booking function globally for form submission
window.bookAppointmentForm = function(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    const appointmentData = {
        date: formData.get('date'),
        time: formData.get('time'),
        type: formData.get('type'),
        notes: formData.get('notes')
    };
    
    window.appointmentSync.bookAppointment(appointmentData);
};

// Refresh realtime calendar when doctors update their schedules
window.addEventListener('scheduleUpdated', () => {
    if (window.appointmentSync) {
        window.appointmentSync.loadCalendar(false);
    }
});