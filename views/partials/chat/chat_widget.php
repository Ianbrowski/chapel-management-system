<?php
/**
 * Parish Internal Staff & Clergy Chatbox Floating Widget
 * Included conditionally in dashboard.php for Admin, Clergy, and Staff roles.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$chatCsrfToken = $_SESSION['csrf'] ?? '';
if (empty($chatCsrfToken)) {
    try {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $chatCsrfToken = $_SESSION['csrf'];
    } catch (\Throwable $e) {
        $chatCsrfToken = md5(uniqid((string)mt_rand(), true));
        $_SESSION['csrf'] = $chatCsrfToken;
    }
}
?>
<!-- ══ PARISH MINISTRY CHAT WIDGET ══ -->
<div id="parishChatWidget" class="parish-chat-root">

    <!-- Hidden Server-side CSRF Token for immediate fallback -->
    <input type="hidden" id="chatCsrfToken" value="<?php echo htmlspecialchars($chatCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Floating Chat Window / Modal Drawer (Launched from Topbar or Sidebar Chat Icon) -->
    <div id="parishChatWindow" class="chat-window-drawer" style="display: none;" role="dialog" aria-modal="true" aria-label="Parish Ministry Chat">
        
        <!-- Chat Inline Alert / Error Notice Banner -->
        <div id="chatNoticeBanner" class="chat-notice-banner" style="display: none;" role="alert">
            <i class="fa-solid fa-circle-exclamation chat-notice-icon"></i>
            <span id="chatNoticeText" class="chat-notice-text"></span>
            <button type="button" class="chat-notice-close" onclick="ParishChat.hideNotice()" aria-label="Dismiss">&times;</button>
        </div>

        <!-- ── VIEW 1: HUB (Channels, Direct Messages, Staff Directory) ── -->
        <div id="chatViewHub" class="chat-view active-view">
            <!-- Hub Header -->
            <div class="chat-header">
                <div class="chat-header-main">
                    <div class="chat-header-avatar">
                        <i class="fa-solid fa-church"></i>
                    </div>
                    <div class="chat-header-text">
                        <h3 class="chat-title">Parish Ministry Chat</h3>
                        <p class="chat-subtitle">Internal Clergy &amp; Staff Coordination</p>
                    </div>
                </div>
                <div class="chat-header-actions">
                    <button type="button" class="chat-head-btn" id="chatMinimizeBtn" title="Minimize Chat" aria-label="Close Chat" onclick="ParishChat.toggleChat(false)">
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                </div>
            </div>

            <!-- Hub Segment Tabs -->
            <div class="chat-hub-nav">
                <button type="button" class="chat-tab-btn active" data-tab="channels" onclick="ParishChat.switchTab('channels')" title="Ministry Channels">
                    <i class="fa-solid fa-layer-group"></i> <span class="chat-tab-text">Channels</span>
                    <span id="badgeTabChannels" class="chat-tab-badge" style="display: none;">0</span>
                </button>
                <button type="button" class="chat-tab-btn" data-tab="directs" onclick="ParishChat.switchTab('directs')" title="Direct Messages">
                    <i class="fa-solid fa-comments"></i> <span class="chat-tab-text">Direct</span>
                    <span id="badgeTabDirects" class="chat-tab-badge" style="display: none;">0</span>
                </button>
                <button type="button" class="chat-tab-btn" data-tab="contacts" onclick="ParishChat.switchTab('contacts')" title="Staff & Clergy Directory">
                    <i class="fa-solid fa-address-book"></i> <span class="chat-tab-text">Directory</span>
                </button>
            </div>

            <!-- Hub Content Body -->
            <div class="chat-hub-body">

                <!-- Tab 1: Ministry Channels -->
                <div id="chatTabChannels" class="chat-tab-panel active-tab">
                    <div class="chat-section-label">Ministry Channels</div>
                    <div id="chatChannelsList" class="chat-list-container">
                        <div class="chat-loading-state">
                            <i class="fa-solid fa-circle-notch fa-spin"></i> Loading channels...
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Direct Messages -->
                <div id="chatTabDirects" class="chat-tab-panel" style="display: none;">
                    <div class="chat-section-label">Direct Conversations</div>
                    <div id="chatDirectsList" class="chat-list-container">
                        <div class="chat-loading-state">
                            <i class="fa-solid fa-circle-notch fa-spin"></i> Loading conversations...
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Staff & Clergy Directory -->
                <div id="chatTabContacts" class="chat-tab-panel" style="display: none;">
                    <div class="chat-section-label">Staff &amp; Clergy Directory</div>
                    <div class="chat-search-wrap">
                        <i class="fa-solid fa-magnifying-glass chat-search-icon"></i>
                        <input type="text" id="chatContactSearch" class="chat-search-input" placeholder="Search admin, priest, or staff..." oninput="ParishChat.filterContacts(this.value)">
                    </div>
                    <div id="chatContactsList" class="chat-list-container">
                        <div class="chat-loading-state">
                            <i class="fa-solid fa-circle-notch fa-spin"></i> Loading directory...
                        </div>
                    </div>
                </div>

            </div>

            <!-- Hub Footer Status -->
            <div class="chat-hub-footer">
                <div class="chat-user-status">
                    <span class="chat-status-dot online"></span>
                    <span class="chat-status-text">
                        Logged in as <strong><?php echo htmlspecialchars($full_name ?: ($username ?? 'User')); ?></strong>
                        <span class="chat-role-chip"><?php echo htmlspecialchars($role); ?></span>
                    </span>
                </div>
            </div>
        </div>

        <!-- ── VIEW 2: ACTIVE CONVERSATION ROOM ── -->
        <div id="chatViewRoom" class="chat-view" style="display: none;">
            <!-- Room Header -->
            <div class="chat-header chat-header-room">
                <button type="button" class="chat-head-btn chat-btn-back" onclick="ParishChat.backToHub()" title="Back to Channels" aria-label="Back">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <div class="chat-room-meta">
                    <div id="chatRoomAvatar" class="chat-room-avatar">
                        <i class="fa-solid fa-comments"></i>
                    </div>
                    <div class="chat-room-info">
                        <div class="chat-room-title-row">
                            <span id="chatRoomTitle" class="chat-room-title">General Ministry</span>
                            <span id="chatRoomBadge" class="chat-role-chip" style="display: none;">Priest</span>
                        </div>
                        <div id="chatRoomSubtitle" class="chat-room-sub">Staff &amp; Clergy Channel</div>
                    </div>
                </div>
                <div class="chat-header-actions">
                    <button type="button" class="chat-head-btn" onclick="ParishChat.refreshMessages(true)" title="Refresh Messages" aria-label="Refresh">
                        <i class="fa-solid fa-rotate-right"></i>
                    </button>
                    <button type="button" class="chat-head-btn" onclick="ParishChat.toggleChat(false)" title="Minimize Chat" aria-label="Minimize">
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                </div>
            </div>

            <!-- Messages Stream Area -->
            <div id="chatMessagesStream" class="chat-messages-stream">
                <div class="chat-loading-state">
                    <i class="fa-solid fa-circle-notch fa-spin"></i> Loading messages...
                </div>
            </div>

            <!-- New Messages Sticky Pill (appears when scrolled up) -->
            <button type="button" id="chatScrollBottomBtn" class="chat-scroll-bottom-pill" style="display: none;" onclick="ParishChat.scrollToBottom(true)">
                <span>New messages</span>
                <i class="fa-solid fa-arrow-down"></i>
            </button>

            <!-- Message Composer Area -->
            <form id="chatComposerForm" class="chat-composer" onsubmit="ParishChat.handleFormSubmit(event)">
                <input type="hidden" id="chatCurrentRoomId" value="0">
                <div class="chat-input-wrapper">
                    <textarea 
                        id="chatInputText" 
                        class="chat-textarea" 
                        rows="1" 
                        placeholder="Type a message... (Enter to send)" 
                        onkeydown="ParishChat.handleInputKeydown(event)"
                        oninput="ParishChat.autoResizeTextarea(this)"></textarea>
                    
                    <button type="submit" id="chatSendBtn" class="chat-send-btn" title="Send message" aria-label="Send">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
                <div class="chat-composer-hint">
                    <i class="fa-solid fa-shield-halved"></i> Visible strictly to cathedral &amp; chapel ministry staff
                </div>
            </form>
        </div>

    </div>

</div>
