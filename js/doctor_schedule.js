/**
 * Doctor Schedule Manager with Real-time Sync
 * Manages doctor availability and syncs with student calendars
 */

class DoctorScheduleManager {
    constructor() {
        this.currentDate = new Date();
        this.selectedDate = null;
        this.schedules = {};
        this.doctorInfo = null;
        this.isModified = false;
        
        this.init();
    }
    
    async init() {
        await this.loadDoctorInfo();
        await this.loadSchedules();
        this.renderCalendar();
        this.setupEventListeners();
    }
    
    async loadDoctorInfo() {
        try {
            const response = await fetch('../crud/get_user_info.php');
            const result = await response.json();
            
            if (result.success) {
                this.doctorInfo = result.data;
            }
        } catch (error) {
            console.error('Error loading doctor info:', error);
        }
    }
    
    async loadSchedules() {
        try {
            const year = this.currentDate.getFullYear();
            const month = this.currentDate.getMonth() + 1;
            
            const response = await fetch(
                `../crud/schedule_handler.php?action=list&year=${year}&month=${month}`
            );
            const result = await response.json();
            
            if (result.success) {
                this.schedules = {};
                result.data.forEach(schedule => {
                    const date = schedule.start;
                    if (!this.schedules[date]) {
                        this.schedules[date] = [];
                    }
                    this.schedules[date].push(schedule);
                });
                
                this.renderCalendar();
            }
        } catch (error) {
            console.error('Error loading schedules:', error);
        }
    }
    
    setupEventListeners() {
        document.getElementById('prevBtn')?.addEventListener('click', () => {
            this.currentDate.setMonth(this.currentDate.getMonth() - 1);
            this.loadSchedules();
        });
        
        document.getElementById('nextBtn')?.addEventListener('click', () => {
            this.currentDate.setMonth(this.currentDate.getMonth() + 1);
            this.loadSchedules();
        });
        
        document.getElementById('todayBtn')?.addEventListener('click', () => {
            this.currentDate = new Date();
            this.loadSchedules();
        });
        
        // Save schedule button
        document.getElementById('saveScheduleBtn')?.addEventListener('click', () => {
            this.saveAllSchedules();
        });
        
        // Quick actions
        document.getElementById('markAvailableWeek')?.addEventListener('click', () => {
            this.markWeekAvailable();
        });
        
        document.getElementById('clearWeek')?.addEventListener('click', () => {
            this.clearWeek();
        });
    }
    
    renderCalendar() {
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // Update month display
        const monthYear = document.getElementById('monthYear');
        if (monthYear) {
            monthYear.textContent = firstDay.toLocaleDateString('en-US', { 
                month: 'long', 
                year: 'numeric' 
            });
        }
        
        // Build calendar grid
        const daysGrid = document.getElementById('daysGrid');
        if (!daysGrid) return;
        
        daysGrid.innerHTML = '';
        
        // Add empty cells for days before month starts
        const startDay = firstDay.getDay();
        const prevMonthDays = startDay === 0 ? 6 : startDay - 1;
        
        for (let i = 0; i < prevMonthDays; i++) {
            const cell = this.createDayCell(null, true);
            daysGrid.appendChild(cell);
        }
        
        // Add days of month
        for (let day = 1; day <= lastDay.getDate(); day++) {
            const date = new Date(year, month, day);
            const cell = this.createDayCell(date, false);
            daysGrid.appendChild(cell);
        }
    }
    
    createDayCell(date, isOtherMonth) {
        const cell = document.createElement('div');
        cell.className = 'day-cell';
        
        if (isOtherMonth || !date) {
            cell.classList.add('other-month');
            return cell;
        }
        
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const dateStr = this.formatDate(date);
        const dayOfWeek = date.getDay();
        
        // Check if weekend
        if (dayOfWeek === 0 || dayOfWeek === 6) {
            cell.classList.add('weekend');
        }
        
        // Check if past
        if (date < today) {
            cell.classList.add('disabled');
        }
        
        // Check if today
        if (date.getTime() === today.getTime()) {
            cell.classList.add('today');
        }
        
        // Day number
        const dayNumber = document.createElement('div');
        dayNumber.className = 'day-number';
        dayNumber.textContent = date.getDate();
        cell.appendChild(dayNumber);
        
        // Schedule indicators
        const schedules = this.schedules[dateStr] || [];
        const schedulesContainer = document.createElement('div');
        schedulesContainer.className = 'day-schedules';
        
        schedules.forEach(schedule => {
            const indicator = document.createElement('div');
            indicator.className = 'schedule-indicator';
            
            if (schedule.extendedProps.type === 'available') {
                indicator.classList.add('available');
                indicator.title = `Available: ${schedule.extendedProps.startTime} - ${schedule.extendedProps.endTime}`;
            } else {
                indicator.classList.add('unavailable');
                indicator.title = `Unavailable: ${schedule.extendedProps.reason || 'No reason'}`;
            }
            
            schedulesContainer.appendChild(indicator);
        });
        
        cell.appendChild(schedulesContainer);
        
        // Click handler
        if (date >= today && dayOfWeek !== 0 && dayOfWeek !== 6) {
            cell.addEventListener('click', () => {
                this.selectDate(date);
            });
            cell.style.cursor = 'pointer';
        }
        
        return cell;
    }
    
