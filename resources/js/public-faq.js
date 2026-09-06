const initializePublicFaq = () => {
    document.querySelectorAll('[data-faq-group]:not([data-faq-bound])').forEach((group) => {
        group.dataset.faqBound = 'true';
        const items = [...group.querySelectorAll('details')];
        items.forEach((item) => item.addEventListener('toggle', () => {
            if (!item.open) return;
            items.filter((other) => other !== item).forEach((other) => { other.open = false; });
        }));
    });
};

initializePublicFaq();
document.addEventListener('livewire:navigated', initializePublicFaq);
