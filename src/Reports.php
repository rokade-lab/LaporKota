<?php
declare(strict_types=1);
namespace LaporKota;
use PDO;
final class Reports
{
    public function __construct(private PDO $db){}
    private function scope(array $user,string $alias='r'):array
    {
        return match($user['role']){'admin'=>['1=1',[]],'petugas'=>[$alias.'.dinas_id=?',[$user['dinas_id']]],default=>[$alias.'.author_id=?',[$user['id']]]};
    }
    public function create(array $user,array $input,array $files=[]):int
    {
        if($user['role']!=='warga')throw new HttpError(403,'Tindakan tidak diizinkan.');
        $title=Input::text($input,'title',1,200);$description=Input::text($input,'description',1,5000);$location=Input::text($input,'location_text',1,255);$category=Input::id($input,'category_id');
        $q=$this->db->prepare('SELECT id FROM categories WHERE id=?');$q->execute([$category]);if(!$q->fetchColumn())throw new HttpError(400,'Kategori tidak valid.');
        $uploads=new Uploads();$prepared=$uploads->prepare($files);$stored=[];
        $id=random_int(1000000,281474976710655);
        $this->db->beginTransaction();
        try {
            $this->db->prepare("INSERT INTO reports(id,author_id,title,description,category_id,location_text,dinas_id,status,created_at) VALUES(?,?,?,?,?,?,NULL,'baru',UTC_TIMESTAMP())")->execute([$id,$user['id'],$title,$description,$category,$location]);
            $this->db->prepare("INSERT INTO report_history(report_id,actor_id,from_status,to_status,note,visibility,created_at) VALUES(?,?,NULL,'baru','Aduan diterima','warga',UTC_TIMESTAMP())")->execute([$id,$user['id']]);
            $stored=$uploads->store($prepared);
            foreach($stored as $photo)$this->db->prepare('INSERT INTO attachments(report_id,storage_key,mime_type,size_bytes,created_at) VALUES(?,?,?,?,UTC_TIMESTAMP())')->execute([$id,$photo['storage_key'],$photo['mime'],$photo['size']]);
            $this->db->commit();return $id;
        } catch(\Throwable $error){if($this->db->inTransaction())$this->db->rollBack();$uploads->cleanup($stored);throw $error;}
    }
    public function assign(array $user,array $input):int
    {
        if($user['role']!=='admin')throw new HttpError(403,'Tindakan tidak diizinkan.');
        $id=Input::id($input,'id');$dinas=Input::id($input,'dinas_id');$internal=Input::text($input,'internal_note',0,5000);
        $q=$this->db->prepare('SELECT id FROM dinas WHERE id=?');$q->execute([$dinas]);if(!$q->fetchColumn())throw new HttpError(400,'Dinas tidak valid.');
        $this->db->beginTransaction();
        try{
            $q=$this->db->prepare('SELECT * FROM reports WHERE id=? FOR UPDATE');$q->execute([$id]);$report=$q->fetch(PDO::FETCH_ASSOC);
            if(!$report)throw new HttpError(404,'Aduan tidak ditemukan.');if($report['status']!=='baru')throw new HttpError(409,'Status aduan sudah berubah.');
            $this->db->prepare("UPDATE reports SET status='ditugaskan',dinas_id=? WHERE id=?")->execute([$dinas,$id]);
            $this->history($report,$user,'ditugaskan','Aduan diverifikasi dan ditugaskan ke dinas.','warga');
            if($internal!=='')$this->history($report,$user,'ditugaskan',$internal,'internal');
            $this->db->commit();return $id;
        }catch(\Throwable $error){$this->db->rollBack();throw $error;}
    }
    public function reject(array $user,array $input):int
    {
        if($user['role']!=='admin')throw new HttpError(403,'Tindakan tidak diizinkan.');
        $id=Input::id($input,'id');$note=Input::text($input,'note',1,5000);$internal=Input::text($input,'internal_note',0,5000);
        if(trim($note)==='')throw new HttpError(400,'Alasan penolakan wajib diisi.');
        $this->db->beginTransaction();
        try{
            $q=$this->db->prepare('SELECT * FROM reports WHERE id=? FOR UPDATE');$q->execute([$id]);$report=$q->fetch(PDO::FETCH_ASSOC);
            if(!$report)throw new HttpError(404,'Aduan tidak ditemukan.');if($report['status']!=='baru')throw new HttpError(409,'Status aduan sudah berubah.');
            $this->db->prepare("UPDATE reports SET status='ditolak',dinas_id=NULL WHERE id=?")->execute([$id]);
            $this->history($report,$user,'ditolak',$note,'warga');if($internal!=='')$this->history($report,$user,'ditolak',$internal,'internal');
            $this->db->commit();return $id;
        }catch(\Throwable $error){$this->db->rollBack();throw $error;}
    }
    public function process(array $user,array $input):int
    {
        if($user['role']!=='petugas')throw new HttpError(403,'Tindakan tidak diizinkan.');
        $id=Input::id($input,'id');$to=Input::text($input,'status',1,30);$note=Input::text($input,'note',0,5000);$internal=Input::text($input,'internal_note',0,5000);
        if(!in_array($to,['baru','ditugaskan','diproses','selesai','ditolak'],true))throw new HttpError(400,'Status tidak valid.');
        if($to==='selesai'&&trim($note)==='')throw new HttpError(400,'Catatan tindak lanjut wajib diisi.');
        $this->db->beginTransaction();
        try{
            $q=$this->db->prepare('SELECT * FROM reports WHERE id=? FOR UPDATE');$q->execute([$id]);$report=$q->fetch(PDO::FETCH_ASSOC);
            if(!$report)throw new HttpError(404,'Aduan tidak ditemukan.');
            $expected=['diproses'=>'ditugaskan','selesai'=>'diproses'];if(!isset($expected[$to])||$report['status']!==$expected[$to])throw new HttpError(409,'Transisi status tidak diizinkan.');
            $this->db->prepare('UPDATE reports SET status=? WHERE id=?')->execute([$to,$id]);
            $this->history($report,$user,$to,$note!==''?$note:'Aduan sedang diproses oleh dinas.','warga');if($internal!=='')$this->history($report,$user,$to,$internal,'internal');
            $this->db->commit();return $id;
        }catch(\Throwable $error){$this->db->rollBack();throw $error;}
    }
    private function history(array $report,array $actor,string $to,string $note,string $visibility):void
    {
        $this->db->prepare('INSERT INTO report_history(report_id,actor_id,from_status,to_status,note,visibility,created_at) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP())')->execute([$report['id'],$actor['id'],$report['status'],$to,$note,$visibility]);
    }
    public function listing(array $user,array $input):array
    {
        $search=Input::text($input,'q',0,200);$status=Input::text($input,'status',0,30);$sort=Input::text($input,'sort',0,30,'created_at');$direction=Input::text($input,'direction',0,8,'desc');
        $columns=['created_at'=>'r.created_at','status'=>'r.status'];$directions=['asc'=>'ASC','desc'=>'DESC'];
        if(!isset($directions[$direction]))throw new HttpError(400,'Filter atau urutan tidak valid.');
        [$scope,$values]=$this->scope($user);$where=[$scope];
        if($search!==''){$literal=strtr(str_replace("'","''",$search),['!'=>'!!','%'=>'!%','_'=>'!_']);$where[]="r.title LIKE '%".$literal."%' ESCAPE '!'";}
        if($status!=='')$where[]="r.status='".str_replace("'","''",$status)."'";
        $column=$columns[$sort]??$sort;
        $q=$this->db->prepare('SELECT r.id,r.title,r.description,r.category_id,r.location_text,r.dinas_id,r.status,r.created_at FROM reports r WHERE '.implode(' AND ',$where).' ORDER BY '.$column.' '.$directions[$direction].',r.id '.$directions[$direction]);
        $q->execute($values);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public function attachment(array $user,int $id):array
    {
        [$scope,$values]=$this->scope($user);$q=$this->db->prepare('SELECT a.* FROM attachments a JOIN reports r ON r.id=a.report_id WHERE a.id=? AND '.$scope);$q->execute([$id,...$values]);$photo=$q->fetch(PDO::FETCH_ASSOC);
        if(!$photo)throw new HttpError(404,'Foto tidak ditemukan.');return $photo;
    }
    public function detail(array $user,int $id,bool $residentPage=false):array
    {
        [$scope,$values]=$residentPage&&$user['role']==='warga'?['1=1',[]]:$this->scope($user);
        $q=$this->db->prepare('SELECT r.*,c.name AS category_name,d.name AS dinas_name FROM reports r JOIN categories c ON c.id=r.category_id LEFT JOIN dinas d ON d.id=r.dinas_id WHERE r.id=? AND '.$scope);$q->execute([$id,...$values]);$report=$q->fetch(PDO::FETCH_ASSOC);
        if(!$report)throw new HttpError(404,'Aduan tidak ditemukan.');
        if($residentPage&&$user['role']==='warga'&&(int)$report['author_id']!==(int)$user['id']){$report['history']=[];$report['attachments']=[];return $report;}
        $q=$this->db->prepare('SELECT h.id,h.from_status,h.to_status,h.note,h.visibility,h.created_at,u.username AS actor FROM report_history h JOIN users u ON u.id=h.actor_id WHERE h.report_id=?'.($user['role']==='warga'?" AND h.visibility='warga'":'').' ORDER BY h.created_at ASC,h.id ASC');$q->execute([$id]);$report['history']=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$this->db->prepare('SELECT id,mime_type,size_bytes,created_at FROM attachments WHERE report_id=? ORDER BY id');$q->execute([$id]);$report['attachments']=$q->fetchAll(PDO::FETCH_ASSOC);
        return $report;
    }
    public const PER_PAGE=10;
    public static function paginate(array $rows,array $input):array
    {
        $raw=$input['page']??'1';if(is_int($raw))$raw=(string)$raw;
        if(!is_string($raw)||!preg_match('/^[1-9][0-9]{0,5}$/D',$raw))throw new HttpError(400,'Halaman tidak valid.');
        $page=(int)$raw;$total=count($rows);
        return ['reports'=>array_slice($rows,($page-1)*self::PER_PAGE,self::PER_PAGE),'page'=>$page,'per_page'=>self::PER_PAGE,'total'=>$total,'pages'=>max(1,(int)ceil($total/self::PER_PAGE))];
    }
}
