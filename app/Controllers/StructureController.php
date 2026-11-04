<?php
namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\Tenant;
use App\Services\OperationalHierarchyService;

/** Cadastro hierárquico operacional a partir das Configurações, sem alterar histórico legado. */
final class StructureController {
 public function save(string $municipio):void {
  if(!Csrf::validate($_POST['_token']??null)){Response::abort(419,'Sessão expirada.');return;}
  try {
   $s=new OperationalHierarchyService();$type=(string)($_POST['tipo']??'');
   switch($type){
    case 'processo':$s->saveProcess($_POST);break;
    case 'etapa':$s->saveEtapa($_POST);break;
    case 'fase':$s->saveFase($_POST);break;
    case 'ato':$s->saveAto($_POST);break;
    case 'atividade':$s->saveAtividade($_POST);break;
    case 'alternar':$s->toggleNode((string)($_POST['nivel']??''),(int)($_POST['id']??0));break;
    default:throw new \RuntimeException('Cadastro inválido.');
   }
   Session::flash('ok','Estrutura atualizada.');
  }catch(\Throwable $e){Session::flash('erro',$e->getMessage());}
  Response::redirect('/'.Tenant::current()['slug'].'/configuracoes?secao=estrutura');
 }
}
