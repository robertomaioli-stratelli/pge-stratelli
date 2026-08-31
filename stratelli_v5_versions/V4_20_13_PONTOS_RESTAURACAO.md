# INPACTA v4.20.13 — Pontos de Restauração da Instância

## Objetivo
Permitir que a Stratelli salve o estado de um município antes de alterações importantes, exporte esse estado em um pacote ZIP e restaure a instância posteriormente, caso necessário.

## Onde fica
`Configurações da Instância > Importação e Backup > Pontos de restauração`

## Recursos
- Criar ponto de restauração manual com nome livre.
- Exportar qualquer ponto em pacote `.zip` com manifesto JSON, dados da instância e cópias dos arquivos vinculados.
- Importar novamente um pacote exportado da mesma instância.
- Restaurar um ponto salvo/importado.
- Remover pontos que não são mais necessários.
- Ver resumo do ponto: fases, Secretarias, usuários, documentos e arquivos.
- Ver quantidade/data das restaurações realizadas.
- Criação automática de um ponto de segurança antes de toda restauração.
- Criação automática de um ponto de segurança antes da confirmação de uma importação estrutural.
- Verificação SHA-256 do pacote antes de download/restauração.
- Auditoria das operações de criar, exportar, importar, restaurar e remover.

## Escopo do pacote
O pacote arquiva cadastro municipal, parâmetros, Secretarias, Departamentos, usuários municipais, fases, vínculos, tipos/requisitos documentais, modelos, documentos, históricos, cronograma, notificações, Inteligência Territorial, histórico de importações e auditoria, além das cópias dos arquivos vinculados disponíveis em `storage/uploads`.

## Política de restauração
A restauração devolve o estado configurável e operacional da instância ao ponto escolhido, mas respeita as regras de imutabilidade já existentes na plataforma:

- auditoria nunca é apagada ou reescrita;
- histórico documental nunca é apagado;
- documentos auditados nunca são excluídos fisicamente;
- histórico formal de fases nunca é apagado ou alterado;
- senhas e `auth_version` de usuários já existentes não são retrocedidos para evitar reativação de credenciais/sessões antigas;
- modelos têm o estado ativo restaurado sem apagar versões posteriores;
- registros criados depois do snapshot que possuam campo `ativo` são desativados quando não pertencem ao ponto restaurado;
- fases formais posteriores ao snapshot são mantidas na trilha histórica e o estado operacional correspondente é reaberto quando necessário.

Essa política permite rollback operacional sem destruir evidências ou trilhas de conformidade.

## Segurança adicional
Antes de aplicar um ponto, o INPACTA cria automaticamente outro ponto chamado `Segurança automática antes de restaurar #ID`. Se for necessário desfazer a própria restauração, esse ponto pode ser usado.

Pacotes importados só são aceitos se pertencerem ao mesmo `municipio_id` e `slug` da instância aberta.

## Banco de dados
Executar:

```bat
C:\xampp\php\php.exe bin\upgrade_v4_20_13.php
C:\xampp\php\php.exe bin\check.php
```

Nova tabela: `pontos_restauracao_instancia`.

## Armazenamento
Os pacotes internos são guardados fora da pasta pública em:

`storage/backups/municipio_<ID>/`

## Requisito PHP
A extensão PHP `zip`/`ZipArchive` deve estar habilitada. O XAMPP utilizado pelo projeto já é compatível com essa extensão quando habilitada no `php.ini`.
