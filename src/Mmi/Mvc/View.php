<?php

/**
 * Mmi Framework (https://github.com/milejko/mmi.git)
 * 
 * @link       https://github.com/milejko/mmi.git
 * @copyright  Copyright (c) 2010-2016 Mariusz Miłejko (http://milejko.com)
 * @license    http://milejko.com/new-bsd.txt New BSD License
 */

namespace Mmi\Mvc;

use Mmi\App\FrontController;

/**
 * Klasa widoku
 * @method string url(array $params = [], $reset = false, $https = null)
 * @method string widget($module, $controller = 'index', $action = 'index', array $params = [])
 * @method ViewHelper\Navigation navigation()
 */
class View extends \Mmi\DataObject
{

    /**
     * Bieżąca wersja językowa
     * @var string
     */
    private $_locale;

    /**
     * Tabela z załadowanymi helperami
     * @var array
     */
    private $_helpers = [];

    /**
     * Tabela z załadowanymi filtrami
     * @var array
     */
    private $_filters = [];

    /**
     * Przechowuje dane placeholderów
     * @var array
     */
    private $_placeholders = [];

    /**
     * Wyłączony
     * @var boolean
     */
    private $_layoutDisabled = false;

    /**
     * Obiekt tłumaczeń
     * @var \Mmi\Translate
     */
    private $_translate;

    /**
     * Obiekt buforujący
     * @var \Mmi\Cache\Cache
     */
    private $_cache;

    /**
     * Włączone buforowanie
     * @var boolean
     */
    private $_alwaysCompile = true;

    /**
     * Obiekt requestu
     * @var \Mmi\Http\Request
     */
    public $request;

    /**
     * Bazowa ścieżka
     * @var string
     */
    public $baseUrl;

    /**
     * Adres CDN
     * @var string 
     */
    public $cdn;

    /**
     * Magicznie wywołuje metodę na widoku
     * przekierowuje wywołanie na odpowiedni helper lub placeholder
     * @param string $name nazwa metody
     * @param array $params parametry
     * @return mixed
     */
    public function __call($name, array $params = [])
    {
        //znaleziony helper
        if (null !== $helper = $this->getHelper($name)) {
            return call_user_func_array([$helper, $name], $params);
        }
        //rollback do placeholdera
        return $this->getPlaceholder($name);
    }

