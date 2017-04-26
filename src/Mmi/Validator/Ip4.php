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
 * Walidator IP4
 */
class Ip4 extends ValidatorAbstract
{

    /**
     * Treść wiadomości
     */
    const INVALID = 'Niepoprawny adres IP';

    /**
     * Walidacja IPv4
     * @param mixed $value wartość
     * @return boolean
     */
    public function isValid($value)
    {
        if (!preg_match('/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/', $value)) {
            return $this->_error(self::INVALID);
        }
        foreach (explode('.', $value) as $num) {
            if ($num > 255 || $num < 0) {
                return false;
            }
        }
        return true;
    }

}
