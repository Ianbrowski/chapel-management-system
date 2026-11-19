/**
 * Dashboard Profile Module
 * Handles profile picture previews, password matching, and drag & drop.
 */

function previewPic(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('profile-preview');
        const initials = document.getElementById('profile-preview-initials');
        if (img) { img.src = e.target.result; img.style.display = 'block'; }
        if (initials) initials.style.display = 'none';
        const dz = document.getElementById('drop-zone');
        if (dz) dz.innerHTML = `<i class="fa-solid fa-check" style="color:#16a34a;font-size:20px;display:block;margin-bottom:6px;"></i><span style="color:#16a34a;font-weight:600;">${input.files[0].name}</span>`;
    };
    reader.readAsDataURL(input.files[0]);
}

function togglePwd(inputId, iconId) {
    const inp  = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (!inp) return;
    if (inp.type === 'password') { 
        inp.type = 'text';     
        if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); } 
    } else { 
        inp.type = 'password'; 
        if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); } 
    }
}

function checkPwdMatch() {
    const np  = document.getElementById('new-pwd')?.value;
    const cp  = document.getElementById('confirm-pwd')?.value;
    const msg = document.getElementById('pwd-match-msg');
    const btn = document.getElementById('save-profile-btn');
    if (!msg || !np || !cp) return;
    if (np === cp) {
        msg.innerHTML = '<span style="color:#16a34a;"><i class="fa-solid fa-check"></i> Passwords match</span>';
        if (btn) btn.disabled = false;
    } else {
        msg.innerHTML = '<span style="color:#ef4444;"><i class="fa-solid fa-xmark"></i> Passwords do not match</span>';
        if (btn) btn.disabled = true;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const dz    = document.getElementById('drop-zone');
    const input = document.getElementById('pic-input');
    if (dz && input) {
        dz.addEventListener('dragover',  e => { 
            e.preventDefault(); 
            dz.style.borderColor = 'var(--accent)'; 
            dz.style.background = 'var(--accent-glow)'; 
        });
        dz.addEventListener('dragleave', e => { 
            dz.style.borderColor = 'var(--border)';  
            dz.style.background = ''; 
        });
        dz.addEventListener('drop', e => {
            e.preventDefault();
            dz.style.borderColor = 'var(--border)'; dz.style.background = '';
            const file = e.dataTransfer.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            const dt = new DataTransfer(); 
            dt.items.add(file);
            input.files = dt.files;
            previewPic(input);
        });
    }
});
