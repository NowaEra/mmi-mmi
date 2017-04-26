<?php

/**
 * Mmi Framework (https://github.com/milejko/mmi.git)
 * 
 * @link       https://github.com/milejko/mmi.git
 * @copyright  Copyright (c) 2010-2016 Mariusz Miłejko (http://milejko.com)
 * @license    http://milejko.com/new-bsd.txt New BSD License
 */

namespace Mmi\Mvc\ViewHelper;

class Escape extends HelperAbstract
{

    /**
     * Usuwa tagi z ciągu znaków
     * @see \Mmi\Filter\Escape
     * @param string $input ciąg wejściowy
     * @return string
     */
    public function escape($input)
    {
        return (new \Mmi\Filter\Escape)->filter($input);
    }

}
