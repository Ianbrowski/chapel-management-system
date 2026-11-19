<!-- --------------------------------------------------------------- -->
<!-- block html dis ->: MY PROFILE                                         -->
<!-- All roles — change display name, profile pic, password           -->
<!-- --------------------------------------------------------------- -->
<div class="section <?php echo ($active_section === 'my_profile') ? 'active' : ''; ?>" id="sec-my_profile" style="padding: 24px 32px 40px; max-width: 1240px; margin: 0 auto;">
    <div class="page-header-bar" style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-user-circle"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    My Profile
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">Personalize your account credentials, display name, photo, and security password</p>
            </div>
        </div>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; align-items: start;" class="profile-layout-grid">
        <style>
            @media (min-width: 1024px) {
                .profile-layout-grid {
                    grid-template-columns: 340px 1fr !important;
                }
            }
            .avatar-preview-wrapper img {
                width: 100% !important;
                height: 100% !important;
                object-fit: cover !important;
                border-radius: 50% !important;
                aspect-ratio: 1 / 1 !important;
            }
        </style>

        <!-- Left: Avatar + current info -->
        <div class="card text-center" style="padding: 32px 24px; border-radius: 12px; position: relative;">
            <div class="profile-avatar-control mb-16" style="position: relative; width: 140px; height: 140px; margin: 0 auto 24px; border-radius: 50%;">
                <div id="profile-preview-wrap" class="avatar-preview-wrapper" style="width: 140px; height: 140px; margin: 0 auto; border-radius: 50%; overflow: hidden; border: 3px solid var(--border); box-shadow: 0 4px 14px rgba(0,0,0,0.06); background: var(--bg2);">
                    <?php if ($profile_pic && file_exists(BASE_PATH . '/storage/' . $profile_pic)): ?>
                        <img id="profile-preview" src="storage/<?php echo htmlspecialchars($profile_pic); ?>?v=<?php echo time(); ?>" style="width: 140px; height: 140px; object-fit: cover; border-radius: 50%; display: block; aspect-ratio: 1/1;">
                    <?php else: ?>
                        <i id="profile-preview-initials" class="fa-solid fa-circle-user" style="font-size: 132px; color: var(--text2); opacity: 0.5; line-height: 132px; display: block; text-align: center;"></i>
                        <img id="profile-preview" src="" style="width: 140px; height: 140px; object-fit: cover; border-radius: 50%; aspect-ratio: 1/1;" class="d-none">
                    <?php endif; ?>
                </div>
                <label for="pic-input" class="avatar-cam-label" title="Change photo" style="position: absolute; bottom: 2px; right: 2px; background: #111111; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid #FFFFFF; box-shadow: 0 2px 8px rgba(0,0,0,0.15); z-index: 10;">
                    <i class="fa-solid fa-camera" style="color:#FFFFFF; font-size:13px;"></i>
                </label>
            </div>
            
            <div style="font-size: 1.5rem; font-weight: 800; color: var(--text); margin-bottom: 4px;"><?php echo htmlspecialchars($full_name); ?></div>
            <div style="color: var(--text2); font-weight: 600; font-size: 0.95rem; margin-bottom: 16px;">@<?php echo htmlspecialchars($username); ?></div>
            <div class="mt-8 mb-16"><span class="badge badge-confirmed" style="font-size: 13px; padding: 6px 16px; border-radius: 20px;"><?php echo htmlspecialchars($role); ?></span></div>
            
            <div style="background: #F6F6F6; border-radius: 8px; padding: 16px; margin-top: 24px; border: 1px dashed var(--border);">
                <!-- Drag & drop zone moved here -->
                <div id="drop-zone" style="cursor: pointer;">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size: 24px; color: var(--text); margin-bottom: 8px;"></i>
                    <div style="font-size: 13px; color: var(--text2); line-height: 1.5;">
                        Drag & drop a photo here, or <label for="pic-input" style="color: var(--text); cursor: pointer; font-weight: 600; text-decoration: underline;">browse</label><br>
                        <span style="font-size: 11px; opacity: 0.7;">Max size: 2MB · JPG, PNG, WEBP</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Edit form -->
        <div class="card" style="padding: 32px 24px; border-radius: 12px;">
            <div class="card-title" style="font-size: 1.1rem; border-bottom: 1px solid var(--border); padding-bottom: 16px; margin-bottom: 24px;"><i class="fa-solid fa-pen-to-square"></i> Edit Profile</div>
            <form method="POST" enctype="multipart/form-data" id="profile-form">
                <input type="hidden" name="form_section" value="my_profile">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="file" name="profile_pic" id="pic-input" accept="image/*" style="display:none;" onchange="previewPic(this)">

                <div class="form-group mb-14">
                    <label>Display Name <span class="text-helper">(shown in sidebar & greetings)</span></label>
                    <input type="text" name="full_name" maxlength="100" value="<?php echo htmlspecialchars($full_name !== $username ? $full_name : ''); ?>" placeholder="<?php echo htmlspecialchars($username); ?>">
                </div>

                <div class="pwd-change-section">
                    <div class="card-title mb-12"><i class="fa-solid fa-lock"></i> Change Password <span class="text-helper-regular">(leave blank to keep current)</span></div>
                    <div class="form-group mb-12">
                        <label>Current Password</label>
                        <div class="pos-relative">
                            <input type="password" name="current_password" id="cur-pwd" maxlength="72" placeholder="Enter current password">
                            <button type="button" onclick="togglePwd('cur-pwd','eye1')" class="pwd-toggle-btn">
                                <i class="fa-regular fa-eye" id="eye1"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group mb-12">
                        <label>New Password</label>
                        <div class="pos-relative">
                            <input type="password" name="new_password" id="new-pwd" minlength="6" maxlength="72" placeholder="Min. 6 characters">
                            <button type="button" onclick="togglePwd('new-pwd','eye2')" class="pwd-toggle-btn">
                                <i class="fa-regular fa-eye" id="eye2"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <div class="pos-relative">
                            <input type="password" id="confirm-pwd" minlength="6" maxlength="72" placeholder="Repeat new password"
                                   oninput="checkPwdMatch()">
                            <button type="button" onclick="togglePwd('confirm-pwd','eye3')" class="pwd-toggle-btn">
                                <i class="fa-regular fa-eye" id="eye3"></i>
                            </button>
                        </div>
                        <div id="pwd-match-msg" class="pwd-match-msg"></div>
                    </div>
                </div>

                <div class="btn-actions" style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--border); display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-primary" id="save-profile-btn" style="padding: 10px 24px;"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                    <button type="button" class="btn btn-gray" onclick="showSection('overview',null)" style="padding: 10px 24px;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    window.previewPic = function(input) {
        if (input.files && input.files[0]) {
            let reader = new FileReader();
            reader.onload = function(e) {
                let preview = document.getElementById('profile-preview');
                let initials = document.getElementById('profile-preview-initials');
                if (preview) {
                    preview.src = e.target.result;
                    preview.classList.remove('d-none');
                }
                if (initials) {
                    initials.classList.add('d-none');
                }
            }
            reader.readAsDataURL(input.files[0]);
        }
    };

    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('pic-input');

    if (dropZone && fileInput) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        dropZone.addEventListener('drop', handleDrop, false);

        function preventDefaults (e) { e.preventDefault(); e.stopPropagation(); }
        function highlight(e) { dropZone.style.borderColor = "var(--accent)"; }
        function unhighlight(e) { dropZone.style.borderColor = "var(--border)"; }
        
        function handleDrop(e) {
            let dt = e.dataTransfer;
            let files = dt.files;
            if (files.length > 0) {
                fileInput.files = files;
                window.previewPic(fileInput);
            }
        }
    }
})();
</script>
<style>
.pwd-toggle-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    color: var(--text2);
    font-size: 16px;
    cursor: pointer;
    padding: 0;
    outline: none;
}
.pwd-toggle-btn:hover {
    color: var(--accent);
}
</style>
