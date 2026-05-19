document.addEventListener('DOMContentLoaded', function() {
    const themeToggle = document.getElementById('themeToggle') || document.querySelector('[data-theme-toggle]');
    const themeOptions = document.querySelectorAll('.theme-option');
    const htmlElement = document.documentElement;

    let savedTheme = localStorage.getItem('theme');

    if (!savedTheme) {
        savedTheme = 'dark';
        localStorage.setItem('theme', savedTheme);
    }

    window.AkkuTheme = {
        getTheme: function() {
            return htmlElement.getAttribute('data-theme') || localStorage.getItem('theme') || 'dark';
        },
        setTheme: setTheme
    };

    setTheme(savedTheme);
    updateActiveButton(savedTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const currentTheme = window.AkkuTheme.getTheme();
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            setTheme(newTheme);
            updateActiveButton(newTheme);
        });
    }

    themeOptions.forEach(option => {
        option.addEventListener('click', function() {
            const theme = this.getAttribute('data-theme');
            setTheme(theme);
            updateActiveButton(theme);
        });
    });

    function setTheme(theme) {
        htmlElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);

        if (themeToggle) {
            const icon = themeToggle.querySelector('i');
            if (icon) {
                icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        }
    }

    function updateActiveButton(activeTheme) {
        themeOptions.forEach(opt => {
            opt.classList.remove('active');
            if (opt.getAttribute('data-theme') === activeTheme) {
                opt.classList.add('active');
            }
        });
    }

    const animatedElements = document.querySelectorAll('.animate-slideUp');
    animatedElements.forEach((el, index) => {
        el.style.animationDelay = `${index * 0.1}s`;
    });
});

(function() {
    const savedTheme = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
})();
