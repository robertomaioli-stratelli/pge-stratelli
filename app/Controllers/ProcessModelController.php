<?php
namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use PDO;

/** Cadastro de modelos (não interfere na execução legada por município). */
final class ProcessModelController
{
    private const TYPES = [
        'modelo' => ['pge_modelos', null, ['codigo','nome','versao','origem']],
        'etapa' => ['pge_modelo_etapas', 'modelo_id', ['codigo','nome','ordem']],
        'fase' => ['pge_modelo_fases', 'etapa_id', ['codigo','nome','ordem']],
        'ato' => ['pge_modelo_atos', 'fase_id', ['codigo','nome','ordem','prazo_texto','fundamento_normativo']],
        'atividade' => ['pge_modelo_atividades', 'ato_id', ['codigo','nome','ordem']],
    ];

    public function index(): void
    {
        try {
            $pdo = Database::connection();
            $modelos = $pdo->query('SELECT id,codigo,nome,versao FROM pge_modelos ORDER BY id')->fetchAll();
            $id = filter_input(INPUT_GET, 'modelo', FILTER_VALIDATE_INT) ?: (int)($modelos[0]['id'] ?? 0);
            $selecionado = null;
            foreach ($modelos as $m) if ((int)$m['id']===$id) $selecionado=$m;
            if (!$selecionado) $id=0;
            $arvore = [];
            if ($id) {
                $q = $pdo->prepare('SELECT id,codigo,nome,ordem FROM pge_modelo_etapas WHERE modelo_id=? ORDER BY ordem,id');
                $q->execute([$id]);
                $etapas=$q->fetchAll();
                $qf=$pdo->prepare('SELECT id,codigo,nome,ordem FROM pge_modelo_fases WHERE etapa_id=? ORDER BY ordem,id');
                $qa=$pdo->prepare('SELECT id,codigo,nome,ordem,prazo_texto,fundamento_normativo FROM pge_modelo_atos WHERE fase_id=? ORDER BY ordem,id');
                $qv=$pdo->prepare('SELECT id,codigo,nome,ordem,codigo_inferido FROM pge_modelo_atividades WHERE ato_id=? ORDER BY ordem,id');
                foreach ($etapas as $e) {
                    $qf->execute([$e['id']]); $fases=$qf->fetchAll();
                    foreach ($fases as &$f) {
                        $qa->execute([$f['id']]); $atos=$qa->fetchAll();
                        foreach ($atos as &$a) {
                            $qv->execute([$a['id']]); $a['atividades']=$qv->fetchAll();
                        }
                        unset($a); $f['atos']=$atos;
                    }
                    unset($f); $e['fases']=$fases; $arvore[]=$e;
                }
            }
            View::render('admin/modelos_processos', [
                'title'=>'Modelos de Processos · PGE V6', 'modelos'=>$modelos, 'selecionado'=>$selecionado,
                'arvore'=>$arvore,'erro'=>Session::pullFlash('erro'),'ok'=>Session::pullFlash('ok')
            ]);
        } catch (\PDOException $e) {
            // Não expõe credenciais nem nomes de tabelas ao usuário.
            View::render('admin/modelos_processos', [
                'title'=>'Modelos de Processos · PGE V6', 'modelos'=>[], 'selecionado'=>null,
                'arvore'=>[], 'erro'=>'Catálogo indisponível. Execute a migração v6_01 em um banco de homologação.', 'ok'=>null
            ]);
        }
    }

