/**
 * Parish Internal Ministry Chatbox Engine
 * Handles real-time polling, channels, direct messaging, unread counts, and Web Audio chimes.
 */
const ParishChat = (() => {
    let state = {
        isOpen: false,
        activeTab: 'channels',
        currentRoomId: 0,
        currentRoomMeta: null,
        currentUser: null,
        lastMessageId: 0,
        pollTimer: null,
        soundEnabled: localStorage.getItem('parish_chat_sound') !== 'disabled',
        isScrolledUp: false,
        cachedContacts: [],
        noticeTimeout: null
    };

    const API_ENDPOINT = (function() {
        if (typeof window !== 'undefined' && window.BASE_URL) {
            return window.BASE_URL.replace(/\/+$/, '') + '/app/controllers/chat.php';
        }
        const baseEl = document.querySelector('base');
        if (baseEl && baseEl.href) {
            try {
                return new URL('app/controllers/chat.php', baseEl.href).href;
            } catch (e) {}
        }
        return 'app/controllers/chat.php';
    })();

    const DEFAULT_HEADERS = {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
    };

    // Centralized fetch with same-origin credentials (ensures InfinityFree security cookies and session pass through)
    function chatFetch(url, options = {}) {
        const opts = Object.assign({
            credentials: 'same-origin'
        }, options);
        opts.headers = Object.assign({}, DEFAULT_HEADERS, options.headers || {});
        return fetch(url, opts);
    }

    function resolveMediaUrl(path) {
        if (!path) return '';
        if (path.startsWith('http://') || path.startsWith('https://')) return path;
        const clean = path.replace(/^\/+/, '');
        const relative = clean.startsWith('storage/') ? clean : ('storage/' + clean);
        if (typeof window !== 'undefined' && window.BASE_URL) {
            return window.BASE_URL.replace(/\/+$/, '') + '/' + relative;
        }
        return relative;
    }

    // ── CSRF HELPER ──
    function getCsrfToken() {
        if (state.currentUser && state.currentUser.csrf_token) {
            return state.currentUser.csrf_token;
        }
        const hiddenEl = document.getElementById('chatCsrfToken');
        if (hiddenEl && hiddenEl.value) {
            return hiddenEl.value;
        }
        if (typeof window !== 'undefined' && window.CSRF_TOKEN) {
            return window.CSRF_TOKEN;
        }
        return '';
    }

    // ── INLINE NOTICE BANNER ──
    function showNotice(message, type = 'error') {
        const banner = document.getElementById('chatNoticeBanner');
        const textEl = document.getElementById('chatNoticeText');
        if (!banner || !textEl) return;

        textEl.textContent = message;
        banner.style.display = 'flex';

        clearTimeout(state.noticeTimeout);
        state.noticeTimeout = setTimeout(() => {
            hideNotice();
        }, 6000);
    }

    function hideNotice() {
        const banner = document.getElementById('chatNoticeBanner');
        if (banner) banner.style.display = 'none';
    }

    // ── INITIALIZATION ──
    function init() {
        const drawer = document.getElementById('parishChatWindow');
        const trigger = document.getElementById('parishChatTrigger');
        const topbarBtn = document.getElementById('topbarChatBtn');
        const minBtn = document.getElementById('chatMinimizeBtn');

        if (!drawer) return;

        if (trigger && !trigger.getAttribute('onclick')) {
            trigger.addEventListener('click', () => toggleChat());
        }
        if (topbarBtn && !topbarBtn.getAttribute('onclick')) {
            topbarBtn.addEventListener('click', (e) => {
                e.preventDefault();
                toggleChat();
            });
        }
        if (minBtn) minBtn.addEventListener('click', () => toggleChat(false));

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && state.isOpen) {
                toggleChat(false);
            }
        });

        // Tab visibility management (pause polling when backgrounded)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopPoller();
            } else {
                startPoller();
                checkUnreadCount();
            }
        });

        // Track scroll position in message stream
        const stream = document.getElementById('chatMessagesStream');
        if (stream) {
            stream.addEventListener('scroll', () => {
                const threshold = 60;
                const isBottom = stream.scrollHeight - stream.scrollTop - stream.clientHeight < threshold;
                state.isScrolledUp = !isBottom;
                const pill = document.getElementById('chatScrollBottomBtn');
                if (pill) {
                    pill.style.display = state.isScrolledUp ? 'flex' : 'none';
                }
            });
        }

        // Initial state load
        loadInitialState();
        startPoller();
    }

    // ── SOUND NOTIFICATION (Web Audio API Synthesizer) ──
    function playChime() {
        if (!state.soundEnabled) return;
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();

            // First note (gentle bell)
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            gain1.gain.setValueAtTime(0.12, ctx.currentTime);
            gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start();
            osc1.stop(ctx.currentTime + 0.3);

            // Second harmonious note
            setTimeout(() => {
                try {
                    const osc2 = ctx.createOscillator();
                    const gain2 = ctx.createGain();
                    osc2.type = 'sine';
                    osc2.frequency.setValueAtTime(880.00, ctx.currentTime); // A5
                    gain2.gain.setValueAtTime(0.10, ctx.currentTime);
                    gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
                    osc2.connect(gain2);
                    gain2.connect(ctx.destination);
                    osc2.start();
                    osc2.stop(ctx.currentTime + 0.35);
                } catch (e) {}
            }, 80);
        } catch (e) {
            // Audio context blocked or unsupported; silent fallback
        }
    }

    // ── CHAT WINDOW TOGGLE ──
    function toggleChat(forceState) {
        state.isOpen = (typeof forceState === 'boolean') ? forceState : !state.isOpen;
        const drawer = document.getElementById('parishChatWindow');
        const trigger = document.getElementById('parishChatTrigger');
        const topbarBtn = document.getElementById('topbarChatBtn');

        if (!drawer) return;

        if (state.isOpen) {
            drawer.style.display = 'flex';
            if (trigger) trigger.classList.add('is-open');
            if (topbarBtn) topbarBtn.classList.add('active');
            startPoller();
            // If opening and in a room, refresh messages and focus input
            if (state.currentRoomId > 0) {
                refreshMessages(true);
                setTimeout(() => focusInput(), 100);
            } else {
                loadInitialState();
            }
        } else {
            drawer.style.display = 'none';
            if (trigger) trigger.classList.remove('is-open');
            if (topbarBtn) topbarBtn.classList.remove('active');
            stopPoller();
        }
    }

    // ── POLLING ENGINE (OPTIMIZED FOR FREE HOSTING) ──
    function startPoller() {
        stopPoller();
        if (!state.isOpen) return; // Do not poll while chat is closed to protect hosting limits
        const interval = 8000; // 8 seconds to prevent free hosting rate-limiting
        state.pollTimer = setInterval(pollCycle, interval);
    }

    function stopPoller() {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }

    async function pollCycle() {
        if (document.hidden || !state.isOpen) return;

        if (state.isOpen && state.currentRoomId > 0) {
            // Poll for active room messages + unread count
            try {
                const res = await chatFetch(`${API_ENDPOINT}?action=poll&room_id=${state.currentRoomId}&after_id=${state.lastMessageId}`);
                if (!res.ok) return;
                const raw = await res.text();
                if (raw.trim().startsWith('<')) return; // Hosting security intercept; ignore poll cycle
                const data = JSON.parse(raw);
                if (data.success) {
                    if (data.new_messages && data.new_messages.length > 0) {
                        appendMessages(data.new_messages);
                        const hasIncoming = data.new_messages.some(msg => 
                            !state.currentUser || parseInt(msg.sender_id, 10) !== parseInt(state.currentUser.id, 10)
                        );
                        if (hasIncoming) {
                            playChime();
                        }
                    }
                    updateBadges(data.total_unread);
                }
            } catch (err) {
                // Network glitch; keep polling
            }
        }
    }

    async function checkUnreadCount() {
        try {
            const res = await chatFetch(`${API_ENDPOINT}?action=unread_count`);
            if (!res.ok) return;
            const raw = await res.text();
            if (raw.trim().startsWith('<')) return;
            const data = JSON.parse(raw);
            if (data.success) {
                updateBadges(data.total_unread);
            }
        } catch (e) {}
    }

    function updateBadges(totalUnread) {
        const fabBadge = document.getElementById('parishChatBadge');
        const topbarBadge = document.getElementById('topbarChatBadge');
        const sidebarBadge = document.getElementById('badge-sidebar-chat');
        const count = parseInt(totalUnread, 10) || 0;

        [fabBadge, topbarBadge, sidebarBadge].forEach(badge => {
            if (!badge) return;
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        });
    }

    // ── STATE LOADING ──
    async function loadInitialState() {
        try {
            const res = await chatFetch(`${API_ENDPOINT}?action=get_state`);
            const raw = await res.text();

            if (raw.trim().startsWith('<') || raw.includes('__test') || raw.includes('aes.js')) {
                throw new Error('Unable to connect to parish chat right now. Please reload the page.');
            }

            let data;
            try {
                data = JSON.parse(raw);
            } catch (err) {
                throw new Error(`Server returned HTTP ${res.status}: ${raw.substring(0, 70)}`);
            }

            if (!res.ok) {
                if (data && data.session_expired) {
                    const base = (typeof window !== 'undefined' && window.BASE_URL) ? window.BASE_URL.replace(/\/+$/, '') : '';
                    window.location.href = base ? `${base}/index.php?error=session` : 'index.php?error=session';
                    return;
                }
                throw new Error((data && data.error) ? data.error : `Server returned HTTP ${res.status}`);
            }
            if (!data.success) {
                throw new Error(data.error || 'Failed to load chat state');
            }

            state.currentUser = data.current_user;
            if (data.current_user && data.current_user.csrf_token) {
                const hiddenEl = document.getElementById('chatCsrfToken');
                if (hiddenEl) hiddenEl.value = data.current_user.csrf_token;
                window.CSRF_TOKEN = data.current_user.csrf_token;
            }

            renderChannels(data.channels || []);
            renderDirects(data.directs || []);
            state.cachedContacts = data.contacts || [];
            renderContacts(state.cachedContacts);
            updateBadges(data.total_unread || 0);

            // Update tab badges
            updateTabBadges(data.channels, data.directs);
            hideNotice();
        } catch (e) {
            console.error('ParishChat loadInitialState error:', e);
            renderHubError(e.message);
        }
    }

    function renderHubError(errMsg) {
        const chList = document.getElementById('chatChannelsList');
        const dList = document.getElementById('chatDirectsList');
        const cList = document.getElementById('chatContactsList');
        const errorHtml = `
            <div class="chat-empty-state" style="color:var(--chat-text-muted);">
                <i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;"></i>
                <div>Unable to connect to chat service</div>
                <button type="button" class="chat-retry-btn" onclick="ParishChat.loadInitialState()">
                    <i class="fa-solid fa-rotate-right"></i> Try Reconnecting
                </button>
            </div>
        `;
        if (chList) chList.innerHTML = errorHtml;
        if (dList) dList.innerHTML = errorHtml;
        if (cList) cList.innerHTML = errorHtml;
    }

    function updateTabBadges(channels = [], directs = []) {
        const chUnread = channels.reduce((acc, c) => acc + (parseInt(c.unread_count, 10) || 0), 0);
        const dirUnread = directs.reduce((acc, d) => acc + (parseInt(d.unread_count, 10) || 0), 0);

        const bCh = document.getElementById('badgeTabChannels');
        if (bCh) {
            bCh.textContent = chUnread;
            bCh.style.display = chUnread > 0 ? 'inline-block' : 'none';
        }

        const bDir = document.getElementById('badgeTabDirects');
        if (bDir) {
            bDir.textContent = dirUnread;
            bDir.style.display = dirUnread > 0 ? 'inline-block' : 'none';
        }
    }

    // ── RENDERING HUB LISTS ──
    function renderChannels(channels) {
        const container = document.getElementById('chatChannelsList');
        if (!container) return;

        if (channels.length === 0) {
            container.innerHTML = `
                <div class="chat-empty-state">
                    <i class="fa-solid fa-bullhorn"></i>
                    <div>No channels available</div>
                </div>`;
            return;
        }

        container.innerHTML = channels.map(ch => {
            const unread = parseInt(ch.unread_count, 10) || 0;
            const hasUnread = unread > 0;
            const preview = ch.last_message_text 
                ? escapeHtml(ch.last_message_text) 
                : (ch.description ? escapeHtml(ch.description) : 'No messages yet');
            const time = ch.last_message_time ? formatChatTime(ch.last_message_time) : '';

            return `
                <div class="chat-item-card ${hasUnread ? 'has-unread' : ''}" onclick="ParishChat.openRoom(${ch.room_id})">
                    <div class="chat-item-icon">
                        <i class="fa-solid ${ch.icon || 'fa-comments'}"></i>
                    </div>
                    <div class="chat-item-content">
                        <div class="chat-item-title-row">
                            <span class="chat-item-title">#${escapeHtml(ch.name)}</span>
                            ${time ? `<span class="chat-item-time">${time}</span>` : ''}
                        </div>
                        <div class="chat-item-preview-row">
                            <p class="chat-item-preview">${preview}</p>
                            ${hasUnread ? `<span class="chat-item-badge">${unread}</span>` : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderDirects(directs) {
        const container = document.getElementById('chatDirectsList');
        if (!container) return;

        if (directs.length === 0) {
            container.innerHTML = `
                <div class="chat-empty-state">
                    <i class="fa-solid fa-user-group"></i>
                    <div>No direct messages yet</div>
                    <p style="font-size:11px; margin-top:4px; opacity:0.8;">Go to Directory to start chatting with staff or priests.</p>
                </div>`;
            return;
        }

        container.innerHTML = directs.map(d => {
            const unread = parseInt(d.unread_count, 10) || 0;
            const hasUnread = unread > 0;
            const preview = d.last_message_text ? escapeHtml(d.last_message_text) : 'Started conversation';
            const time = d.last_message_time ? formatChatTime(d.last_message_time) : '';
            const roleClass = getRoleBadgeClass(d.other_user_role);

            return `
                <div class="chat-item-card ${hasUnread ? 'has-unread' : ''}" onclick="ParishChat.openRoom(${d.room_id})">
                    ${d.other_profile_pic ? `
                        <img src="${resolveMediaUrl(d.other_profile_pic)}" class="chat-item-avatar-img" alt="${escapeHtml(d.other_user_name)}">
                    ` : `
                        <div class="chat-item-icon">
                            <i class="fa-solid ${d.other_user_role === 'Priest' || d.other_user_role === 'Pastor' ? 'fa-cross' : 'fa-user'}"></i>
                        </div>
                    `}
                    <div class="chat-item-content">
                        <div class="chat-item-title-row">
                            <span class="chat-item-title">${escapeHtml(d.other_user_name)}</span>
                            ${time ? `<span class="chat-item-time">${time}</span>` : ''}
                        </div>
                        <div class="chat-item-preview-row">
                            <p class="chat-item-preview">${preview}</p>
                            <div style="display:flex; align-items:center; gap:4px;">
                                <span class="chat-role-chip ${roleClass}">${escapeHtml(d.other_user_role || 'Staff')}</span>
                                ${hasUnread ? `<span class="chat-item-badge">${unread}</span>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderContacts(contacts) {
        const container = document.getElementById('chatContactsList');
        if (!container) return;

        if (contacts.length === 0) {
            container.innerHTML = `
                <div class="chat-empty-state">
                    <i class="fa-solid fa-address-book"></i>
                    <div>No other staff or clergy found</div>
                </div>`;
            return;
        }

        container.innerHTML = contacts.map(c => {
            const roleClass = getRoleBadgeClass(c.role);
            const chapelTag = c.chapel_name ? `<span style="font-size:11px; color:var(--chat-text-muted);">· ${escapeHtml(c.chapel_name)}</span>` : '';

            return `
                <div class="chat-item-card" onclick="ParishChat.startDirectChat(${c.id})">
                    ${c.profile_pic ? `
                        <img src="${resolveMediaUrl(c.profile_pic)}" class="chat-item-avatar-img" alt="${escapeHtml(c.display_name)}">
                    ` : `
                        <div class="chat-item-icon">
                            <i class="fa-solid ${c.role === 'Priest' || c.role === 'Pastor' ? 'fa-cross' : 'fa-user-tie'}"></i>
                        </div>
                    `}
                    <div class="chat-item-content">
                        <div class="chat-item-title-row">
                            <span class="chat-item-title">${escapeHtml(c.display_name)}</span>
                            <span class="chat-role-chip ${roleClass}">${escapeHtml(c.role)}</span>
                        </div>
                        <div class="chat-item-preview-row">
                            <p class="chat-item-preview">@${escapeHtml(c.user)} ${chapelTag}</p>
                            <button type="button" class="chat-contact-btn" onclick="event.stopPropagation(); ParishChat.startDirectChat(${c.id});">
                                <i class="fa-solid fa-comment"></i> Chat
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function filterContacts(query) {
        const q = (query || '').toLowerCase().trim();
        if (!q) {
            renderContacts(state.cachedContacts);
            return;
        }
        const filtered = state.cachedContacts.filter(c => 
            (c.display_name && c.display_name.toLowerCase().includes(q)) ||
            (c.user && c.user.toLowerCase().includes(q)) ||
            (c.role && c.role.toLowerCase().includes(q)) ||
            (c.chapel_name && c.chapel_name.toLowerCase().includes(q))
        );
        renderContacts(filtered);
    }

    // ── TAB SWITCHING ──
    function switchTab(tabName) {
        state.activeTab = tabName;
        document.querySelectorAll('.chat-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-tab') === tabName);
        });

        const panels = {
            'channels': document.getElementById('chatTabChannels'),
            'directs': document.getElementById('chatTabDirects'),
            'contacts': document.getElementById('chatTabContacts')
        };

        Object.keys(panels).forEach(key => {
            if (panels[key]) {
                panels[key].style.display = (key === tabName) ? 'block' : 'none';
            }
        });
    }

    // ── ROOM VIEW & MESSAGES ──
    async function openRoom(roomId) {
        state.currentRoomId = roomId;
        state.lastMessageId = 0;
        state.isScrolledUp = false;

        // Switch to Room View
        const hubView = document.getElementById('chatViewHub');
        const roomView = document.getElementById('chatViewRoom');
        if (hubView) hubView.style.display = 'none';
        if (roomView) roomView.style.display = 'flex';

        const stream = document.getElementById('chatMessagesStream');
        if (stream) {
            stream.innerHTML = `<div class="chat-loading-state"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading messages...</div>`;
        }

        const roomHiddenInput = document.getElementById('chatCurrentRoomId');
        if (roomHiddenInput) roomHiddenInput.value = roomId;

        await refreshMessages(false);
        focusInput();
        startPoller(); // restarts with faster 3.5s interval
    }

    function backToHub() {
        state.currentRoomId = 0;
        state.currentRoomMeta = null;

        const hubView = document.getElementById('chatViewHub');
        const roomView = document.getElementById('chatViewRoom');
        if (roomView) roomView.style.display = 'none';
        if (hubView) hubView.style.display = 'flex';

        loadInitialState();
        startPoller();
    }

    async function refreshMessages(manualClick = false) {
        if (state.currentRoomId <= 0) return;

        try {
            const res = await chatFetch(`${API_ENDPOINT}?action=get_messages&room_id=${state.currentRoomId}&after_id=0`);
            const raw = await res.text();
            if (raw.trim().startsWith('<')) throw new Error('Unable to load chat messages right now. Please reload the page.');
            const data = JSON.parse(raw);
            if (!res.ok) {
                throw new Error(data.error || `Server returned HTTP ${res.status}`);
            }
            if (!data.success) {
                throw new Error(data.error || 'Failed to load messages');
            }

            state.currentRoomMeta = data.room;
            updateRoomHeader(data.room);
            renderMessageStream(data.messages || [], data.room);
            updateBadges(data.total_unread || 0);

            if (data.messages && data.messages.length > 0) {
                state.lastMessageId = parseInt(data.messages[data.messages.length - 1].message_id, 10);
            }

            if (!state.isScrolledUp || manualClick) {
                scrollToBottom(false);
            }
        } catch (e) {
            console.error('ParishChat refreshMessages error:', e);
            const stream = document.getElementById('chatMessagesStream');
            if (stream) {
                stream.innerHTML = `
                    <div class="chat-empty-state" style="color:var(--chat-text-muted);">
                        <i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;"></i>
                        <div>Failed to load messages</div>
                        <button type="button" class="chat-retry-btn" onclick="ParishChat.refreshMessages(true)">
                            <i class="fa-solid fa-rotate-right"></i> Try Again
                        </button>
                    </div>`;
            }
        }
    }

    function updateRoomHeader(room) {
        if (!room) return;
        const titleEl = document.getElementById('chatRoomTitle');
        const subEl = document.getElementById('chatRoomSubtitle');
        const badgeEl = document.getElementById('chatRoomBadge');
        const avatarEl = document.getElementById('chatRoomAvatar');

        const isChannel = (room.room_type === 'channel');
        const displayName = isChannel ? `#${room.name}` : room.display_name;

        if (titleEl) titleEl.textContent = displayName;
        if (subEl) {
            subEl.textContent = isChannel 
                ? (room.description || 'Ministry Channel') 
                : (room.other_user ? `@${room.other_user.user}` : 'Direct Conversation');
        }

        if (badgeEl) {
            if (!isChannel && room.other_user && room.other_user.role) {
                badgeEl.textContent = room.other_user.role;
                badgeEl.className = 'chat-role-chip ' + getRoleBadgeClass(room.other_user.role);
                badgeEl.style.display = 'inline-block';
            } else {
                badgeEl.style.display = 'none';
            }
        }

        if (avatarEl) {
            if (!isChannel && room.other_user && room.other_user.profile_pic) {
                avatarEl.innerHTML = `<img src="${resolveMediaUrl(room.other_user.profile_pic)}" alt="${escapeHtml(displayName)}">`;
            } else {
                avatarEl.innerHTML = `<i class="fa-solid ${isChannel ? (room.icon || 'fa-bullhorn') : 'fa-user'}"></i>`;
            }
        }
    }

    function renderMessageStream(messages, room) {
        const stream = document.getElementById('chatMessagesStream');
        if (!stream) return;

        const isChannel = (room && room.room_type === 'channel');
        let html = '';

        // Channel welcome banner
        if (isChannel) {
            html += `
                <div class="chat-welcome-banner">
                    <i class="fa-solid ${room.icon || 'fa-comments'}"></i>
                    <h4>Welcome to #${escapeHtml(room.name)}</h4>
                    <p>${escapeHtml(room.description || 'Coordination space for parish clergy, chapel coordinators, and office staff.')}</p>
                </div>
            `;
        }

        if (messages.length === 0) {
            html += `
                <div class="chat-empty-state" style="margin-top:20px;">
                    <i class="fa-regular fa-comment-dots"></i>
                    <div>No messages in this conversation yet.</div>
                    <div style="font-size:11px; margin-top:4px;">Be the first to share an update or note.</div>
                </div>
            `;
            stream.innerHTML = html;
            return;
        }

        html += messages.map(msg => createMessageHtml(msg)).join('');
        stream.innerHTML = html;
    }

    function appendMessages(newMessages) {
        const stream = document.getElementById('chatMessagesStream');
        if (!stream || !newMessages || newMessages.length === 0) return;

        // Remove empty state placeholder if present
        const empty = stream.querySelector('.chat-empty-state');
        if (empty) empty.remove();

        newMessages.forEach(msg => {
            const msgId = parseInt(msg.message_id, 10);
            if (msgId > state.lastMessageId) {
                state.lastMessageId = msgId;
            }
            // Prevent duplicate message rendering
            if (!document.getElementById(`chat-msg-${msg.message_id}`)) {
                stream.insertAdjacentHTML('beforeend', createMessageHtml(msg));
            }
        });

        if (!state.isScrolledUp) {
            scrollToBottom(true);
        } else {
            const pill = document.getElementById('chatScrollBottomBtn');
            if (pill) pill.style.display = 'flex';
        }
    }

    function createMessageHtml(msg) {
        const isMe = state.currentUser && (parseInt(msg.sender_id, 10) === parseInt(state.currentUser.id, 10));
        const timeStr = formatChatTime(msg.created_at);
        const roleClass = getRoleBadgeClass(msg.sender_role);

        const avatarContent = msg.sender_profile_pic 
            ? `<img src="${resolveMediaUrl(msg.sender_profile_pic)}" alt="${escapeHtml(msg.sender_name)}">`
            : escapeHtml((msg.sender_name || 'U').charAt(0).toUpperCase());

        return `
            <div id="chat-msg-${msg.message_id}" class="chat-msg-row ${isMe ? 'msg-outgoing' : 'msg-incoming'}">
                ${!isMe ? `
                    <div class="chat-msg-avatar" title="${escapeHtml(msg.sender_name)} (${escapeHtml(msg.sender_role)})">
                        ${avatarContent}
                    </div>
                ` : ''}
                <div class="chat-msg-bubble-wrap">
                    ${!isMe ? `
                        <div class="chat-msg-sender-meta">
                            <span class="chat-msg-sender-name">${escapeHtml(msg.sender_name)}</span>
                            <span class="chat-role-chip ${roleClass}">${escapeHtml(msg.sender_role)}</span>
                        </div>
                    ` : ''}
                    <div class="chat-msg-bubble">${escapeHtml(msg.message_text)}</div>
                    <span class="chat-msg-time">${timeStr}</span>
                </div>
            </div>
        `;
    }

    // ── SENDING MESSAGES ──
    async function handleFormSubmit(e) {
        if (e) e.preventDefault();
        const input = document.getElementById('chatInputText');
        if (!input) return;

        const text = input.value.trim();
        if (!text || state.currentRoomId <= 0) return;

        // Temporarily clear and shrink
        input.value = '';
        autoResizeTextarea(input);

        const sendBtn = document.getElementById('chatSendBtn');
        if (sendBtn) sendBtn.disabled = true;

        const token = getCsrfToken();

        try {
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('room_id', state.currentRoomId);
            formData.append('message_text', text);
            formData.append('csrf_token', token);
            formData.append('csrf', token);

            const res = await chatFetch(API_ENDPOINT, {
                method: 'POST',
                body: formData
            });

            const data = await res.json().catch(() => null);

            if (res.ok && data && data.success && data.message) {
                appendMessages([data.message]);
                scrollToBottom(true);
                if (typeof data.total_unread !== 'undefined') {
                    updateBadges(data.total_unread);
                }
                hideNotice();
            } else {
                // Restore text to prevent message loss
                input.value = text;
                autoResizeTextarea(input);

                if (data && data.session_expired) {
                    const base = (typeof window !== 'undefined' && window.BASE_URL) ? window.BASE_URL.replace(/\/+$/, '') : '';
                    window.location.href = base ? `${base}/index.php?error=session` : 'index.php?error=session';
                    return;
                }

                const errorMsg = (data && data.error) ? data.error : 'Failed to send message. Please try again.';
                showNotice(errorMsg, 'error');

                if (data && data.new_csrf) {
                    if (state.currentUser) state.currentUser.csrf_token = data.new_csrf;
                    const hiddenEl = document.getElementById('chatCsrfToken');
                    if (hiddenEl) hiddenEl.value = data.new_csrf;
                    window.CSRF_TOKEN = data.new_csrf;
                }
            }
        } catch (err) {
            console.error('Failed to send chat message:', err);
            // Restore text on network failure
            input.value = text;
            autoResizeTextarea(input);
            showNotice('Network error: Unable to deliver message. Check connection.', 'error');
        } finally {
            if (sendBtn) sendBtn.disabled = false;
            focusInput();
        }
    }

    function handleInputKeydown(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleFormSubmit();
        }
    }

    function autoResizeTextarea(el) {
        if (!el) return;
        el.style.height = 'auto';
        const newHeight = Math.min(el.scrollHeight, 90);
        el.style.height = newHeight + 'px';
        el.style.overflowY = el.scrollHeight > 90 ? 'auto' : 'hidden';
    }

    function focusInput() {
        const input = document.getElementById('chatInputText');
        if (input) input.focus();
    }

    function scrollToBottom(smooth = true) {
        const stream = document.getElementById('chatMessagesStream');
        if (!stream) return;
        if (smooth) {
            stream.scrollTo({ top: stream.scrollHeight, behavior: 'smooth' });
        } else {
            stream.scrollTop = stream.scrollHeight;
        }
        state.isScrolledUp = false;
        const pill = document.getElementById('chatScrollBottomBtn');
        if (pill) pill.style.display = 'none';
    }

    // ── START DIRECT CHAT FROM DIRECTORY ──
    async function startDirectChat(targetUserId) {
        try {
            const token = getCsrfToken();
            const formData = new FormData();
            formData.append('action', 'start_direct');
            formData.append('target_user_id', targetUserId);
            formData.append('csrf_token', token);
            formData.append('csrf', token);

            const res = await chatFetch(API_ENDPOINT, {
                method: 'POST',
                body: formData
            });

            if (!res.ok) {
                const errData = await res.json().catch(() => null);
                if (errData && errData.session_expired) {
                    const base = (typeof window !== 'undefined' && window.BASE_URL) ? window.BASE_URL.replace(/\/+$/, '') : '';
                    window.location.href = base ? `${base}/index.php?error=session` : 'index.php?error=session';
                    return;
                }
                showNotice((errData && errData.error) ? errData.error : 'Could not start direct chat. Server returned error.');
                return;
            }
            const data = await res.json().catch(() => null);
            if (data && data.success && data.room_id) {
                openRoom(data.room_id);
                hideNotice();
            } else {
                showNotice((data && data.error) ? data.error : 'Could not start direct chat.');
            }
        } catch (e) {
            console.error('startDirectChat error:', e);
            showNotice('Network error: Could not start direct chat.');
        }
    }

    // ── UTILITY HELPERS ──
    function getRoleBadgeClass(role) {
        if (!role) return 'role-staff';
        if (role === 'Priest' || role === 'Pastor') return 'role-priest';
        if (role === 'Admin' || role === 'Cathedral Admin') return 'role-admin';
        return 'role-staff';
    }

    function formatChatTime(dateString) {
        if (!dateString) return '';
        try {
            const d = new Date(dateString.replace(/-/g, '/'));
            const now = new Date();
            const isToday = d.toDateString() === now.toDateString();

            const timeFormatted = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true });
            if (isToday) return timeFormatted;

            return `${d.toLocaleDateString([], { month: 'short', day: 'numeric' })} ${timeFormatted}`;
        } catch (e) {
            return dateString;
        }
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Public API
    return {
        init,
        toggleChat,
        switchTab,
        openRoom,
        backToHub,
        refreshMessages,
        handleFormSubmit,
        handleInputKeydown,
        autoResizeTextarea,
        scrollToBottom,
        filterContacts,
        startDirectChat,
        loadInitialState,
        showNotice,
        hideNotice
    };
})();

// Expose to window for inline onclick handlers & cross-script calls
window.ParishChat = ParishChat;
window.toggleParishChat = function() { ParishChat.toggleChat(); };

// Auto-boot on DOM Ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ParishChat.init());
} else {
    ParishChat.init();
}
