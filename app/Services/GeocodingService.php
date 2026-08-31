<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Tenant;
use PDO;
use RuntimeException;

final class GeocodingService
{
    private PDO $pdo;
    private int $municipioId;
    private array $municipio;
    private string $provider;
    private string $baseUrl;
    private string $appUrl;
    private string $userAgent;
    private ?string $email;
    private int $cacheTtl;
    private string $cacheDir;

    public function __construct()
    {
        $this->pdo=Database::connection();
        $this->municipioId=(int)(Tenant::id()??0);
        if(!$this->municipioId) throw new RuntimeException('Instância municipal não definida.');
        $st=$this->pdo->prepare('SELECT id,nome,uf,latitude,longitude,geojson_delimitacao FROM municipios WHERE id=? AND ativo=1 LIMIT 1');
        $st->execute([$this->municipioId]);
        $this->municipio=$st->fetch()?:[];
        if(!$this->municipio) throw new RuntimeException('Município não encontrado.');

        $this->provider=strtolower(trim((string)(getenv('GEOCODING_PROVIDER')?:'nominatim')));
        $this->baseUrl=rtrim((string)(getenv('GEOCODING_BASE_URL')?:'https://nominatim.openstreetmap.org'),'/');
        $this->appUrl=(string)(getenv('APP_URL')?:'http://localhost');
        $this->userAgent=trim((string)(getenv('GEOCODING_USER_AGENT')?:('INPACTA/4.1 (+'.$this->appUrl.')')));
        $email=trim((string)(getenv('GEOCODING_EMAIL')?:''));
        $this->email=$email!==''?$email:null;
        $this->cacheTtl=max(3600,(int)(getenv('GEOCODING_CACHE_TTL')?:2592000));
        $this->cacheDir=dirname(__DIR__,2).'/storage/cache/geocoding';
        if(!is_dir($this->cacheDir) && !@mkdir($this->cacheDir,0775,true) && !is_dir($this->cacheDir)){
            throw new RuntimeException('Não foi possível preparar o cache de geocodificação.');
        }
    }

