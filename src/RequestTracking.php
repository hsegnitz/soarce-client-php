<?php

namespace Soarce;

use Predis\ClientInterface;

class RequestTracking
{
    const HEADER_NAME    = 'HTTP_X_SOARCE_REQUEST_ID';
    const EXPIRY_SECONDS = 600;

    /** @var ClientInterface */
    private $client;

    /** @var string */
    private $requestId;

    /** @var string */
    private $serverIp;

    /** @var string */
    private $requestIp;

    /**
     * RequestTracking constructor.
     *
     * @param ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @return string
     */
    public function getRequestId()
    {
        if (null !== $this->requestId) {
            return $this->requestId;
        }

        # get Request ID from header value
        $this->requestId = $this->getRequestIdFromHeader();

        # or get Request Id from redis using calling IP address
        if ($this->requestId === '') {
            $this->requestId = $this->getRequestIdFromRedis();
        }

        # or get default Request Id (random)
        if ($this->requestId === '') {
            $this->requestId = bin2hex(random_bytes(8));
        }

        # write own RequestId to redis using own IP as key
        $key = 'request:' . $this->getServerIp();
        $this->client->lpush($key,  array($this->requestId));
        $this->client->expire($key, self::EXPIRY_SECONDS);

        return $this->requestId;
    }

    /**
     * This is a "shutdown function" which removes the request ID from redis - as the request is done.
     */
    public function unregisterRequest()
    {
        $key = 'request:' . $this->getServerIp();
        $this->client->lrem($key, 1, $this->requestId);
    }

    /**
     * Uses redis and some sort of "reverse dns" to determine (educated guess) one's own request ID
     * {Parent-Request-ID}-{counter}
     *
     * @return string
     */
    protected function getRequestIdFromRedis()
    {
        $key = 'request:' . $this->getRequestIp();

        // if there is not precicesly one request running for the parent service, we can't guess our request ID, so we skip this part
        if ($this->client->llen($key) !== 1) {
            return '';
        }

        // get the request ID from parent
        $temp = $this->client->lrange($key, 0, 1);
        $parentRequestId = $temp[0];

        // increase a unique counter for the parent request ID to number it's requests
        $counter = $this->client->incr($parentRequestId);

        // let it cleanup automatically
        $this->client->expire($parentRequestId, self::EXPIRY_SECONDS);

        // use the parent request + counter as our own request id
        return $parentRequestId . '-' . $counter;
    }

    /**
     * @return string
     */
    protected function getRequestIdFromHeader()
    {
        if (isset($_SERVER[self::HEADER_NAME])) {
            return $_SERVER[self::HEADER_NAME];
        }

        return '';
    }

    /**
     * @return string
     */
    protected function getRequestIp()
    {
        if (null === $this->requestIp) {
            $this->requestIp = '';
            if (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $this->requestIp = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $this->requestIp = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $this->requestIp = $_SERVER['REMOTE_ADDR'];
            }
        }

        return $this->requestIp;
    }

    /**
     * @return string
     */
    protected function getServerIp()
    {
        if (null === $this->serverIp) {
            $this->serverIp = $_SERVER['SERVER_ADDR'];
        }
        return $this->serverIp;
    }

}