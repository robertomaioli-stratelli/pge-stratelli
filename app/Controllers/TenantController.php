<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Core\Tenant;
use App\Core\View;
use App\Services\ConfigurationService;
use App\Services\DocumentService;
use App\Services\HistoryService;
use App\Services\HistoricalIndicatorsService;
use App\Services\PendingService;
use App\Services\NotificationService;
use App\Services\OperationalSlaService;
use App\Services\StructureImportService;
use App\Services\InstanceSnapshotService;
use App\Services\PhaseClosureService;
use App\Services\GeocodingService;
use App\Services\TerritorialService;
use App\Services\WorkflowService;
use App\Services\EtapaArchiveService;
use RuntimeException;

final class TenantController
{
    public function dashboard(string $municipio): void
    {
        $data=(new WorkflowService())->load();
        $data['pendingDesk']=(new PendingService())->tenantFromState($data);
        $data['slaOperacional']=(new OperationalSlaService())->fromWorkflowState($data);
        if($this->territorialEnabledForCurrentUser()){
            $territorial=(new TerritorialService())->load();
            $data['territorialSummary']=$territorial['summary'];
            $data['territorialObjects']=$territorial['objetos'];
            $data['territorialLayers']=$territorial['camadas'];
        }else{
            $data['territorialSummary']=['objetos'=>0,'camadas'=>0,'processos_vinculados'=>0,'atencao'=>0,'criticos'=>0];
            $data['territorialObjects']=[];$data['territorialLayers']=[];
        }
        View::render('tenant/dashboard',$this->base($data,'Dashboard Situacional'));
    }

