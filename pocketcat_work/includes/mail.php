<?php
function smtp_send(string $to,string $subject,string $body):array{
 $host=trim((string)setting('smtp_host',''));$port=(int)setting('smtp_port','587');$user=trim((string)setting('smtp_username',''));$pass=secret_setting('smtp_password','');$from=trim((string)setting('smtp_from_email','support@pocketcat.online'));$fromName=trim((string)setting('smtp_from_name','PocketCat'));
 if(!$host)return[false,'SMTP is not configured.'];
 $transport=$port===465?'ssl':'tcp';$target=$transport==='ssl'?'ssl://'.$host.':'.$port:$host.':'.$port;
 $errno=0;$err='';$fp=@stream_socket_client($target,$errno,$err,15,STREAM_CLIENT_CONNECT);if(!$fp)return[false,'SMTP connection failed.'];stream_set_timeout($fp,15);
 $read=function()use($fp){$out='';while(($line=fgets($fp,515))!==false){$out.=$line;if(isset($line[3])&&$line[3]===' ')break;}return $out;};
 $send=function(string $cmd)use($fp,$read){fwrite($fp,$cmd."\r\n");return $read();};
 $banner=$read();if(strpos($banner,'220')!==0){fclose($fp);return[false,'SMTP server rejected the connection.'];}
 $r=$send('EHLO pocketcat.local');
 if(strpos($r,'250')!==0){fclose($fp);return[false,'SMTP EHLO failed.'];}
 if($port!==465){$r=$send('STARTTLS');if(strpos($r,'220')!==0){fclose($fp);return[false,'SMTP STARTTLS failed.'];}if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)){fclose($fp);return[false,'SMTP TLS negotiation failed.'];}$r=$send('EHLO pocketcat.local');}
 if($user!==''&&$pass!==''){if(strpos($send('AUTH LOGIN'),'334')!==0||strpos($send(base64_encode($user)),'334')!==0||strpos($send(base64_encode($pass)),'235')!==0){fclose($fp);return[false,'SMTP authentication failed.'];}}
 if(strpos($send('MAIL FROM:<'.$from.'>'),'250')!==0||strpos($send('RCPT TO:<'.$to.'>'),'250')!==0||strpos($send('DATA'),'354')!==0){fclose($fp);return[false,'SMTP message envelope was rejected.'];}
 $headers="From: ".($fromName!==''?$fromName.' ':'')."<".$from.">\r\nTo: <".$to.">\r\nSubject: ".preg_replace('/[\r\n]+/',' ', $subject)."\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";$payload=$headers."\r\n".preg_replace('/^\./m','..',$body)."\r\n.";fwrite($fp,$payload."\r\n");$result=$read();$send('QUIT');fclose($fp);return[strpos($result,'250')===0,$result];
}
