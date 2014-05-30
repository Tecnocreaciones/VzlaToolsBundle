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
     * 
     * @var \Lsw\ApiCallerBundle\Caller\LoggingApiCaller 
     */
    protected $apiCaller;
    
    /**
     * Traductor
     * @var \Symfony\Component\Translation\TranslatorInterface
     */
    protected $translator;
    
    protected $container;

    private $cache = array();
    
    public function getRif($rifString){
        $rif = new Rif();
        
        if($this->isValidFormat($rifString)){
            if($this->isValidCheckDigit($rifString)){
                $parameters = array(
                    'rif' => $this->normalizeRif($rifString)
                );
                $request = new \Tecnocreaciones\Vzla\ToolsBundle\Call\HttpGetXml($this->url,null,$parameters);
                $response = $this->getApiCaller()->call($request);
                if($request->getStatusCode() == 404){
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
     * Obtener la información en formato Json
     * 
     * @param String $rif
     * @return Json
     * @throws Exception
     */
    public function getInfo() {
        if ($this->isValidFormat()) {
            if(function_exists('curl_init')) {
                $this->url .= $this->_rif;

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->url);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                $result = curl_exec ($ch);

                if ($result) {
                    try {
                        if(substr($result,0,1)!= '<' ) {
                            throw new Exception($result);
                        }

                        $xml = simplexml_load_string($result);

                        if(!is_bool($xml)) {
                            $elements = $xml->children('rif');
                            $seniat = array();
                            $this->_responseJson['code_result'] = 1;

                            foreach($elements as $key => $node) {
                                $index = strtolower($node->getName());
                                $seniat[$index] = (string)$node;
                            }
                            $this->_responseJson['seniat'] = $seniat;
                        }
                    } catch(Exception $e) {
                        $exception = explode(' ', $result, 2);
                        $this->_responseJson['code_result'] =(int) $exception[0];
                    }
                } else {
                    // No hay conexión a internet
                    $this->_responseJson['code_result'] = 0;
                }
            } else {
                // No hay soporte CURL
                $this->_responseJson['code_result'] = -1;
            } 
        } else {
            // Formato de RIF inválido
            $this->_responseJson['code_result'] = -2;
        }

        return json_encode($this->_responseJson);
    }
    
    /**
     * Validar formato del RIF
     * 
     * @return boolean 
     */
    public function isValidFormat($rif) {
        $rif = str_replace('-', '', strtoupper($rif));
        $retorno = preg_match("/^([VEJPG]{1})([0-9]{9}$)/", $rif);
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
            
            $suma = (array_sum($digitos) - $digitos[9]) + ($digitoEspecial*4);
            $residuo = $suma % 11;
            $resta = 11 - $residuo;
            
            $digitoVerificador = ($resta >= 10) ? 0 : $resta;
            
            if ($digitoVerificador != $digitos[9]) {
                return false;
            }
        return true;
    }
    
    /**
     * 
     * @return \Lsw\ApiCallerBundle\Caller\LoggingApiCaller
     */
    public function getApiCaller() {
        return $this->apiCaller;
    }

    /**
     * 
     * @param \Lsw\ApiCallerBundle\Caller\LoggingApiCaller $apiCaller
     */
    public function setApiCaller(\Lsw\ApiCallerBundle\Caller\LoggingApiCaller $apiCaller) {
        $this->apiCaller = $apiCaller;
    }
    
    /**
     * 
     * @return \Symfony\Component\Translation\TranslatorInterface
     */
    public function getTranslator() {
        return $this->translator;
    }

    /**
     * 
     * @param \Symfony\Component\Translation\TranslatorInterface $translator
     */
    public function setTranslator(\Symfony\Component\Translation\TranslatorInterface $translator) {
        $this->translator = $translator;
    }

    public function setContainer(\Symfony\Component\DependencyInjection\ContainerInterface $container = null) {
        $this->container = $container;
    }
    
    private function buildMessage($message){
        if($this->container->getParameter('tecnocreaciones_vzla_tools.rif.translate_message') == true){
            return $this->translator->trans($message);
        }
        return $message;
    }
}
