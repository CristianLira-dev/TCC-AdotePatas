// assets/js/pages/autenticacao/modules/ui/toast.js

/**
 * Inicializa o sistema de notificações (toast).
 * Mantém compatibilidade com os tipos antigos do PHP:
 * warning -> alert
 * danger  -> error
 */
export const initToastNotification = () => {
    const phpData = document.getElementById("php-data");

    if (!phpData) {
        return;
    }

    const message = phpData.dataset.message?.trim();
    const receivedType = phpData.dataset.type?.trim().toLowerCase();

    if (!message) {
        return;
    }

    const typeAliases = {
        success: "success",
        warning: "alert",
        alert: "alert",
        danger: "error",
        error: "error",
    };

    const type = typeAliases[receivedType] || "alert";
    const duration = 5000;

    const toast = document.getElementById("toast-notification");
    const toastIcon = document.getElementById("toast-icon");
    const toastMessage = document.getElementById("toast-message");
    const progressBar = toast?.querySelector(".toast-progress-bar");
    const toastContent = toast?.querySelector(".toast-content");

    if (!toast || !toastIcon || !toastMessage || !toastContent) {
        return;
    }

    const toastTypes = {
        success: {
            title: "Sucesso",
            animation: "animações/gatinho-amor.json",
            background: "#dcfce7",
            color: "#166534",
            border: "#22c55e",
            progress: "#22c55e",
        },
        alert: {
            title: "Alerta",
            animation: "animações/gatinho-aviso.json",
            background: "#fef3c7",
            color: "#92400e",
            border: "#f59e0b",
            progress: "#f59e0b",
        },
        error: {
            title: "Erro",
            animation: "animações/cachorro_agua_erro.json",
            background: "#fee2e2",
            color: "#991b1b",
            border: "#ef4444",
            progress: "#ef4444",
        },
    };

    const config = toastTypes[type];

    let toastTitle = document.getElementById("toast-title");

    if (!toastTitle) {
        toastTitle = document.createElement("p");
        toastTitle.id = "toast-title";
        toastTitle.style.margin = "0 0 0.2rem";
        toastTitle.style.fontWeight = "700";
        toastTitle.style.fontSize = "1rem";
        toastContent.insertBefore(toastTitle, toastMessage);
    }

    toastTitle.textContent = config.title;
    toastMessage.textContent = message;

    toastIcon.innerHTML = `
        <lottie-player
            src="${config.animation}"
            background="transparent"
            speed="1"
            style="width: 100%; height: 100%;"
            loop
            autoplay>
        </lottie-player>
    `;

    toast.className = `toast toast--${type}`;
    toast.setAttribute("role", type === "error" ? "alert" : "status");
    toast.setAttribute("aria-live", type === "error" ? "assertive" : "polite");
    toast.setAttribute("aria-atomic", "true");

    // As cores são aplicadas aqui para não depender das classes antigas
    // warning/danger, que utilizavam o mesmo visual vermelho.
    toast.style.backgroundColor = config.background;
    toast.style.color = config.color;
    toast.style.border = `1px solid ${config.border}`;
    toast.style.display = "flex";

    if (progressBar) {
        progressBar.style.backgroundColor = config.progress;
        progressBar.style.animation = "none";
        void progressBar.offsetWidth;
    }

    toast.classList.remove("hide");
    toast.classList.add("show");

    if (progressBar) {
        progressBar.style.animation = `shrink ${duration}ms linear forwards`;
    }

    window.setTimeout(() => {
        toast.classList.remove("show");
        toast.classList.add("hide");

        window.setTimeout(() => {
            toast.style.display = "none";
        }, 500);
    }, duration);
};
