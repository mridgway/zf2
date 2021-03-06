<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Tool
 * @subpackage Framework
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */

/**
 * @namespace
 */
namespace Zend\Tool\Project\Provider;
use Zend\Tool\Project\Profile as ProjectProfile;

/**
 * @uses       \Zend\Tool\Framework\Provider\Pretendable
 * @uses       \Zend\Tool\Project\Provider\AbstractProvider
 * @uses       \Zend\Tool\Project\Provider\Exception
 * @uses       \Zend\Tool\Project\Provider\View
 * @category   Zend
 * @package    Zend_Tool
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Action
    extends AbstractProvider
    implements \Zend\Tool\Framework\Provider\Pretendable
{

    /**
     * createResource()
     *
     * @param \Zend\Tool\Project\Profile $profile
     * @param string $actionName
     * @param string $controllerName
     * @param string $moduleName
     * @return \Zend\Tool\Project\Profile\Resource
     */
    public static function createResource(ProjectProfile $profile, $actionName, $controllerName, $moduleName = null)
    {

        if (!is_string($actionName)) {
            throw new Exception('Zend_Tool_Project_Provider_Action::createResource() expects \"actionName\" is the name of a action resource to create.');
        }

        if (!is_string($controllerName)) {
            throw new Exception('Zend_Tool_Project_Provider_Action::createResource() expects \"controllerName\" is the name of a controller resource to create.');
        }

        $controllerFile = self::_getControllerFileResource($profile, $controllerName, $moduleName);

        $actionMethod = $controllerFile->createResource('ActionMethod', array('actionName' => $actionName));

        return $actionMethod;
    }

    /**
     * hasResource()
     *
     * @param \Zend\Tool\Project\Profile $profile
     * @param string $actionName
     * @param string $controllerName
     * @param string $moduleName
     * @return \Zend\Tool\Project\Profile\Resource
     */
    public static function hasResource(ProjectProfile $profile, $actionName, $controllerName, $moduleName = null)
    {
        if (!is_string($actionName)) {
            throw new Exception('Zend_Tool_Project_Provider_Action::createResource() expects \"actionName\" is the name of a action resource to create.');
        }

        if (!is_string($controllerName)) {
            throw new Exception('Zend_Tool_Project_Provider_Action::createResource() expects \"controllerName\" is the name of a controller resource to create.');
        }

        $controllerFile = self::_getControllerFileResource($profile, $controllerName, $moduleName);

        if ($controllerFile == null) {
            throw new Exception('Controller ' . $controllerName . ' was not found.');
        }
       
        return (($controllerFile->search(array('actionMethod' => array('actionName' => $actionName)))) instanceof ProjectProfile\Resource);
    }

    /**
     * _getControllerFileResource()
     *
     * @param \Zend\Tool\Project\Profile $profile
     * @param string $controllerName
     * @param string $moduleName
     * @return \Zend\Tool\Project\Profile\Resource
     */
    protected static function _getControllerFileResource(ProjectProfile $profile, $controllerName, $moduleName = null)
    {
        $profileSearchParams = array();

        if ($moduleName != null && is_string($moduleName)) {
            $profileSearchParams = array('modulesDirectory', 'moduleDirectory' => array('moduleName' => $moduleName));
        }

        $profileSearchParams[] = 'controllersDirectory';
        $profileSearchParams['controllerFile'] = array('controllerName' => $controllerName);

        return $profile->search($profileSearchParams);
    }

    /**
     * create()
     *
     * @param string $name           Action name for controller, in camelCase format.
     * @param string $controllerName Controller name action should be applied to.
     * @param bool $viewIncluded     Whether the view should the view be included.
     * @param string $module         Module name action should be applied to.
     */
    public function create($name, $controllerName = 'Index', $viewIncluded = true, $module = null)
    {

        $this->_loadProfile();

        // Check that there is not a dash or underscore, return if doesnt match regex
        if (preg_match('#[_-]#', $name)) {
            throw new Exception('Action names should be camel cased.');
        }
        
        $originalName = $name;
        $originalControllerName = $controllerName;
        
        // ensure it is camelCase (lower first letter)
        $name = strtolower(substr($name, 0, 1)) . substr($name, 1);
        
        // ensure controller is MixedCase
        $controllerName = ucfirst($controllerName);
        
        if (self::hasResource($this->_loadedProfile, $name, $controllerName, $module)) {
            throw new Exception('This controller (' . $controllerName . ') already has an action named (' . $name . ')');
        }
        
        $actionMethod = self::createResource($this->_loadedProfile, $name, $controllerName, $module);

        // get request/response object
        $request = $this->_registry->getRequest();
        $response = $this->_registry->getResponse();
        
        // alert the user about inline converted names
        $tense = (($request->isPretend()) ? 'would be' : 'is');
        
        if ($name !== $originalName) {
            $response->appendContent(
                'Note: The canonical action name that ' . $tense
                    . ' used with other providers is "' . $name . '";'
                    . ' not "' . $originalName . '" as supplied',
                array('color' => array('yellow'))
                );
        }
        
        if ($controllerName !== $originalControllerName) {
            $response->appendContent(
                'Note: The canonical controller name that ' . $tense
                    . ' used with other providers is "' . $controllerName . '";'
                    . ' not "' . $originalControllerName . '" as supplied',
                array('color' => array('yellow'))
                );
        }
        
        unset($tense);
        
        if ($request->isPretend()) {
            $response->appendContent(
                'Would create an action named ' . $name .
                ' inside controller at ' . $actionMethod->getParentResource()->getContext()->getPath()
                );
        } else {
            $response->appendContent(
                'Creating an action named ' . $name .
                ' inside controller at ' . $actionMethod->getParentResource()->getContext()->getPath()
                );
            $actionMethod->create();
            $this->_storeProfile();
        }

        if ($viewIncluded) {
            $viewResource = View::createResource($this->_loadedProfile, $name, $controllerName, $module);

            if ($this->_registry->getRequest()->isPretend()) {
                $response->appendContent(
                    'Would create a view script for the ' . $name . ' action method at ' . $viewResource->getContext()->getPath()
                    );
            } else {
                $response->appendContent(
                    'Creating a view script for the ' . $name . ' action method at ' . $viewResource->getContext()->getPath()
                    );
                $viewResource->create();
                $this->_storeProfile();
            }

        }

    }

}
