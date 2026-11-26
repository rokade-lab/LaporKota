<?php
declare(strict_types=1);
namespace LaporKota;
final class Uploads
{
    private const MAX_BYTES=2097152;
    public function prepare(array $files):array
    {
        if(!$files)return [];
        if(array_keys($files)!==['photos'])throw new HttpError(400,'Kiriman foto tidak valid.');
        $photos=$files['photos'];$keys=['name','type','tmp_name','error','size'];
        foreach($keys as $key)if(!isset($photos[$key])||!is_array($photos[$key]))throw new HttpError(400,'Kiriman foto tidak valid.');
        $indices=array_keys($photos['name']);if(count($indices)>3)throw new HttpError(400,'Maksimal tiga foto per aduan.');
        foreach($keys as $key)if(array_keys($photos[$key])!==$indices)throw new HttpError(400,'Kiriman foto tidak valid.');
        $prepared=[];
        foreach($indices as $index){
            $name=$photos['name'][$index];$type=$photos['type'][$index];$temp=$photos['tmp_name'][$index];$error=$photos['error'][$index];$size=$photos['size'][$index];
            if(!is_string($name)||!is_string($type)||!is_string($temp)||!is_int($error)||!is_int($size))throw new HttpError(400,'Kiriman foto tidak valid.');
            if($error===UPLOAD_ERR_NO_FILE)continue;
            if($error!==UPLOAD_ERR_OK||$size<1||$size>self::MAX_BYTES||!is_uploaded_file($temp))throw new HttpError(400,'Foto maksimal 2 MiB dan harus terkirim utuh.');
            $bytes=file_get_contents($temp);if($bytes===false||strlen($bytes)<1||strlen($bytes)>self::MAX_BYTES)throw new HttpError(400,'Ukuran foto tidak valid.');
            $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);$info=@getimagesizefromstring($bytes);
            if(!in_array($mime,['image/png','image/jpeg'],true)||$type!==$mime||!$info||($info['mime']??null)!==$mime)throw new HttpError(400,'Gunakan foto JPEG atau PNG yang valid.');
            if($info[0]<1||$info[1]<1||$info[0]>10000||$info[1]>10000||$info[0]*$info[1]>20000000)throw new HttpError(400,'Dimensi foto maksimal 20 megapiksel dan 10.000 piksel per sisi.');
            $image=@imagecreatefromstring($bytes);if(!$image)throw new HttpError(400,'Foto tidak dapat dibaca.');
            ob_start();$ok=$mime==='image/png'?imagepng($image,null,6):imagejpeg($image,null,85);$encoded=ob_get_clean();imagedestroy($image);
            if(!$ok||$encoded===false||strlen($encoded)>self::MAX_BYTES)throw new HttpError(400,'Foto hasil pemrosesan melebihi batas 2 MiB.');
            $prepared[]=['bytes'=>$encoded,'mime'=>$mime,'extension'=>$mime==='image/png'?'png':'jpg'];
        }
        return $prepared;
    }
    private function directory():string
    {
        $dir=getenv('UPLOAD_DIR')?:dirname(__DIR__).'/var/uploads';
        if(!is_dir($dir)&&!@mkdir($dir,0700,true))throw new \RuntimeException('Photo storage unavailable');
        $real=realpath($dir);$web=realpath(dirname(__DIR__).'/public');
        if(!$real||$real===$web||str_starts_with($real,$web.'/')||!@chmod($real,0700))throw new \RuntimeException('Photo storage unavailable');
        return $real;
    }
    public function store(array $prepared):array
    {
        if(!$prepared)return [];
        $dir=$this->directory();$stored=[];
        try{
            foreach($prepared as $photo){
                $key=bin2hex(random_bytes(32)).'.'.$photo['extension'];$path=$dir.'/'.$key;
                $stream=@fopen($path,'xb');if(!$stream)throw new \RuntimeException('Photo write failed');
                $stored[]=['path'=>$path,'storage_key'=>$key,'mime'=>$photo['mime'],'size'=>strlen($photo['bytes'])];
                try{
                    if(!chmod($path,0600))throw new \RuntimeException('Photo permissions failed');
                    $written=0;while($written<strlen($photo['bytes'])){$n=fwrite($stream,substr($photo['bytes'],$written));if($n===false||$n===0)throw new \RuntimeException('Photo write failed');$written+=$n;}
                    if(!fflush($stream))throw new \RuntimeException('Photo write failed');
                }finally{fclose($stream);}
            }
            return $stored;
        }catch(\Throwable $error){$this->cleanup($stored);throw $error;}
    }
    public function cleanup(array $stored):void{foreach($stored as $photo)if(is_file($photo['path']))unlink($photo['path']);}
    public function download(array $photo):void
    {
        if(!preg_match('/^[a-f0-9]{64}\.(png|jpg)$/D',$photo['storage_key']))throw new HttpError(404,'Foto tidak ditemukan.');
        $dir=$this->directory();$path=realpath($dir.'/'.$photo['storage_key']);
        if(!$path||!str_starts_with($path,$dir.'/')||!is_file($path))throw new HttpError(404,'Foto tidak ditemukan.');
        header('Content-Type: '.$photo['mime_type']);header('X-Content-Type-Options: nosniff');header('Content-Length: '.filesize($path));
        header('Content-Disposition: inline; filename="foto-'.(int)$photo['id'].($photo['mime_type']==='image/png'?'.png':'.jpg').'"');readfile($path);
    }
}
