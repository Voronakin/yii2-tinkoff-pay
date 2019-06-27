<?php

namespace chumakovanton\tinkoffPay\request;

use chumakovanton\tinkoffPay\exceptions\HttpException;
use chumakovanton\tinkoffPay\response\HttpResponse;

/**
 * Class AbstractRequest
 *
 *
 * @package chumakovanton\tinkoffPay\request
 */
abstract class AbstractRequest implements RequestInterface
{
    /**
     * @var string
     */
    private $_apiUrl;
    /**
     * Идентификатор терминала, выдается Продавцу Банком
     * @var string(20)
     */
    protected $_terminalKey;

    /**
     * Секретный ключ терминала
     * @var string
     */
    protected $_secretKey;
    /**
     * Массив полей запроса
     * @var array
     */
    protected $_dataFields;

    /**
     * Функция заполняет свойство $_dataFields значениями полей запроса
     */
    abstract protected function buildDataFields(): void;

    /**
     * Сериализовать объект
     * @return null|string
     */
    protected function serializeDataFields(): ?string
    {
        $this->buildDataFields();

        $this->_dataFields['TerminalKey'] = $this->_terminalKey;

        unset($this->_dataFields['Token']);

        $this->_dataFields['Token'] = $this->_generateToken();

        return json_encode($this->_dataFields);
    }

    /**
     * Generates token
     * @return null|string
     */
    private function _generateToken(): ?string
    {
        $token = '';
        $this->_dataFields['Password'] = $this->_secretKey;
        ksort($this->_dataFields);
        foreach ($this->_dataFields as $field) {
            $token .= $field;
        }

        return hash('sha256', $token);
    }

    /**
     * Combines parts of URL. Simply gets all parameters and puts '/' between
     *
     * @return string
     */
    protected function _combineUrl(): string
    {
        $args = func_get_args();
        $url = '';
        foreach ($args as $arg) {
            if (is_string($arg)) {
                if ($arg[strlen($arg) - 1] !== '/') {
                    $arg .= '/';
                }
                $url .= $arg;
            } else {
                continue;
            }
        }

        return $url;
    }

    /**
     * @param string $path
     * @return HttpResponse
     * @throws HttpException
     */
    protected function _sendRequest(string $path): HttpResponse
    {
        $url = $this->_apiUrl;

        $url = $this->_combineUrl($url, $path);

        $postData = $this->serializeDataFields();

        $curl = curl_init();

        if (false === $curl) {
            throw new HttpException(
                'Can not create connection to ' . $url . ' with args '
                . $postData, 404
            );
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_POST, true);

        if (!empty($postData)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($postData))
            );
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        }

        $statusCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $body = curl_exec($curl);

        curl_close($curl);

        return new HttpResponse($statusCode, (string)$body);
    }

    public function setCredentials(string $apiUrl, string $terminalKey, string $secretKey): RequestInterface
    {
        $this->_apiUrl = $apiUrl;
        $this->_terminalKey = $terminalKey;
        $this->_secretKey = $secretKey;

        return $this;
    }
}