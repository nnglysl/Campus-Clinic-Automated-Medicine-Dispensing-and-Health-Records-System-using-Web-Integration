/**
 * Notification System for Medical Pages
 */

(function() {
    'use strict';
    
    const CONFIG = {
        refreshInterval: 30000, // 30 seconds - frequent for real-time appointment alerts
    };
    
    function getApiUrl() {
        return 'api/notifications.php';
    }
    
    let notificationInterval = null;
    let isInitialized = false;
    
    function init() {
        if (isInitialized) return;
        
        const notificationIcon = document.getElementById('notificationIcon');
        const notificationDropdown = document.getElementById('notificationDropdown');
        
        if (!notificationIcon || !notificationDropdown) {
            console.warn('Notification elements not found.');
            return;
        }
        
        loadNotifications();
        setupEventListeners();
        setupAutoRefresh();
        setupCrossTabSync();
        
        isInitialized = true;
    }
    
    function setupEventListeners() {
        const notificationIcon = document.getElementById('notificationIcon');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const markAllRead = document.getElementById('markAllRead');
        
        if (notificationIcon) {
            notificationIcon.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationDropdown.classList.toggle('show');
            });
        }
        
        document.addEventListener('click', function(e) {
            if (notificationIcon && !notificationIcon.contains(e.target) && 
                notificationDropdown && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.remove('show');
            }
        });
        
        $(document).on('click', '.notification-item, .notification-message, .notification-time', function(e) {
            e.stopPropagation();
            const $item = $(this).closest('.notification-item');
            const notifId = $item.data('id');
            const notifType = $item.data('type');
            const notifData = $item.data('notif-data');
            
            if (notifId) {
                // Immediately update visual state
                if ($item.hasClass('unread')) {
                    $item.removeClass('unread');
                    updateBadgeCountInstantly(-1);
                    
                    // Broadcast to other tabs
                    broadcastNotificationRead(notifId);
                }
                
                // Mark as read in backend (non-blocking)
                markNotificationRead(notifId, false);
                
                // Small delay to ensure backend update starts before navigation
                setTimeout(function() {
                    handleNotificationClick(notifType, notifData);
                }, 100);
            }
        });
        
        if (markAllRead) {
            markAllRead.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Immediately update all visible notifications
                const unreadItems = document.querySelectorAll('.notification-item.unread');
                const unreadCount = unreadItems.length;
                const unreadIds = [];
                
                unreadItems.forEach(function(item) {
                    item.classList.remove('unread');
                    const id = $(item).data('id');
                    if (id) unreadIds.push(id);
                });
                
                // Immediately update badge
                if (unreadCount > 0) {
                    updateBadgeCountInstantly(-unreadCount);
                    
                    // Broadcast to other tabs
                    broadcastMarkAllRead(unreadIds);
                }
                
                // Mark all as read in backend (non-blocking)
                markAllNotificationsRead(false);
            });
        }
    }
    
    function loadNotifications() {
        $.ajax({
            url: getApiUrl(),
            method: 'POST',
            data: { action: 'getNotifications' },
            dataType: 'json'
        }).done(function(response) {
            if (response.success) {
                updateNotificationBadge(response.unread_count);
                renderNotifications(response.notifications);
            }
        }).fail(function(xhr, status, error) {
            console.error('Failed to load notifications:', error);
        });
    }
    
    function updateNotificationBadge(count) {
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
    }
    
    function updateBadgeCountInstantly(delta) {
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            const currentCount = parseInt(badge.textContent) || 0;
            const newCount = Math.max(0, currentCount + delta);
            
            if (newCount > 0) {
                badge.textContent = newCount;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
    }
    
    function renderNotifications(notifications) {
        const list = document.getElementById('notificationList');
        if (!list) return;
        
        if (notifications.length === 0) {
            list.innerHTML = `
                <div class="notification-empty">
                    <i class="bi bi-bell-slash" style="font-size: 40px; color: #ddd;"></i>
                    <p>No notifications</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        notifications.forEach(function(notif) {
            const unreadClass = notif.status === 'unread' ? 'unread' : '';
            const typeLabel = 'NEW APPOINTMENT';
            const timeAgo = formatTimeAgo(notif.created_at);
            
            const notifData = notif.data ? JSON.parse(notif.data) : {};
            html += `
                <div class="notification-item ${unreadClass}" data-id="${notif.id}" data-type="${notif.type}" data-notif-data='${JSON.stringify(notifData)}' style="cursor: pointer;">
                    <span class="notification-type appointment_booked">${typeLabel}</span>
                    <div class="notification-message" style="cursor: pointer;">${escapeHtml(notif.message)}</div>
                    <div class="notification-time" style="cursor: pointer;">${timeAgo}</div>
                </div>
            `;
        });
        
        list.innerHTML = html;
    }
    
    function markNotificationRead(id, reload = true) {
        $.ajax({
            url: getApiUrl(),
            method: 'POST',
            data: { 
                action: 'markNotificationRead',
                id: id
            },
            dataType: 'json'
        }).done(function(response) {
            if (response.success && reload) {
                loadNotifications();
            }
        }).fail(function(xhr, status, error) {
            console.error('Failed to mark notification as read:', error);
            // Reload on error to sync state
            if (reload) {
                loadNotifications();
            }
        });
    }
    
    function markAllNotificationsRead(reload = true) {
        $.ajax({
            url: getApiUrl(),
            method: 'POST',
            data: { action: 'markAllNotificationsRead' },
            dataType: 'json'
        }).done(function(response) {
            if (response.success && reload) {
                loadNotifications();
            }
        }).fail(function(xhr, status, error) {
            console.error('Failed to mark all notifications as read:', error);
            // Reload on error to sync state
            if (reload) {
                loadNotifications();
            }
        });
    }
    
    function formatTimeAgo(timestamp) {
        const now = new Date();
        const time = new Date(timestamp);
        const diff = Math.floor((now - time) / 1000);
        
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
        if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
        if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
        return time.toLocaleDateString();
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function setupAutoRefresh() {
        if (notificationInterval) {
            clearInterval(notificationInterval);
        }
        
        notificationInterval = setInterval(function() {
            loadNotifications();
        }, CONFIG.refreshInterval);
    }
    
    function setupCrossTabSync() {
        // Listen for storage events from other tabs
        window.addEventListener('storage', function(e) {
            if (e.key === 'notification_read' && e.newValue) {
                try {
                    const data = JSON.parse(e.newValue);
                    if (data.timestamp && Date.now() - data.timestamp < 5000) {
                        // Update UI immediately
                        const $item = $('.notification-item[data-id="' + data.id + '"]');
                        if ($item.length && $item.hasClass('unread')) {
                            $item.removeClass('unread');
                            updateBadgeCountInstantly(-1);
                        }
                    }
                } catch (err) {
                    console.error('Error parsing notification sync data:', err);
                }
            } else if (e.key === 'notification_mark_all_read' && e.newValue) {
                try {
                    const data = JSON.parse(e.newValue);
                    if (data.timestamp && Date.now() - data.timestamp < 5000) {
                        // Update all unread notifications
                        const unreadItems = document.querySelectorAll('.notification-item.unread');
                        const unreadCount = unreadItems.length;
                        
                        unreadItems.forEach(function(item) {
                            item.classList.remove('unread');
                        });
                        
                        if (unreadCount > 0) {
                            updateBadgeCountInstantly(-unreadCount);
                        }
                    }
                } catch (err) {
                    console.error('Error parsing mark all read sync data:', err);
                }
            }
        });
        
        // Also listen for custom events (for same-tab communication)
        window.addEventListener('notificationUpdated', function() {
            loadNotifications();
        });
    }
    
    function broadcastNotificationRead(id) {
        try {
            const data = {
                id: id,
                timestamp: Date.now()
            };
            localStorage.setItem('notification_read', JSON.stringify(data));
            // Remove after a short delay to allow other tabs to process
            setTimeout(function() {
                localStorage.removeItem('notification_read');
            }, 100);
        } catch (err) {
            console.error('Error broadcasting notification read:', err);
        }
    }
    
    function broadcastMarkAllRead(ids) {
        try {
            const data = {
                ids: ids,
                timestamp: Date.now()
            };
            localStorage.setItem('notification_mark_all_read', JSON.stringify(data));
            // Remove after a short delay to allow other tabs to process
            setTimeout(function() {
                localStorage.removeItem('notification_mark_all_read');
            }, 100);
        } catch (err) {
            console.error('Error broadcasting mark all read:', err);
        }
    }
    
    /**
     * Handle notification click - navigate to relevant page
     */
    function handleNotificationClick(type, data) {
        // For appointment bookings, navigate to appointments page
        if (type === 'appointment_booked' && data.appointment_type === 'medical') {
            window.location.href = 'medical_appointments.php';
        }
    }
    
    window.MedicalNotificationSystem = {
        init: init,
        load: loadNotifications,
        refresh: function() {
            loadNotifications();
        }
    };
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

