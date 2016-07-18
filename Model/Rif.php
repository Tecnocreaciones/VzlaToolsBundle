<?php

/*
 * This file is part of the TecnoCreaciones package.
 * 
 * (c) www.tecnocreaciones.com.ve
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tecnocreaciones\Vzla\ToolsBundle\Model;

/**
 * Description of Rif
 *
 * @author Carlos Mendoza <inhack20@tecnocreaciones.com>
 */
class Rif 
{
    
    /**
     * El formato del rif es invalido
     */
    const STATUS_ERROR_INVALID_RIF_FORMAT = 0;
    /**
     * El servidor sel SENIAT no responde
     */
    const STATUS_ERROR_SERVER_DOWN = 1;
    /**
     * El rif no existe
     */
    const STATUS_ERROR_RIF_DOES_NOT_EXIST = 2;
    /**
     * El digito verificador es invalido
     */
    const STATUS_ERROR_INVALID_CHECK_DIGIT = 3;
    /**
     * No se pudo conectar con el servidor(Error comun a usar un proxy)
     */
    const STATUS_ERROR_COULD_NOT_CONNECT_TO_SERVER = 4;
    /**
     * El rif se recupero exisotamente
     */
    const STATUS_OK = 5;
    
    /**
     * Rif devuelto por el portal
     * @var type 
     */
    private $rif;
    
    /**
     * Rif original
     * @var type 
     */
    private $originalRif;
    
    private $name;
    
    /**
     * Agente de retencion de iva
     * @var string
     */
    private $withholdingAgentVAT = false;
    
    /**
     * Contribuyente iva
     * @var string
     */
    private $contributorVAT = false;
    
    /**
     * Tasa
     * @var string
     */
    private $rate = 0;
    
    private $message;
    
    private $codeResponse;
    
    /**
     * Verifica si el rif es valido
     * @var boolean
     */
    private $valid = true;
    
    public function getRifFormated() {
        $formated = "";
        if($this->isValid() == true){
            $rif = $this->rif;
            $digitos = str_split($rif);
            $lastKey = count($digitos) - 1;
            foreach ($digitos as $key => $value) {
                if($key === 0){
                    $formated .= $value."-";
                }else if($key === $lastKey){
                    $formated .= '-'.$value;
                }else {
                    $formated .= $value;
                }
            }
            
        }
        return $formated;
    }
    
    public function getRif() {
        return $this->rif;
    }

    public function getName() {
        return $this->name;
    }

    public function getCodeResponse() {
        return $this->codeResponse;
    }

    public function setRif($rif) {
        $this->rif = $rif;
        
        return $this;
    }

    public function setName($name) {
        $this->name = $name;
        
        return $this;
    }

    public function setCodeResponse($codeResponse) {
        $this->codeResponse = $codeResponse;
        if($this->codeResponse != self::STATUS_OK){
            $this->valid = false;
        }
        return $this;
    }

    public function getMessage() {
        return $this->message;
    }

    public function setMessage($message) {
        $this->message = $message;
        
        return $this;
    }
    
    public function getArrayResponse() {
        $data = array(
            'message' => $this->getMessage(),
            'name' => $this->getName(),
            'withholdingAgentVAT' => $this->getWithholdingAgentVAT(),
            'contributorVAT' => $this->getContributorVAT(),
            'rate' => $this->getRate(),
        );
        return $data;
    }
    
    /**
     * Retorna true si es valido
     * @return boolean
     */
    public function isValid() {
        return $this->valid;
    }
    
    public function getWithholdingAgentVAT() {
        return $this->withholdingAgentVAT;
    }

    public function getContributorVAT() {
        return $this->contributorVAT;
    }

    public function getRate() {
        return $this->rate;
    }

    public function setWithholdingAgentVAT($withholdingAgentVAT) {
        $this->withholdingAgentVAT = $withholdingAgentVAT;
    }

    public function setContributorVAT($contributorVAT) {
        $this->contributorVAT = $contributorVAT;
    }

    public function setRate($rate) {
        $this->rate = $rate;
    }

    public function getOriginalRif() {
        return $this->originalRif;
    }

    public function setOriginalRif($originalRif) {
        $this->originalRif = $originalRif;
        return $this;
    }
    
    public function __toString() {
        $rif = $this->originalRif;
        if($this->isValid()){
            $rif = sprintf("(%s) %s",$this->getRifFormated(),$this->name);
        }else{
            $rif = sprintf("%s (%s)",$this->originalRif,$this->message);
        }
        return $rif;
    }
}
