<?php
/**
 * AJAX Renderer for Dashboard Sections
 * This file returns only the required fragment for a smooth section change.
 * Variables shared: $active_section and $viewData.
 */

if (!isset($active_section)) exit('No section specified.');

require BASE_PATH . '/views/partials/dashboard/view_context.php';

// ── RENDER ACTIVE PARTIAL ───────────────────────────────────────────
switch ($active_section) {
    case 'overview':
    case 'events':
    case 'schedule_audit':     include BASE_PATH . '/views/partials/pastoral/calendar.php'; break;
    case 'my_profile':         include BASE_PATH . '/views/partials/dashboard/profile.php'; break;
    case 'bulletin':           include BASE_PATH . '/views/partials/dashboard/bulletin.php'; break;
    // case 'tickets': REMOVED per revision #14
    
    // Finance
    case 'donations':          include BASE_PATH . '/views/partials/finance/donations.php'; break;
    case 'merchandise':        include BASE_PATH . '/views/partials/finance/merchandise.php'; break;
    
    // Records
    case 'records_cathedral': 
        $scope = 'cathedral'; 
        include BASE_PATH . '/views/partials/records/main.php'; 
        break;
    case 'records_chapel':    
        $scope = 'chapel'; 
        include BASE_PATH . '/views/partials/records/main.php'; 
        break;

    // Pastoral/Scheduling
    case 'request_event':       include BASE_PATH . '/views/partials/pastoral/request_event.php'; break;
    case 'request_certificate': include BASE_PATH . '/views/partials/pastoral/request_certificate.php'; break;
    case 'cathedral':
    case 'schedule_cathedral':  include BASE_PATH . '/views/partials/pastoral/schedule_cathedral.php'; break;
    case 'chapel':
    case 'schedule_chapel':    include BASE_PATH . '/views/partials/pastoral/schedule_chapel.php'; break;
    case 'priest_schedule':
    case 'pastor_schedule':
    case 'priest_prefs':
    case 'pastor_prefs':       include BASE_PATH . '/views/partials/pastoral/priest_schedule.php'; break;

    // Admin & Governance Hub
    case 'settings':
    case 'users':
    case 'chapels':
    case 'services':
    case 'parish_info':
    case 'backups':
    case 'reports':
    case 'insights':
        include BASE_PATH . '/views/partials/management/settings_hub.php';
        break;

    // Inquiries
    case 'public_inquiries':   include BASE_PATH . '/views/partials/inquiries/messages.php'; break;
    case 'event_requests':     include BASE_PATH . '/views/partials/inquiries/event_requests.php'; break;

    default:
        echo "<div class='alert alert-warning'>Section not found or not accessible.</div>";
        break;
}
?>
<script>
if (typeof window.initSearchAutocomplete !== 'function') {
    var _parishAcScript = document.createElement('script');
    _parishAcScript.src = 'assets/js/search-autocomplete.js?v=' + Date.now();
    _parishAcScript.onload = function() {
        if (typeof window.initSearchAutocomplete === 'function') window.initSearchAutocomplete();
    };
    document.head.appendChild(_parishAcScript);
} else {
    if (typeof window.hideSearchAutocomplete === 'function') window.hideSearchAutocomplete();
    window.initSearchAutocomplete();
}
</script>
