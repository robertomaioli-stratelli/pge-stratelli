<?php
namespace App\Services;

use App\Core\Format;
use RuntimeException;

final class FileStorage
{
    public function storeUpload(string $field,string $relativeDir,string $prefix,array $allowedExtensions,int $maxBytes=20971520): array
    {
        if(!isset($_FILES[$field])||($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) throw new RuntimeException('Selecione um arquivo para enviar.');
        if((int)$_FILES[$field]['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Não foi possível receber o arquivo enviado.');
        $maxMb=max(1,(int)ceil($maxBytes/1024/1024));if((int)$_FILES[$field]['size']>$maxBytes) throw new RuntimeException('O arquivo deve ter no máximo '.$maxMb.' MB.');
        $original=basename((string)$_FILES[$field]['name']);
        $ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
        $allowed=array_values(array_unique(array_filter(array_map(fn($x)=>ltrim(strtolower(trim((string)$x)),'.'),$allowedExtensions))));
        if(!$ext||!in_array($ext,$allowed,true)) throw new RuntimeException('Formato não permitido para este documento.');
        $root=dirname(__DIR__,2).'/storage/uploads';
        $relative=trim($relativeDir,'/');
        $dir=$root.'/'.$relative;
        if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir)) throw new RuntimeException('Não foi possível criar a pasta de arquivos.');
        $safe=preg_replace('/[^a-zA-Z0-9._-]/','_',$original)?:'arquivo.'.$ext;
        $name=Format::slug($prefix).'_'.date('Ymd_His').'_'.bin2hex(random_bytes(5)).'_'.$safe;
        $dest=$dir.'/'.$name;
        if(!move_uploaded_file($_FILES[$field]['tmp_name'],$dest)) throw new RuntimeException('Não foi possível salvar o arquivo enviado.');
        $checksum=hash_file('sha256',$dest);
        if($checksum===false){@unlink($dest);throw new RuntimeException('Não foi possível calcular a integridade do arquivo enviado.');}
        $mime=$this->detectMime($dest);
        return ['original'=>$original,'path'=>$relative.'/'.$name,'size'=>(int)filesize($dest),'mime'=>$mime,'checksum'=>$checksum];
    }

    public function discard(string $relative): void
    {
        $file=$this->resolve($relative,false);if($file&&is_file($file))@unlink($file);
    }

    public function inspect(string $relative,?string $expectedChecksum=null): array
    {
        $file=$this->resolve($relative,false);
        if(!$file)return ['exists'=>false,'valid'=>false,'checksum'=>'','mime'=>'','size'=>0];
        $checksum=hash_file('sha256',$file)?:'';
        $expected=trim(strtolower((string)$expectedChecksum));
        $valid=$expected===''?true:($checksum!==''&&hash_equals($expected,strtolower($checksum)));
        return ['exists'=>true,'valid'=>$valid,'checksum'=>$checksum,'mime'=>$this->detectMime($file),'size'=>(int)filesize($file)];
    }

    public function sendInline(string $relative): never
    {
        $file=$this->resolve($relative,true);
        clearstatcache(true,$file);
        $ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));
        $known=[
            'png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','gif'=>'image/gif','svg'=>'image/svg+xml','pdf'=>'application/pdf',
        ];
        $mime=$known[$ext]??$this->detectMime($file);
        $size=(int)filesize($file);$mtime=(int)filemtime($file);$etag='"'.sha1($relative.'|'.$size.'|'.$mtime).'"';
        header('Content-Type: '.$mime);header('Content-Length: '.$size);header('Content-Disposition: inline; filename="'.rawurlencode(basename($file)).'"');
        header('Cache-Control: private, max-age=86400, immutable');header('ETag: '.$etag);header('Last-Modified: '.gmdate('D, d M Y H:i:s',$mtime).' GMT');header('X-Content-Type-Options: nosniff');
        $ifNoneMatch=trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''));if($ifNoneMatch!==''&&hash_equals($etag,$ifNoneMatch)){http_response_code(304);exit;}
        $handle=fopen($file,'rb');if($handle===false)throw new RuntimeException('Não foi possível abrir o arquivo solicitado.');fpassthru($handle);fclose($handle);exit;
    }

    public function send(string $relative,string $downloadName,?string $expectedChecksum=null,?string $expectedMime=null): never
    {
        $file=$this->resolve($relative,true);
        if($expectedChecksum){$actual=hash_file('sha256',$file);if($actual===false||!hash_equals(strtolower($expectedChecksum),strtolower($actual)))throw new RuntimeException('Falha de integridade: o arquivo armazenado não corresponde ao checksum auditado. Contate a Stratelli antes de utilizá-lo.');header('X-Content-SHA256: '.$actual);}
        $mime=$expectedMime?:$this->detectMime($file);
        header('Content-Type: '.$mime);header('Content-Length: '.filesize($file));header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode(basename($downloadName)));header('X-Content-Type-Options: nosniff');
        readfile($file);exit;
    }

    private function resolve(string $relative,bool $throw): ?string
    {
        $root=realpath(dirname(__DIR__,2).'/storage/uploads');
        $file=realpath(dirname(__DIR__,2).'/storage/uploads/'.ltrim($relative,'/'));
        if(!$root||!$file||strpos($file,$root.DIRECTORY_SEPARATOR)!==0||!is_file($file)){
            if($throw)throw new RuntimeException('O arquivo solicitado não está disponível.');
            return null;
        }
        return $file;
    }

    private function detectMime(string $file): string
    {
        if(class_exists('finfo')){$f=new \finfo(FILEINFO_MIME_TYPE);$m=$f->file($file);if(is_string($m)&&$m!=='')return substr($m,0,160);}
        return function_exists('mime_content_type')?(mime_content_type($file)?:'application/octet-stream'):'application/octet-stream';
    }
}
