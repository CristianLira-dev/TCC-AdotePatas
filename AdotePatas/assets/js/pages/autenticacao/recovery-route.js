document.addEventListener('DOMContentLoaded', () => {
    const endpoint = new URL('recuperar-senha/', document.baseURI).href;
    const form = document.getElementById('recuperar-form');
    const resendButton = document.getElementById('resend-btn');
    const sentEmail = document.getElementById('sent-email-address');

    if (form) {
        form.setAttribute('action', endpoint);
    }

    if (!resendButton) {
        return;
    }

    resendButton.addEventListener('click', async (event) => {
        if (resendButton.disabled) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        const email = sentEmail?.textContent?.trim() || '';
        if (!email) {
            return;
        }

        resendButton.disabled = true;
        resendButton.textContent = 'Reenviando...';

        try {
            const body = new FormData();
            body.append('email_recuperar', email);

            const response = await fetch(endpoint, {
                method: 'POST',
                body,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.error || 'email_send_failed');
            }

            resendButton.textContent = 'E-mail reenviado';
            window.setTimeout(() => {
                resendButton.disabled = false;
                resendButton.textContent = 'Reenviar';
            }, 30000);
        } catch (error) {
            console.error('Falha ao reenviar e-mail de recuperação:', error);
            resendButton.disabled = false;
            resendButton.textContent = 'Reenviar';
        }
    }, true);
});
