// public/js/main.js

document.addEventListener('DOMContentLoaded', function() {
    const themeToggleButton = document.getElementById('theme-toggle-button');
    const htmlElement = document.documentElement;

    // Function to apply theme
    function applyTheme(theme) {
        if (theme === 'dark') {
            htmlElement.classList.add('dark-mode');
        } else {
            htmlElement.classList.remove('dark-mode');
        }
    }

    // Function to toggle theme
    function toggleTheme() {
        const currentTheme = htmlElement.classList.contains('dark-mode') ? 'dark' : 'light';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

        applyTheme(newTheme);
        localStorage.setItem('theme', newTheme); // Save preference
    }

    // Event listener for the button
    if (themeToggleButton) {
        themeToggleButton.addEventListener('click', toggleTheme);
    }

    // Initial theme setup based on saved preference or system preference
    // This is already handled by the inline script in <head> for faster paint,
    // but we can ensure consistency or add more complex logic here if needed.
    // For example, update button text based on current theme:
    function updateButtonText() {
        if (themeToggleButton) {
            // const currentTheme = htmlElement.classList.contains('dark-mode') ? 'dark' : 'light';
            // themeToggleButton.textContent = currentTheme === 'dark' ? '切换到日间模式' : '切换到夜间模式';
            // Or keep it simple:
            // themeToggleButton.textContent = '切换主题';
        }
    }
    updateButtonText(); // Call it once on load

    // Add simple fade-in animation to news cards if they don't have CSS animation
    // This is a fallback or enhancement, CSS animation is preferred for performance.
    // The current CSS already includes a staggered fadeIn animation.
    // This JS part for animation can be removed if CSS animation is sufficient.
    /*
    const newsItems = document.querySelectorAll('.news-item.card');
    newsItems.forEach((item, index) => {
        item.style.opacity = '0'; // Ensure it starts hidden if not handled by CSS
        item.style.transition = `opacity 0.5s ease-out ${index * 0.1}s, transform 0.5s ease-out ${index * 0.1}s`;

        // Trigger reflow to apply initial styles before transitioning
        // eslint-disable-next-line no-unused-expressions
        item.offsetHeight;

        // Start animation after a short delay to ensure styles are applied
        setTimeout(() => {
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, 50 + (index * 100)); // Stagger the animation
    });
    */
});
