document.addEventListener('DOMContentLoaded', () => {
    const themeSwitcher = document.getElementById('themeSwitcher');
    const body = document.body;

    const applyTheme = (theme) => {
        if (theme === 'dark') {
            body.classList.add('dark-theme');
            if (themeSwitcher) themeSwitcher.textContent = '☀️ 日间模式';
        } else {
            body.classList.remove('dark-theme');
            if (themeSwitcher) themeSwitcher.textContent = '🌙 夜间模式';
        }
    };

    let currentTheme = localStorage.getItem('theme');
    if (!currentTheme) {
        const hour = new Date().getHours();
        currentTheme = (hour < 7 || hour >= 19) ? 'dark' : 'light';
    }
    applyTheme(currentTheme);

    if (themeSwitcher) {
        themeSwitcher.addEventListener('click', () => {
            const newTheme = body.classList.contains('dark-theme') ? 'light' : 'dark';
            applyTheme(newTheme);
            localStorage.setItem('theme', newTheme);
        });
    }

    const newsItems = document.querySelectorAll('.news-item');
    newsItems.forEach((item, index) => {
        if (!item.classList.contains('animated')) {
            item.style.animationDelay = `${index * 0.08}s`;
            item.classList.add('animated');
        }
    });
});
