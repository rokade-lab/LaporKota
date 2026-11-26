<?php
declare(strict_types=1);
namespace LaporKota;
use PDO;
final class LoginRateLimit
{
    public function __construct(private PDO $db,private int $limit,private int $window,private ?\Closure $clock=null){}
    public function verify(string $key,\Closure $verify):bool
    {
        return $this->locked($key,function()use($key,$verify):bool{
            $this->assertBudget($key);
            if($verify())return true;
            $this->increment($key);return false;
        });
    }
    public function failed(string $key):void
    {
        $this->locked($key,function()use($key):void{$this->assertBudget($key);$this->increment($key);});
    }
    public function succeeded(string $key):void
    {
        $this->locked($key,function()use($key):void{$this->db->prepare('DELETE FROM rate_limit_state WHERE identity_key=?')->execute([$key]);});
    }
    private function now():int{return $this->clock?($this->clock)():time();}
    private function assertBudget(string $key):void
    {
        $query=$this->db->prepare('SELECT failures,UNIX_TIMESTAMP(expires_at) AS expiry FROM rate_limit_state WHERE identity_key=?');$query->execute([$key]);$rate=$query->fetch(PDO::FETCH_ASSOC);
        if($rate&&(int)$rate['expiry']>$this->now()&&(int)$rate['failures']>=$this->limit)throw new HttpError(429,'Terlalu banyak percobaan. Silakan coba lagi nanti.');
    }
    private function increment(string $key):void
    {
        $now=$this->now();
        $query=$this->db->prepare('INSERT INTO rate_limit_state(identity_key,failures,window_started_at,expires_at) VALUES(?,1,FROM_UNIXTIME(?),FROM_UNIXTIME(?)) ON DUPLICATE KEY UPDATE failures=IF(expires_at<=FROM_UNIXTIME(?),1,failures+1),window_started_at=IF(expires_at<=FROM_UNIXTIME(?),FROM_UNIXTIME(?),window_started_at),expires_at=IF(expires_at<=FROM_UNIXTIME(?),FROM_UNIXTIME(?),expires_at)');
        $query->execute([$key,$now,$now+$this->window,$now,$now,$now,$now,$now+$this->window]);
    }
    private function locked(string $key,\Closure $operation):mixed
    {
        $name=hash('sha256','LaporKota auth:'.$this->db->query('SELECT DATABASE()')->fetchColumn().':'.$key);
        $query=$this->db->prepare('SELECT GET_LOCK(?,5)');$query->execute([$name]);
        if((int)$query->fetchColumn()!==1)throw new \RuntimeException('Authentication limiter unavailable');
        try{return $operation();}
        finally{
            $release=$this->db->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$name]);
            if((int)$release->fetchColumn()!==1)throw new \RuntimeException('Authentication limiter unavailable');
        }
    }
}
