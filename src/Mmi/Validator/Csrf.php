<?php

/**
 * Mmi Framework (https://github.com/milejko/mmi.git)
 * 
 * @link       https://github.com/milejko/mmi.git
 * @copyright  Copyright (c) 2010-2016 Mariusz Miłejko (http://milejko.com)
 * @license    http://milejko.com/new-bsd.txt New BSD License
 */

namespace Mmi\Validator;

/**
 * Walidator dla elementu formularza Csrf
 * 
 * @method self setName($name) ustawia nazwę namespace
 * 
 * @method string getName() pobiera nazwę pola
 * 
 * @see \Mmi\Form\Element\Csrf
 */
class Csrf extends ValidatorAbstract
{

    /**
     * Komunikat błędnego kodu zabezpieczającego
     */
    const INVALID = 'Kod CSRF niepoprawny';

    /**
     * Ustawia opcje
     * @param array $options
     * @return self
     */
    public function setOptions(array $options = [], $reset = false)
    {
        return $this->setName(current($options));
    }

    /**
     * Waliduje poprawność captcha
     * @param string $value
     * @return boolean
     */
    public function isValid($value)
    {
        //wartość niepusta i zgodna z sesją
        if ($value != '' && $this->getHash() == $value) {
            return true;
        }
        return $this->_error(self::INVALID);
    }

    /**
     * Pobiera bierzący losowy ciąg z sesji (ze zdefiniowanego namespace)
     * @return string
     */
    public function getHash()
    {
        return (new \Mmi\Session\SessionSpace($this->getOption('name')))
            ->hash;
    }

    /**
     * Generuje hash i ustawia do sesji (do zdefiniowanego namespace)
     * @return string
     */
    public function generateHash()
    {
        $sessionSpace = new \Mmi\Session\SessionSpace($this->getOption('name'));
        $sessionSpace->hash = sha1(microtime(true) . rand(0, 1000000));
        return $sessionSpace->hash;
    }

}
