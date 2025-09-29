document.addEventListener('DOMContentLoaded', () => {

    // Chave que usaremos para salvar os dados no localStorage
    const CHAVE_STORAGE_PETS_CURTIDOS = 'petsCurtidos';

    // Seleciona todos os ícones de coração
    const botoesCurtir = document.querySelectorAll('.pet-like');

    /**
     * Função para buscar os IDs dos pets curtidos no localStorage.
     * @returns {string[]} Um array com os IDs dos pets.
     */
    const obterPetsCurtidos = () => {
        const petsCurtidos = localStorage.getItem(CHAVE_STORAGE_PETS_CURTIDOS);
        // Se não houver nada, retorna um array vazio. Senão, converte de JSON para array.
        return petsCurtidos ? JSON.parse(petsCurtidos) : [];
    };

    /**
     * Função para salvar o array de pets curtidos no localStorage.
     * @param {string[]} arrayPetsCurtidos - O array de IDs para salvar.
     */
    const salvarPetsCurtidos = (arrayPetsCurtidos) => {
        localStorage.setItem(CHAVE_STORAGE_PETS_CURTIDOS, JSON.stringify(arrayPetsCurtidos));
    };

    /**
     * Função que atualiza a aparência do ícone (preenchido ou não).
     * @param {HTMLElement} icone - O elemento <i> do ícone.
     * @param {boolean} estaCurtido - True se o pet estiver curtido, false caso contrário.
     */
    const atualizarEstadoIcone = (icone, estaCurtido) => {
        icone.classList.toggle('fa-solid', estaCurtido);
        icone.classList.toggle('fa-regular', !estaCurtido);
    };

    // --- LÓGICA PRINCIPAL ---

    // 1. Ao carregar a página, verificar o localStorage e atualizar os corações
    const petsCurtidosInicialmente = obterPetsCurtidos();
    botoesCurtir.forEach(botao => {
        const petId = botao.dataset.petId;
        // Se o ID do pet estiver no nosso array de curtidas, marcamos como preenchido
        if (petsCurtidosInicialmente.includes(petId)) {
            atualizarEstadoIcone(botao, true);
        }
    });

    // 2. Adicionar o evento de clique para cada botão de coração
    botoesCurtir.forEach(botao => {
        botao.addEventListener('click', (evento) => {
            const botaoAtual = evento.currentTarget;
            const petId = botaoAtual.dataset.petId;
            
            // Pega a lista atual de pets curtidos
            let petsCurtidos = obterPetsCurtidos();

            // Verifica se o pet já está na lista
            if (petsCurtidos.includes(petId)) {
                // Se já estiver, remove (descurtir)
                petsCurtidos = petsCurtidos.filter(id => id !== petId);
                atualizarEstadoIcone(botaoAtual, false);
            } else {
                // Se não estiver, adiciona (curtir)
                petsCurtidos.push(petId);
                atualizarEstadoIcone(botaoAtual, true);
            }

            // Salva a nova lista atualizada no localStorage
            salvarPetsCurtidos(petsCurtidos);
        });
    });

});