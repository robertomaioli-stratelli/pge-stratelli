<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Core\Session;
use App\Core\Tenant;
use App\Core\View;
use App\Services\GlobalSearchService;

final class SearchController
{
    public function index(): void
    {
        if(!Auth::isPlatformAdmin()){
            $slug=(string)(Auth::user()['municipio_slug']??'');
            if($slug!=='')Tenant::resolveBySlug($slug);
        }
        $query=(string)($_GET['q']??'');
        $data=(new GlobalSearchService())->search($query,20);
        View::render('search/index',$data+[
            'title'=>'Busca Global',
            'erro'=>Session::pullFlash('erro'),
            'ok'=>Session::pullFlash('ok'),
        ]);
    }

    public function suggestions(): void
    {
        $query=(string)($_GET['q']??'');
        $data=(new GlobalSearchService())->search($query,3);
        Response::json(['ok'=>true]+$data);
    }
}
