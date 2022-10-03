<?php
require_once('Model/Spider.php');

use \Model\Spider;
$spider = new Spider();

$spider->getCookie();
$parans = $spider->getParams();

$cnpj = readline('CNPJ: ');
exec('start chrome '. $parans['captchaBase64']);
$captcha = readline('CAPTCHA: ');

$cnpj = preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "\$1.\$2.\$3/\$4-\$5", $cnpj);
$return = $spider->set(array('cnpj' => $cnpj, 'captcha' => $captcha));

print_r($return);
