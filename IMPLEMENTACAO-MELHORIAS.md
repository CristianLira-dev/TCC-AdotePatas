# Implementação das melhorias — 28/09/2026

## Escopo entregue

| Área | Implementação |
| --- | --- |
| Sessão e autenticação | Cookies endurecidos, expiração por inatividade, regeneração de sessão no login, rate limit e migração de senhas administrativas legadas. |
| CSRF e métodos HTTP | Token global em formulários e `fetch`; ações de escrita convertidas para `POST`; exclusões administrativas não usam mais links `GET`. |
| Senhas e recuperação | Tokens aleatórios armazenados como SHA-256, resposta resistente à enumeração e compatibilidade temporária com links antigos. |
| E-mail | Serviço SMTP centralizado; segredos somente por ambiente; formulário de ajuda com validação, honeypot e rate limit. |
| Uploads | MIME real, tamanho, assinatura de imagem, nomes aleatórios e bloqueio de execução/acesso direto a anexos privados. |
| Chat | Anexos servidos por endpoint autorizado, confirmação de leitura, polling protegido e etapas da solicitação de adoção. |
| Moderação | Aprovação/recusa de pets auditada e fluxo de verificação de ONGs no painel administrativo. |
| Adoção | Estados `pendente`, `em_conversa`, `entrevista`, `aprovado`, `recusado`, `concluido` e `cancelado`; conclusão marca o pet como adotado. |
| Notificações | Central de notificações persistentes para moderação e mudanças no processo de adoção. |
| Privacidade/LGPD | Exportação de dados, solicitação de exclusão e fila administrativa para concluir ou rejeitar o pedido. |
| Banco | Migração versionada, índices de catálogo/chat/status, unicidade de favoritos e solicitações, auditoria e notificações. |
| Desempenho | Paginação do catálogo, limite de resultados filtrados e índices para consultas frequentes. |
| XSS e erros | Renderização segura de mensagens, alertas e campos dinâmicos; detalhes internos ficam apenas no log. |
| Repositório e deploy | `vendor`, credenciais e uploads fora do Git; CI com lint, testes, auditoria Composer e deploy concorrente protegido. |

## Ordem de implantação

1. Fazer backup do banco e dos diretórios de upload.
2. Rotacionar a senha SMTP que já tenha sido exposta no histórico e configurar um novo `AdotePatas/.env` a partir de `.env.example`.
3. Validar e resolver as duplicidades descritas em `AdotePatas/database/README.md`.
4. Aplicar `AdotePatas/database/migrations/20260928_security_and_workflow.sql` uma única vez, primeiro em homologação.
5. Executar `php AdotePatas/scripts/migrate-admin-passwords.php` no ambiente autorizado.
6. Executar `composer install --no-dev --prefer-dist --optimize-autoloader` em `AdotePatas/`.
7. Validar login dos três perfis, recuperação de senha, cadastro/moderação de pet, filtros, anexos, adoção, notificações e privacidade.
8. Publicar e observar logs HTTP/PHP e falhas de e-mail durante a primeira hora.

## Comandos de validação

```bash
find AdotePatas -path 'AdotePatas/vendor' -prune -o -path 'AdotePatas/fpdf' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
php AdotePatas/tests/security_test.php
cd AdotePatas && composer validate --strict --no-check-publish
cd AdotePatas && composer audit --locked --no-interaction
```

## Critérios mínimos de aceite

- Uma requisição de escrita sem CSRF retorna `419`.
- Login excessivo e recuperação excessiva são limitados sem revelar se o e-mail existe.
- Um usuário não consegue abrir anexos de conversa da qual não participa.
- Upload renomeado ou com MIME incompatível é rejeitado.
- Usuário comum não consegue moderar pet, verificar ONG ou alterar adoção alheia.
- A conclusão de uma adoção altera o pet para adotado e gera notificações.
- Exportação não contém hash de senha.
- Exclusão de conta somente ocorre após confirmação de senha e revisão administrativa.
- O deploy falha antes da publicação se lint, testes ou auditoria de dependências falharem.

## Operações externas obrigatórias

- Rotacionar credenciais antigas no provedor SMTP; remover do código não invalida o segredo histórico.
- Aplicar a migração no banco de homologação/produção; o aplicativo não altera mais schema durante requisições.
- Configurar retenção, backup e acesso aos logs conforme a política real da organização.
