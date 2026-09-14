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

    const metaBase = document.querySelector('meta[name="adotepatas-lottie-base"]')?.content;
    const animationBaseUrl = metaBase
        ? new URL(metaBase, window.location.origin).href
        : new URL("anima%C3%A7%C3%B5es/", document.baseURI).href;

    const toastTypes = {
        success: {
            title: "Sucesso",
            animation: `${animationBaseUrl}gatinho-amor.json`,
            background: "#dcfce7",
            color: "#166534",
            border: "#22c55e",
            progress: "#22c55e",
        },
        alert: {
            title: "Alerta",
            animation: `${animationBaseUrl}gatinho-aviso.json`,
            background: "#fef3c7",
            color: "#92400e",
            border: "#f59e0b",
            progress: "#f59e0b",
        },
        error: {
            title: "Erro",
            animation: `${animationBaseUrl}cachorro_agua_erro.json`,
            creatorFormat: true,
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

    toastIcon.replaceChildren();
    const player = document.createElement("lottie-player");
    player.setAttribute("background", "transparent");
    player.setAttribute("speed", "1");
    player.setAttribute("loop", "");
    player.setAttribute("autoplay", "");
    player.style.width = "100%";
    player.style.height = "100%";
    toastIcon.appendChild(player);

    const loadCreatorAnimation = async () => {
        try {
            const response = await fetch(config.animation, { cache: "force-cache" });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const animationData = await response.json();

            // Arquivos exportados pelo LottieFiles Creator podem vir sem alguns
            // metadados exigidos pelo lottie-player, embora as layers estejam válidas.
            animationData.v ||= "5.12.1";
            animationData.fr ||= 30;
            animationData.ip ??= 0;
            animationData.assets ||= [];

            if (animationData.op == null) {
                const layerEndFrames = Array.isArray(animationData.layers)
                    ? animationData.layers.map((layer) => Number(layer?.op || 0))
                    : [];
                animationData.op = Math.max(1, ...layerEndFrames);
            }

            if (typeof player.load === "function") {
                player.load(animationData);
            } else {
                player.setAttribute("src", config.animation);
            }
        } catch (error) {
            console.warn("Não foi possível normalizar a animação de erro:", error);
            player.setAttribute("src", config.animation);
        }
    };

    const loadAnimation = () => {
        if (config.creatorFormat) {
            loadCreatorAnimation();
            return;
        }

        player.setAttribute("src", config.animation);
    };

    if (window.customElements?.get("lottie-player")) {
        loadAnimation();
    } else if (window.customElements?.whenDefined) {
        window.customElements.whenDefined("lottie-player").then(loadAnimation);
    } else {
        loadAnimation();
    }

    toast.className = `toast toast--${type}`;
    toast.setAttribute("role", type === "error" ? "alert" : "status");
    toast.setAttribute("aria-live", type === "error" ? "assertive" : "polite");
    toast.setAttribute("aria-atomic", "true");

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
