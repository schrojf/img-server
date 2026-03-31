import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-toggle-section]').forEach(toggle => {
        toggle.addEventListener('click', () => {
            const section = toggle.closest('.dashboard-section');
            const body = section.querySelector('.section-body');
            const chevron = toggle.querySelector('.toggle-chevron');

            body.classList.toggle('hidden');

            if (chevron) {
                chevron.classList.toggle('rotate-180');
            }
        });
    });
});