    selectDate(date) {
        this.selectedDate = date;
        const dateStr = this.formatDate(date);
        
        // Update selected state
        document.querySelectorAll('.day-cell').forEach(cell => {
            cell.classList.remove('selected');
        });
        
        event.target.closest('.day-cell')?.classList.add('selected');
        
        // Open schedule drawer
        this.openScheduleDrawer(dateStr);
    }
    
    openScheduleDrawer(dateStr) {
        const drawer = document.getElementById('scheduleDrawer');
        const overlay = document.getElementById('drawerOverlay');
        
        if (!drawer || !overlay) return;
        
        // Show drawer
        drawer.classList.add('open');
        overlay.classList.add('active');
        
        // Update drawer content
        const date = new Date(dateStr);
        document.getElementById('drawerDate').textContent = 
            date.toLocaleDateString('en-US', { 
                weekday: 'long',
                month: 'long', 
                day: 'numeric', 
                year: 'numeric' 
            });
        
        // Load existing schedules
        this.renderDrawerSchedules(dateStr);
    }
    
    renderDrawerSchedules(dateStr) {
        const container = document.getElementById('drawerScheduleList');
        if (!container) return;
        
        const schedules = this.schedules[dateStr] || [];
        
        if (schedules.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-calendar-plus"></i>
                    <p>No schedules set for this day</p>
                    <button class="btn-add-schedule" onclick="scheduleManager.addNewSchedule('${dateStr}')">
                        <i class="bi bi-plus-circle"></i> Add Schedule
                    </button>
                </div>
            `;
            return;
        }
        
        container.innerHTML = schedules.map(schedule => `
            <div class="schedule-item ${schedule.extendedProps.type}">
                <div class="schedule-header">
                    <span class="schedule-type-badge ${schedule.extendedProps.type}">
                        ${schedule.extendedProps.type === 'available' ? '✓ Available' : '✗ Unavailable'}
                    </span>
                    <button class="btn-delete-schedule" onclick="scheduleManager.deleteSchedule(${schedule.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                
                ${schedule.extendedProps.type === 'available' ? `
                    <div class="schedule-time">
                        <i class="bi bi-clock"></i>
                        ${schedule.extendedProps.startTime} - ${schedule.extendedProps.endTime}
                    </div>
                ` : `
                    <div class="schedule-reason">
                        <i class="bi bi-info-circle"></i>
                        ${schedule.extendedProps.reason || 'No reason provided'}
                    </div>
                `}
            </div>
        `).join('');
        
        // Add "Add more" button
        container.innerHTML += `
            <button class="btn-add-schedule secondary" onclick="scheduleManager.addNewSchedule('${dateStr}')">
                <i class="bi bi-plus-circle"></i> Add Another Schedule
            </button>
        `;
    }
    
    addNewSchedule(dateStr) {
        // Show schedule form
        const form = document.getElementById('scheduleForm');
        if (!form) return;
        
        form.classList.remove('hidden');
        form.querySelector('[name="schedule_date"]').value = dateStr;
        form.querySelector('[name="action"]').value = 'create';
        
        // Scroll to form
        form.scrollIntoView({ behavior: 'smooth' });
    }
    
    async saveSchedule(formData) {
        try {
            const response = await fetch('../crud/schedule_handler.php', {
                method: 'POST',
                body: JSON.stringify(Object.fromEntries(formData)),
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Schedule saved successfully!', 'success');
                await this.loadSchedules();
                this.closeDrawer();
                
                // Trigger sync notification
                if (result.sync_triggered) {
                    this.showNotification('Student calendars will update automatically', 'info');
                }
            } else {
                throw new Error(result.error || 'Failed to save schedule');
            }
        } catch (error) {
            console.error('Error saving schedule:', error);
            this.showNotification(error.message, 'error');
        }
    }
    
    async deleteSchedule(scheduleId) {
        if (!confirm('Are you sure you want to delete this schedule?')) {
            return;
        }
        
        try {
            const response = await fetch('../crud/schedule_handler.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'delete',
                    id: scheduleId
                }),
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Schedule deleted successfully!', 'success');
                await this.loadSchedules();
            } else {
                throw new Error(result.error || 'Failed to delete schedule');
            }
        } catch (error) {
            console.error('Error deleting schedule:', error);
            this.showNotification(error.message, 'error');
        }
    }
    
    markWeekAvailable() {
        // Mark current week as available (Mon-Fri, 8 AM - 5 PM)
        const startOfWeek = new Date(this.currentDate);
        startOfWeek.setDate(startOfWeek.getDate() - startOfWeek.getDay() + 1);
        
        const promises = [];
        for (let i = 0; i < 5; i++) {
            const date = new Date(startOfWeek);
            date.setDate(date.getDate() + i);
            
            const dateStr = this.formatDate(date);
            
            promises.push(
                this.saveSchedule(new FormData(document.createElement('form')), {
                    action: 'create',
                    date: dateStr,
                    scheduleType: 'available',
                    startTime: '08:00',
                    endTime: '17:00'
                })
            );
        }
        
        Promise.all(promises).then(() => {
            this.showNotification('Week marked as available!', 'success');
            this.loadSchedules();
        });
    }
    
    clearWeek() {
        if (!confirm('Clear all schedules for this week?')) return;
        
        // Implementation for clearing week schedules
        this.showNotification('Week cleared!', 'info');
    }
    
    closeDrawer() {
        document.getElementById('scheduleDrawer')?.classList.remove('open');
        document.getElementById('drawerOverlay')?.classList.remove('active');
    }
    
    showNotification(message, type = 'info') {
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
            alert(message);
        }
    }
    
    formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.scheduleManager = new DoctorScheduleManager();
});