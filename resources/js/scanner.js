import { Html5Qrcode } from 'html5-qrcode';

/**
 * Scanner de QR personnel pour le responsable : lit le jeton encodé dans le
 * QR de chaque membre et l'envoie à l'endpoint de scan de la session ouverte.
 */
window.initAttendanceScanner = function (elementId, scanUrl, csrfToken, feedbackId) {
    const feedback = document.getElementById(feedbackId);
    const html5QrCode = new Html5Qrcode(elementId);
    let processing = false;
    let lastToken = null;
    let lastTokenAt = 0;

    function showFeedback(message, type) {
        feedback.textContent = message;
        feedback.className = type === 'success'
            ? 'mt-4 p-3 rounded bg-green-100 text-green-800'
            : 'mt-4 p-3 rounded bg-red-100 text-red-800';
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
                        let message = data.message;
                        if (data.new_badges && data.new_badges.length > 0) {
                            message += ' — badge(s) : ' + data.new_badges.join(', ');
                        }
                        showFeedback(message, 'success');
                    } else {
                        showFeedback(data.error || 'Erreur inconnue.', 'error');
                    }
                })
                .catch(() => showFeedback('Erreur réseau, réessayez.', 'error'))
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
        showFeedback("Impossible d'accéder à la caméra. Vérifiez les autorisations du navigateur.", 'error');
    });
};
