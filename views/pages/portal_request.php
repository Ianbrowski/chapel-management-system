<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Service Portal — IFI Cathedral</title>
    <link rel="stylesheet" href="assets/css/fonts.css">
    <link rel="stylesheet" href="assets/css/all.min.css">
    <style>
        :root {
            --bg: #0c1017;
            --bg2: #161f30;
            --accent: #c9a84c;
            --accent-glow: rgba(201, 168, 76, 0.22);
            --text: #f8fafc;
            --text2: #94a3b8;
            --border: rgba(255, 255, 255, 0.12);
            --card-bg: #161f30;
        }
        html { font-size: 87.5%; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background: radial-gradient(circle at top right, #161f30 0%, #0c1017 100%);
        }
        .portal-container {
            max-width: 600px;
            width: 100%;
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
        }
        .header { text-align: center; margin-bottom: 24px; }
        .logo { width: 60px; height: 60px; margin-bottom: 16px; filter: drop-shadow(0 0 10px var(--accent-glow)); }
        .header h1 { font-family: 'Cinzel', serif; font-size: 24px; color: var(--accent); margin-bottom: 8px; }
        .header p { color: var(--text2); font-size: 14px; }
        
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 13px; font-weight: 600; color: var(--text2); margin-bottom: 8px; }
        input, select, textarea {
            width: 100%;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 16px;
            color: var(--text);
            font-family: inherit;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px var(--accent-glow);
            background: rgba(15, 23, 42, 0.8);
        }
        
        .btn-submit {
            width: 100%;
            background: var(--accent);
            color: #000;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: transform 0.2s, opacity 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 10px;
        }
        .btn-submit:hover { opacity: 0.9; transform: translateY(-2px); }
        .btn-submit:active { transform: translateY(0); }
        
        .flash {
            padding: 16px; border-radius: 12px; margin-bottom: 24px;
            font-size: 14px; display: flex; align-items: center; gap: 12px;
        }
        .flash-success { background: rgba(34, 197, 94, 0.1); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.2); }
        .flash-error { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }

        .back-link {
            display: block; text-align: center; margin-top: 24px;
            color: var(--text2); font-size: 13px; text-decoration: none; transition: color 0.3s;
        }
        .back-link:hover { color: var(--accent); }

        /* Responsive Tweaks */
        @media (max-width: 600px) {
            body { padding: 20px 10px; }
            .portal-container { padding: 30px 20px; }
            .header h1 { font-size: 20px; }
            .form-grid-mobile { grid-template-columns: 1fr !important; gap: 0 !important; }
        }
    </style>
</head>
<body>

