<?php

/*
 * This file is part of the TecnoCreaciones package.
 * 
 * (c) www.tecnocreaciones.com
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tecnocreaciones\Vzla\ToolsBundle\Call;

use Lsw\ApiCallerBundle\Call\ApiCallInterface;
use Lsw\ApiCallerBundle\Call\CurlCall;

/**
 * Description of HttpGetXmlRif
 *
 * @author Carlos Mendoza <inhack20@tecnocreaciones.com>
 */
class HttpGetXml extends CurlCall implements ApiCallInterface
{
    /**
     * ApiCall class constructor
     *
     * @param string $url           API url
     * @param object $cookie        Cookie
     * @param object $requestObject Request
     */
    public function __construct($url,$cookie,$requestObject=null)
    {
        $this->url = $url;
        $this->cookie = $cookie;
        $this->requestObject = $requestObject;
        $this->generateRequestData();
    }
    
    /**
     * {@inheritdoc}
     */
    public function generateRequestData()
    {
        if ($this->requestObject) {
                $this->requestData = http_build_query($this->requestObject);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function parseResponseData()
    {
        $this->responseObject = $this->responseData;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getResponseObjectRepresentation()
    {
        return $this->responseObject;
    }
    
    /**
     * {@inheritdoc}
     */
    public function makeRequest($curl, $options)
    {
        $url = $this->url;
        if ($this->requestData) {
                $url.= '?'.$this->requestData;
        }
        $curl->setopt(CURLOPT_URL, $url);
        $curl->setopt(CURLOPT_TIMEOUT, 30);
        $curl->setopt(CURLOPT_RETURNTRANSFER, true);
        $curl->setopt(CURLOPT_FOLLOWLOCATION, true);
        $curl->setopt(CURLOPT_COOKIE, $this->cookie);
        $curl->setoptArray($options);
        $this->responseData = $curl->exec();
    }
}
