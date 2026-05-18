// Protection anti-capture d'écran avancée
class QRSecurityProtection {
    constructor() {
        this.isProtected = false;
        this.alertCount = 0;
        this.maxAlerts = 3;
        this.init();
    }

    init() {
        this.enableBasicProtections();
        this.enableAdvancedDetection();
        this.enableVisibilityProtection();
        this.startSecurityMonitoring();
    }

    enableBasicProtections() {
        // Désactiver clic droit
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            this.triggerSecurityAlert('Clic droit désactivé');
        });

        // Désactiver sélection de texte
        document.addEventListener('selectstart', (e) => {
            e.preventDefault();
        });

        // Désactiver raccourcis clavier dangereux
        document.addEventListener('keydown', (e) => {
            const forbiddenKeys = ['F12', 'PrintScreen'];
            const forbiddenCombos = [
                { ctrl: true, shift: true, key: 'I' },
                { ctrl: true, shift: true, key: 'C' },
                { ctrl: true, key: 'u' },
                { ctrl: true, key: 's' },
                { ctrl: true, key: 'p' }
            ];

            if (forbiddenKeys.includes(e.key)) {
                e.preventDefault();
                this.triggerSecurityAlert(`Touche ${e.key} bloquée`);
                return;
            }

            for (const combo of forbiddenCombos) {
                if (combo.ctrl && !e.ctrlKey) continue;
                if (combo.shift && !e.shiftKey) continue;
                if (combo.key && e.key !== combo.key) continue;

                e.preventDefault();
                this.triggerSecurityAlert('Raccourci bloqué');
                return;
            }
        });
    }

    enableAdvancedDetection() {
        // Détection DevTools
        setInterval(() => {
            if (window.outerHeight - window.innerHeight > 160 || 
                window.outerWidth - window.innerWidth > 160) {
                this.blurContent();
                this.triggerSecurityAlert('Outils développeur détectés');
            }
        }, 500);
    }

    enableVisibilityProtection() {
        // Protection lors de la perte de focus
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.blurContent();
                this.triggerSecurityAlert('Page cachée');
            } else {
                setTimeout(() => this.unblurContent(), 1000);
            }
        });

        window.addEventListener('blur', () => {
            this.blurContent();
            this.triggerSecurityAlert('Fenêtre inactive');
        });

        window.addEventListener('focus', () => {
            setTimeout(() => this.unblurContent(), 1000);
        });
    }

    startSecurityMonitoring() {
        setInterval(() => {
            this.checkForSuspiciousActivity();
        }, 2000);
    }

    checkForSuspiciousActivity() {
        const start = performance.now();
        debugger;
        const end = performance.now();
        
        if (end - start > 100) {
            this.blurContent();
            this.triggerSecurityAlert('Console développeur active');
        }
    }

    blurContent() {
        const qrSection = document.getElementById('qr-section');
        const overlay = document.getElementById('protection-overlay');
        
        if (qrSection) {
            qrSection.style.filter = 'blur(15px)';
        }
        
        if (overlay) {
            overlay.classList.remove('hidden');
        }
        
        this.isProtected = true;
    }

    unblurContent() {
        const qrSection = document.getElementById('qr-section');
        const overlay = document.getElementById('protection-overlay');
        
        if (qrSection) {
            qrSection.style.filter = 'none';
        }
        
        if (overlay) {
            overlay.classList.add('hidden');
        }
        
        this.isProtected = false;
    }

    triggerSecurityAlert(message) {
        this.alertCount++;
        
        const alertDiv = document.getElementById('security-alert');
        if (alertDiv) {
            alertDiv.classList.remove('hidden');
            const messageSpan = alertDiv.querySelector('span');
            if (messageSpan) {
                messageSpan.textContent = message;
            }
            
            setTimeout(() => {
                alertDiv.classList.add('hidden');
            }, 3000);
        }

        if (this.alertCount >= this.maxAlerts) {
            this.lockScreen();
        }
    }

    lockScreen() {
        const qrSection = document.getElementById('qr-section');
        if (qrSection) {
            qrSection.innerHTML = `
                <div class="text-center p-8 bg-red-50 border border-red-200 rounded-lg">
                    <h3 class="text-xl font-bold text-red-700 mb-2">Accès Bloqué</h3>
                    <p class="text-red-600">Trop de tentatives de capture détectées.</p>
                    <button onclick="location.reload()" class="mt-4 bg-red-600 text-white px-4 py-2 rounded">
                        Actualiser
                    </button>
                </div>
            `;
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('qr-section')) {
        window.qrSecurity = new QRSecurityProtection();
    }
});