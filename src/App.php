<?php
declare(strict_types=1);
namespace LaporKota;
use PDO;
final class App
{
    public function __construct(private PDO $db,private ?\Closure $clock=null,private ?\Closure $beforeCredentialWrite=null){}
    public function run():void
    {
        header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');header('Cache-Control: no-store');
        header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'self'; img-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
        if(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)==='/assets/app.css'){header('Content-Type: text/css; charset=utf-8');readfile(dirname(__DIR__).'/public/assets/app.css');return;}
        if(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)==='/assets/favicon.svg'){header('Content-Type: image/svg+xml');header('Cache-Control: public, max-age=86400');readfile(dirname(__DIR__).'/public/assets/favicon.svg');return;}
        if(preg_match('#\A/assets/img/([a-z0-9-]{1,40})\.jpg\z#',(string)parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),$asset)&&is_file($file=dirname(__DIR__).'/public/assets/img/'.$asset[1].'.jpg')){header('Content-Type: image/jpeg');header('Cache-Control: public, max-age=86400');readfile($file);return;}
        if((int)($_SERVER['CONTENT_LENGTH']??0)>7*1024*1024)throw new HttpError(400,'Ukuran kiriman terlalu besar.');
        if(str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json')){
            try{$data=json_decode(file_get_contents('php://input'),true,flags:JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new HttpError(400,'Data kiriman tidak valid.');}
            if(!is_array($data)||array_is_list($data))throw new HttpError(400,'Data kiriman tidak valid.');$_POST=$data;
        }
        if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true))throw new HttpError(405,'Metode tidak didukung.');
        session_name('laporkota');
        session_start(['use_strict_mode'=>1,'cookie_path'=>'/','cookie_httponly'=>true,'cookie_samesite'=>'Lax','cookie_secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off']);
        $_SESSION['csrf']??=bin2hex(random_bytes(32));
        $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
        if(in_array($path,['/login','/register'],true)){$this->authentication($path);return;}
        if($path==='/logout'){
            $this->postOnly();$this->csrf();$_SESSION=[];session_destroy();
            setcookie('laporkota','',['expires'=>time()-3600,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);$this->redirect('/login');return;
        }
        $user=$this->currentUser();
        if($path==='/'){$this->redirect($user?'/laporan':'/login');return;}
        if(str_starts_with($path,'/admin/')&&($user['role']??null)!=='admin')throw new HttpError(403,'Tindakan tidak diizinkan.');
        if(str_starts_with($path,'/petugas/')&&($user['role']??null)!=='petugas')throw new HttpError(403,'Tindakan tidak diizinkan.');
        if(in_array($path,['/laporan/detail','/api/laporan/detail','/lampiran'],true)&&!$user)throw new HttpError(404,'Aduan tidak ditemukan.');
        if((str_starts_with($path,'/laporan')||str_starts_with($path,'/api/laporan'))&&!$user){
            if(str_starts_with($path,'/api/')){http_response_code(401);$this->json(['error'=>'Silakan masuk terlebih dahulu.']);return;}
            $this->redirect('/login');return;
        }
        if($path==='/admin/petugas'){
            if($_SERVER['REQUEST_METHOD']==='POST'){$this->csrf();(new Accounts($this->db))->create($_POST,'petugas');$this->redirect('/admin/petugas?created=1');return;}
            View::page('Buat akun petugas',((isset($_GET['created'])&&$_GET['created']==='1')?'<p role="status">Akun petugas berhasil dibuat.</p>':'').View::officerForm($this->db->query('SELECT id,name FROM dinas ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)),$user);return;
        }
        $reports=new Reports($this->db);
        if($path==='/petugas/laporan/status'){$this->postOnly();$this->csrf();$id=$reports->process($user,$_POST);$this->redirect('/laporan/detail?id='.$id);return;}
        if($path==='/admin/laporan/tolak'){$this->postOnly();$this->csrf();$id=$reports->reject($user,$_POST);$this->redirect('/laporan/detail?id='.$id);return;}
        if($path==='/admin/laporan/tugaskan'){$this->postOnly();$this->csrf();$id=$reports->assign($user,$_POST);$this->redirect('/laporan/detail?id='.$id);return;}
        if($path==='/laporan/buat'){
            if($user['role']!=='warga')throw new HttpError(403,'Tindakan tidak diizinkan.');
            if($_SERVER['REQUEST_METHOD']==='POST'){
                $this->csrf();
                $id=$reports->create($user,$_POST,$_FILES);$this->redirect('/laporan/detail?id='.$id);return;
            }
            View::page('Buat aduan',View::createForm($this->db->query('SELECT id,name FROM categories ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)),$user);return;
        }
        if($path==='/lampiran'){(new Uploads())->download($reports->attachment($user,Input::id($_GET,'id')));return;}
        if($path==='/laporan/detail'){$report=$reports->detail($user,Input::id($_GET,'id'),true);View::page($report['title'],View::detail($report,$user,$user['role']==='admin'?$this->db->query('SELECT id,name FROM dinas ORDER BY id')->fetchAll(PDO::FETCH_ASSOC):[]),$user,true);return;}
        if($path==='/api/laporan/detail'){$this->json($reports->detail($user,Input::id($_GET,'id')));return;}
        if(in_array($path,['/laporan','/api/laporan','/admin/laporan','/petugas/laporan'],true)){
            $rows=$reports->listing($user,$_GET);
            $result=Reports::paginate($rows,$_GET);
            if($path==='/api/laporan'){$this->json($result);return;}
            View::page(match($user['role']){'admin'=>'Antrean layanan','petugas'=>'Antrean dinas',default=>'Aduan saya'},View::listing($result['reports'],$_GET).View::pager($result,$_GET),$user);return;
        }
        throw new HttpError(404,'Halaman tidak ditemukan.');
    }
    private function authentication(string $path):void
    {
        if($_SERVER['REQUEST_METHOD']==='POST'){
            $this->csrf();
            if($path==='/register'){(new Accounts($this->db))->create($_POST,'warga');$this->redirect('/login');return;}
            unset($_SESSION['user_id'],$_SESSION['version']);
            if(!is_string($_POST['username']??null)||!is_string($_POST['password']??null)||strlen($_POST['username'])>100||strlen($_POST['password'])>1024)throw new HttpError(400,'Periksa isian formulir.');
            $user=(new Auth($this->db,$this->clock,$this->beforeCredentialWrite))->login($_POST['username'],$_POST['password'],$_SERVER['REMOTE_ADDR']);
            if(!$user){http_response_code(401);View::page('Masuk',View::authForm(false,'Nama pengguna atau kata sandi tidak valid.'));return;}
            session_regenerate_id(true);$_SESSION['user_id']=(int)$user['id'];$_SESSION['version']=(int)$user['password_version'];$_SESSION['csrf']=bin2hex(random_bytes(32));$this->redirect('/laporan');return;
        }
        View::page($path==='/register'?'Daftar warga':'Masuk',View::authForm($path==='/register'));
    }
    private function currentUser():?array
    {
        if(!isset($_SESSION['user_id']))return null;
        $q=$this->db->prepare('SELECT id,username,password_version,active,role,dinas_id FROM users WHERE id=?');$q->execute([$_SESSION['user_id']]);$user=$q->fetch(PDO::FETCH_ASSOC);
        if(!$user||!$user['active']||(int)$user['password_version']!==($_SESSION['version']??null)){unset($_SESSION['user_id'],$_SESSION['version']);return null;}
        return $user;
    }
    private function csrf():void
    {
        if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf']))throw new HttpError(403,'Formulir kedaluwarsa. Silakan muat ulang halaman.');
    }
    private function postOnly():void{if($_SERVER['REQUEST_METHOD']!=='POST')throw new HttpError(405,'Metode tidak didukung.');}
    private function redirect(string $path):void{header('Location: '.$path,true,303);}
    private function json(array $data):void{header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);}
}
