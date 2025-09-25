
document.addEventListener('DOMContentLoaded', ()=> {
  const navbar = document.querySelector('.navbar');

  // Função para adicionar/remover a classe com base na posição do scroll
  const handleScroll = () => {
    if (window.scrollY > 50) { // Adiciona a classe após rolar 50 pixels
      navbar.classList.add('navbar-scrolled');
    } else {
      navbar.classList.remove('navbar-scrolled');
    }
  };

  // Adiciona o listener de evento de scroll
  window.addEventListener('scroll', handleScroll);
});