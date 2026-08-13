export function initSidebar() {
    const openSidebarBtn = document.getElementById('openSidebarBtn');
    const closeSidebarBtn = document.getElementById('closeSidebarBtn');
    const appSidebar = document.getElementById('appSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (!openSidebarBtn || !closeSidebarBtn || !appSidebar || !sidebarOverlay) return;

    const openSidebar = () => {
        appSidebar.classList.add('open');
        sidebarOverlay.classList.add('open');
    };

    const closeSidebar = () => {
        appSidebar.classList.remove('open');
        sidebarOverlay.classList.remove('open');
    };

    openSidebarBtn.addEventListener('click', openSidebar);
    closeSidebarBtn.addEventListener('click', closeSidebar);
    sidebarOverlay.addEventListener('click', closeSidebar);
}
