<?php

/**
 * Mmi Framework (https://github.com/milejko/mmi.git)
 * 
 * @link       https://github.com/milejko/mmi.git
 * @copyright  Copyright (c) 2010-2017 Mariusz Miłejko (mariusz@milejko.pl)
 * @license    https://en.wikipedia.org/wiki/BSD_licenses New BSD License
 */

namespace Mmi\Mvc;

//użycie front controllera i requestu
use Mmi\App\FrontController,
    \Mmi\Http\Request;

/**
 * Helper akcji
 */
class ActionHelper
{

    /**
     * Obiekt ACL
     * @var \Mmi\Security\Acl
     */
    protected $_acl;

    /**
     * Obiekt Auth
     * @var \Mmi\Security\Auth
     */
    protected $_auth;

    /**
     * Instancja helpera akcji
     * @var \Mmi\Mvc\ActionHelper 
     */
    protected static $_instance;

    /**
     * Pobranie instancji
     * @return \Mmi\Mvc\ActionHelper
     */
    public static function getInstance()
    {
        //zwrot instancji, lub utworzenie nowej
        return self::$_instance ? self::$_instance : (self::$_instance = new self);
    }

    /**
     * Ustawia obiekt ACL
     * @param \Mmi\Security\Acl $acl
     * @return \Mmi\Security\Acl
     */
    public function setAcl(\Mmi\Security\Acl $acl)
    {
        //acl
        $this->_acl = $acl;
        //zwrot siebie
        return $this;
    }

    /**
     * Ustawia obiekt autoryzacji
     * @param \Mmi\Security\Auth $auth
     * @return \Mmi\Security\Auth
     */
    public function setAuth(\Mmi\Security\Auth $auth)
    {
        //auth
        $this->_auth = $auth;
        //zwrot siebie
        return $this;
    }

    /**
     * Uruchamia akcję z kontrolera ze sprawdzeniem ACL
     * @param \Mmi\Http\Request $request
     * @return mixed
     */
    public function action(Request $request, $main = false)
    {
        //sprawdzenie ACL
        if (!$this->_checkAcl($request)) {
            //logowanie zablokowania akcji
            return FrontController::getInstance()->getProfiler()->event('Mvc\ActionExecuter: ' . $request->getAsColonSeparatedString() . ' blocked');
        }
        //rendering szablonu jeśli akcja zwraca null
        return $this->_renderAction($request, (FrontController::getInstance()->getView()->request ? FrontController::getInstance()->getView()->request : new Request), $main);
    }

    /**
     * Przekierowuje wykonanie do akcji
     * @param \Mmi\Http\Request $request
     * @return string
     * @throws \Mmi\Mvc\MvcException
     */
    public function forward(Request $request)
    {
        //sprawdzenie ACL
        if (!$this->_checkAcl($request)) {
            //wyjątek niedozwolonej akcji
            throw new MvcForbiddenException('Action ' . $request->getAsColonSeparatedString() . ' blocked');
        }
        //zmiana requestu i render layoutu
        return FrontController::getInstance()
            ->setRequest($request)
            ->getView()->renderLayout($this->_renderAction($request, $request, true), $request);
    }

    /**
     * Renderuje akcję (zwraca content akcji, lub template)
     * @param Request $request
     * @param Request $resetRequest request przekazywany do widoku po zakończeniu renderingu
     * @param boolean $main określa czy akcja jest akcją główną (2 przypadki - gdy wywołana z front-controllera, lub forward)
     * @return string
     */
    private function _renderAction(Request $request, Request $resetRequest, $main)
    {
        //zapamiętanie stanu wyłączenia layoutu
        $resetLayoutDisabled = FrontController::getInstance()->getView()->isLayoutDisabled();
        //wywołanie akcji
        if (null !== $actionContent = $this->_invokeAction($request)) {
            //reset requestu i dla akcji głównej wyłączenie layoutu
            FrontController::getInstance()->getView()->setRequest($resetRequest)
                //jeśli akcja główna - to ona decyduje o wyłączeniu layoutu, jeśli nie - reset do tego co było przed nią
                ->setLayoutDisabled($main ? true : FrontController::getInstance()->getView()->isLayoutDisabled());
            //zwrot danych z akcji
            return $actionContent;
        }
        //zwrot wyrenderowanego szablonu
        $content = FrontController::getInstance()->getView()->renderTemplate($request->getModuleName() . '/' . $request->getControllerName() . '/' . $request->getActionName());
        //pobranie widoku
        FrontController::getInstance()->getView()
            //reset requestu
            ->setRequest($resetRequest)
            //jeśli akcja główna - to ona decyduje o wyłączeniu layoutu, jeśli nie - reset do tego co było przed nią
            ->setLayoutDisabled($main ? FrontController::getInstance()->getView()->isLayoutDisabled() : $resetLayoutDisabled);
        //profiler wyrenderowaniu szablonu
        FrontController::getInstance()->getProfiler()->event('Mvc\View: ' . $request->getAsColonSeparatedString() . ' rendered');
        //zwrot wyrenderowanego szablonu
        return $content;
    }

    /**
     * Sprawdza uprawnienie do widgetu
     * @param \Mmi\Http\Request $request
     * @return boolean
     */
    private function _checkAcl(Request $request)
    {
        //brak acl lub brak auth lub dozwolone acl
        return !$this->_acl || !$this->_auth || $this->_acl->isAllowed($this->_auth->getRoles(), $request->getAsColonSeparatedString());
    }

    /**
     * Wywołanie akcji
     * @param \Mmi\Http\Request $request
     * @return string
     * @throws MvcNotFoundException
     */
    private function _invokeAction(Request $request)
    {
        //informacja do profilera o rozpoczęciu wykonywania akcji
        FrontController::getInstance()->getProfiler()->event('Mvc\ActionHelper: ' . $request->getAsColonSeparatedString() . ' start');
        //pobranie struktury
        $structure = FrontController::getInstance()->getStructure('module');
        //sprawdzenie w strukturze
        if (!isset($structure[$request->getModuleName()][$request->getControllerName()][$request->getActionName()])) {
            //komponent nieodnaleziony
            throw new MvcNotFoundException('Component not found: ' . $request->getAsColonSeparatedString());
        }
        //ustawienie requestu w widoku
        FrontController::getInstance()->getView()->setRequest($request);
        //rozbijanie po myślniku
        $controllerParts = explode('-', $request->getControllerName());
        //iteracja po częściach
        foreach ($controllerParts as $key => $controllerPart) {
            //stosowanie camelcase
            $controllerParts[$key] = ucfirst($controllerPart);
        }
        //ustalenie klasy kontrolera
        $controllerClassName = ucfirst($request->getModuleName()) . '\\' . implode('\\', $controllerParts) . 'Controller';
        //nazwa akcji
        $actionMethodName = $request->getActionName() . 'Action';
        //wywołanie akcji
        $content = (new $controllerClassName($request))->$actionMethodName();
        //informacja o zakończeniu wykonywania akcji do profilera
        FrontController::getInstance()->getProfiler()->event('Mvc\ActionHelper: ' . $request->getAsColonSeparatedString() . ' done');
        return $content;
    }

}
