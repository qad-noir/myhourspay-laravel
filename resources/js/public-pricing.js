const initializePublicPricing = () => {
    document.querySelectorAll('[data-pricing-page]:not([data-pricing-bound])').forEach((page) => {
        page.dataset.pricingBound = 'true';
        const switches = [...page.querySelectorAll('[data-pricing-switch]')];
        const panels = [...page.querySelectorAll('[data-pricing-panel]')];
        const status = page.querySelector('[data-pricing-interval-status]');

        const renderInterval = (nextInterval) => {
            const interval = nextInterval === 'yearly' ? 'yearly' : 'monthly';
            page.dataset.activeInterval = interval;
            switches.forEach((button) => {
                const active = button.dataset.pricingSwitch === interval;
                button.setAttribute('aria-pressed', String(active));
            });
            panels.forEach((panel) => {
                panel.hidden = panel.dataset.pricingPanel !== interval;
            });
            if (status) status.textContent = `Showing ${interval === 'yearly' ? 'yearly' : 'monthly'} billing.`;
        };

        switches.forEach((button) => button.addEventListener('click', () => renderInterval(button.dataset.pricingSwitch)));
        renderInterval(page.dataset.initialInterval);
    });
};

initializePublicPricing();
document.addEventListener('livewire:navigated', initializePublicPricing);
