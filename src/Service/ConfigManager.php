<?php
namespace App\Service;

use App\Entity\Config;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;

class ConfigManager
{

    /**
     * @var mixed
     */
    private $configs;

    /**
     * @var EntityManager
     */
    private $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    public function get($key, $default = null)
    {
        if (!$this->configs) {
            $this->init();
        }

        if (!isset($this->configs[$key])) {
            return $default;
        }

        $value = $this->configs[$key];

        if ($this->is_serialized($value)) {
            $value = unserialize($value);
        }

        return $value;
    }

    public function set($key, $value)
    {
        if (!$this->configs) {
            $this->init();
        }

        if (is_array($value) || is_object($value)) {
            $value = serialize($value);
        }

        if (isset($this->configs[$key])) {
            $this->_update($key, $value);
        } else {
            $this->_insert($key, $value);
        }

        $this->configs[$key] = $value;
    }

    private function _insert($key, $value)
    {
        $config = $this->em->getRepository(Config::class)->findOneBy([
            "configKey" => $key
        ]);

        if ($config) {
            $this->_update($key, $value);
            return;
        }

        $config = new Config();
        $config->setConfigKey($key);
        $config->setConfigValue($value);

        $this->em->persist($config);
        $this->em->flush();
    }

    private function _update($key, $value)
    {
        $config = $this->em->getRepository(Config::class)->findOneBy([
            "configKey" => $key
        ]);

        $config->setConfigValue($value);

        $this->em->persist($config);
        $this->em->flush();
    }

    private function init()
    {
        $configs = $this->em->getRepository(Config::class)->findAll();

        foreach ($configs as $config) {
            $this->configs[$config->getConfigKey()] = $config->getConfigValue();
        }
    }

    /**
     * @return mixed
     */
    public function getConfigs()
    {
        if (!$this->configs) {
            $this->init();
        }

        return $this->configs;
    }

    /**
     * Check value to find if it was serialized.
     *
     * If $data is not an string, then returned value will always be false.
     * Serialized data is always a string.
     *
     * source: wordpress
     *
     * @param string $data Value to check to see if was serialized.
     * @param bool $strict Optional. Whether to be strict about the end of the string. Default true.
     * @return bool False if not serialized and true if it was.
     */
    private function is_serialized($data, $strict = true)
    {
        // if it isn't a string, it isn't serialized.
        if (!is_string($data)) {
            return false;
        }
        $data = trim($data);
        if ('N;' == $data) {
            return true;
        }
        if (strlen($data) < 4) {
            return false;
        }
        if (':' !== $data[1]) {
            return false;
        }
        if ($strict) {
            $lastc = substr($data, -1);
            if (';' !== $lastc && '}' !== $lastc) {
                return false;
            }
        } else {
            $semicolon = strpos($data, ';');
            $brace = strpos($data, '}');
            // Either ; or } must exist.
            if (false === $semicolon && false === $brace) {
                return false;
            }
            // But neither must be in the first X characters.
            if (false !== $semicolon && $semicolon < 3) {
                return false;
            }
            if (false !== $brace && $brace < 4) {
                return false;
            }
        }
        $token = $data[0];
        switch ($token) {
            case 's' :
                if ($strict) {
                    if ('"' !== substr($data, -2, 1)) {
                        return false;
                    }
                } elseif (false === strpos($data, '"')) {
                    return false;
                }
            // or else fall through
            case 'a' :
            case 'O' :
                return (bool)preg_match("/^{$token}:[0-9]+:/s", $data);
            case 'b' :
            case 'i' :
            case 'd' :
                $end = $strict ? '$' : '';

                return (bool)preg_match("/^{$token}:[0-9.E-]+;$end/", $data);
        }

        return false;
    }

    public function getEntityManager()
    {
        return $this->em;
    }
} 
