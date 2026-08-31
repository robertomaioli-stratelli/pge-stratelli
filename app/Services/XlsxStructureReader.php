<?php
namespace App\Services;

use Phar;
use PharData;
use RuntimeException;
use ZipArchive;

final class XlsxStructureReader
{
    public function read(string $path): array
    {
        if(!is_file($path))throw new RuntimeException('Arquivo de planilha não encontrado.');
        $archive=$this->openArchive($path);
        try{$shared=$this->sharedStrings($archive);$sheets=$this->sheetMap($archive);$result=[];foreach($sheets as$name=>$sheetPath)$result[$name]=$this->readSheet($archive,$sheetPath,$shared);return$result;}
        finally{if($archive instanceof ZipArchive)$archive->close();}
    }
    private function openArchive(string $path): ZipArchive|PharData
    {
        if(class_exists(ZipArchive::class)){$zip=new ZipArchive();if($zip->open($path)===true)return$zip;}
        try{return new PharData($path,0,null,Phar::ZIP);}catch(\Throwable){throw new RuntimeException('Não foi possível abrir o arquivo XLSX. Verifique se o arquivo não está corrompido.');}
    }
    private function get(ZipArchive|PharData $archive,string $name): string|false
    {
        if($archive instanceof ZipArchive)return$archive->getFromName($name);
        try{return isset($archive[$name])?$archive[$name]->getContent():false;}catch(\Throwable){return false;}
    }
    private function sharedStrings(ZipArchive|PharData $archive): array
    {
        $xml=$this->get($archive,'xl/sharedStrings.xml');if($xml===false)return[];$out=[];
        if(preg_match_all('/<(?:\w+:)?si\b[^>]*>(.*?)<\/(?:\w+:)?si>/si',$xml,$sis))foreach($sis[1]as$si){$parts=[];if(preg_match_all('/<(?:\w+:)?t\b[^>]*>(.*?)<\/(?:\w+:)?t>/si',$si,$ts))foreach($ts[1]as$t)$parts[]=$this->decode($t);$out[]=implode('',$parts);}return$out;
    }
    private function sheetMap(ZipArchive|PharData $archive): array
    {
        $workbook=$this->get($archive,'xl/workbook.xml');$rels=$this->get($archive,'xl/_rels/workbook.xml.rels');if($workbook===false||$rels===false)throw new RuntimeException('Estrutura interna do XLSX inválida.');
        $relMap=[];if(preg_match_all('/<(?:\w+:)?Relationship\b([^>]*)\/?\s*>/si',$rels,$rs))foreach($rs[1]as$attrs){$id=$this->attr($attrs,'Id');$target=$this->attr($attrs,'Target');if($id!==''&&$target!=='')$relMap[$id]=$target;}
        $out=[];if(preg_match_all('/<(?:\w+:)?sheet\b([^>]*)\/?\s*>/si',$workbook,$ss))foreach($ss[1]as$attrs){$name=$this->attr($attrs,'name');$rid=$this->attr($attrs,'r:id');if($rid===''&&preg_match('/(?:^|\s)(?:\w+:)?id="([^"]+)"/i',$attrs,$m))$rid=$this->decode($m[1]);$target=$relMap[$rid]??'';if($name===''||$target==='')continue;$target=ltrim(str_replace('\\','/',$target),'/');while(str_starts_with($target,'../'))$target=substr($target,3);if(!str_starts_with($target,'xl/'))$target='xl/'.$target;$out[$name]=$target;}
        if(!$out)throw new RuntimeException('Nenhuma aba foi encontrada na planilha.');return$out;
    }
    private function readSheet(ZipArchive|PharData $archive,string $path,array $shared): array
    {
        $xml=$this->get($archive,$path);if($xml===false)return[];$rows=[];
        if(!preg_match_all('/<(?:\w+:)?row\b[^>]*>(.*?)<\/(?:\w+:)?row>/si',$xml,$rm))return[];
        foreach($rm[1]as$rowXml){$cells=[];if(preg_match_all('/<(?:\w+:)?c\b([^>]*)>(.*?)<\/(?:\w+:)?c>/si',$rowXml,$cm,PREG_SET_ORDER))foreach($cm as$c){$attrs=$c[1];$body=$c[2];$ref=$this->attr($attrs,'r');$col=$this->columnIndex($ref);$type=$this->attr($attrs,'t');$value='';if($type==='inlineStr'){if(preg_match_all('/<(?:\w+:)?t\b[^>]*>(.*?)<\/(?:\w+:)?t>/si',$body,$tm))$value=implode('',array_map(fn($v)=>$this->decode($v),$tm[1]));}elseif(preg_match('/<(?:\w+:)?v\b[^>]*>(.*?)<\/(?:\w+:)?v>/si',$body,$vm)){$raw=$this->decode($vm[1]);if($type==='s')$value=$shared[(int)$raw]??'';elseif($type==='b')$value=$raw==='1'?'SIM':'NAO';else$value=$raw;}$cells[$col]=$value;}if(!$cells)continue;$max=max(array_keys($cells));$line=[];for($i=0;$i<=$max;$i++)$line[]=$cells[$i]??'';$rows[]=$line;}return$rows;
    }
    private function attr(string $attrs,string $name): string
    {
        $q=preg_quote($name,'/');if(preg_match('/(?:^|\s)'.$q.'="([^"]*)"/i',$attrs,$m))return$this->decode($m[1]);if(preg_match("/(?:^|\\s)".$q."='([^']*)'/i",$attrs,$m))return$this->decode($m[1]);return'';
    }
    private function decode(string $value): string{return html_entity_decode(strip_tags($value),ENT_QUOTES|ENT_XML1,'UTF-8');}
    private function columnIndex(string $ref): int{if(!preg_match('/^([A-Z]+)/i',$ref,$m))return 0;$n=0;foreach(str_split(strtoupper($m[1]))as$ch)$n=$n*26+(ord($ch)-64);return$n-1;}
}
