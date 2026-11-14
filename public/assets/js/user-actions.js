document.querySelectorAll('[data-user-menu-trigger]').forEach((trigger) => {
    const menu = document.getElementById(trigger.getAttribute('popovertarget'));

    menu.addEventListener('beforetoggle', (event) => {
        const opening = event.newState === 'open';
        trigger.setAttribute('aria-expanded', String(opening));
        if (!opening) return;

        const bounds = trigger.getBoundingClientRect();
        const spaceBelow = window.innerHeight - bounds.bottom - 12;
        const openAbove = spaceBelow < 120 && bounds.top > spaceBelow;
        menu.style.left = `${Math.max(12, Math.min(bounds.right - 168, window.innerWidth - 180))}px`;
        menu.style.top = openAbove ? 'auto' : `${bounds.bottom + 6}px`;
        menu.style.bottom = openAbove ? `${window.innerHeight - bounds.top + 6}px` : 'auto';
        menu.style.maxHeight = `${Math.max(44, openAbove ? bounds.top - 18 : spaceBelow - 6)}px`;
    });

    const closeMenu = () => {
        if (menu.matches(':popover-open')) menu.hidePopover();
    };
    window.addEventListener('resize', closeMenu);
    window.addEventListener('scroll', (event) => {
        if (!menu.contains(event.target)) closeMenu();
    }, true);
});