<div class="portal-container">
    <div class="header">
        <?php if (isset($is_logged_in) && $is_logged_in): ?>
        <a href="logout.php" style="position: absolute; top: 15px; right: 15px; text-decoration: none; font-size: 10px; font-weight: 700; color: var(--accent); background: var(--accent-glow); padding: 4px 10px; border-radius: 20px; border: 1px solid var(--accent); transition: all 0.3s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
            <i class="fa-solid fa-right-from-bracket"></i> SIGN OUT
        </a>
        <?php endif; ?>
        <img src="assets/img/2323.png" alt="Logo" class="logo">
        <h1>Parish Service Portal</h1>
        <p>IFI Cathedral Parish</p>
    </div>

    <?php if ($flash): ?>
        <div class="flash flash-<?php echo $flash_type; ?>">
            <i class="fa-solid <?php echo ($flash_type==='success'?'fa-circle-check':'fa-circle-exclamation'); ?>"></i>
            <span><?php echo htmlspecialchars($flash); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!$flash || $flash_type === 'error'): ?>
    <form method="POST" action="public_requests.php">
        <input type="hidden" name="csrf" value="<?php echo $csrf ?? ''; ?>">
        
        <div class="form-group">
            <label>Full Name *</label>
            <input type="text" name="full_name" required maxlength="100" placeholder="Juan Dela Cruz" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
        </div>

        <div class="form-grid-mobile" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email" required placeholder="juan@michel.ph" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Phone Number <span style="font-size:11px; color:var(--text2);">(09XX-XXX-XXXX)</span></label>
                <input type="tel" name="phone" id="portal_phone" class="tel-mask" placeholder="0912-345-6789" maxlength="13" inputmode="tel" oninput="formatTelMask(this)" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Request Type *</label>
            <select name="request_type" required id="req_type" onchange="updateGuidance()">
                <option value="">— Select Service —</option>
                <option value="Certificate" <?php echo (isset($_POST['request_type']) && $_POST['request_type']==='Certificate'?'selected':''); ?>>Request a Certificate</option>
                <option value="Event Inquiry" <?php echo (isset($_POST['request_type']) && $_POST['request_type']==='Event Inquiry'?'selected':''); ?>>Schedule / Event Inquiry</option>
                <option value="Other" <?php echo (isset($_POST['request_type']) && $_POST['request_type']==='Other'?'selected':''); ?>>Other Concerns</option>
            </select>
        </div>

        <div class="form-group">
            <label>Target Venue / Chapel *</label>
            <select name="target_chapel" required>
                <option value="0">IFI Cathedral (Main)</option>
                <?php foreach($chapels as $c): ?>
                    <option value="<?php echo $c['chapel_id']; ?>" <?php echo (isset($_POST['target_chapel']) && (int)$_POST['target_chapel']===(int)$c['chapel_id']?'selected':''); ?>>
                        <?php echo htmlspecialchars($c['chapel_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="form-group" id="cat_group">
                <label>Category *</label>
                <select name="service_category" id="req_cat" onchange="updateGuidance()">
                    <option value="Baptism">Baptism</option>
                    <option value="Wedding">Wedding</option>
                    <option value="Funeral">Funeral</option>
                    <option value="Confirmation">Confirmation</option>
                    <option value="Mass">Mass Intentions</option>
                    <option value="Office">Office Appointment</option>
                </select>
            </div>
            <div class="form-group">
                <label>Target / Event Date</label>
                <input type="date" name="target_date" min="<?php echo date('Y-m-d'); ?>" max="2099-12-31" value="<?php echo htmlspecialchars($_POST['target_date'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-group" style="background:rgba(192,161,73,0.05); border:1px solid var(--accent); border-radius:12px; padding:16px;">
            <div id="checklist_container" style="font-size:12px; color:var(--text2);">
                <p style="margin-bottom:8px; opacity:0.8;">To process your <span id="sel_cat_name" style="color:var(--accent); font-weight:700;">request</span>, please provide:</p>
                <div id="checklist_items"></div>
            </div>
            <textarea name="details" id="req_details" rows="5" required maxlength="2000" placeholder="Type the information here..." style="margin-top:10px;"><?php echo htmlspecialchars($_POST['details'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="btn-submit">
            <i class="fa-solid fa-paper-plane"></i> Submit Request
        </button>
    </form>
    <?php else: ?>
        <div style="text-align:center; padding: 20px;">
            <p style="color: var(--text2); margin-bottom: 20px;">Our office hours are Mon–Sat: 8:00 AM – 5:00 PM. We will get back to you within 24–48 hours.</p>
            <a href="public_requests.php" class="btn-submit" style="text-decoration:none;">Submit Another Request</a>
        </div>
    <?php endif; ?>

    <a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Main Page</a>
</div>

<script>
    function updateGuidance() {
        const type     = document.getElementById('req_type').value;
        const cat      = document.getElementById('req_cat').value;
        const catGroup = document.getElementById('cat_group');
        const checklist = document.getElementById('checklist_items');
        const catName  = document.getElementById('sel_cat_name');

        catGroup.style.display = (type === 'Other' ? 'none' : 'block');
        catName.innerText = (type === 'Other' ? 'General Request' : cat);
        checklist.innerHTML = ''; 

        const requirements = {
            'Baptism': ['Child\'s Full Name', 'Parents\' Names (Father & Mother)', 'Approximate year of baptism'],
            'Wedding': ['Name of Groom', 'Name of Bride', 'Requested Wedding Date'],
            'Funeral': ['Full Name of the Deceased', 'Date of Death / Burial', 'Approximate age at time of death'],
            'Mass': ['Intention Name (e.g., In Memory of...)', 'Date and Time of Mass'],
            'Confirmation': ['Candidate\'s Full Name', 'Year of Confirmation', 'Church Location'],
            'Office': ['Reason for Consultation', 'Requested Appointment Time']
        };

        let items = requirements[cat] || ['Detailed description of your request'];
        if (type === 'Other') items = ['Specific details regarding your concern'];

        items.forEach(item => {
            const div = document.createElement('div');
            div.style = 'background:rgba(192,161,73,0.04); border-radius:6px; padding:6px 10px; margin-bottom:6px; border:1px solid rgba(192,161,73,0.1); display:flex; align-items:center; gap:8px;';
            div.innerHTML = `<i class="fa-solid fa-check" style="color:var(--accent); font-size:10px;"></i> ${item}`;
            checklist.appendChild(div);
        });
    }
    updateGuidance();

    function formatTelMask(input) {
        if (!input) return;
        let digits = input.value.replace(/\D/g, '');
        if (digits.startsWith('63') && digits.length >= 12) digits = '0' + digits.substring(2);
        if (digits.length > 11) digits = digits.substring(0, 11);
        let formatted = '';
        if (digits.length > 7) {
            formatted = digits.substring(0, 4) + '-' + digits.substring(4, 7) + '-' + digits.substring(7);
        } else if (digits.length > 4) {
            formatted = digits.substring(0, 4) + '-' + digits.substring(4);
        } else {
            formatted = digits;
        }
        input.value = formatted;
    }

    document.addEventListener('DOMContentLoaded', () => {
        const phone = document.getElementById('portal_phone');
        if (phone && phone.value) formatTelMask(phone);
    });
</script>

</body>
</html>