    public function save(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) Response::abort(419,'Sessão expirada.');
        $tipo=(string)($_POST['tipo']??'');
        if (!isset(self::TYPES[$tipo])) Response::abort(400,'Tipo inválido.');
        [$table,$parent,$fields]=self::TYPES[$tipo];
        $id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT) ?: 0;
        $parentId=$parent ? (filter_var($_POST[$parent]??0,FILTER_VALIDATE_INT) ?: 0) : 0;
        $modeloRetorno=filter_var($_POST['modelo_retorno']??0,FILTER_VALIDATE_INT) ?: 0;
        $redirect='/admin/modelos-processos'.($modeloRetorno?'?modelo='.$modeloRetorno:'');
        try {
            $pdo=Database::connection();
            $values=[];
            foreach($fields as $field) {
                $value=trim((string)($_POST[$field]??''));
                if($field==='ordem') {
                    if(!ctype_digit($value) || (int)$value<1 || (int)$value>100000) throw new \InvalidArgumentException('A ordem deve ser um inteiro positivo.');
                    $values[$field]=(int)$value;
                } else {
                    $max=($field==='codigo'?100:($field==='versao'?50:($field==='origem'?255:($field==='nome' && in_array($tipo,['modelo','etapa','fase'],true)?255:10000))));
                    if((function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value))>$max) throw new \InvalidArgumentException('Campo excede tamanho permitido: '.$field);
                    if(in_array($field,['codigo','nome'],true) && $value==='') throw new \InvalidArgumentException('Código e nome são obrigatórios.');
                    if($tipo==='modelo' && in_array($field,['versao','origem'],true) && $value==='') throw new \InvalidArgumentException('Versão e origem são obrigatórias.');
                    $values[$field]=$value==='' && in_array($field,['prazo_texto','fundamento_normativo'],true) ? null : $value;
                }
            }
            $pdo->beginTransaction();
            if($parent) {
                if($parentId<1) throw new \InvalidArgumentException('Selecione um registro pai.');
                $parentTable=['etapa'=>'pge_modelos','fase'=>'pge_modelo_etapas','ato'=>'pge_modelo_fases','atividade'=>'pge_modelo_atos'][$tipo];
                $s=$pdo->prepare("SELECT id FROM {$parentTable} WHERE id=?");$s->execute([$parentId]);
                if(!$s->fetchColumn()) throw new \InvalidArgumentException('Registro pai não encontrado.');
                // Impede mover uma entidade entre árvores acidentalmente.
                if($id) {
                    $s=$pdo->prepare("SELECT {$parent} FROM {$table} WHERE id=? FOR UPDATE");$s->execute([$id]);
                    $old=$s->fetchColumn();
                    if($old===false) throw new \InvalidArgumentException('Registro não encontrado.');
                    if((int)$old!==$parentId) throw new \InvalidArgumentException('Não é permitido alterar o pai de um registro existente.');
                }
            }
            if($tipo==='atividade') {
                $values['texto_origem']=$values['nome'];
                $values['codigo_inferido']=0;
            }
            if($id) {
                // Não alterar origem documental/código inferido na edição de atividades.
                if($tipo==='atividade') { unset($values['texto_origem'],$values['codigo_inferido']); }
                $sql=implode(',',array_map(static fn($k)=>"{$k}=?",array_keys($values)));
                $s=$pdo->prepare("UPDATE {$table} SET {$sql} WHERE id=?");
                $s->execute([...array_values($values),$id]);
                if($s->rowCount()===0) { $s=$pdo->prepare("SELECT id FROM {$table} WHERE id=?");$s->execute([$id]);if(!$s->fetchColumn())throw new \InvalidArgumentException('Registro não encontrado.'); }
            } else {
                if($parent)$values=[$parent=>$parentId]+$values;
                $columns=implode(',',array_keys($values));$placeholders=implode(',',array_fill(0,count($values),'?'));
                $s=$pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})");
                $s->execute(array_values($values));
            }
            $pdo->commit();Session::flash('ok','Modelo atualizado com sucesso.');
        } catch(\Throwable $e) {
            if(isset($pdo) && $pdo->inTransaction())$pdo->rollBack();
            Session::flash('erro',($e instanceof \InvalidArgumentException)?$e->getMessage():'Não foi possível salvar: confira código único, referências e campos.');
        }
        Response::redirect($redirect);
    }
}