    public function municipioCadastro(string $municipio): void
    {
        $pdo=Database::connection();
        $mid=(int)Tenant::id();
        $st=$pdo->prepare('SELECT m.*,
            (SELECT COUNT(*) FROM usuarios u WHERE u.municipio_id=m.id AND u.ativo=1) usuarios_ativos,
            (SELECT COUNT(*) FROM usuarios u WHERE u.municipio_id=m.id AND u.grupo="GESTOR" AND u.ativo=1) gestores_ativos,
            (SELECT COUNT(*) FROM secretarias s WHERE s.municipio_id=m.id AND s.ativo=1) secretarias_ativas,
            (SELECT COUNT(*) FROM departamentos d WHERE d.municipio_id=m.id AND d.ativo=1) departamentos_ativos,
            (SELECT COUNT(*) FROM fases f WHERE f.municipio_id=m.id AND f.ativo=1) fases_ativas,
            p.nome_etapa_atual,p.cor_primaria,p.cor_secundaria,p.estilo_decoracao_cabecalho
            FROM municipios m
            LEFT JOIN parametros_instancia p ON p.municipio_id=m.id
            WHERE m.id=? LIMIT 1');
        $st->execute([$mid]);
        $cadastro=$st->fetch();
        if(!$cadastro)Response::abort(404,'Município não encontrado.');
        View::render('tenant/municipio',$this->base(['municipioCadastro'=>$cadastro],'Dados do Município'));
    }

    public function pendencias(string $municipio): void
    {
        $state=(new WorkflowService())->load();
        $data=array_merge($state,['pendingDesk'=>(new PendingService())->tenantFromState($state)]);
        View::render('tenant/pendencias',$this->base($data,($state['scope']??'municipio')==='stratelli'?'Central de Pendências':'Minha Mesa'));
    }

    public function workflow(string $municipio): void { $this->workflowRender(null); }
    public function workflowPhase(string $municipio,string $fase): void { $this->workflowRender((int)$fase); }

    public function archiveEtapa1(string $municipio): void
    {
        $this->csrf();
        try{
            $r=(new EtapaArchiveService())->archive();
            Session::flash('ok','Etapa 1 encerrada e arquivada com sucesso. Pacote de encerramento #'.$r['id'].' gerado com SHA-256 '.$r['checksum'].'.');
        }catch(\Throwable $e){Session::flash('erro',$e->getMessage());}
        Response::redirect('/'.Tenant::current()['slug'].'/workflow');
    }

    public function downloadEtapa1Archive(string $municipio): void
    {
        try{(new EtapaArchiveService())->download();}catch(\Throwable $e){Session::flash('erro',$e->getMessage());Response::redirect('/'.Tenant::current()['slug'].'/workflow');}
    }

    private function workflowRender(?int $faseId): void
    {
        $data=(new WorkflowService())->load($faseId);
        $data['etapaArquivamento']=(new EtapaArchiveService())->status();
        if($faseId && !($data['faseAcesso'][$faseId]['visivel']??false)){
            Session::flash('erro','Esta fase não está disponível para o seu perfil.');
            Response::redirect('/'.Tenant::current()['slug'].'/workflow');
        }
        if($faseId && !($data['faseAcesso'][$faseId]['pode_acessar']??false)){
            Session::flash('erro','Esta fase ainda está bloqueada. A fase anterior precisa estar formalmente encerrada pela Stratelli para liberar o acesso.');
            Response::redirect('/'.Tenant::current()['slug'].'/workflow');
        }
        View::render('tenant/workflow',$this->base($data,'Workflow de Contratação / Fases'));
    }

    public function documentos(string $municipio): void
    {
        $data=(new WorkflowService())->load();
        View::render('tenant/documentos',$this->base($data,'Documentos'));
    }

    public function documentAudit(string $municipio,string $requisito): void
    {
        try{
            $data=(new DocumentService())->auditData((int)$requisito);
            View::render('tenant/documento_auditoria',$this->base($data,'Auditoria Documental'));
        }catch(\Throwable $e){
            Session::flash('erro',$e->getMessage());
            Response::redirect('/'.Tenant::current()['slug'].'/documentos');
        }
    }

    public function territorio(string $municipio): void
    {
        if(!$this->territorialEnabledForCurrentUser()){
            Session::flash('erro','A Inteligência Territorial ainda não foi ativada pela Stratelli para este município.');
            Response::redirect('/'.Tenant::current()['slug'].'/dashboard');
        }
        $data=(new WorkflowService())->load();
        $data=array_merge($data,(new TerritorialService())->load());
        View::render('tenant/territorio',$this->base($data,'Inteligência Territorial'));
    }

    public function territorialSaveLayer(string $municipio): void
    {
        $this->csrf();try{(new TerritorialService())->saveLayer($_POST);Session::flash('ok','Camada territorial salva com sucesso.');}catch(\Throwable $e){Session::flash('erro',$e->getMessage());}
        Response::redirect('/'.Tenant::current()['slug'].'/territorio?modo=configuracao');
    }

    public function territorialToggleLayer(string $municipio,string $id): void
    {
        $this->csrf();try{(new TerritorialService())->toggleLayer((int)$id);Session::flash('ok','Status da camada territorial alterado.');}catch(\Throwable $e){Session::flash('erro',$e->getMessage());}
        Response::redirect('/'.Tenant::current()['slug'].'/territorio?modo=configuracao');
    }

    public function territorialGeocode(string $municipio): void
    {
        if(!Csrf::validate($_POST['_token']??null)) Response::json(['ok'=>false,'error'=>'Sessão expirada. Atualize a página e tente novamente.'],419);
        try{
            $results=(new GeocodingService())->search((string)($_POST['endereco']??''));
            Response::json(['ok'=>true,'results'=>$results,'municipio'=>Tenant::current()['nome']??'']);
        }catch(\Throwable $e){
            Response::json(['ok'=>false,'error'=>$e->getMessage()],422);
        }
    }

    public function territorialSaveObject(string $municipio): void
    {
        $this->csrf();try{(new TerritorialService())->saveObject($_POST);Session::flash('ok','Objeto territorial salvo com sucesso.');}catch(\Throwable $e){Session::flash('erro',$e->getMessage());}
        Response::redirect('/'.Tenant::current()['slug'].'/territorio?modo=configuracao');
    }

    public function territorialToggleObject(string $municipio,string $id): void
    {
        $this->csrf();try{(new TerritorialService())->toggleObject((int)$id);Session::flash('ok','Status do objeto territorial alterado.');}catch(\Throwable $e){Session::flash('erro',$e->getMessage());}
        Response::redirect('/'.Tenant::current()['slug'].'/territorio?modo=configuracao');
    }

    public function territorialImport(string $municipio): void
    {
        $this->csrf();try{$count=(new TerritorialService())->importGeoJson($_POST,$_FILES['arquivo_geojson']??[]);Session::flash('ok',$count.' objeto(s) territorial(is) importado(s).');}catch(\Throwable $e){Session::flash('erro',$e->getMessage());}
        Response::redirect('/'.Tenant::current()['slug'].'/territorio?modo=configuracao');
    }

    public function relatorios(string $municipio): void
    {
        $data=(new WorkflowService())->load();
        $data['pendingDesk']=(new PendingService())->tenantFromState($data);
        $data['slaOperacional']=(new OperationalSlaService())->fromWorkflowState($data);
        if($this->territorialEnabledForCurrentUser()){
            $territorial=(new TerritorialService())->load();
            $data['territorialSummary']=$territorial['summary'];
            $data['territorialObjects']=$territorial['objetos'];
            $data['territorialLayers']=$territorial['camadas'];
        }else{
            $data['territorialSummary']=['objetos'=>0,'camadas'=>0,'processos_vinculados'=>0,'atencao'=>0,'criticos'=>0];
            $data['territorialObjects']=[];$data['territorialLayers']=[];
        }
        View::render('tenant/relatorios',$this->base($data,'Relatórios'));
    }

    public function indicadoresHistoricos(string $municipio): void
    {
        $data=(new HistoricalIndicatorsService())->tenant($_GET);
        View::render('tenant/indicadores_historicos',$this->base($data,'Indicadores Executivos Históricos'));
    }

    public function exportIndicadoresHistoricos(string $municipio): void
    {
        (new HistoricalIndicatorsService())->exportTenant($_GET);
    }

    public function slaSecretarias(string $municipio): void
    {
        $state=(new WorkflowService())->load();
        $data=array_merge($state,['slaOperacional'=>(new OperationalSlaService())->fromWorkflowState($state)]);
        View::render('tenant/sla_secretarias',$this->base($data,'SLA por Secretaria'));
    }

    public function exportSlaSecretarias(string $municipio): void
    {
        $state=(new WorkflowService())->load();
        $sla=(new OperationalSlaService())->fromWorkflowState($state);
        (new OperationalSlaService())->exportCsv($sla);
    }

    public function historico(string $municipio): void
    {
        $data=(new WorkflowService())->historyData();
        View::render('tenant/historico',$this->base($data,'Histórico'));
    }

    public function configuracoes(string $municipio): void
    {
        $data=(new WorkflowService())->load();$pdo=Database::connection();$mid=(int)Tenant::id();
        $section=(string)($_GET['secao']??'fases');
        $data['secao']=in_array($section,['parametros','fases','secretarias','tipos','documentos','importacao'],true)?$section:'fases';
        $data['parametrosInstancia']=(new \App\Services\InstanceParameterService())->forMunicipio($mid,true);
        foreach(['fase','secretaria','departamento','tipo','requisito'] as $entity){
            $key='editar_'.$entity;$value=(int)($_GET[$key]??0);$data['editar'.ucfirst($entity)]=null;
            if(!$value)continue;
            $table=match($entity){'fase'=>'fases','secretaria'=>'secretarias','departamento'=>'departamentos','tipo'=>'tipos_documento',default=>'requisitos_documentais'};
            $st=$pdo->prepare("SELECT * FROM {$table} WHERE id=? AND municipio_id=?");$st->execute([$value,$mid]);$data['editar'.ucfirst($entity)]=$st->fetch()?:null;
        }
        $data['faseIdsSecretaria']=[];
        if(!empty($data['editarSecretaria'])){$st=$pdo->prepare('SELECT fase_id FROM fase_secretarias WHERE municipio_id=? AND secretaria_id=?');$st->execute([$mid,$data['editarSecretaria']['id']]);$data['faseIdsSecretaria']=array_map('intval',$st->fetchAll(\PDO::FETCH_COLUMN));}
        if($data['secao']==='importacao'){
            $importService=new StructureImportService();
            $data['municipiosOrigem']=$importService->sourceMunicipalities();
            $data['importacoesEstrutura']=$importService->recentImports(10);
            $data['importacaoPreview']=Session::get($this->structureImportPreviewKey());
            $data['pontosRestauracao']=(new InstanceSnapshotService())->list(20);
        }
        View::render('tenant/configuracoes',$this->base($data,'Configurações da Instância'));
    }

    public function uploadDocument(string $municipio,string $requisito): void
    {
        $this->csrf();try{(new DocumentService())->uploadDocument((int)$requisito,(string)($_POST['observacao_envio']??''));Session::flash('ok','Documento enviado com sucesso e registrado na trilha de auditoria.');}catch(\Throwable$e){Session::flash('erro',$e->getMessage());}
        $this->backToWorkflow();
    }

    public function uploadModel(string $municipio,string $requisito): void
    {
        $this->csrf();try{$ids=preg_split('/[,;\s]+/',(string)($_POST['requisito_ids']??''),-1,PREG_SPLIT_NO_EMPTY)?:[];(new DocumentService())->uploadModel((int)$requisito,$ids);Session::flash('ok','Modelo disponibilizado para o grupo de documentos.');}catch(\Throwable$e){Session::flash('erro',$e->getMessage());}
        $this->backToWorkflow();
    }

    public function validateDocument(string $municipio,string $documento): void
    {
        $this->csrf();try{(new DocumentService())->validate((int)$documento,(string)($_POST['validacao']??''),(string)($_POST['observacao']??''));Session::flash('ok',($_POST['validacao']??'')==='aprovar'?'Documento aprovado.':'Correção solicitada.');}catch(\Throwable$e){Session::flash('erro',$e->getMessage());}
        $this->backToWorkflow();
    }

    public function downloadModel(string $municipio,string $requisito): void {(new DocumentService())->downloadModel((int)$requisito);}
    public function downloadDocument(string $municipio,string $documento): void {(new DocumentService())->downloadDocument((int)$documento);}
    public function downloadHistory(string $municipio,string $historico): void {(new DocumentService())->downloadHistory((int)$historico);}

    public function saveScheduleStart(string $municipio): void
    {
        $this->csrf();if(!Auth::isPlatformAdmin())Response::abort(403,'Apenas a Stratelli pode recalcular o cronograma.');(new EtapaArchiveService())->assertOpen();$date=(string)($_POST['data_inicio']??'');if(!$this->validDate($date)){Session::flash('erro','Informe uma data válida.');$this->backToWorkflow();}
        $pdo=Database::connection();$st=$pdo->prepare('SELECT COUNT(*) FROM cronograma_fases WHERE municipio_id=? AND status="ENCERRADA"');$st->execute([Tenant::id()]);if((int)$st->fetchColumn()>0){Session::flash('erro','O marco inicial não pode ser alterado enquanto existirem fases formalmente encerradas. Reabra as fases encerradas antes de recalcular o cronograma.');$this->backToWorkflow();}$pdo->prepare('INSERT INTO cronograma_processos(municipio_id,data_inicio,criado_em,atualizado_em) VALUES(?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE data_inicio=VALUES(data_inicio),atualizado_em=NOW()')->execute([Tenant::id(),$date]);Audit::log('CRONOGRAMA_ATUALIZADO','Marco inicial alterado para '.$date,(int)Tenant::id(),['categoria'=>'ADMINISTRACAO','severidade'=>'ATENCAO']);Session::flash('ok','Marco inicial atualizado e prazos recalculados.');$this->backToWorkflow();
    }

    public function concludePhase(string $municipio,string $fase): void
    {
        $this->csrf();if(!Auth::isPlatformAdmin())Response::abort(403,'Apenas a Stratelli pode encerrar formalmente fases.');
        try{
            (new PhaseClosureService())->close((int)$fase,(string)($_POST['data_conclusao']??''),(string)($_POST['observacao']??''));
            Session::flash('ok','Fase encerrada formalmente. O snapshot documental foi preservado e a próxima fase foi recalculada.');
        }catch(\Throwable $e){Session::flash('erro',$e->getMessage());}
        $this->backToWorkflow((int)$fase);
    }

    public function reopenPhase(string $municipio,string $fase): void
    {
        $this->csrf();if(!Auth::isPlatformAdmin())Response::abort(403,'Apenas a Stratelli pode reabrir fases.');
        try{
            (new PhaseClosureService())->reopen((int)$fase,(string)($_POST['motivo_reabertura']??''));
            Session::flash('ok','Fase reaberta com registro de auditoria. Alterações documentais voltaram a ser permitidas.');
        }catch(\Throwable $e){Session::flash('erro',$e->getMessage());}
        $this->backToWorkflow((int)$fase);
    }

    public function configSaveParameters(string $municipio): void
    {
        $this->csrf();
        try{(new \App\Services\InstanceParameterService())->saveForCurrent($_POST);Session::flash('ok','Parâmetros da Instância atualizados com sucesso.');}
        catch(\Throwable $e){Session::flash('erro',$e->getMessage());}
        Response::redirect('/'.Tenant::current()['slug'].'/configuracoes?secao=parametros');
    }

    public function configImportTemplate(string $municipio): void
    {
        $file=dirname(__DIR__,2).'/resources/templates/Modelo_Importacao_Estrutura_INPACTA.xlsx';
        if(!is_file($file))Response::abort(404,'Modelo de importação não encontrado.');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="Modelo_Importacao_Estrutura_INPACTA.xlsx"');
        header('Content-Length: '.filesize($file));
        header('X-Content-Type-Options: nosniff');
        readfile($file);exit;
    }

    public function configImportPreviewMunicipio(string $municipio): void
    {
        $this->csrf();
        try{
            $preview=(new StructureImportService())->previewMunicipality((int)($_POST['municipio_origem_id']??0),[
                'fases'=>!empty($_POST['importar_fases']),'secretarias'=>!empty($_POST['importar_secretarias']),'departamentos'=>!empty($_POST['importar_departamentos'])
            ],(string)($_POST['estrategia_conflito']??'IGNORAR'));
            Session::put($this->structureImportPreviewKey(),$preview);
            Session::flash('ok',empty($preview['errors'])?'Prévia gerada. Revise os itens antes de confirmar a importação.':'Prévia gerada com pendências impeditivas. Revise os itens em vermelho.');
        }catch(\Throwable $e){Session::flash('erro',$e->getMessage());}
        Response::redirect('/'.Tenant::current()['slug'].'/configuracoes?secao=importacao');
    }

    public function configImportPreviewPlanilha(string $municipio): void
    {
        $this->csrf();
        try{
            $file=$_FILES['planilha_estrutura']??null;
            if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Selecione a planilha XLSX preenchida.');
            $preview=(new StructureImportService())->previewWorkbook((string)$file['tmp_name'],(string)$file['name'],(string)($_POST['estrategia_conflito']??'IGNORAR'));
            Session::put($this->structureImportPreviewKey(),$preview);
            Session::flash('ok',empty($preview['errors'])?'Planilha validada. Revise a prévia antes de importar.':'Planilha validada com erros. Nenhum dado foi gravado.');
        }catch(\Throwable $e){Session::flash('erro',$e->getMessage());}
        Response::redirect('/'.Tenant::current()['slug'].'/configuracoes?secao=importacao');
    }

    public function configImportExecute(string $municipio): void
    {
        $this->csrf();
        $key=$this->structureImportPreviewKey();$preview=Session::get($key);
        if(!$preview){Session::flash('erro','A prévia da importação expirou. Valide novamente a origem ou a planilha.');Response::redirect('/'.Tenant::current()['slug'].'/configuracoes?secao=importacao');}
        try{$safety=(new InstanceSnapshotService())->create('Segurança automática antes da importação estrutural','AUTOMATICO');$result=(new StructureImportService())->execute($preview);Session::forget($key);$s=$result['summary'];Session::flash('ok','Importação #'.$result['id'].' concluída: '.$s['created_total'].' registro(s) criado(s) e '.$s['updated_total'].' atualizado(s). Ponto de segurança #'.$safety['id'].' salvo antes da importação.');}
        catch(\Throwable $e){Session::flash('erro',$e->getMessage());}
        Response::redirect('/'.Tenant::current()['slug'].'/configuracoes?secao=importacao');
    }

    public function configImportCancel(string $municipio): void
    {
        $this->csrf();Session::forget($this->structureImportPreviewKey());Session::flash('ok','Prévia descartada. Nenhum dado foi alterado.');Response::redirect('/'.Tenant::current()['slug'].'/configuracoes?secao=importacao');
    }

    public function configImportUndo(string $municipio,string $id): void
    {
        $this->csrf();try{(new StructureImportService())->undo((int)$id);Session::flash('ok','Importação #'.(int)$id.' desfeita com sucesso.');}catch(\Throwable $e){Session::flash('erro',$e->getMessage());}Response::redirect('/'.Tenant::current()['slug'].'/configuracoes?secao=importacao');
    }

    public function configSavePhase(string $municipio): void {$this->configAction(fn($s)=>$s->savePhase($_POST),'Fase salva com sucesso.','fases');}
    public function configTogglePhase(string $municipio,string $id): void {$this->configAction(fn($s)=>$s->togglePhase((int)$id),'Status da fase alterado.','fases');}
    public function configSaveSecretaria(string $municipio): void {$this->configAction(fn($s)=>$s->saveSecretaria($_POST),'Secretaria e vínculos salvos.','secretarias');}
    public function configToggleSecretaria(string $municipio,string $id): void {$this->configAction(fn($s)=>$s->toggleSecretaria((int)$id),'Status da secretaria alterado.','secretarias');}
    public function configSaveDepartamento(string $municipio): void {$this->configAction(fn($s)=>$s->saveDepartamento($_POST),'Departamento salvo.','secretarias');}
    public function configToggleDepartamento(string $municipio,string $id): void {$this->configAction(fn($s)=>$s->toggleDepartamento((int)$id),'Status do departamento alterado.','secretarias');}
    public function configSaveTipo(string $municipio): void {$this->configAction(fn($s)=>$s->saveTipo($_POST),'Tipo de documento salvo.','tipos');}
    public function configToggleTipo(string $municipio,string $id): void {$this->configAction(fn($s)=>$s->toggleTipo((int)$id),'Status do tipo alterado.','tipos');}
    public function configSaveRequirement(string $municipio): void {$this->configAction(fn($s)=>$s->saveRequirement($_POST),'Regra documental salva.','documentos');}
    public function configToggleRequirement(string $municipio,string $id): void {$this->configAction(fn($s)=>$s->toggleRequirement((int)$id),'Status da regra documental alterado.','documentos');}


    public function configSnapshotCreate(string $municipio): void
    {
        $this->csrf();try{$r=(new InstanceSnapshotService())->create((string)($_POST['nome_snapshot']??''));Session::flash('ok','Ponto de restauração #'.$r['id'].' salvo com sucesso.');}catch(\Throwable$e){Session::flash('erro',$e->getMessage());}
        Response::redirect('/'.Tenant::current()['slug'].'/configuracoes?secao=importacao#pontos-restauracao');
    }

    public function configSnapshotDownload(string $municipio,string $id): void
    {
        try{(new InstanceSnapshotService())->download((int)$id);}catch(\Throwable$e){Session::flash('erro',$e->getMessage());Response::redirect('/'.Tenant::current()['slug'].'/configuracoes?secao=importacao#pontos-restauracao');}
    }

    public function configSnapshotImport(string $municipio): void
    {
        $this->csrf();try{$f=$_FILES['pacote_backup']??null;if(!$f||($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Selecione um pacote de backup .zip.');$id=(new InstanceSnapshotService())->importUploaded((string)$f['tmp_name'],(string)$f['name']);Session::flash('ok','Pacote importado e registrado como ponto de restauração #'.$id.'.');}catch(\Throwable$e){Session::flash('erro',$e->getMessage());}
        Response::redirect('/'.Tenant::current()['slug'].'/configuracoes?secao=importacao#pontos-restauracao');
    }

    public function configSnapshotRestore(string $municipio,string $id): void
    {
        $this->csrf();try{(new EtapaArchiveService())->assertOpen();$r=(new InstanceSnapshotService())->restore((int)$id);Session::flash('ok','Instância restaurada a partir do ponto #'.$r['snapshot_id'].'. Antes da operação, o sistema criou automaticamente o ponto de segurança #'.$r['safety_id'].'.');}catch(\Throwable$e){Session::flash('erro',$e->getMessage());}
        Response::redirect('/'.Tenant::current()['slug'].'/configuracoes?secao=importacao#pontos-restauracao');
    }

    public function configSnapshotRemove(string $municipio,string $id): void
    {
        $this->csrf();try{(new InstanceSnapshotService())->remove((int)$id);Session::flash('ok','Ponto de restauração removido.');}catch(\Throwable$e){Session::flash('erro',$e->getMessage());}
        Response::redirect('/'.Tenant::current()['slug'].'/configuracoes?secao=importacao#pontos-restauracao');
    }

    private function structureImportPreviewKey(): string {return 'structure_import_preview_'.(int)Tenant::id();}

    private function configAction(callable $action,string $ok,string $section): void
    {
        $this->csrf();try{$service=new ConfigurationService();$action($service);Audit::log('CONFIGURACAO_ALTERADA','Alteração administrativa na seção '.$section,(int)Tenant::id(),['categoria'=>'ADMINISTRACAO','severidade'=>'ATENCAO','secao'=>$section]);Session::flash('ok',$ok);}catch(\Throwable$e){Session::flash('erro',$e->getMessage());}Response::redirect('/'.Tenant::current()['slug'].'/configuracoes?secao='.$section);
    }

    private function base(array $data,string $title): array
    {
        return $data+['tenant'=>Tenant::current(),'territorialEnabled'=>$this->territorialEnabledForCurrentUser(),'etapaArquivamento'=>(new EtapaArchiveService())->status(),'title'=>$title,'erro'=>Session::pullFlash('erro'),'ok'=>Session::pullFlash('ok')];
    }
    private function territorialEnabledForCurrentUser(): bool
    {
        if(Auth::isPlatformAdmin())return true;
        return (int)(Tenant::current()['inteligencia_territorial_ativa']??0)===1;
    }
    private function csrf(): void {if(!Csrf::validate($_POST['_token']??null))Response::abort(419,'Sessão expirada.');}
    private function validDate(string $d): bool {$dt=\DateTimeImmutable::createFromFormat('!Y-m-d',$d);return$dt!==false&&$dt->format('Y-m-d')===$d;}
    private function backToWorkflow(?int $phase=null): never {$slug=Tenant::current()['slug'];$phase=$phase?:max(0,(int)($_POST['fase_id']??0));Response::redirect('/'.$slug.'/workflow'.($phase?'/fase/'.$phase:''));}
}
