// Inline JavaScript
    const DOCTOR_AVAILABLE_DAYS = [1, 2, 3];
    let currentDate = new Date();
    let selectedDate = new Date();
    let appointmentCounts = window.appointmentCounts || {};

    document.addEventListener('DOMContentLoaded', () => {
      console.log('Initializing calendar');
      initializeEventListeners();
      renderMiniCalendar();
      renderWeekView();
    });

    function initializeEventListeners() {
      document.getElementById('prevMonth')?.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderMiniCalendar();
      });

      document.getElementById('nextMonth')?.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderMiniCalendar();
      });

      document.getElementById('appointmentForm')?.addEventListener('submit', handleFormSubmit);
    }

    function renderMiniCalendar() {
      const calendar = document.getElementById('calendar');
      if (!calendar) return;

      const year = currentDate.getFullYear();
      const month = currentDate.getMonth();
      const firstDay = new Date(year, month, 1);
      const lastDay = new Date(year, month + 1, 0);
      const daysInMonth = lastDay.getDate();
      const startDay = firstDay.getDay();
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      document.getElementById('monthYear').textContent = 
        firstDay.toLocaleString('default', { month: 'long', year: 'numeric' });

      calendar.innerHTML = '';

      ['S', 'M', 'T', 'W', 'T', 'F', 'S'].forEach(day => {
        const el = document.createElement('div');
        el.className = 'weekday';
        el.textContent = day;
        calendar.appendChild(el);
      });

      const prevMonthLastDay = new Date(year, month, 0).getDate();
      for (let i = startDay - 1; i >= 0; i--) {
        const el = document.createElement('div');
        el.className = 'day-cell other-month';
        el.textContent = prevMonthLastDay - i;
        calendar.appendChild(el);
      }

      for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month, day);
        date.setHours(0, 0, 0, 0);
        const dateISO = date.toISOString().split('T')[0];
        const dayOfWeek = date.getDay();

        const el = document.createElement('div');
        el.className = 'day-cell';
        el.textContent = day;

        if (date.getTime() === today.getTime()) el.classList.add('today');
        if (dateISO === selectedDate.toISOString().split('T')[0]) el.classList.add('selected');

        const isPast = date < today;
        const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;
        const isAvailable = DOCTOR_AVAILABLE_DAYS.includes(dayOfWeek);

        if (isPast) {
          el.classList.add('other-month');
        } else if (isWeekend || !isAvailable) {
          el.classList.add('unavailable');
        } else {
          el.addEventListener('click', () => {
            selectedDate = date;
            renderMiniCalendar();
            renderWeekView();
          });
        }

        calendar.appendChild(el);
      }

      const totalCells = calendar.children.length - 7;
      const remaining = (Math.ceil(totalCells / 7) * 7) - totalCells;
      for (let i = 1; i <= remaining; i++) {
        const el = document.createElement('div');
        el.className = 'day-cell other-month';
        el.textContent = i;
        calendar.appendChild(el);
      }
    }

    function renderWeekView() {
      const container = document.getElementById('appointmentsList');
      if (!container) return;

      const weekDays = getWeekDays(selectedDate);
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      container.innerHTML = '<div class="time-slots-container"></div>';
      const slotsContainer = container.querySelector('.time-slots-container');

      weekDays.forEach(date => {
        const column = document.createElement('div');
        column.className = 'day-column';

        const dayOfWeek = date.getDay();
        const isPast = date < today;
        const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;
        const isAvailable = DOCTOR_AVAILABLE_DAYS.includes(dayOfWeek);
        const isUnavailable = isPast || isWeekend || !isAvailable;

        if (isUnavailable) column.classList.add('unavailable');

        column.innerHTML = `
          <div class="day-column-header">
            <div class="day-name">${date.toLocaleDateString('en-US', { weekday: 'short' }).toUpperCase()}</div>
            <div class="day-date">${date.getDate()}</div>
          </div>
        `;

        if (isUnavailable) {
          column.innerHTML += '<div class="no-slots-message">—</div>';
        } else {
          const dateISO = date.toISOString().split('T')[0];
          for (let hour = 8; hour <= 17; hour++) {
            if (hour === 12) continue;
            const time = `${hour.toString().padStart(2, '0')}:00:00`;
            const display = formatTime(time);
            
            const btn = document.createElement('button');
            btn.className = 'time-slot-btn';
            btn.textContent = display;
            btn.onclick = () => openBookingModal(dateISO, date.getDate(), time, display);
            column.appendChild(btn);
          }
        }

        slotsContainer.appendChild(column);
      });
    }

    function getWeekDays(date) {
      const days = [];
      const current = new Date(date);
      const dayOfWeek = current.getDay();
      current.setDate(current.getDate() - dayOfWeek);
      
      for (let i = 0; i < 7; i++) {
        days.push(new Date(current));
        current.setDate(current.getDate() + 1);
      }
      return days;
    }

    function formatTime(time24) {
      const [hours] = time24.split(':');
      const hour = parseInt(hours);
      const ampm = hour >= 12 ? 'pm' : 'am';
      const hour12 = hour % 12 || 12;
      return `${hour12}:00${ampm}`;
    }

    function openBookingModal(dateISO, day, time24, displayTime) {
      const modal = new bootstrap.Modal(document.getElementById('bookingModal'));
      const form = document.getElementById('appointmentForm');
      if (form) form.reset();

      document.getElementById('appointmentDate').value = dateISO;
      document.getElementById('appointmentTime').value = time24;

      const dateObj = new Date(dateISO + 'T00:00:00');
      const formatted = dateObj.toLocaleDateString('en-US', { 
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
      });
      document.getElementById('selectedDateDisplay').textContent = `${formatted} at ${displayTime}`;

      modal.show();
    }

    async function handleFormSubmit(e) {
      e.preventDefault();
      const formData = new FormData(e.target);
      formData.append('book_appointment', '1');

      if (!formData.get('appointment_type')) {
        Swal.fire({ icon: 'warning', title: 'Select Type', text: 'Please select appointment type' });
        return;
      }

      try {
        const response = await fetch('appointment.php', { method: 'POST', body: formData });
        const result = await response.json();

        bootstrap.Modal.getInstance(document.getElementById('bookingModal'))?.hide();

        if (result.success) {
          await Swal.fire({
            icon: 'success',
            title: 'Appointment Confirmed!',
            html: `<p>Your ${result.appointment.type} appointment has been booked.</p>`,
            confirmButtonColor: '#1a73e8'
          });
          location.reload();
        } else {
          Swal.fire({ icon: 'error', title: 'Booking Failed', text: result.message });
        }
      } catch (error) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'An error occurred' });
      }
    }