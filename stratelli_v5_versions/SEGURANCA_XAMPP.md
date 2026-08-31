# Endurecimento local no XAMPP

## Objetivo

A aplicação deve ser acessada somente por `http://stratelli.local`. A pasta física
`C:/xampp/htdocs/stratelli/plataforma_etapa1` não é uma URL da aplicação e não deve listar
arquivos, backups, `.env`, código ou diretórios internos.

## 1. VirtualHosts

Use o conteúdo de `deploy/xampp-vhosts.conf.example` em:

`C:/xampp/apache/conf/extra/httpd-vhosts.conf`

Depois valide:

```bat
C:\xampp\apache\bin\httpd.exe -t
C:\xampp\apache\bin\httpd.exe -S
```

O primeiro comando deve retornar `Syntax OK`; o segundo deve listar `stratelli.local` na porta 80.

## 2. Hosts do Windows

Em `C:/Windows/System32/drivers/etc/hosts`:

```text
127.0.0.1 stratelli.local
```

## 3. .env

No ambiente local:

```env
APP_URL=http://stratelli.local
APP_ENFORCE_HOST=true
SESSION_SECURE=false
APP_DEBUG=true
```

Em produção com HTTPS:

```env
APP_URL=https://seu-dominio
APP_ENFORCE_HOST=true
SESSION_SECURE=true
APP_DEBUG=false
```

## 4. Backups

Não mantenha ZIPs de versões, dumps SQL, `.env` de backup ou arquivos temporários dentro de
`htdocs`. Guarde-os, por exemplo, em `C:/Projetos/Backups/INPACTA/`.

A proteção da raiz bloqueia vários formatos conhecidos, mas a regra de segurança mais forte é
não armazenar backups dentro do diretório servido pelo Apache.

## 5. Testes após reiniciar o XAMPP

- `http://stratelli.local` deve abrir/encaminhar para a aplicação.
- sem login, `http://stratelli.local/maringa/dashboard` deve encaminhar para `/login`.
- `http://localhost/stratelli/plataforma_etapa1/` deve redirecionar para `http://stratelli.local/login`.
- `http://localhost/stratelli/plataforma_etapa1/.env` não pode ser exibido.
- `http://stratelli.local/index.php` não pode ser usado como URL pública.

## 6. Produção

O `DocumentRoot` deve continuar apontando exclusivamente para `/public`. Não exponha a raiz do
projeto. Habilite HTTPS antes de usar dados reais e configure `SESSION_SECURE=true`.
