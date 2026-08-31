# INPACTA v4.20.14 — Encerramento e Arquivamento da Etapa 1

## Objetivo
Formalizar o encerramento da única Etapa existente no MVP atual sem criar as Etapas futuras.

Quando todas as fases ativas estiverem formalmente encerradas, a Stratelli pode executar **Encerrar e arquivar Etapa 1**.

## Pacote de encerramento
O sistema gera um `.zip` imutável contendo:

- fases e cronograma;
- requisitos/documentos;
- todas as versões documentais;
- SHA-256 dos arquivos e versões;
- responsáveis e usuários envolvidos;
- aprovações e decisões de validação;
- encerramentos formais das fases;
- histórico documental;
- histórico de fases;
- auditoria da instância;
- arquivos físicos vinculados.

O pacote possui `manifest.json`, `snapshot.json`, arquivos específicos de responsáveis/aprovações/encerramentos/histórico e um `README.txt`.

## Integridade
Cada arquivo físico é validado e incluído no manifesto com SHA-256 e tamanho. Se um arquivo vinculado estiver ausente, o arquivamento é bloqueado.

O pacote final também recebe SHA-256 registrado na tabela `arquivamentos_etapa`. A exportação verifica novamente o hash antes de entregar o arquivo.

## Pós-arquivamento
A Etapa 1 passa para **ENCERRADA E ARQUIVADA** e a instância fica em modo somente leitura para alterações operacionais/configuratórias.

Ficam preservadas para consulta:

- auditoria;
- histórico documental;
- histórico formal das fases;
- versões e hashes;
- pacote de encerramento.

Tentativas de alteração após o arquivamento são bloqueadas no serviço e não apenas na interface.

## PDF
A geração de **Relatório de Encerramento em PDF** fica preparada conceitualmente para uma próxima versão. O pacote ZIP é a fonte documental oficial desta versão.

## Banco de dados
Nova tabela: `arquivamentos_etapa`.

A partir da v4.20.14.1, a tabela é preparada automaticamente pelo próprio aplicativo ao acessar o Workflow. Não é necessário executar PHP-CLI manualmente.

O script `bin/upgrade_v4_20_14.php` permanece disponível como alternativa para instalações com PHP-CLI funcional.

## Armazenamento
Os pacotes são guardados fora da pasta pública em:

`storage/archives/municipio_<ID>/`
