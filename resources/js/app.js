import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-toggle-section]').forEach(toggle => {
        const section = toggle.closest('.dashboard-section');
        const body = section.querySelector('.section-body');
        const chevron = toggle.querySelector('.toggle-chevron');

        function toggleSection() {
            const isHidden = body.classList.toggle('hidden');
            toggle.setAttribute('aria-expanded', !isHidden);

            if (chevron) {
                chevron.classList.toggle('rotate-180', isHidden);
            }
        }

        toggle.addEventListener('click', toggleSection);

        toggle.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleSection();
            }
        });
    });
});
