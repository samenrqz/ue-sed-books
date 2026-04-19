const hamburger = document.getElementById('hamburger');
const nav = document.getElementById('nav');

if (hamburger) {
    hamburger.addEventListener('click', () => {
        nav.classList.toggle('active');
    });
    
    // Close menu when a link is clicked
    nav.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            nav.classList.remove('active');
        });
    });
    
    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.header')) {
            nav.classList.remove('active');
        }
    });
}

// Floating animation for images
document.querySelectorAll('.floating-item').forEach((item, index) => {
    const speed = 2 + index * 0.5;
    const range = 8 + index * 3;
    let start = null;

    function float(timestamp) {
        if (!start) start = timestamp;
        const elapsed = timestamp - start;
        const y = Math.sin(elapsed / (speed * 300)) * range;
        const x = Math.cos(elapsed / (speed * 400)) * (range * 0.5);
        item.style.translate = `${x}px ${y}px`;
        requestAnimationFrame(float);
    }
    requestAnimationFrame(float);
});

// Smooth fade-in on scroll for hero elements
const observerOptions = { threshold: 0.1 };
const fadeObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            fadeObserver.unobserve(entry.target);
        }
    });
}, observerOptions);

document.querySelectorAll('.hero-left, .hero-right').forEach(el => {
    el.classList.add('fade-in');
    fadeObserver.observe(el);
});

// Button ripple effect
document.querySelectorAll('.btn-register, .btn-register-cta, .btn-create-account').forEach(btn => {
    btn.addEventListener('click', function(e) {
        const ripple = document.createElement('span');
        ripple.classList.add('ripple');
        const rect = this.getBoundingClientRect();
        ripple.style.left = (e.clientX - rect.left) + 'px';
        ripple.style.top = (e.clientY - rect.top) + 'px';
        this.appendChild(ripple);
        ripple.addEventListener('animationend', () => ripple.remove());
    });
});

// Password toggle
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('passwordInput');

if (togglePassword && passwordInput) {
    togglePassword.addEventListener('click', () => {
        const type = passwordInput.type === 'password' ? 'text' : 'password';
        passwordInput.type = type;
        togglePassword.querySelector('.eye-icon').style.opacity = type === 'text' ? '0.5' : '1';
    });
}

// Register form fade-in
const registerCard = document.querySelector('.register-card');
if (registerCard) {
    registerCard.classList.add('fade-in');
    const cardObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                cardObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    cardObserver.observe(registerCard);
}