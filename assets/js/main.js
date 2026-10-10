// Menu mobile
const toggle = document.querySelector('.navtoggle');
const nav = document.getElementById('nav');
if (toggle && nav) {
    toggle.addEventListener('click', () => {
        const open = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!open));
        nav.classList.toggle('is-open', !open);
    });
    nav.addEventListener('click', e => {
        if (e.target.closest('a')) {
            toggle.setAttribute('aria-expanded', 'false');
            nav.classList.remove('is-open');
        }
    });
}

// Bayangan header saat halaman di-scroll
const topbar = document.querySelector('.topbar');
if (topbar) {
    const onScroll = () => {
        topbar.style.boxShadow = window.scrollY > 20 ? '0 10px 30px -10px rgba(11, 42, 60, 0.18)' : 'none';
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
}

// Elemen muncul halus saat masuk layar
const revealEls = document.querySelectorAll('.reveal');
if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    revealEls.forEach((el, i) => {
        el.style.transitionDelay = (i % 4) * 70 + 'ms';
        io.observe(el);
    });
} else {
    revealEls.forEach(el => el.classList.add('is-visible'));
}
