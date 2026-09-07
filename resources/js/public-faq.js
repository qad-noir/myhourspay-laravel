const initializePublicFaq = () => {
    document.querySelectorAll('[data-faq-group]:not([data-faq-bound])').forEach((group) => {
        group.dataset.faqBound = 'true';
        const items = [...group.querySelectorAll('details')];
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const states = new Map(items.map((item) => [item, { expanded: item.open, animation: null }]));

        const setExpanded = (item, expanded) => {
            const state = states.get(item);
            const summary = item.querySelector('summary');
            const answer = summary.nextElementSibling;
            const startHeight = item.getBoundingClientRect().height;
            state.animation?.cancel();
            state.expanded = expanded;
            item.dataset.faqExpanded = String(expanded);
            summary.setAttribute('aria-expanded', String(expanded));
            answer.inert = !expanded;

            const finish = () => {
                item.open = state.expanded;
                item.style.height = '';
                item.style.overflow = '';
                state.animation = null;
            };

            if (reducedMotion.matches || !item.animate) {
                finish();
                return;
            }

            // Keep the answer rendered until the closing animation finishes.
            item.open = true;
            item.style.height = `${startHeight}px`;
            item.style.overflow = 'hidden';
            const endHeight = summary.getBoundingClientRect().height
                + (expanded ? answer.getBoundingClientRect().height : 0);
            const animation = item.animate(
                { height: [`${startHeight}px`, `${endHeight}px`] },
                { duration: 260, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' },
            );
            state.animation = animation;
            animation.onfinish = () => {
                if (state.animation === animation) finish();
            };
        };

        items.forEach((item) => {
            // Native exclusive groups close instantly. JS coordinates both
            // animations; without JS the original name preserves native use.
            item.removeAttribute('name');
            const summary = item.querySelector('summary');
            item.dataset.faqExpanded = String(item.open);
            summary.setAttribute('aria-expanded', String(item.open));
            summary.nextElementSibling.inert = !item.open;
            summary.addEventListener('click', (event) => {
                event.preventDefault();
                const expanded = !states.get(item).expanded;
                if (expanded) {
                    items.filter((other) => other !== item && states.get(other).expanded)
                        .forEach((other) => setExpanded(other, false));
                }
                setExpanded(item, expanded);
            });
        });
    });
};

initializePublicFaq();
document.addEventListener('livewire:navigated', initializePublicFaq);
