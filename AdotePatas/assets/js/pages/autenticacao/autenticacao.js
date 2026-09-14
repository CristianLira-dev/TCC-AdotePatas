// assets/js/pages/autenticacao/autenticacao.js

import { initTabs } from './modules/ui/tabs.js';
import { initRecoveryModal } from './modules/ui/modal-recovery.js';
import { initToastNotification } from './modules/ui/toast.js?v=20260914-3';
import { initPasswordToggle, initInputMasks } from './modules/forms/validation.js';
import { initCadastroUsuarioForm } from './modules/forms/form-cadastro-usuario.js';
import { initCadastroOngForm } from './modules/forms/form-cadastro-ong.js';
import { initBuscaCep } from './modules/forms/busca-cep.js';

/**
 * Ponto de entrada principal da aplicação.
 * Aguarda o DOM estar pronto e inicializa todos os módulos.
 */
document.addEventListener("DOMContentLoaded", function () {
    initTabs();
    initPasswordToggle();
    initInputMasks();
    initToastNotification();
    initRecoveryModal();
    initCadastroUsuarioForm();
    initCadastroOngForm();
    initBuscaCep();
});
