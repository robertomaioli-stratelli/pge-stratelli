# INPACTA v4.16 — Gestão Documental e Integridade

A v4.16 fortalece a cadeia de custódia documental da Etapa 1 sem alterar o fluxo operacional existente.

## O que passa a ser registrado em cada versão

- número sequencial da versão;
- vínculo explícito com a versão anterior;
- nome original e caminho interno do arquivo;
- tamanho em bytes;
- tipo MIME detectado pelo servidor;
- checksum SHA-256 do conteúdo;
- usuário que enviou;
- data e hora do envio;
- observação opcional do envio;
- situação de validação;
- usuário que validou;
- data e hora da validação;
- observação da validação/correção.

## Integridade

No upload, o SHA-256 é calculado depois que o arquivo é gravado no armazenamento. No download, quando existe checksum auditado, o sistema recalcula o hash do arquivo físico antes de enviá-lo. Se o conteúdo não corresponder ao registro, o download é bloqueado e a aplicação informa falha de integridade.

Arquivos existentes são recalculados pelo `bin/upgrade_v4_16.php` quando estão presentes em `storage/uploads`.

## Histórico de versões

A rota `/{municipio}/documentos/{requisito}/auditoria` apresenta uma ficha documental com:

- certificado da versão atual;
- todas as versões anteriores;
- responsáveis pelo envio e validação;
- observações;
- checksum completo;
- estado de integridade física;
- versões de modelo;
- eventos de auditoria.

A ficha pode ser impressa pelo navegador.

## Preservação

A v4.16 altera os vínculos de documentos e modelos para `ON DELETE RESTRICT` e cria triggers que impedem `DELETE` físico em `documentos_enviados` e `historico_documentos`.

Isso significa que um documento auditado não pode desaparecer silenciosamente. Futuramente, caso exista necessidade administrativa de descarte, deverá ser criado um fluxo formal de arquivamento/descarte, com autorização e registro próprio — nunca um `DELETE` simples.

## Atualização

Após substituir os arquivos:

```bat
cd C:\xampp\htdocs\stratelli\plataforma_etapa1
C:\xampp\php\php.exe bin\upgrade_v4_16.php
C:\xampp\php\php.exe bin\check.php
```

Faça backup do banco e da pasta `storage/uploads` antes da atualização.
