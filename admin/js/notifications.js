(function() {
    'use strict';
    
    const CONFIG = {
        refreshInterval: 300000, // 5 minutes
        checkAlertsInterval: 300000 // 5 minutes
    };
    
    function getApiUrl() {
        return 'api/notifications.php';
    }
    
    let notificationInterval = null;
    let isInitialized = false;
    

    function init() {
        if (isInitialized) {
            return;
        }
        
        const notificationIcon = document.getElementById('notificationIcon');
        const notificationDropdown = document.getElementById('notificationDropdown');
        
        if (!notificationIcon || !notificationDropdown) {
            console.warn('Notification elements not found. Make sure notification HTML is included.');
            return;
        }
        
        loadNotifications();
        
        setupEventListeners();
        
        setupAutoRefresh();
        
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
        
        $(document).on('click', '.notification-item', function(e) {
            e.stopPropagation();
            const notifId = $(this).data('id');
            if (notifId) {
                // Only mark as read if it's currently unread
                if ($(this).hasClass('unread')) {
                    $(this).removeClass('unread');
                    markNotificationRead(notifId);
                }
            }
        });
        
        if (markAllRead) {
            markAllRead.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                markAllNotificationsRead();
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
            const typeLabel = notif.type.replace('_', ' ').toUpperCase();
            const timeAgo = formatTimeAgo(notif.created_at);
            
            html += `
                <div class="notification-item ${unreadClass}" data-id="${notif.id}">
                    <span class="notification-type ${notif.type}">${typeLabel}</span>
                    <div class="notification-message">${escapeHtml(notif.message)}</div>
                    <div class="notification-time">${timeAgo}</div>
                </div>
            `;
        });
        
        list.innerHTML = html;
    }
    
    function markNotificationRead(id) {
        $.ajax({
            url: getApiUrl(),
            method: 'POST',
            data: { 
                action: 'markNotificationRead',
                id: id
            },
            dataType: 'json'
        }).done(function(response) {
            if (response.success) {
                loadNotifications();
            }
        }).fail(function(xhr, status, error) {
            console.error('Failed to mark notification as read:', error);
        });
    }
    

    function markAllNotificationsRead() {
        $.ajax({
            url: getApiUrl(),
            method: 'POST',
            data: { action: 'markAllNotificationsRead' },
            dataType: 'json'
        }).done(function(response) {
            if (response.success) {
                loadNotifications(); 
            }
        }).fail(function(xhr, status, error) {
            console.error('Failed to mark all notifications as read:', error);
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
    
    window.NotificationSystem = {
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

