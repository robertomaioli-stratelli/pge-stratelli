<?php
namespace App\Services;

use App\Core\Database;
use PDO;

final class MvpSeedService
{
    private PDO $pdo;
    public function __construct(){ $this->pdo=Database::connection(); }

    public function seedMaringa(): void
    {
        $st=$this->pdo->prepare("SELECT id FROM municipios WHERE slug='maringa'");$st->execute();$mid=(int)$st->fetchColumn();if(!$mid)return;
        $secretarias=[
            ['Stratelli','STRATELLI'],['Secretaria de Segurança Urbana','SESEG'],['Secretaria de Administração e Licitações','SEADM'],
            ['Procuradoria-Geral do Município','PGM'],['Gabinete do Prefeito','GAB'],['Secretaria de Fazenda','SEFAZ'],
            ['Secretaria de Infraestrutura','SEINFRA'],['Secretaria de Mobilidade Urbana','SEMOB'],
        ];
        $sec=[];foreach($secretarias as[$n,$s]){$st=$this->pdo->prepare('SELECT id FROM secretarias WHERE municipio_id=? AND nome=?');$st->execute([$mid,$n]);$id=(int)($st->fetchColumn()?:0);if(!$id){$this->pdo->prepare('INSERT INTO secretarias(municipio_id,nome,sigla,ativo,criado_em,atualizado_em) VALUES(?,?,?,1,NOW(),NOW())')->execute([$mid,$n,$s]);$id=(int)$this->pdo->lastInsertId();}$sec[$n]=$id;}

        $types=[
            ['Comprovante interno','Evidência de atividade executada pela Stratelli','pdf,doc,docx,jpg,jpeg,png,webp,zip'],
            ['Documento administrativo','Documento formal administrativo','pdf,doc,docx,odt,rtf'],
            ['Estudo técnico','Estudos, matrizes e peças técnicas','pdf,doc,docx,xls,xlsx,odt,ods'],
            ['Formulário de Estrutura Técnica','Formulário técnico compartilhado entre secretarias','pdf,doc,docx,xls,xlsx'],
            ['Formulário de Processos','Formulário de levantamento de processos','pdf,doc,docx,xls,xlsx'],
            ['Pesquisa e orçamento','Orçamentos, mapas comparativos e pesquisas','pdf,doc,docx,xls,xlsx'],
            ['Documento jurídico','Pareceres, despachos e documentos jurídicos','pdf,doc,docx,odt,rtf'],
            ['Contrato e publicação','Editais, contratos, publicações e ordens de serviço','pdf,doc,docx'],
        ];
        $type=[];foreach($types as[$n,$d,$e]){$st=$this->pdo->prepare('SELECT id FROM tipos_documento WHERE municipio_id=? AND nome=?');$st->execute([$mid,$n]);$id=(int)($st->fetchColumn()?:0);if(!$id){$this->pdo->prepare('INSERT INTO tipos_documento(municipio_id,nome,descricao,extensoes,ativo,criado_em,atualizado_em) VALUES(?,?,?,?,1,NOW(),NOW())')->execute([$mid,$n,$d,$e]);$id=(int)$this->pdo->lastInsertId();}$type[$n]=$id;}

        $phases=[
            [0,'INICIAL-01','Apresentação','Apresentação e Negociação','Reunião de apresentação das soluções e definição da estratégia de contratação.','Stratelli',1,1,'Apresentação concluída e município liberado','Checklist interno concluído',1],
            [1,'INICIAL-02','Solicitação','Solicitação','Sinalização formal de interesse e definição da estratégia de contratação, direta ou licitatória.','Secretaria de Segurança Urbana / Prefeito',1,9,'Sinalização da prefeitura pelo prosseguimento','Aprovação do prefeito e equipe',0],
            [2,'INICIAL-03','ETP','Estudo Técnico Preliminar','Consolidação das necessidades e preparação das peças técnicas de contratação.','Setor de licitações municipal',10,24,'ETP e Matriz de Risco','Peças técnicas prontas',0],
            [3,'INICIAL-04','Pesquisa','Pesquisa de Mercado','Coleta de orçamentos e verificação de expertise técnica das empresas candidatas.','Setor de licitações municipal',10,19,'Pesquisa de preços consolidada','Pesquisa pronta',0],
            [4,'INICIAL-05','Termo de Referência','Termo de Referência','Redação do Termo de Referência com base no cronograma técnico escolhido.','Setor de licitações municipal',16,25,'Termo de Referência consolidado','TR pronto',0],
            [5,'INICIAL-06','Parecer Jurídico','Parecer Jurídico','Análise jurídica e bloqueio de dotação orçamentária para o projeto.','Setor Jurídico Municipal',26,40,'Parecer jurídico para prosseguimento','Parecer e reserva aprovados',0],
            [6,'INICIAL-07','Seleção / Contratação','Seleção ou Contratação Direta','Publicação de edital ou formalização de dispensa/inexigibilidade.','Setor de licitações municipal',41,85,'Publicação do certame e seleção','Publicação ou rito formalizado',0],
            [7,'INICIAL-08','Contrato e OS','Contrato e Ordem de Serviço','Assinatura do contrato e emissão da Ordem de Serviço para início técnico.','Prefeito / Secretaria / Licitações',86,90,'Contrato assinado e recurso empenhado','Contrato assinado, empenho e OS emitidos',0],
        ];
        $phase=[];foreach($phases as[$o,$c,$a,$t,$d,$r,$di,$df,$e,$cr,$ex]){$st=$this->pdo->prepare('SELECT id FROM fases WHERE municipio_id=? AND ordem=?');$st->execute([$mid,$o]);$id=(int)($st->fetchColumn()?:0);if(!$id){$this->pdo->prepare('INSERT INTO fases(municipio_id,ordem,codigo,aba,titulo,descricao,responsavel,dia_inicio,dia_fim,entregavel,criterio,exclusivo_stratelli,ativo,criado_em,atualizado_em) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())')->execute([$mid,$o,$c,$a,$t,$d,$r,$di,$df,$e,$cr,$ex]);$id=(int)$this->pdo->lastInsertId();}$phase[$o]=$id;}

        $docs=[
            [0,'Stratelli','Comprovante interno','Reunião de apresentação realizada','STRATELLI',''],
            [0,'Stratelli','Comprovante interno','Interesse formalizado pelo município','STRATELLI',''],
            [0,'Stratelli','Comprovante interno','Projeto aprovado internamente','STRATELLI',''],
            [0,'Stratelli','Comprovante interno','Definição da estratégia de contratação','STRATELLI',''],
            [1,'Secretaria de Segurança Urbana','Documento administrativo','Manifestação formal de interesse','MUNICIPIO','Manifestação formal da secretaria responsável.'],
            [1,'Gabinete do Prefeito','Documento administrativo','Ata ou despacho de aprovação','MUNICIPIO','Aprovação do prefeito e equipe.'],
            [1,'Secretaria de Segurança Urbana','Documento administrativo','Definição da modalidade de contratação','MUNICIPIO','Definição da estratégia de contratação.'],
            [2,'Secretaria de Administração e Licitações','Estudo técnico','ETP','MUNICIPIO','Estudo Técnico Preliminar.'],
            [2,'Secretaria de Administração e Licitações','Estudo técnico','Matriz de Risco','MUNICIPIO','Matriz de riscos da contratação.'],
            [2,'Secretaria de Administração e Licitações','Estudo técnico','Necessidades consolidadas','MUNICIPIO','Consolidação das necessidades das secretarias envolvidas.'],
            [2,'Secretaria de Mobilidade Urbana','Formulário de Estrutura Técnica','Formulário de Estrutura Técnica','MUNICIPIO','Preencher o formulário com os equipamentos e estruturas relacionadas ao projeto.'],
            [2,'Secretaria de Infraestrutura','Formulário de Estrutura Técnica','Formulário de Estrutura Técnica','MUNICIPIO','Preencher o formulário com os equipamentos relacionados aos aspectos/focos do projeto.'],
            [2,'Secretaria de Infraestrutura','Formulário de Processos','Formulário de Processos','MUNICIPIO','Preencher o formulário com todos os processos relacionados aos aspectos/focos do projeto.'],
            [3,'Secretaria de Administração e Licitações','Pesquisa e orçamento','Orçamentos coletados','MUNICIPIO',''],
            [3,'Secretaria de Administração e Licitações','Pesquisa e orçamento','Mapa comparativo','MUNICIPIO',''],
            [3,'Secretaria de Administração e Licitações','Pesquisa e orçamento','Comprovação de expertise técnica','MUNICIPIO',''],
            [4,'Secretaria de Administração e Licitações','Estudo técnico','Termo de Referência','MUNICIPIO',''],
            [4,'Secretaria de Administração e Licitações','Estudo técnico','Objeto detalhado','MUNICIPIO',''],
            [4,'Secretaria de Administração e Licitações','Estudo técnico','Critérios de execução e aceite','MUNICIPIO',''],
            [5,'Procuradoria-Geral do Município','Documento jurídico','Parecer jurídico','MUNICIPIO',''],
            [5,'Secretaria de Fazenda','Documento administrativo','Reserva orçamentária','MUNICIPIO',''],
            [5,'Procuradoria-Geral do Município','Documento jurídico','Despacho de prosseguimento','MUNICIPIO',''],
            [6,'Secretaria de Administração e Licitações','Contrato e publicação','Edital ou contratação direta','MUNICIPIO',''],
            [6,'Secretaria de Administração e Licitações','Contrato e publicação','Publicação oficial','MUNICIPIO',''],
            [6,'Secretaria de Administração e Licitações','Contrato e publicação','Resultado da seleção','MUNICIPIO',''],
            [7,'Gabinete do Prefeito','Contrato e publicação','Contrato assinado','MUNICIPIO',''],
            [7,'Secretaria de Fazenda','Documento administrativo','Empenho','MUNICIPIO',''],
            [7,'Secretaria de Administração e Licitações','Contrato e publicação','Ordem de Serviço','MUNICIPIO',''],
        ];
        $order=[];
        foreach($docs as[$o,$sn,$tn,$name,$profile,$desc]){
            $fid=$phase[$o];$sid=$sec[$sn];$tid=$type[$tn];$this->pdo->prepare('INSERT IGNORE INTO fase_secretarias(municipio_id,fase_id,secretaria_id) VALUES(?,?,?)')->execute([$mid,$fid,$sid]);
            $st=$this->pdo->prepare('SELECT id FROM requisitos_documentais WHERE municipio_id=? AND fase_id=? AND secretaria_id=? AND nome=? LIMIT 1');$st->execute([$mid,$fid,$sid,$name]);if($st->fetchColumn())continue;$order[$o]=($order[$o]??0)+1;$this->pdo->prepare('INSERT INTO requisitos_documentais(municipio_id,fase_id,secretaria_id,departamento_id,tipo_documento_id,nome,descricao,perfil_envio,obrigatorio,ativo,ordem,criado_em,atualizado_em) VALUES(?,?,?,NULL,?,?,?,?,1,1,?,NOW(),NOW())')->execute([$mid,$fid,$sid,$tid,$name,$desc,$profile,$order[$o]]);
        }
        $this->pdo->prepare('INSERT IGNORE INTO cronograma_processos(municipio_id,data_inicio,criado_em,atualizado_em) VALUES(?,CURDATE(),NOW(),NOW())')->execute([$mid]);
    }
}
