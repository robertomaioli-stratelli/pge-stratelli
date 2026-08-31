# INPACTA v4.20.14.2 — Senha pelo administrador

## Objetivo
Permitir que um Administrador da plataforma Stratelli defina uma nova senha diretamente na tela de edição do usuário.

## Alterações
- Nova seção **Definir nova senha** em `admin/usuario.php`.
- Campo de nova senha e confirmação, mínimo de 10 caracteres.
- Validação no navegador e novamente no backend.
- Senha armazenada somente como hash com Argon2id quando disponível, ou `PASSWORD_DEFAULT`.
- `auth_version` é incrementada para invalidar sessões anteriores.
- `senha_alterada_em` é atualizada.
- A operação exige usuário autenticado como Administrador da plataforma.
- Usuário inativo não pode receber nova senha por este mecanismo.
- A senha atual nunca é exibida nem registrada em auditoria.
- Registro de auditoria `SENHA_ALTERADA_ADMIN`.
- Não há alteração de banco nesta versão.

## Rota
`POST /admin/usuarios/{id}/senha`

## Segurança
A senha nunca é armazenada em texto puro. A sessão do usuário-alvo é invalidada por incremento de `auth_version`.
