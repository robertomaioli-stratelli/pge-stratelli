# Segurança e isolamento multi-tenant

## Princípios desta versão

1. **O município nunca é escolhido livremente pelo usuário cliente.** O slug da rota é validado contra `usuarios.municipio_id` pelo middleware `Tenant`.
2. **O administrador Stratelli é explicitamente global** (`administrador_plataforma=1` e `municipio_id=NULL`).
3. **Todas as tabelas operacionais têm `municipio_id`.** As relações críticas usam chaves estrangeiras compostas `(id, municipio_id)` para impedir vínculos entre dados de clientes diferentes.
4. **Usuário comum fica vinculado a uma secretaria.** Gestores veem o escopo municipal. O administrador da plataforma pode atravessar tenants de forma controlada.
5. **Nenhuma página protegida é executada fora do Router.** O DocumentRoot deve ser `public/`.
6. **Arquivos enviados ficam fora de `public/`.** O download futuro deve ser servido por controller após autorização.
7. **CSRF em alterações de estado.** Login e logout também usam token.
8. **Auditoria separada do histórico documental.** Login, logout e operações administrativas devem ir para `auditoria`.
9. **Último gestor.** Não se deve desativar/remover/rebaixar o último gestor ativo de uma instância.
10. **Produção:** habilitar HTTPS e `SESSION_SECURE=true`.

## Não confiar em

- parâmetros `perfil`, `municipio`, `secretaria` recebidos da URL;
- IDs de formulário sem revalidar tenant no banco;
- itens ocultados no menu como mecanismo de autorização;
- caminhos físicos de arquivos fornecidos pelo navegador.

## Isolamento territorial — v4.0

As tabelas territoriais possuem `municipio_id` obrigatório e relacionamentos compostos sempre que o recurso relacionado também pertence a uma instância municipal. O cadastro e a alteração de camadas/objetos são restritos ao Administrador Stratelli nesta versão.

Gestores municipais possuem leitura integral do território do seu município. Usuários comuns têm a consulta filtrada pela Secretaria vinculada à conta e, quando informado, pelo Departamento. O acesso territorial não utiliza IDs fornecidos pelo navegador sem validação do `municipio_id` resolvido pelo middleware de tenant.


## Hardening de entrada e XAMPP — v4.2

- A raiz do projeto possui `.htaccess` próprio com `Options -Indexes` e bloqueio de diretórios/arquivos internos.
- Acesso acidental por `localhost/stratelli/plataforma_etapa1` é redirecionado para a URL canônica `stratelli.local/login` no ambiente XAMPP configurado.
- `APP_ENFORCE_HOST=true` impede que o Front Controller seja usado por um hostname alternativo ao `APP_URL`.
- A pasta `public/` reforça `-Indexes`, `-MultiViews`, bloqueio de backups e cabeçalhos defensivos.
- Sessões usam `session.use_strict_mode=1`, `session.use_only_cookies=1`, cookie `HttpOnly` e `SameSite=Lax`.
- Backups ZIP/SQL não devem permanecer dentro de `htdocs`, ainda que existam regras de bloqueio no Apache.

## v4.18 — Auditoria e segurança de produção

- auditoria técnica imutável com IP, User-Agent, rota, método, sessão e contexto;
- registro e limitação de tentativas de login;
- sessão com tempo ocioso e duração absoluta configuráveis;
- invalidação de sessões após mudança de senha/permissão/status;
- recuperação de senha com token SHA-256, expiração e uso único;
- alterações de permissão com snapshot anterior/depois;
- downloads e exportações sensíveis auditados.
