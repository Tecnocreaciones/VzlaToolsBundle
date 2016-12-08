<?php

/*
 * This file is part of the TecnoCreaciones package.
 * 
 * (c) www.tecnocreaciones.com.ve
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tecnocreaciones\Vzla\ToolsBundle\Tools;

use Tecnocreaciones\Vzla\ToolsBundle\Model\Rif;

/**
 * Description of RifTools
 *
 * @author Carlos Mendoza <inhack20@tecnocreaciones.com>
 */
class RifTool implements \Symfony\Component\DependencyInjection\ContainerAwareInterface
{   
    /**
     * 
     * @var String
     */
    private $url = 'http://contribuyente.seniat.gob.ve/getContribuyente/getrif';
    
    /**
     *
     * @var String
     */
    private $_rif;
    
    /**
     * Traductor
     * @var \Symfony\Component\Translation\TranslatorInterface
     */
    protected $translator;
    
    protected $container;

    /**
     * Obtiene el rif
     * @param type $rifString
     * @return Rif
     * @throws \Exception
     */
    public function getRif($rifString){
        $rif = new Rif();
        $rif->setOriginalRif($rifString);
        if($this->isValidFormat($rifString)){
            if($this->isValidCheckDigit($rifString)){
                $parameters = array(
                    'rif' => $this->normalizeRif($rifString)
                );
                
                $client = new \GuzzleHttp\Client();
                $res = null;
                $timeout = 4.00;
                try{
                    $res = $client->request('GET', $this->url.'?'.http_build_query($parameters),[
                        'timeout' => $timeout,
                        'connect_timeout' => $timeout,
                    ]);
                }  catch (\GuzzleHttp\Exception\RequestException $e){
                    if ($e->hasResponse()) {
                        if($e->getResponse()->getStatusCode() === 452){
                            $rif
                                ->setCodeResponse(Rif::STATUS_ERROR_RIF_DOES_NOT_EXIST)    
                                ->setMessage(utf8_encode(((string)$e->getResponse()->getBody())));
                        }
                    }else{
                        $rif
                        ->setCodeResponse(Rif::STATUS_ERROR_SERVER_DOWN)
                        ->setMessage($this->buildMessage('tecnocreaciones.vzlatools.the_seniat_server_is_not_available_at_this_time'));
                    }
                }
                if($res === null){
                    return $rif;
                }
                $response = (string)$res->getBody();
                
                if($res->getStatusCode() == 404){
                    $rif
                    ->setCodeResponse(Rif::STATUS_ERROR_SERVER_DOWN)
                    ->setMessage($this->buildMessage('tecnocreaciones.vzlatools.the_seniat_server_is_not_available_at_this_time'));
                }else{
                    try {
                        if(substr($response,0,1)!= '<' ) {
                            throw new \Exception($response);
                        }

                        $xml = simplexml_load_string($response);

                        if(!is_bool($xml)) {
                            $elements = $xml->children('rif');
                            $rif->setRif($rifString);
                            foreach($elements as $key => $node) {
                                $value = (string)$node;
                                switch ($node->getName()){
                                    case 'Nombre':
                                        $rif->setName($value);
                                        break;
                                    case 'AgenteRetencionIVA':
                                        if($value === 'SI'){
                                            $rif->setWithholdingAgentVAT(true);
                                        }
                                        break;
                                    case 'ContribuyenteIVA':
                                        if($value === 'SI'){
                                            $rif->setContributorVAT(true);
                                        }
                                        break;
                                    case 'Tasa':
                                        $rif->setRate($value);
                                        break;
                                }
                            }
                            $rif->setCodeResponse(Rif::STATUS_OK);
                        }
                    } catch(\Exception $e) {
                        if($response == ''){
                            $rif
                                ->setCodeResponse(Rif::STATUS_ERROR_COULD_NOT_CONNECT_TO_SERVER)
                                ->setMessage($this->buildMessage('tecnocreaciones.vzlatools.could_not_connect_to_server'));
                        }else{
                            $rif
                                ->setCodeResponse(Rif::STATUS_ERROR_RIF_DOES_NOT_EXIST)
                                ->setMessage($this->buildMessage('tecnocreaciones.vzlatools.the_rif_does_not_exist'));
                        }
                    }
                }
            }else{
                $rif
                ->setCodeResponse(Rif::STATUS_ERROR_INVALID_CHECK_DIGIT)
                ->setMessage($this->buildMessage('tecnocreaciones.vzlatools.invalid_check_digit'));
            }
        }else{
            $rif
                ->setCodeResponse(Rif::STATUS_ERROR_INVALID_RIF_FORMAT)
                ->setMessage($this->buildMessage('tecnocreaciones.vzlatools.invalid_format_rif'));
        }
        return $rif;
    }
    
