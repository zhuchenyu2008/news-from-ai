// news-from-ai/public/js/main.js

document.addEventListener('DOMContentLoaded', function() {
    const body = document.body;
    const themeToggleButton = document.getElementById('theme-toggle-button');
    let currentTheme = localStorage.getItem('theme'); // 'light' or 'dark'

    function applyThemePreference(theme) {
        body.classList.add('theme-changing');
        if (theme === 'dark') {
            body.classList.remove('theme-light');
            body.classList.add('theme-dark');
            if(themeToggleButton) themeToggleButton.textContent = "切换日间模式";
        } else {
            body.classList.remove('theme-dark');
            body.classList.add('theme-light');
            if(themeToggleButton) themeToggleButton.textContent = "切换夜间模式";
        }
        localStorage.setItem('theme', theme);
        // Remove transition class after animation
        setTimeout(() => {
            body.classList.remove('theme-changing');
        }, 500); // Matches CSS transition duration
    }

    // Function to set theme based on time (auto mode)
    function applyAutoTheme() {
        const hour = new Date().getHours(); // 0-23
        const autoTheme = (hour >= 7 && hour < 19) ? 'light' : 'dark'; // 7 AM to 7 PM is light
        applyThemePreference(autoTheme);
    }

    // Initialize theme
    if (currentTheme) { // User has a preference
        applyThemePreference(currentTheme);
    } else { // No preference, or 'auto' was set by PHP (which JS can't directly see from class)
        // Check if PHP set a specific theme, otherwise go auto
        if (body.classList.contains('theme-dark')) {
            currentTheme = 'dark';
            applyThemePreference('dark');
        } else if (body.classList.contains('theme-light')) {
            currentTheme = 'light';
            applyThemePreference('light');
        } else { // PHP was likely in 'auto' or no class was set
            applyAutoTheme(); // Sets currentTheme via applyThemePreference
        }
    }

    // Theme toggle button functionality
    if (themeToggleButton) {
        themeToggleButton.style.display = 'inline-block'; // Show the button
        themeToggleButton.addEventListener('click', () => {
            const newTheme = body.classList.contains('theme-dark') ? 'light' : 'dark';
            applyThemePreference(newTheme);
        });
    }

    // Optionally, re-apply auto theme periodically if no user preference is set
    // This is useful if the page is left open across the day/night boundary
    // and the user hasn't manually changed the theme.
    setInterval(() => {
        if (!localStorage.getItem('theme')) { // Only if no manual preference
            applyAutoTheme();
        }
    }, 60 * 1000 * 5); // Check every 5 minutes for auto theme change

    console.log('AI新闻聚合器 JS loaded. Current theme:', currentTheme || 'auto');

    // Animate news items on load
    const newsItems = document.querySelectorAll('.news-item');
    if (newsItems.length > 0) {
        newsItems.forEach((item, index) => {
            // Ensure items are initially hidden by CSS if JS is enabled
            // The CSS already has opacity: 0 and transform: translateY(20px)
            setTimeout(() => {
                item.style.opacity = '1';
                item.style.transform = 'translateY(0)';
            }, index * 100 + 50); // Staggered animation, slight delay
        });
    } else {
        console.log('No news items found to animate.');
    }
});
