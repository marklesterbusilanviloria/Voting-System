// shared.js — navbar dropdown + modal logic used on every page

function toggleUserMenu(event) {
    event.stopPropagation();
    document.getElementById('userDropdownMenu')?.classList.toggle('show');
}

window.addEventListener('click', function () {
    document.getElementById('userDropdownMenu')?.classList.remove('show');
});

// Guest auth modal
function openAuthModal()  { 
    const modal = document.getElementById('authModal');
    if (modal) {
        modal.style.display = 'flex';
    }   
}

function closeAuthModal() { 
    const modal = document.getElementById('authModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

document.getElementById('authModal')?.addEventListener('click', function (e) {
    if (e.target === this) closeAuthModal();
});

window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        window.location.reload();
    }
});