    public function search(string $address): array
    {
        $address=trim(preg_replace('/\s+/u',' ',$address)??'');
        if(mb_strlen($address)<4) throw new RuntimeException('Informe um endereço mais completo para localizar no mapa.');
        if($this->provider==='disabled' || $this->provider==='off') throw new RuntimeException('A geocodificação por endereço está desativada nesta instalação.');
        if($this->provider!=='nominatim') throw new RuntimeException('Provedor de geocodificação não suportado nesta versão.');

        $cacheKey=sha1($this->provider.'|'.$this->municipioId.'|'.mb_strtolower($address));
        $cacheFile=$this->cacheDir.'/'.$cacheKey.'.json';
        if(is_file($cacheFile) && (time()-filemtime($cacheFile))<$this->cacheTtl){
            $cached=json_decode((string)file_get_contents($cacheFile),true);
            if(is_array($cached)) return $cached;
        }

        $query=$this->buildQuery($address);
        $params=[
            'q'=>$query,
            'format'=>'jsonv2',
            'limit'=>5,
            'addressdetails'=>1,
            'countrycodes'=>'br',
            'accept-language'=>'pt-BR,pt;q=0.9',
        ];
        $bbox=$this->municipalityBbox();
        if($bbox){
            // Nominatim expects x=longitude and y=latitude. This confines results to the municipal bounding box.
            $params['viewbox']=$bbox['minLng'].','.$bbox['maxLat'].','.$bbox['maxLng'].','.$bbox['minLat'];
            $params['bounded']=1;
        }
        if($this->email) $params['email']=$this->email;
        $url=$this->baseUrl.'/search?'.http_build_query($params,'','&',PHP_QUERY_RFC3986);

        $this->respectRateLimit();
        $raw=$this->httpGet($url);
        $decoded=json_decode($raw,true);
        if(!is_array($decoded)) throw new RuntimeException('O serviço de localização retornou uma resposta inválida.');

        $results=[];
        foreach($decoded as $r){
            if(!isset($r['lat'],$r['lon'])) continue;
            $lat=(float)$r['lat'];$lng=(float)$r['lon'];
            $inside=$this->pointInsideMunicipality($lat,$lng);
            $results[]=[
                'lat'=>$lat,
                'lng'=>$lng,
                'display_name'=>(string)($r['display_name']??''),
                'type'=>(string)($r['type']??$r['addresstype']??''),
                'importance'=>(float)($r['importance']??0),
                'inside_municipio'=>$inside,
                'address'=>is_array($r['address']??null)?$r['address']:[],
            ];
        }
        usort($results,function(array $a,array $b): int{
            if($a['inside_municipio']!==$b['inside_municipio']) return $a['inside_municipio']?-1:1;
            return $b['importance']<=>$a['importance'];
        });
        @file_put_contents($cacheFile,json_encode($results,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
        return $results;
    }

    public function bestMatch(string $address): ?array
    {
        $results=$this->search($address);
        if(!$results) return null;
        foreach($results as $r) if(!empty($r['inside_municipio'])) return $r;
        return $results[0]??null;
    }

    private function buildQuery(string $address): string
    {
        $city=(string)($this->municipio['nome']??'');$uf=(string)($this->municipio['uf']??'');
        return implode(', ',array_filter([$address,$city,$uf,'Brasil']));
    }

    private function httpGet(string $url): string
    {
        if(function_exists('curl_init')){
            $ch=curl_init($url);
            curl_setopt_array($ch,[
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_CONNECTTIMEOUT=>5,
                CURLOPT_TIMEOUT=>10,
                CURLOPT_HTTPHEADER=>[
                    'Accept: application/json',
                    'Accept-Language: pt-BR,pt;q=0.9',
                    'Referer: '.$this->appUrl,
                ],
                CURLOPT_USERAGENT=>$this->userAgent,
            ]);
            $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);
            if($body===false || $status<200 || $status>=300) throw new RuntimeException('Não foi possível consultar o serviço de localização'.($err?': '.$err:'.'));
            return (string)$body;
        }
        $ctx=stream_context_create(['http'=>[
            'method'=>'GET','timeout'=>10,'ignore_errors'=>true,
            'header'=>"Accept: application/json\r\nAccept-Language: pt-BR,pt;q=0.9\r\nUser-Agent: {$this->userAgent}\r\nReferer: {$this->appUrl}\r\n",
        ]]);
        $body=@file_get_contents($url,false,$ctx);
        if($body===false) throw new RuntimeException('Não foi possível consultar o serviço de localização.');
        return (string)$body;
    }

    private function respectRateLimit(): void
    {
        // The public Nominatim policy requires an absolute maximum of one request per second.
        if(!str_contains(strtolower($this->baseUrl),'nominatim.openstreetmap.org')) return;
        $lockFile=$this->cacheDir.'/.nominatim-rate.lock';$fp=fopen($lockFile,'c+');if(!$fp)return;
        try{
            if(!flock($fp,LOCK_EX)) return;
            rewind($fp);$last=(float)trim((string)stream_get_contents($fp));$now=microtime(true);$wait=1.05-($now-$last);
            if($wait>0) usleep((int)round($wait*1000000));
            ftruncate($fp,0);rewind($fp);fwrite($fp,(string)microtime(true));fflush($fp);
        }finally{flock($fp,LOCK_UN);fclose($fp);}
    }

    private function municipalityBbox(): ?array
    {
        $raw=(string)($this->municipio['geojson_delimitacao']??'');
        if($raw==='') return null;$geo=json_decode($raw,true);if(!is_array($geo))return null;
        $points=[];$this->collectCoordinates($geo,$points);if(!$points)return null;
        $lngs=array_column($points,0);$lats=array_column($points,1);
        return ['minLng'=>min($lngs),'maxLng'=>max($lngs),'minLat'=>min($lats),'maxLat'=>max($lats)];
    }

    private function collectCoordinates(mixed $node,array &$points): void
    {
        if(!is_array($node))return;
        if(isset($node[0],$node[1])&&is_numeric($node[0])&&is_numeric($node[1])){$points[]=[(float)$node[0],(float)$node[1]];return;}
        foreach($node as $v)$this->collectCoordinates($v,$points);
    }

    private function pointInsideMunicipality(float $lat,float $lng): bool
    {
        $raw=(string)($this->municipio['geojson_delimitacao']??'');
        if($raw==='') return true; // no perimeter available: do not falsely flag the address.
        $geo=json_decode($raw,true);if(!is_array($geo))return true;
        $geometries=[];
        $this->extractGeometries($geo,$geometries);
        foreach($geometries as $g){
            $type=$g['type']??'';$coords=$g['coordinates']??null;
            if($type==='Polygon'&&is_array($coords)&&$this->pointInPolygon($lng,$lat,$coords))return true;
            if($type==='MultiPolygon'&&is_array($coords))foreach($coords as $poly)if(is_array($poly)&&$this->pointInPolygon($lng,$lat,$poly))return true;
        }
        return false;
    }

    private function extractGeometries(array $node,array &$out): void
    {
        $type=$node['type']??'';
        if(in_array($type,['Polygon','MultiPolygon'],true)&&isset($node['coordinates'])){$out[]=$node;return;}
        if($type==='Feature'&&is_array($node['geometry']??null)){$this->extractGeometries($node['geometry'],$out);return;}
        if($type==='FeatureCollection')foreach(($node['features']??[]) as $f)if(is_array($f))$this->extractGeometries($f,$out);
        if($type==='GeometryCollection')foreach(($node['geometries']??[]) as $g)if(is_array($g))$this->extractGeometries($g,$out);
    }

    private function pointInPolygon(float $x,float $y,array $rings): bool
    {
        if(!$rings||!is_array($rings[0]??null)||!$this->pointInRing($x,$y,$rings[0]))return false;
        for($i=1,$n=count($rings);$i<$n;$i++)if(is_array($rings[$i])&&$this->pointInRing($x,$y,$rings[$i]))return false;
        return true;
    }

    private function pointInRing(float $x,float $y,array $ring): bool
    {
        $inside=false;$n=count($ring);if($n<3)return false;
        for($i=0,$j=$n-1;$i<$n;$j=$i++){
            $xi=(float)($ring[$i][0]??0);$yi=(float)($ring[$i][1]??0);$xj=(float)($ring[$j][0]??0);$yj=(float)($ring[$j][1]??0);
            $intersect=(($yi>$y)!==($yj>$y))&&($x<(($xj-$xi)*($y-$yi)/(($yj-$yi)?:1e-12)+$xi));
            if($intersect)$inside=!$inside;
        }
        return $inside;
    }
}