    /**
     * Validar formato del RIF
     * 
     * @return boolean 
     */
    public function isValidFormat($rif) {
        $rif = str_replace('-', '', strtoupper($rif));
        $retorno = (bool)preg_match("/^([VEJPG]{1})([0-9]{9}$)/", $rif);
        return $retorno;
    }
    
    public function normalizeRif($rif) {
        return str_replace('-', '', strtoupper($rif));
    }
    
    /**
     * Validar el digito verificador del RIF
     * 
     * Basado en el método módulo 11 para el cálculo del dígito verificador 
     * y aplicando las modificaciones propias ejecutadas por el seniat
     * @link http://es.wikipedia.org/wiki/C%C3%B3digo_de_control#C.C3.A1lculo_del_d.C3.ADgito_verificador
     * 
     * @return boolean 
     */
    function isValidCheckDigit($rif) {
        $rif = str_replace('-', '', strtoupper($rif));
            $digitos = str_split($rif);
           
            $digitos[8] *= 2; 
            $digitos[7] *= 3; 
            $digitos[6] *= 4; 
            $digitos[5] *= 5; 
            $digitos[4] *= 6; 
            $digitos[3] *= 7; 
            $digitos[2] *= 2; 
            $digitos[1] *= 3; 

            $digitoVerificador = $this->getCheckDigit($rif);
            
            if ($digitoVerificador != $digitos[9]) {
                return false;
            }
        return true;
    }
    
    /**
     * Obtiene el digito verificador del rif
     * @param type $rif
     * @return string
     * @link http://es.wikipedia.org/wiki/C%C3%B3digo_de_control#C.C3.A1lculo_del_d.C3.ADgito_verificador
     */
    function getCheckDigit($rif) {
        $rif = str_replace('-', '', strtoupper($rif));
            $digitos = str_split($rif);
            //Rif invalido
            if(count($digitos) < 9){
                return null;
            }
            $digitos[8] *= 2; 
            $digitos[7] *= 3; 
            $digitos[6] *= 4; 
            $digitos[5] *= 5; 
            $digitos[4] *= 6; 
            $digitos[3] *= 7; 
            $digitos[2] *= 2; 
            $digitos[1] *= 3; 
            
            // Determinar dígito especial según la inicial del RIF
            // Regla introducida por el SENIAT
            switch ($digitos[0]) {
                case 'V':
                    $digitoEspecial = 1;
                    break;
                case 'E':
                    $digitoEspecial = 2;
                    break;
                case 'J':
                    $digitoEspecial = 3;
                    break;
                case 'P':
                    $digitoEspecial = 4;
                    break;
                case 'G':
                    $digitoEspecial = 5;
                    break;
            }
            $lastDigit = 0;
            if(isset($digitos[9])){
                $lastDigit = $digitos[9];
            }
            $suma = (array_sum($digitos) - $lastDigit) + ($digitoEspecial*4);
            $residuo = $suma % 11;
            $resta = 11 - $residuo;
            
            $digitoVerificador = ($resta >= 10) ? 0 : $resta;
            
        return $digitoVerificador;
    }
    
    /**
     * Completar un rif con cero a la izquerda
     * @param type $rif
     * @return type
     */
    function completeLeftRif($rif) {
        $rif = $this->normalizeRif($rif);
        $digitos = str_split($rif);
        $countRif = count($digitos);
        //Rif completo
        if($countRif == 10){
            return $rif;
        } else if($countRif == 9){
            $rif .= $this->getCheckDigit($rif);
            return $rif;
        } else if($countRif < 9){
            //Rif incompleto hay que completar con cero a la izquerda
            $onlyNumber = "";
            for($i = 1;$i < $countRif; $i++){
                $onlyNumber .= $digitos[$i];
            }
            $newRif = $digitos[0].str_pad($onlyNumber,8,"0",STR_PAD_LEFT);
            if(strlen($newRif) === 9) {
                $newRif .= $this->getCheckDigit($newRif);
            }
            return $newRif;
        }
            
    }
    
    public function setContainer(\Symfony\Component\DependencyInjection\ContainerInterface $container = null) {
        $this->container = $container;
    }
    
    private function buildMessage($message){
       return $this->container->get("translator")->trans($message,[],"messages");
    }
}
