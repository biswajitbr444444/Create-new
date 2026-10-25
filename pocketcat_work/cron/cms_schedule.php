<?php
declare(strict_types=1);
require __DIR__.'/../bootstrap.php';
if(PHP_SAPI!=='cli') exit("CLI only\n");
$published=cms_publish_scheduled();
echo "published {$published}\n";
