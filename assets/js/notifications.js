/**
 * Real-time Notification Service
 * Polls the server for updates and updates sidebar badges.
 */
(function() {
    let lastStats = { events: null, inquiries: null, stale: null };
    let pollInterval = 15000; // 15 seconds

    function updateBadge(id, count) {
        const badges = document.querySelectorAll('#' + id + ', .' + id);
        if (badges.length === 0) return;

        badges.forEach(badge => {
            if (count > 0) {
                badge.innerText = count > 99 ? '99+' : count;
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        });

        const prev = lastStats[id.replace('badge-', '')];
        if (prev !== null && count > prev) {
            badges.forEach(b => {
                b.classList.add('badge-pulse');
                setTimeout(() => b.classList.remove('badge-pulse'), 3000);
            });
            showToast("New " + id.replace('badge-', '') + " notification!");
        }

        if (id === 'badge-inquiries') {
            const pill = document.getElementById('inboxDdCountPill');
            if (pill) {
                if (count > 0) {
                    pill.textContent = count + ' pending';
                    pill.style.display = 'inline-flex';
                } else {
                    pill.style.display = 'none';
                }
            }
            if (window.InboxDropdown && window.InboxDropdown.isOpen && prev !== null && count !== prev) {
                window.InboxDropdown.fetchData(true);
            }
        }
    }

    function checkNotifications() {
        const baseUrl = (typeof window !== 'undefined' && window.BASE_URL) ? window.BASE_URL.replace(/\/+$/, '') : '';
        const endpoint = baseUrl ? `${baseUrl}/app/controllers/notifications.php` : 'app/controllers/notifications.php';
        fetch(endpoint, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
            .then(res => res.json())
            .then(data => {
                if (data.error) return;

                updateBadge('badge-events', data.events);
                updateBadge('badge-inquiries', data.inquiries);
                updateBadge('badge-stale', data.stale);
                
                lastStats.events = data.events;
                lastStats.inquiries = data.inquiries;
                lastStats.stale = data.stale;
            })
            .catch(err => console.error('Notification check failed:', err));
    }

    function showToast(message) {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText = "position:fixed; bottom:20px; right:20px; z-index:9999;";
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = 'cms-toast';
        toast.innerHTML = `
            <div style="background:var(--accent); color:var(--bg); padding:12px 20px; border-radius:8px; 
                        box-shadow:0 10px 30px rgba(0,0,0,0.5); display:flex; align-items:center; gap:10px;
                        margin-top:10px; font-weight:600; font-size:14px; animation: slideIn 0.3s ease-out;">
                <i class="fa-solid fa-bell"></i>
                <span>${message}</span>
            </div>
        `;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.5s';
            setTimeout(() => toast.remove(), 500);
        }, 5000);
    }

    // Add CSS animations
    const style = document.createElement('style');
    style.innerHTML = `
        @keyframes badge-pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(193, 161, 75, 0.7); }
            70% { transform: scale(1.2); box-shadow: 0 0 0 10px rgba(193, 161, 75, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(193, 161, 75, 0); }
        }
        .badge-pulse { animation: badge-pulse 1s infinite !important; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);

    // Initial check and set interval
    checkNotifications();
    setInterval(checkNotifications, pollInterval);
})();