    /**
     * Ustawia obiekt request
     * @param \Mmi\Http\Request $request
     * @return \Mmi\Mvc\View
     */
    public function setRequest(\Mmi\Http\Request $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Ustawia translator
     * @param \Mmi\Translate $translate
     * @return \Mmi\Mvc\View
     */
    public function setTranslate(\Mmi\Translate $translate)
    {
        $this->_translate = $translate;
        return $this;
    }

    /**
     * Ustawia obiekt cache
     * @param \Mmi\Cache\Cache $cache
     * @return \Mmi\Mvc\View
     */
    public function setCache(\Mmi\Cache\Cache $cache)
    {
        $this->_cache = $cache;
        return $this;
    }

    /**
     * Ustawia opcję zawsze kompiluj szablony
     * @param boolean $compile
     * @return \Mmi\Mvc\View
     */
    public function setAlwaysCompile($compile = true)
    {
        $this->_alwaysCompile = $compile;
        return $this;
    }

    /**
     * Ustawia bazowy url
     * @param string $baseUrl
     * @return \Mmi\Mvc\View
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    /**
     * Ustawia adres CDN
     * @param string $cdn
     * @return \Mmi\Mvc\View
     */
    public function setCdn($cdn)
    {
        $this->cdn = $cdn;
        return $this;
    }

    /**
     * Zwraca obiekt translatora
     * @return \Mmi\Translate
     */
    public function getTranslate()
    {
        //próba załadowania translate, lub utworzenie i zbuforowanie nowego translate
        return ($this->_translate !== null) ? $this->_translate : ($this->_translate = new \Mmi\Translate);
    }

    /**
     * Zwraca obiekt cache
     * @return \Mmi\Cache
     */
    public function getCache()
    {
        return $this->_cache;
    }

    /**
     * Pobiera helper na podstawie nazwy z uwzględnieniem ścieżek do helperów
     * @param string $name nazwa
     * @return \Mmi\Mvc\ViewHelper\HelperAbstract
     */
    public function getHelper($name)
    {
        //wyszukiwanie helpera w strukturze
        foreach (\Mmi\App\FrontController::getInstance()->getStructure('helper') as $namespace => $helpers) {
            if (!isset($helpers[$name])) {
                continue;
            }
            //helper znaleziony
            $className = '\\' . $namespace . '\\Mvc\\ViewHelper\\' . ucfirst($name);
        }
        //brak helpera
        if (!isset($className)) {
            return;
        }
        //zwrot helpera z rejestru, lub tworzenie nowego + rejestracja
        return isset($this->_helpers[$className]) ? $this->_helpers[$className] : ($this->_helpers[$className] = new $className);
    }

    /**
     * Pobiera filtr na podstawie nazwy z uwzględnieniem ścieżek do filtrów
     * @param string $name nazwa
     * @return \Mmi\Mvc\ViewHelper\HelperAbstract
     */
    public function getFilter($name)
    {
        //wyszukiwanie filtra w strukturze
        foreach (\Mmi\App\FrontController::getInstance()->getStructure('filter') as $namespace => $filters) {
            if (!isset($filters[$name])) {
                continue;
            }
            //filtr znaleziony
            $className = '\\' . $namespace . '\\Filter\\' . ucfirst($name);
        }
        //brak filtra
        if (!isset($className)) {
            throw new \Mmi\Mvc\MvcException('Filter not found: ' . $name);
        }
        //zwrot zarejestrowanego filtra, lub tworzenie nowego + rejestracja
        return isset($this->_filters[$className]) ? $this->_filters[$className] : ($this->_filters[$className] = new $className);
    }

    /**
     * Ustawia placeholder
     * @param string $name nazwa
     * @param string $content zawartość
     * @return \Mmi\Mvc\View
     */
    public function setPlaceholder($name, $content)
    {
        $this->_placeholders[$name] = $content;
        return $this;
    }

    /**
     * Pobiera placeholder
     * @param string $name nazwa
     * @return string
     */
    public function getPlaceholder($name)
    {
        return isset($this->_placeholders[$name]) ? $this->_placeholders[$name] : null;
    }

    /**
     * Pobiera wszystkie zmienne w postaci tablicy
     * @return array
     */
    public function getAllVariables()
    {
        //pobranie danych widoku
        $data = $this->_data;
        //iteracja po danych
        foreach ($data as $key => $value) {
            //kasowanie danych prywatnych mmi (zaczynają się od _)
            if ($key[0] == '_') {
                //usuwanie klucza
                unset($data[$key]);
            }
        }
        //zwrot danych
        return $data;
    }

    /**
     * Ustawia wyłączenie layoutu
     * @param boolean $disabled wyłączony
     * @return \Mmi\Mvc\View
     */
    public function setLayoutDisabled($disabled = true)
    {
        $this->_layoutDisabled = ($disabled === true) ? true : false;
        return $this;
    }

    /**
     * Czy layout wyłączony
     * @return boolean
     */
    public function isLayoutDisabled()
    {
        return $this->_layoutDisabled;
    }

    /**
     * Pobranie szablonu po ścieżce np. module/controller/action
     * @param string $path
     * @return string|null
     */
    public function getTemplateByPath($path)
    {
        //ścieżka nie jest stringiem
        if (!is_string($path)) {
            throw new \Mmi\Mvc\MvcException('Template path invalid.');
        }
        //pobranie struktury szablonów
        $structure = \Mmi\App\FrontController::getInstance()->getStructure('template');
        //wyszukiwanie ścieżki w strukturze
        foreach (explode('/', $path) as $dir) {
            if (!isset($structure[$dir])) {
                return;
            }
            //obcinanie struktury
            $structure = $structure[$dir];
        }
        //szablon znaleziony
        if (is_string($structure)) {
            return $structure;
        }
        //szablon nadpisany w projekcie (istniała wersja domyślna w vendorach)
        if (is_array($structure) && isset($structure[0]) && is_string($structure[0])) {
            return $structure[0];
        }
    }

    /**
     * Renderuje i zwraca wynik wykonania template
     * @param string $path ścieżka np. news/index/index
     * @param bool $fetch przekaż wynik wywołania w zmiennej
     */
    public function renderTemplate($path)
    {
        //wyszukiwanie template
        if (null === $template = $this->getTemplateByPath($path)) {
            //brak template
            throw new \Mmi\Mvc\MvcException('Template not found: ' . $path);
        }
        //inicjalizacja języka
        $this->_initLocale();
        //kompilacja szablonu
        return $this->_compileTemplate(file_get_contents($template), BASE_PATH . '/var/compile/' . $this->_locale . '_' . str_replace(['/', '\\', '_Resource_template_'], '_', substr($template, strrpos($template, '/src') + 5, -4) . '.php'));
    }

    /**
     * Generowanie kodu PHP z kodu szablonu w locie
     * @param string $templateCode kod szablonu
     * @return string kod PHP
     */
    public function renderDirectly($templateCode)
    {
        //inicjalizacja języka
        $this->_initLocale();
        //kompilacja szablonu
        return $this->_compileTemplate($templateCode, BASE_PATH . '/var/compile/' . $this->_locale . '_direct_' . md5($templateCode) . '.php');
    }

    /**
     * Inicjalizacja lokalizacji / języka
     */
    private function _initLocale()
    {
        //sprawdzanie lokalizacji (języka)
        if (!$this->_locale && null !== $this->_translate) {
            //ustawianie lokalizacji
            $this->_locale = $this->_translate->getLocale();
        }
    }

    /**
     * Uruchomienie szablonu
     * @param string $templateCode kod szablonu
     * @param string $compilationFile adres kompilanta
     * @return string
     */
    private function _compileTemplate($templateCode, $compilationFile)
    {
        //pobranie bufora wyrenderowanego do tego momentu
        $inputBuffer = ob_get_contents();
        //czyszczenie bufora
        ob_clean();
        //wymuszona kompilacja
        if ($this->_alwaysCompile) {
            file_put_contents($compilationFile, $this->template($templateCode));
        }
        //próba włączenia skompilowanego pliku
        try {
            //włączenie kompilanta do kodu
            include $compilationFile;
        } catch (\Exception $e) {
            //kompilacja i zapis i włączenie kompilanta do kodu
            file_put_contents($compilationFile, $this->template($templateCode));
            include $compilationFile;
        }
        //przechwycenie danych z bufora
        $data = ob_get_contents();
        //czyszczenie bufora
        ob_clean();
        //zwrot bufora
        echo $inputBuffer;
        return $data;
    }

}
