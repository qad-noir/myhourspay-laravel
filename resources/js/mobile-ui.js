window.responsiveDisclosure = (reveal = false) => ({
    expanded: true, mobileExpanded: reveal,
    init() {
        this.media = window.matchMedia('(max-width: 650px)');
        this.sync = () => { this.expanded = !this.media.matches || this.mobileExpanded; };
        this.media.addEventListener('change', this.sync);
        this.sync();
    },
    toggled(open) { if (this.media.matches) this.mobileExpanded = open; },
    reveal() { this.mobileExpanded = true; this.expanded = true; },
    destroy() { this.media.removeEventListener('change', this.sync); },
});

const tracks = new Map();
const initialiseTracks = () => {
    document.querySelectorAll('.tool-module-nav__track, .tool-workflow__track').forEach(track => {
        if (tracks.has(track)) return;
        const update = () => {
            track.dataset.moreBefore = String(track.scrollLeft > 2);
            track.dataset.moreAfter = String(track.scrollWidth - track.clientWidth - track.scrollLeft > 2);
        };
        const observer = new ResizeObserver(update);
        observer.observe(track);
        track.addEventListener('scroll', update, {passive:true});
        tracks.set(track, {observer, update});
        // Reveal the active tab without moving the whole page vertically.
        const active = track.querySelector('[aria-current="page"]');
        if (active && track.scrollWidth > track.clientWidth) {
            track.scrollLeft += active.getBoundingClientRect().left - track.getBoundingClientRect().left - 12;
        }
        update();
    });
};
document.addEventListener('DOMContentLoaded', initialiseTracks);
document.addEventListener('livewire:navigated', initialiseTracks);
window.addEventListener('pageshow', initialiseTracks);
document.addEventListener('livewire:navigating', () => {
    tracks.forEach(({observer,update}, track) => { observer.disconnect(); track.removeEventListener('scroll', update); });
    tracks.clear();
});
