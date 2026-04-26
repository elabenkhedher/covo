// ── TOAST ──
function showToast(message, type = 'success') {
  let container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }
  const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
  const toast = document.createElement('div');
  toast.className = `toast ${type !== 'success' ? type : ''}`;
  toast.innerHTML = `<span>${icons[type] || '✅'}</span><span>${message}</span>`;
  container.appendChild(toast);
  setTimeout(() => { toast.style.animation = 'slideIn 0.3s ease reverse'; setTimeout(() => toast.remove(), 300); }, 3000);
}

// ── MODAL ──
function openModal(id) { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('open');
});

// ── SIDEBAR TOGGLE ──
document.addEventListener('DOMContentLoaded', () => {
  const hamburger = document.getElementById('hamburger');
  const sidebar = document.getElementById('sidebar');
  if (hamburger && sidebar) {
    hamburger.addEventListener('click', () => sidebar.classList.toggle('open'));
  }

  // Active nav link
  const links = document.querySelectorAll('.sidebar-nav a');
  links.forEach(link => {
    if (link.href === window.location.href) link.classList.add('active');
  });

  // Star rating
  document.querySelectorAll('.stars').forEach(starsEl => {
    const stars = starsEl.querySelectorAll('.star');
    stars.forEach((star, i) => {
      star.addEventListener('click', () => {
        stars.forEach((s, j) => s.classList.toggle('active', j <= i));
        starsEl.dataset.value = i + 1;
      });
    });
  });

  // Form validation helpers
  document.querySelectorAll('.form-control').forEach(input => {
    input.addEventListener('blur', () => validateField(input));
  });
});

function validateField(input) {
  const errorEl = input.parentElement.querySelector('.form-error');
  if (!input.value.trim() && input.required) {
    input.classList.add('error');
    if (errorEl) errorEl.textContent = 'Ce champ est obligatoire.';
    return false;
  }
  if (input.type === 'email' && !/\S+@\S+\.\S+/.test(input.value)) {
    input.classList.add('error');
    if (errorEl) errorEl.textContent = 'Email invalide.';
    return false;
  }
  input.classList.remove('error');
  if (errorEl) errorEl.textContent = '';
  return true;
}
