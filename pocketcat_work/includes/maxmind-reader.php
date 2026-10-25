<?php
declare(strict_types=1);

/**
 * PocketCat self-hosted MaxMind DB reader.
 * Implements the MaxMind DB v2 binary format without network access.
 * It intentionally exposes only the small subset needed by GeoLite2-City.
 */
final class PocketCatMaxMindReader {
    private $fh;
    private int $size;
    private int $nodeCount;
    private int $recordSize;
    private int $ipVersion;
    private int $treeSize;
    private int $dataBase;
    private array $metadata=[];

    public function __construct(string $file){
        if(!is_file($file) || !is_readable($file)) throw new RuntimeException('GeoIP database is missing or unreadable.');
        $this->fh=@fopen($file,'rb');
        if(!$this->fh) throw new RuntimeException('Unable to open GeoIP database.');
        $st=fstat($this->fh);$this->size=(int)($st['size']??0);
        if($this->size<64) throw new RuntimeException('GeoIP database is too small or invalid.');
        $marker="\xAB\xCD\xEFMaxMind.com";$ml=strlen($marker);$min=max(0,$this->size-131072);$metaStart=-1;
        for($p=$this->size-$ml;$p>=$min;$p--){ if($this->readAt($p,$ml)===$marker){$metaStart=$p+$ml;break;} }
        if($metaStart<0) throw new RuntimeException('GeoIP metadata marker was not found.');
        [$meta]= $this->decode($metaStart,$metaStart,0,[]);
        if(!is_array($meta)) throw new RuntimeException('GeoIP metadata is invalid.');
        $this->metadata=$meta;
        $this->nodeCount=(int)($meta['node_count']??0);$this->recordSize=(int)($meta['record_size']??0);$this->ipVersion=(int)($meta['ip_version']??0);
        if($this->nodeCount<1 || !in_array($this->recordSize,[24,28,32],true) || !in_array($this->ipVersion,[4,6],true)) throw new RuntimeException('Unsupported GeoIP database format.');
        $this->treeSize=(int)(($this->recordSize*2/8)*$this->nodeCount);$this->dataBase=$this->treeSize+16;
        if($this->dataBase>=$this->size) throw new RuntimeException('GeoIP database sections are invalid.');
    }
    public function close():void{if(is_resource($this->fh)){fclose($this->fh);$this->fh=null;}}
    public function __destruct(){ $this->close(); }
    public function metadata():array{return $this->metadata;}
    public function get(string $ip):?array{
        $packed=@inet_pton($ip);if($packed===false)throw new InvalidArgumentException('Invalid IP address.');
        $bits=strlen($packed)*8;if($this->ipVersion===4 && $bits===128) return null;
        if($this->ipVersion===6 && $bits===32){$node=$this->ipv4StartNode();}else{$node=0;}
        $bitCount=$bits;
        for($bit=0;$bit<$bitCount;$bit++){
            if($node===$this->nodeCount)return null;
            if($node>$this->nodeCount) return $this->decodeDataPointer($node);
            $byte=ord($packed[intdiv($bit,8)]);$bitValue=($byte >> (7-($bit%8))) & 1;
            $node=$this->readNodePointer($node,$bitValue);
        }
        if($node===$this->nodeCount)return null;
        if($node<$this->nodeCount)return null;
        return $this->decodeDataPointer($node);
    }
    private function ipv4StartNode():int{
        if($this->ipVersion===4)return 0;
        // Match the official MaxMind PHP reader: IPv4 lookups in an IPv6 tree
        // start after the first 96 zero-bit branches.
        $node=0;
        for($bit=0;$bit<96 && $node<$this->nodeCount;$bit++){$node=$this->readNodePointer($node,0);}
        return $node;
    }
    private function readNodePointer(int $node,int $right):int{
        $bytesPerNode=intdiv($this->recordSize*2,8);$offset=$node*$bytesPerNode;
        if($offset+$bytesPerNode>$this->treeSize)throw new RuntimeException('GeoIP search tree is corrupt.');
        if($this->recordSize===24){$base=$offset+($right?3:0);$b=unpack('C3',$this->readAt($base,3));return (($b[1]<<16)|($b[2]<<8)|$b[3]);}
        if($this->recordSize===32){$base=$offset+($right?4:0);$b=unpack('N',$this->readAt($base,4));return (int)$b[1];}
        $raw=$this->readAt($offset,7);$left=(ord($raw[0])<<16)|(ord($raw[1])<<8)|ord($raw[2])|((ord($raw[3])&0xF0)<<16);$rightValue=(ord($raw[4])<<16)|(ord($raw[5])<<8)|ord($raw[6])|((ord($raw[3])&0x0F)<<24);
        return $right?$rightValue:$left;
    }
    private function decodeDataPointer(int $pointer):?array{
        $offset=$pointer-$this->nodeCount+$this->treeSize;if($offset<0||$offset>=$this->size)throw new RuntimeException('GeoIP data pointer is corrupt.');
        [$data]= $this->decode($offset,$this->dataBase,0,[]);return is_array($data)?$data:null;
    }
    private function readAt(int $offset,int $length):string{if($offset<0||$length<0||$offset+$length>$this->size)throw new RuntimeException('GeoIP read outside database.');if($length===0)return '';if(fseek($this->fh,$offset)!==0)throw new RuntimeException('GeoIP seek failed.');$v=fread($this->fh,$length);if($v===false||strlen($v)!==$length)throw new RuntimeException('GeoIP read failed.');return $v;}
    private function decode(int $offset,int $pointerBase,int $depth,array $stack):array{
        if($depth>32)throw new RuntimeException('GeoIP record nesting limit exceeded.');
        $ctrl=ord($this->readAt($offset,1));$offset++;$type=$ctrl>>5;
        if($type===0){$ext=ord($this->readAt($offset,1));$offset++;$type=$ext+7;}
        if($type===1){[$ptr,$next]=$this->decodePointer($ctrl,$offset,$pointerBase);if(isset($stack[$ptr]))throw new RuntimeException('GeoIP pointer cycle detected.');$stack[$ptr]=1;[$v,$after]=$this->decode($ptr,$pointerBase,$depth+1,$stack);return [$v,$next];}
        [$size,$offset]=$this->decodeSize($ctrl,$offset);
        switch($type){
            case 2:return [$this->readAt($offset,$size),$offset+$size];
            case 3: $v=unpack('E',$this->readAt($offset,8));return [(float)$v[1],$offset+$size];
            case 4:return [$this->readAt($offset,$size),$offset+$size];
            case 5:case 6:case 9:case 10:return [$this->decodeUnsigned($this->readAt($offset,$size)),$offset+$size];
            case 7:$map=[];$cur=$offset;for($i=0;$i<$size;$i++){[$k,$cur]=$this->decode($cur,$pointerBase,$depth+1,$stack);[$v,$cur]=$this->decode($cur,$pointerBase,$depth+1,$stack);if(is_string($k))$map[$k]=$v;}return [$map,$cur];
            case 8:return [$this->decodeSigned($this->readAt($offset,$size)),$offset+$size];
            case 11:$arr=[];$cur=$offset;for($i=0;$i<$size;$i++){[$v,$cur]=$this->decode($cur,$pointerBase,$depth+1,$stack);$arr[]=$v;}return [$arr,$cur];
            case 14:return [$size!==0,$offset];
            case 15:$v=unpack('G',$this->readAt($offset,4));return [(float)$v[1],$offset+$size];
            default:throw new RuntimeException('Unsupported GeoIP data type '.$type.'.');
        }
    }
    private function decodePointer(int $ctrl,int $offset,int $pointerBase):array{
        $size=(($ctrl>>3)&3)+1;$b=$this->readAt($offset,$size);$offset+=$size;
        if($size===1){$x=unpack('n',chr($ctrl&7).$b);return [(int)$x[1]+$pointerBase,$offset];}
        if($size===2){$x=unpack('N',"\0".chr($ctrl&7).$b);return [(int)$x[1]+$pointerBase+2048,$offset];}
        if($size===3){$x=unpack('N',chr($ctrl&7).$b);if($x===false)throw new RuntimeException('GeoIP pointer decode failed.');return [(int)$x[1]+$pointerBase+526336,$offset];}
        $x=$this->decodeUnsigned($b);if(!is_int($x))$x=(int)$x;return [$x+$pointerBase,$offset];
    }
    private function decodeSize(int $ctrl,int $offset):array{$size=$ctrl&31;if($size<29)return[$size,$offset];$n=$size-28;$raw=$this->readAt($offset,$n);if($size===29)$size=29+ord($raw);elseif($size===30){$x=unpack('n',$raw);$size=285+(int)$x[1];}else{$x=unpack('N',"\0".$raw);$size=65821+(int)$x[1];}return[$size,$offset+$n];}
    private function decodeUnsigned(string $bytes){$n=strlen($bytes);$v=0;for($i=0;$i<$n;$i++){if($v>PHP_INT_MAX/256)return sprintf('%u',0)+0; $v=($v<<8)|ord($bytes[$i]);}return $v;}
    private function decodeSigned(string $bytes):int{$n=strlen($bytes);if($n===0)return 0;$u=$this->decodeUnsigned($bytes);if($n===4 && is_int($u) && $u>=2147483648)return $u-4294967296;return (int)$u;}
}
