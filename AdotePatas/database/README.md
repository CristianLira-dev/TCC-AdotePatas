# Banco de dados

1. Faça um backup completo do banco.
2. Confirme que a migração ainda não aparece na tabela de controle do seu ambiente; cada arquivo é executado uma única vez.
3. Antes de criar os índices únicos, confira possíveis duplicidades com:

   `SELECT id_usuario, id_pet, COUNT(*) total FROM favorito GROUP BY id_usuario, id_pet HAVING total > 1;`

   `SELECT id_usuario, id_pet, COUNT(*) total FROM solicitacao GROUP BY id_usuario, id_pet HAVING total > 1;`

4. Execute as migrações de `migrations/` em ordem de data, primeiro em homologação.
5. Após aplicar a migração de segurança, execute no servidor:

   `php scripts/migrate-admin-passwords.php`

6. Verifique login, cadastro, recuperação de senha, adoção, chat, moderação e anexos.

As rotas da aplicação não alteram mais o schema durante requisições. Mudanças de estrutura devem ser feitas somente por migração versionada.
