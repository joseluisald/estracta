<?php
namespace Model;
error_reporting(0);

class Spider
{
    public $cookie;
    public $url = 'http://www.sintegra.fazenda.pr.gov.br/sintegra/';
    private $return;

    public function getCookie()
    {
        $ch = curl_init($this->url);
        $options = array(
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Connection: keep-alive',
                'Content-Type: application/x-www-form-urlencoded',
                'Host: www.sintegra.fazenda.pr.gov.br',
                'Origin: http://www.sintegra.fazenda.pr.gov.br',
                'Referer: '.$this->url,
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36',
            ),
        );

        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches);
        $this->cookie = $matches[1];
        curl_close($ch);
    }

    public function set($postData)
    {
        return $this->send($postData['captcha'], $postData['cnpj']);
    }

    private function send($CodImage, $Cnpj)
    {
        $dados = array
        (
            '_method' => 'POST',
            'data[Sintegra1][CodImage]' => $CodImage,
            'data[Sintegra1][Cnpj]' => $Cnpj,
            'empresa' => 'Consultar Empresa',
            'data[Sintegra1][Cadicms]' => '',
            'data[Sintegra1][CadicmsProdutor]' => '',
            'data[Sintegra1][CnpjCpfProdutor]' => '',
        );

        $postData = http_build_query($dados, NULL,'&');

        $ch = curl_init($this->url.'sintegra1/');
        $options = array(
            CURLOPT_COOKIEJAR => __DIR__.'/cookie.txt',
            CURLOPT_HTTPHEADER => array(
                'Connection: keep-alive',
                'Content-Type: application/x-www-form-urlencoded',
                'Cookie: '.$this->cookie[0],
                'Host: www.sintegra.fazenda.pr.gov.br',
                'Origin: http://www.sintegra.fazenda.pr.gov.br',
                'Referer: '.$this->url,
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36',
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
        );

        curl_setopt_array($ch, $options);
        $this->return = curl_exec($ch);
        $res = array();
        $res[] = $this->return();
        while(true)
        {
            if($this->loopNext())
            {
                $res[] = $this->return();
                continue;
            }
            else
            {
                break;
            }
        }
        return $res;
        curl_close($ch);
    }

    private function loopNext()
    {
        if($this->verifyNextIe())
        {
            preg_match_all('/<input.*?name=".*?" value="(.*?)".*?>/sim', $this->return, $nextIe);
            $this->return = $this->nextIe($nextIe[1][1]);
            return true;
        }
        else
        {
            return false;
        }
    }

    private function return()
    {
        if($this->verifyTable())
        {
            return $this->generateError();
        }
        else
        {
            return $this->generateArray();
        }
    }

    public function getParams()
    {
        $url = 'http://www.sintegra.fazenda.pr.gov.br/sintegra/captcha?'.(float)rand()/(float)getrandmax();
        $ch = curl_init($url);

        $options = array(
            CURLOPT_HTTPHEADER => array(
                'Connection: keep-alive',
                'Content-Type: application/x-www-form-urlencoded',
                'Cookie: '.$this->cookie[0],
                'Host: www.sintegra.fazenda.pr.gov.br',
                'Origin: http://www.sintegra.fazenda.pr.gov.br',
                'Referer: '.$this->url,
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36',
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_BINARYTRANSFER => true
        );

        curl_setopt_array($ch, $options);
        $img = curl_exec($ch);
        curl_close($ch);

        if (@imagecreatefromstring($img) == false)
        {
            throw new Exception('Não foi possível capturar o captcha');
        }

        return array(
            'cookie' => $this->cookie[0],
            'captchaBase64' => 'data:image/png;base64,'. base64_encode($img)
        );
    }

    private function verifyTable()
    {
        preg_match_all('/<table class="(.*?)">(.*?)<\/table>/sim', $this->return, $table);
        if($table[1][0]  == 'erro_table')
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    private function verifyNextIe()
    {
        preg_match_all('/<table class="form_conteudo">.*?<button .*? (id="consultar" name="consultar") .*?>.*?<\/table>/si', $this->return, $consultar);

        if($consultar[1][0] == 'id="consultar" name="consultar"')
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    private function generateError()
    {
        preg_match_all('/<table class="erro_table">.*?(.*).*?<\/table>/sim', $this->return, $t);
        preg_match_all('/.*<td.*?>.*?Servi.*?>.*?<.*?>(.*?)<\/td>.*/si', $t[0][0], $servico);
        preg_match_all('/.*<td.*?>.*?Data \/ Hora.*?>.*?<.*?>(.*?)<\/td>.*/si', $t[0][0], $data_hora);
        preg_match_all('/.*<td.*?>.*?Motivo.*?>.*?<.*?>(.*?)<\/td>.*/si', $t[0][0], $motivo);

        $arr = array();
        $arr['servico'] = utf8_encode($servico[1][0]);
        $arr['data_hora'] = utf8_encode($data_hora[1][0]);
        $arr['motivo'] = utf8_encode($motivo[1][0]);

        return $arr;
    }

    private function generateArray()
    {
        $arr = array();
        preg_match_all('/<table class="form_tabela_consulta">(.*?)<\/table>/sim', $this->return , $t);
        preg_match_all('/<td.*?>.*?CNPJ.*?>.*?<.*?>(.*?)<\/td>/si', $t[0][0], $cnpj);
        preg_match_all('/<td.*?>.*?Estadual.*?>.*?<.*?>(.*?)<\/td>/si', $t[0][0], $ie);
        preg_match_all('/<td.*?>.*?Empresarial.*?>.*?<.*?>(.*?)<\/td>/si', $t[0][0], $razao_social);
        preg_match_all('/<td.*?>.*?Logradouro.*?>.*?<.*?>(.*?)<\/td>/si', $t[0][1], $lagradouro);
        preg_match_all('/<td.*?>.*?N&uacute;mero.*?>.*?<.*?>(.*?)<\/td>/si', $t[0][1], $numero);
        preg_match_all('/<td.*?>.*?Complemento.*?>.*?<.*?>(.*?)<\/td>/si', $t[0][1], $complemento);
        preg_match_all('/<td.*?>.*?Bairro.*?>.*?<.*?>(.*?)<\/td>/si', $t[0][1], $bairro);
        preg_match_all('/<td.*?>.*?Munic&iacute;pio.*?>.*?<.*?>(.*?)<\/td>/si', $t[0][1], $municipio);
        preg_match_all('/<td.*?>.*?UF.*?>.*?<.*?>(.*?)<\/td>/si', $t[0][1], $uf);
        preg_match_all('/<td.*?>.*?CEP.*?>.*?<.*?>(.*?)<\/td>/si', $t[0][1], $cep);
        preg_match_all('/<td.*?>.*?Telefone.*?>.*?<.*?>(.*?)<\/td>/si', $t[0][1], $telefone);
        preg_match_all('/<td.*?>.*?E-mail.*?>.*?<.*?>(.*?)<\/td>/si', $t[0][1], $email);
        preg_match_all('/<td.*?>.*?Atividade.*?Principal.*?>.*?<.*?>(.*?)<\/td>/si', $t[0][2], $atividade_principal);
        preg_match_all('/<td.*?>.*?o.*?Cadastral.*?>.*?<.*?>(.*?)<\/td>/si', $t[0][2], $situacao_cadastral);
        preg_match_all('/<td.*?>.*?das.*Atividades.*?>.*?<.*?>(.*?)<\/td>/si', $t[0][2], $data_inico);

        $arr['cnpj'] = utf8_encode($cnpj[1][0]);
        $arr['ie'] = utf8_encode($ie[1][0]);
        $arr['razao_social'] = utf8_encode($razao_social[1][0]);
        $arr['lagradouro'] = utf8_encode($lagradouro[1][0]);
        $arr['numero'] = utf8_encode($numero[1][0]);
        $arr['complemento'] = utf8_encode($complemento[1][0]);
        $arr['bairro'] = utf8_encode($bairro[1][0]);
        $arr['municipio'] = utf8_encode($municipio[1][0]);
        $arr['uf'] = utf8_encode($uf[1][0]);
        $arr['cep'] = utf8_encode($cep[1][0]);
        $arr['telefone'] = utf8_encode($telefone[1][0]);
        $arr['email'] = utf8_encode($email[1][0]);
        $situacao_cadastral = explode(' - DESDE ', $situacao_cadastral[1][0]);
        $situacao_atual = preg_replace('/\s+(.+)/', '$1', $situacao_cadastral[0], 1);
        $arr['situacao_atual'] = $situacao_atual;
        $arr['data_situacao_atual'] = $situacao_cadastral[1];
        $data_inico = preg_replace('/\s+(.+)/', '$1', $data_inico[1][0], 1);
        $arr['data_inico'] = $data_inico;
        $atividade_principal = explode(' - ', $atividade_principal[1][0]);
        $arr['atividade_principal']['codigo'] = utf8_encode($atividade_principal[0]);
        $arr['atividade_principal']['descricao'] = utf8_encode($atividade_principal[1]);

        return $arr;
    }

    private function nextIe($campoAnterior)
    {
        $dados = array
        (
            '_method' => 'POST',
            'data[Sintegra1][campoAnterior]' => $campoAnterior,
            'consultar' => '',
        );

        $postData = http_build_query($dados, NULL,'&');

        $ch = curl_init($this->url.'sintegra1/consultar');
        $options = array(
            CURLOPT_COOKIEJAR => __DIR__.'/cookie.txt',
            CURLOPT_COOKIEFILE => __DIR__.'/cookie.txt',
            CURLOPT_HTTPHEADER => array(
                'Connection: keep-alive',
                'Content-Type: application/x-www-form-urlencoded',
                'Host: www.sintegra.fazenda.pr.gov.br',
                'Origin: http://www.sintegra.fazenda.pr.gov.br',
                'Referer: '.$this->url,
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36',
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
        );

        curl_setopt_array($ch, $options);
        $return = curl_exec($ch);
        return $return;
        curl_close($ch);
    }
}