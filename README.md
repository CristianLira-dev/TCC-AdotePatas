# 🐶🐱 AdotePatas

<img width="1919" height="909" alt="image" src="https://github.com/user-attachments/assets/26777266-3f23-481c-bdb7-68148a948a06" />
> *Conectando corações a novos lares: Uma plataforma completa para adoção responsável.*

## 💻 Sobre o Projeto

O **AdotePatas** é uma solução Full-Stack robusta desenvolvida para facilitar o encontro entre ONGs/protetores e pessoas interessadas em adotar. 

Diferente de catálogos simples, este sistema gerencia **todo o fluxo de adoção**: desde o cadastro e geolocalização do animal, passando pela comunicação via chat, até a moderação de segurança realizada por administradores.

🔗 **Acesse o projeto online:** [https://adotepatas.page.gd](https://adotepatas.page.gd)

---

## ✨ Funcionalidades Principais

### 👤 Área do Usuário (Doador e Adotante)
- [x] **Cadastro Multi-perfil:** Registro diferenciado para **Pessoas Físicas** e **ONGs**.
- [x] **Gestão de Perfil:** Edição de dados pessoais e personalização de banner de perfil.
- [x] **Interatividade:** Sistema de "Curtir" (Favoritos) e envio de formulário de interesse.
- [x] **Chat Integrado:** Comunicação direta em tempo real entre interessado e doador.
- [x] **Segurança:** Recuperação de senha ("Esqueci minha senha").

### 🐾 Gestão de Pets
- [x] **CRUD Completo:** Cadastro, Edição e Visualização de animais.
- [x] **Geolocalização:** Integração com **Google Maps API** para visualizar pets próximos.
- [x] **Busca Avançada:** Filtros por nome, espécie, porte e localização.

### 🛡️ Painel Administrativo (Back-office)
- [x] **Dashboard Geral:** Visão macro de usuários, ONGs, pets e adoções em andamento.
- [x] **Sistema de Moderação:** Todo pet cadastrado entra em "Análise". O admin aprova ou reprova para impedir conteúdo ilícito.
- [x] **Controle de Usuários:** Visualização e gestão da base de cadastros.

---

## 🛠️ Tecnologias Utilizadas

O projeto foi construído com foco em **Performance** e **Regras de Negócio**:

- **Front-End:** HTML5, CSS3, JavaScript (ES6+).
- **Back-End:** PHP 8.3 com núcleo modular de segurança, serviços e páginas estruturadas.
- **Banco de Dados:** MySQL (Relacional).
- **APIs Externas:** Google Maps Platform (Maps JavaScript API).

---

## 📸 Galeria do Projeto

| Tela Perfil | Tela dos Pets |
| :---: | :---: |
| <img width="1919" height="905" alt="image" src="https://github.com/user-attachments/assets/d4ee4248-713e-4e61-9020-00069d10dc4d" /> | <img width="1919" height="912" alt="image" src="https://github.com/user-attachments/assets/3bedfc75-62c2-4981-8ebf-76f54d51adab" /> |

| Chat | Painel Admin |
| :---: | :---: |
| <img width="1919" height="912" alt="image" src="https://github.com/user-attachments/assets/febb3b7f-16c5-4d7a-92ff-a07dda58c102" /> | <img width="1919" height="913" alt="image" src="https://github.com/user-attachments/assets/243f613c-f771-4c39-99ac-ccd9ab91cce5" /> |

---

## 💡 Desafios e Aprendizados

O maior desafio técnico deste projeto foi a implementação do **Sistema de Moderação e Chat**.
- Para o chat, precisei estruturar o banco de dados para armazenar as mensagens de forma relacional entre dois usuários.
- A integração com o **Google Maps** exigiu manipulação de coordenadas e renderização de pinos dinâmicos baseados no banco de dados MySQL.

---

## 🔐 Segurança e privacidade

- Sessões com cookies `HttpOnly`, `Secure` em HTTPS e `SameSite=Lax`.
- CSRF obrigatório em operações de escrita, inclusive requisições `fetch`.
- Senhas armazenadas com `password_hash` e tokens de recuperação armazenados como hash.
- Uploads verificados por MIME real, tamanho e assinatura de imagem.
- Exclusões executadas por `POST`, com autorização e trilha de auditoria.
- Exportação de dados e solicitação de exclusão da conta.
- Endereço do responsável exibido somente de forma aproximada.

## 🚀 Configuração e atualização

1. Copie `AdotePatas/.env.example` para `AdotePatas/.env` e preencha banco, SMTP, URL e destinatário de contato.
2. Execute `composer install` dentro de `AdotePatas/`.
3. Faça backup do banco e aplique, em ordem, os arquivos de `AdotePatas/database/migrations/`.
4. Execute `php AdotePatas/scripts/migrate-admin-passwords.php` uma única vez para migrar instalações antigas.
5. Revogue qualquer senha SMTP que tenha aparecido em commits anteriores e gere uma nova credencial.

O deploy executa validação do Composer, auditoria de dependências, lint PHP e testes antes da sincronização por FTP.

## 🧪 Testes

```bash
php AdotePatas/tests/security_test.php
```

O ambiente de produção deve manter `.env`, uploads, logs, testes e migrações fora da sincronização pública.

Consulte [IMPLEMENTACAO-MELHORIAS.md](IMPLEMENTACAO-MELHORIAS.md) para o inventário da entrega, ordem de implantação e critérios de aceite.
