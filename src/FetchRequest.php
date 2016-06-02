<?php
namespace Hirak\Prestissimo;

class FetchRequest extends BaseRequest
{
    protected static $defaultCurlOptions = array(
        CURLOPT_HTTPGET => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 20,
        CURLOPT_ENCODING => '',
        CURLOPT_RETURNTRANSFER => true,
    );

    private $headers = array();
    private $errno, $error, $info;

    /**
     * @param string $url
     * @param IO\IOInterface $io
     * @param Config $config
     */
    public function __construct($url, IO\IOInterface $io, Config $config)
    {
        $this->setURL($url);
        $this->setCA($config->get('capath'), $config->get('cafile'));
        $this->setupAuthentication($io, false, $config->get('github-domains'), $config->get('gitlab-domains'));
    }

    public function getCurlOptions()
    {
        $curlOpts = parent::getCurlOptions();
        $curlOpts[CURLOPT_RETURNTRANSFER] = true;
        $curlOpts[CURLOPT_HEADERFUNCTION] = array($this, 'headerCallback');
        return $curlOpts;
    }

    private static function getCurl($key)
    {
        static $curlCache = array();

        if (isset($curlCache[$key])) {
            return $curlCache[$key];
        }

        $ch = curl_init();
        Share::setup($ch);

        return $curlCache[$key] = $ch;
    }

    /**
     * @return string|false
     */
    public function fetch()
    {
        $ch = self::getCurl($this->getOriginURL());
        curl_setopt_array($ch, $this->getCurlOptions());

        $result = curl_exec($ch);

        $this->errno = $errno = curl_errno($ch);
        $this->error = curl_error($ch);
        $this->info = $info = curl_getinfo($ch);

        if ($errno === CURLE_OK && $info['http_code'] === 200) {
            return $result;
        } else {
            return false;
        }
    }

    public function getLastError()
    {
        if ($this->errno || $this->error) {
            return array($this->errno, $this->error);
        } else {
            return array();
        }
    }

    public function getLastHeaders()
    {
        return $this->headers;
    }

    public function headerCallback($ch, $headerString)
    {
        $len = strlen($headerString);
        $this->headers[] = $headerString;
        return $len;
    }
}
