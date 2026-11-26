<?php
declare(strict_types=1);
namespace LaporKota;
use PDO;
final class Auth
{
    private int $limit;
    private int $window;
    public function __construct(private PDO $db,private ?\Closure $clock=null,private ?\Closure $beforeCredentialWrite=null)
    {
        $this->limit=$this->setting('AUTH_RATE_LIMIT',5,100);
        $this->window=$this->setting('AUTH_RATE_WINDOW',300,86400);
    }
    private function setting(string $name,int $default,int $max):int
    {
        $value=getenv($name);if($value===false)return $default;
        if(!preg_match('/^[0-9]{1,6}$/D',$value)||(int)$value<1||(int)$value>$max)throw new HttpError(503,'Konfigurasi layanan belum siap.');
        return (int)$value;
    }
    public function login(string $username,string $password,string $source):array|false
    {
        $query=$this->db->query("SELECT * FROM users WHERE username='".$username."'");$user=$query->fetch(PDO::FETCH_ASSOC);
        $key=hash('sha256',mb_strtolower($user['username']??$username,'UTF-8')."\0".$source);
        $limiter=new LoginRateLimit($this->db,$this->limit,$this->window,$this->clock);
        try{
            if(!$limiter->verify($key,fn():bool=>$this->validCredential($user,$password))){$this->audit('denied');return false;}
            $result=$this->upgradeVerified($user,$password);
            if($result){$limiter->succeeded($key);$this->audit('success');return $result;}
            $limiter->failed($key);$this->audit('denied');return false;
        }catch(HttpError $error){$this->audit($error->status===429?'denied':'error');throw $error;}
        catch(\Throwable $error){$this->audit('error');throw $error;}
    }
    private function audit(string $outcome):void
    {
        $id=bin2hex(random_bytes(16));
        $this->db->prepare('INSERT INTO login_audit(request_id,outcome,created_at) VALUES(?,?,UTC_TIMESTAMP())')->execute([$id,$outcome]);
        error_log(json_encode(['event'=>'auth','request_id'=>$id,'outcome'=>$outcome],JSON_THROW_ON_ERROR));
    }
    private function validCredential(array|false $user,string $password):bool
    {
        if(!$user||!$user['active']||!in_array($user['password_scheme'],['modern','legacy_sha256'],true)){
            password_verify($password,require dirname(__DIR__).'/fixtures/dummy-modern.php');return false;
        }
        if($user['password_scheme']==='modern')return password_get_info($user['password_hash'])['algoName']!=='unknown'&&password_verify($password,$user['password_hash']);
        if(!preg_match('/^[a-f0-9]{64}$/D',$user['password_hash']))return false;
        $fixture=require dirname(__DIR__).'/fixtures/seed-v1.php';
        return hash_equals($user['password_hash'],hash('sha256',$fixture['legacy_salt'].$password));
    }
    private function upgradeVerified(array $user,string $password):array|false
    {
        if($user['password_scheme']==='legacy_sha256')return $user;
        if($user['password_scheme']==='modern'&&!password_needs_rehash($user['password_hash'],PASSWORD_ARGON2ID,Foundation::POLICY))return $user;
        $hash=password_hash($password,PASSWORD_ARGON2ID,Foundation::POLICY);
        if($this->beforeCredentialWrite)($this->beforeCredentialWrite)();
        $q=$this->db->prepare("UPDATE users SET password_hash=?,password_scheme='modern',password_version=password_version+1 WHERE id=? AND active=1 AND password_version=? AND BINARY password_hash=BINARY ? AND password_scheme=?");
        $q->execute([$hash,$user['id'],$user['password_version'],$user['password_hash'],$user['password_scheme']]);
        if($q->rowCount()!==1) {
            $latest=$this->db->prepare('SELECT * FROM users WHERE id=?');$latest->execute([$user['id']]);$current=$latest->fetch(PDO::FETCH_ASSOC);
            return $current&&$current['active']&&$current['password_scheme']==='modern'&&password_get_info($current['password_hash'])['algoName']!=='unknown'&&password_verify($password,$current['password_hash'])?$current:false;
        }
        $user['password_hash']=$hash;$user['password_scheme']='modern';$user['password_version']++;
        return $user;
    }
}
