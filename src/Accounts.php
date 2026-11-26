<?php
declare(strict_types=1);
namespace LaporKota;
use PDO;
final class Accounts
{
    public function __construct(private PDO $db){}
    public function create(array $input,string $role):void
    {
        $username=$input['username']??null;$password=$input['password']??null;
        if(!is_string($username)||!preg_match('/^[a-zA-Z0-9_]{3,100}$/D',$username)||!is_string($password)||strlen($password)<12||strlen($password)>1024)throw new HttpError(400,'Periksa isian formulir.');
        $dinas=null;
        if($role==='petugas'){$dinas=Input::id($input,'dinas_id');$q=$this->db->prepare('SELECT id FROM dinas WHERE id=?');$q->execute([$dinas]);if(!$q->fetchColumn())throw new HttpError(400,'Dinas tidak valid.');}
        try{$this->db->prepare('INSERT INTO users(id,username,password_hash,password_scheme,role,dinas_id) VALUES(?,?,?,\'modern\',?,?)')->execute([random_int(1000000,281474976710655),$username,password_hash($password,PASSWORD_ARGON2ID,Foundation::POLICY),$role,$dinas]);}
        catch(\PDOException $error){if($error->getCode()==='23000')throw new HttpError(400,'Akun tidak dapat dibuat. Periksa nama pengguna.');throw $error;}
    }
}
