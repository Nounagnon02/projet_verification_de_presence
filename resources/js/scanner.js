import { Html5Qrcode } from 'html5-qrcode';

/**
 * Scanner de QR personnel pour le responsable : lit le jeton encodé dans le
 * QR de chaque membre et l'envoie à l'endpoint de scan de la session ouverte.
 */
window.initAttendanceScanner = function (elementId, scanUrl, csrfToken, labels = {}) {
    const waitingEl = document.getElementById('scan-waiting');
    const successEl = document.getElementById('scan-success');
    const successText = document.getElementById('scan-success-text');
    const successBadge = document.getElementById('scan-success-badge');
    const errorEl = document.getElementById('scan-error');
    const errorText = document.getElementById('scan-error-text');
    const html5QrCode = new Html5Qrcode(elementId);

    // Textes traduits fournis par la vue ; le français sert de repli si la vue
    // ne les passe pas (le JS n'a pas accès à __()).
    const defaultLabels = {
        badgeEarned: 'Badge obtenu :',
        unknownError: 'Erreur inconnue.',
        networkError: 'Erreur réseau, réessayez.',
        cameraError: "Impossible d'accéder à la caméra. Vérifiez les autorisations du navigateur.",
    };
    const t = (key) => labels[key] ?? defaultLabels[key];

    let processing = false;
    let lastToken = null;
    let lastTokenAt = 0;
    let revertTimer = null;

    function showWaiting() {
        clearTimeout(revertTimer);
        waitingEl.style.display = 'flex';
        successEl.style.display = 'none';
        errorEl.style.display = 'none';
    }

    function scheduleRevertToWaiting() {
        clearTimeout(revertTimer);
        revertTimer = setTimeout(showWaiting, 4000);
    }

    function showFeedback(message, type, badges) {
        waitingEl.style.display = 'none';
        if (type === 'success') {
            errorEl.style.display = 'none';
            successText.textContent = message;
            successBadge.textContent = badges && badges.length ? t('badgeEarned') + ' ' + badges.join(', ') : '';
            successEl.style.display = 'flex';
        } else {
            successEl.style.display = 'none';
            errorText.textContent = message;
            errorEl.style.display = 'flex';
        }
        scheduleRevertToWaiting();
    }

    function onScanSuccess(decodedText) {
        const now = Date.now();
        // Évite de renvoyer la même lecture en boucle pendant que la caméra reste braquée sur le même QR.
        if (processing || (decodedText === lastToken && now - lastTokenAt < 3000)) {
            return;
        }
        processing = true;
        lastToken = decodedText;
        lastTokenAt = now;

        let latitude = null;
        let longitude = null;

        const submit = () => {
            fetch(scanUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ token: decodedText, latitude, longitude }),
            })
                .then((response) => response.json().then((data) => ({ status: response.status, data })))
                .then(({ status, data }) => {
                    if (status === 200 && data.success) {
                        showFeedback(data.message, 'success', data.new_badges);
                    } else {
                        showFeedback(data.error || t('unknownError'), 'error');
                    }
                })
                .catch(() => showFeedback(t('networkError'), 'error'))
                .finally(() => {
                    processing = false;
                });
        };

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    latitude = position.coords.latitude;
                    longitude = position.coords.longitude;
                    submit();
                },
                () => submit(),
                { timeout: 3000 }
            );
        } else {
            submit();
        }
    }

    html5QrCode.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        onScanSuccess,
        () => {}
    ).catch(() => {
        showFeedback(t('cameraError'), 'error');
    });
};
