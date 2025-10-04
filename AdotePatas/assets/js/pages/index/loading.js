// assets/js/pages/index/loading.js

window.onload = function () {
  const loadingScreen = document.getElementById("loading");

  if (loadingScreen) {
    // Define um tempo mínimo de exibição de 2 segundos APÓS a página carregar
    setTimeout(() => {
      // Adiciona a classe que inicia a transição de fade-out
      loadingScreen.classList.add("hidden");
    }, 3000); // 2000 milissegundos = 2 segundos. Altere este valor conforme necessário.
  }
};
