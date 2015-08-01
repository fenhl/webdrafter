<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Db\TableGateway\TableGateway;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\ResultSet\HydratingResultSet;

class Module
{
    public function onBootstrap(MvcEvent $e)
    {
        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                    'League' => __DIR__ . '/../../vendor/League',
                    'Guzzle' => __DIR__ . '/../../vendor/Guzzle',
                    'Symfony' => __DIR__ . '/../../vendor/Symfony'
                ),
            ),
        );
    }

	public function getServiceConfig()
    {
        return array(
            'factories' => array(
                'Application\Model\Set' =>  function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
            		$set = new \Application\Model\Set();
            		$set->setDbAdapter($dbAdapter);
            		return $set;
                },
                'Application\Model\SetTable' =>  function($sm) {
                    $tableGateway = $sm->get('SetTableGateway');
                    $table = new \Application\Model\SetTable($tableGateway);
                    return $table;
                },
                'SetTableGateway' => function ($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new \Application\Model\Set());
                    return new TableGateway('set', $dbAdapter, null, $resultSetPrototype);
                },
                'Application\Model\CardTable' =>  function($sm) {
                    $tableGateway = $sm->get('CardTableGateway');
                    $table = new \Application\Model\CardTable($tableGateway);
                    return $table;
                },
                'CardTableGateway' => function ($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new \Application\Model\Card());
                    return new TableGateway('card', $dbAdapter, null, $resultSetPrototype);
                },
                'Application\Model\DraftTable' =>  function($sm) {
                    $tableGateway = $sm->get('DraftTableGateway');
                    $table = new \Application\Model\DraftTable($tableGateway);
                    return $table;
                },
                'DraftTableGateway' => function ($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new \Application\Model\Draft());
                    return new TableGateway('draft', $dbAdapter, null, $resultSetPrototype);
                },
                'Application\Model\DraftSetTable' =>  function($sm) {
                    $tableGateway = $sm->get('DraftSetTableGateway');
                    $table = new \Application\Model\DraftSetTable($tableGateway);
                    return $table;
                },
                'DraftSetTableGateway' => function ($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new \Application\Model\DraftSet());
                    return new TableGateway('draft_set', $dbAdapter, null, $resultSetPrototype);
                },
                'Application\Model\DraftPlayerTable' =>  function($sm) {
                    $tableGateway = $sm->get('DraftPlayerTableGateway');
                    $table = new \Application\Model\DraftPlayerTable($tableGateway);
                    return $table;
                },
                'DraftPlayerTableGateway' => function ($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new \Application\Model\DraftPlayer());
                    return new TableGateway('draft_player', $dbAdapter, null, $resultSetPrototype);
                },
                'Application\Model\PickTable' =>  function($sm) {
                    $tableGateway = $sm->get('PickTableGateway');
                    $table = new \Application\Model\PickTable($tableGateway);
                    return $table;
                },
                'PickTableGateway' => function ($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new \Application\Model\Pick());
                    return new TableGateway('pick', $dbAdapter, null, $resultSetPrototype);
                },
                'Application\Model\UserTable' =>  function($sm) {
                    $tableGateway = $sm->get('UserTableGateway');
                    $table = new \Application\Model\UserTable($tableGateway);
                    return $table;
                },
                'UserTableGateway' => function ($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new \Application\Model\User());
                    return new TableGateway('user', $dbAdapter, null, $resultSetPrototype);
                },
                'Application\Model\DraftPlayerBasicTable' =>  function($sm) {
                    $tableGateway = $sm->get('DraftPlayerBasicTableGateway');
                    $table = new \Application\Model\DraftPlayerBasicTable($tableGateway);
                    return $table;
                },
                'DraftPlayerBasicTableGateway' => function ($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new \Application\Model\DraftPlayerBasic());
                    return new TableGateway('draft_player_basic', $dbAdapter, null, $resultSetPrototype);
                },
            )
        );
     }
}

function resultSetToArray($resultSet)
{
	$a = array();
	foreach($resultSet as $item)
	{
		$a[] = $item;		
	}
	return $a;
}
