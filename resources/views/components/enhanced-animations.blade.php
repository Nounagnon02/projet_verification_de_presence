<style>
/* Animations personnalisées */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes pulse-soft {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.8;
    }
}

@keyframes bounce-subtle {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-2px);
    }
}

/* Classes d'animation */
.animate-fade-in-up {
    animation: fadeInUp 0.5s ease-out;
}

.animate-slide-in-right {
    animation: slideInRight 0.3s ease-out;
}

.animate-pulse-soft {
    animation: pulse-soft 2s infinite;
}

.animate-bounce-subtle {
    animation: bounce-subtle 0.3s ease-in-out;
}

/* Transitions améliorées */
.transition-all-smooth {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.hover-lift:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.hover-scale:hover {
    transform: scale(1.02);
}

/* Mode sombre amélioré */
.dark-transition {
    transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
}

/* Effets de focus améliorés */
.focus-ring:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    border-color: #3b82f6;
}

.dark .focus-ring:focus {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

/* Boutons avec effets */
.btn-enhanced {
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.btn-enhanced::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.btn-enhanced:hover::before {
    left: 100%;
}

/* Cartes avec effets */
.card-enhanced {
    transition: all 0.3s ease;
    border: 1px solid transparent;
}

.card-enhanced:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border-color: rgba(59, 130, 246, 0.2);
}

.dark .card-enhanced:hover {
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    border-color: rgba(59, 130, 246, 0.3);
}

/* Indicateurs de chargement */
.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Notifications toast */
.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
    transform: translateX(100%);
    transition: transform 0.3s ease;
}

.toast-notification.show {
    transform: translateX(0);
}

/* Effets de survol pour les liens */
.link-enhanced {
    position: relative;
    text-decoration: none;
}

.link-enhanced::after {
    content: '';
    position: absolute;
    width: 0;
    height: 2px;
    bottom: -2px;
    left: 0;
    background-color: currentColor;
    transition: width 0.3s ease;
}

.link-enhanced:hover::after {
    width: 100%;
}

/* Amélioration des formulaires */
.form-input-enhanced {
    transition: all 0.3s ease;
    border: 2px solid #e5e7eb;
}

.form-input-enhanced:focus {
    border-color: var(--color-primary, #3b82f6);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    transform: scale(1.01);
}

.dark .form-input-enhanced {
    border-color: #4b5563;
    background-color: #374151;
    color: #f9fafb;
}

.dark .form-input-enhanced:focus {
    border-color: var(--color-primary, #3b82f6);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}
</style>

<script>
// Fonction pour ajouter des animations au chargement
document.addEventListener('DOMContentLoaded', function() {
    // Animer les éléments au chargement
    const animatedElements = document.querySelectorAll('.animate-on-load');
    animatedElements.forEach((el, index) => {
        setTimeout(() => {
            el.classList.add('animate-fade-in-up');
        }, index * 100);
    });
    
    // Ajouter des effets de survol dynamiques
    const cards = document.querySelectorAll('.card-enhanced');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
});

// Fonction pour afficher des notifications toast
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast-notification bg-${type === 'success' ? 'green' : 'red'}-500 text-white px-6 py-3 rounded-lg shadow-lg`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

// Améliorer les interactions avec les boutons
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('btn-enhanced')) {
        e.target.classList.add('animate-bounce-subtle');
        setTimeout(() => {
            e.target.classList.remove('animate-bounce-subtle');
        }, 300);
    }
});
</script>