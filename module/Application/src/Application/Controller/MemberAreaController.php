<?php
/**
 * Zend Framework (http://framework.zend.com/)
*
* @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
* @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
* @license   http://framework.zend.com/license/new-bsd New BSD License
*/

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use League\OAuth2\Client\Provider;
use Application\Model\Set;
use Application\Model\Draft;
use Application\Model\Pick;
use Application\Model\DraftSet;
use Zend\View\Model\JsonModel;
use Application\Model\DraftPlayer;
use Application\Model\User;
use Application\PackGenerator\BoosterDraftPackGenerator;
use Application\PackGenerator\CubePackGenerator;

class MemberAreaController extends AbstractActionController
{
	private function checkUser()
	{
		if(!isset($_SESSION['user_id'])){
			throw new \Exception("Must be logged in to access this page");
		}		
	}
	
	public function indexAction()
	{	
		$this->checkUser();
		
		$sm = $this->getServiceLocator();
		$draftTable = $sm->get('Application\Model\DraftTable');
		$setTable = $sm->get('Application\Model\SetTable');
		
		$viewModel = new ViewModel();
		
		$viewModel->setCreated = isset($_GET["set-created"]);
		$viewModel->setRetired = isset($_GET["set-retired"]);
		$viewModel->draftsHosted = $draftTable->fetchByHost($_SESSION["user_id"]);
		$viewModel->draftsPlayed = $draftTable->getPastDraftsByUser($_SESSION["user_id"]);
		$viewModel->setsOwned = $setTable->fetchByUser($_SESSION["user_id"]);
		
		return $viewModel;
	}
	
	public function loginAction()
	{
		$redirectUri = $this->url()->fromRoute('member-area', array('action' => 'login'), array('force_canonical' => true));
		
		$provider = new \League\OAuth2\Client\Provider\Google([
				'clientId'      => $this->getServiceLocator()->get('Config')['auth']['clientId'],
				'clientSecret'  => $this->getServiceLocator()->get('Config')['auth']['clientSecret'],
				'redirectUri'   => $redirectUri,
				'scopes'        => ['email']
		]);
		
		if (!isset($_GET['code'])) {
		
			// If we don't have an authorization code then get one
			$authUrl = $provider->getAuthorizationUrl();
			$_SESSION['oauth2state'] = $provider->state;
			header('Location: '.$authUrl);
			exit;
		
			// Check given state against previously stored one to mitigate CSRF attack
		} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
		
			unset($_SESSION['oauth2state']);
			exit('Invalid state');
		
		} else {
		
			// Try to get an access token (using the authorization code grant)
			$token = $provider->getAccessToken('authorization_code', [
				'code' => $_GET['code']
			]);
		
			// Optional: Now you have a token you can look up a users profile data
			try {
		
				// We got an access token, let's now get the user's details
				$userDetails = $provider->getUserDetails($token);
		
				$_SESSION["email"] = $userDetails->email;

				$sm = $this->getServiceLocator();
				$userTable = $sm->get('Application\Model\UserTable');
				$user = $userTable->tryGetUserByEmail($userDetails->email);				
				if($user == null)
				{
					$user = new User();
					$user->email = $userDetails->email;
					$userTable->saveUser($user);
					$_SESSION["user_id"] = $user->userId;
				}
				else 
				{
					$_SESSION["user_id"] = $user->userId;
				}
				
				return $this->redirect()->toRoute('member-area');				
		
			} catch (Exception $e) {
		
				// Failed to get user details
				exit('Oh dear...');
			}
		
			// Use this to interact with an API on the users behalf
			echo $token->accessToken;
		
			// Use this to get a new access token if the old one expires
			echo $token->refreshToken;
		
			// Number of seconds until the access token will expire, and need refreshing
			echo $token->expires;
		}
		
		return new ViewModel();
	}
	
	public function logoutAction()
	{
	 	//unset($_SESSION["email"]);
	 	session_destroy();

		return $this->redirect()->toRoute('home');
	}
	
	public function createSetAction()
	{
		$this->checkUser();
		
		$form = new \Application\Form\CreateSetForm();		
	
		if ($this->getRequest()->isPost()) 
		{
			$formData = $this->getRequest()->getPost();
			$form->setData($formData);
			
			if ($form->isValid($formData)) 
			{
				$sm = $this->getServiceLocator();
				$adapter = $sm->get("Zend\Db\Adapter\Adapter");
				$adapter->getDriver()->getConnection()->beginTransaction();
				
				try
				{
					$set = new Set();
					$set->name = $formData["name"];
					$set->code = $formData["code"];
					$set->url = $formData["info_url"];
					$set->downloadUrl = $formData["download_url"];
					$set->userId = $_SESSION["user_id"];
					$set->isRetired = 0;
					
					$setTable = $sm->get('Application\Model\SetTable');
					$setTable->saveSet($set);
					
					$artUrl = $formData["art_url"];
					
					$fileContents = file_get_contents($this->getRequest()->getFiles('file')["tmp_name"]);
					
					$parser = new \Application\SetParser\IsochronDrafterSetParser();
					$cards = $parser->Parse($fileContents);
					
					$cardTable = $sm->get('Application\Model\CardTable');
					foreach($cards as $card)
					{
						$card->setId = $set->setId;
						$card->artUrl = $artUrl . "/" . preg_replace("/[^\p{L}0-9- ]/iu", "", $card->name) . ".png";
						$cardTable->saveCard($card);
					}

					$adapter->getDriver()->getConnection()->commit();
				}
				catch(Exception $e)
				{
					$adapter->getDriver()->getConnection()->rollback();
					throw $e;
				}
								
				return $this->redirect()->toRoute('member-area', array(), array('query' => 'draft-opened'));
			} 
			else 
			{
				$form->populate($formData);
			}
		}
		
		$viewModel = new ViewModel();
		$viewModel->form = $form;
		
		return $viewModel;
	}
	
	public function selectGameModeAction()
	{
		$this->checkUser();
		return new ViewModel();
	}
	
	public function hostDraftAction()
	{
		$this->checkUser();
		
		if(!isset($_REQUEST["mode"]) || (int)$_REQUEST["mode"] < 1)
		{
			throw new \Exception("Game mode not set");
		}
		
		$mode = (int)$_REQUEST["mode"];
		
		$sm = $this->getServiceLocator();
		$setTable = $sm->get('Application\Model\SetTable');		
		$form = new \Application\Form\HostDraftForm($setTable, $mode);
	
		if ($this->getRequest()->isPost())
		{
			$formData = $this->getRequest()->getPost();
			$form->setData($formData);
			if ($form->isValid($formData))
			{
				$sm = $this->getServiceLocator();
				$adapter = $sm->get("Zend\Db\Adapter\Adapter");
				$adapter->getDriver()->getConnection()->beginTransaction();
	
				try
				{
					
					$setTable = $sm->get('Application\Model\SetTable');
					

					$setIds = array();
					$numberOfPacks = (int)$formData['number_of_packs'];
					switch($mode)
					{
						case \Application\Model\Draft::MODE_BOOSTER_DRAFT:
						case \Application\Model\Draft::MODE_SEALED_DECK:
						case \Application\Model\Draft::MODE_CUBE_DRAFT:
							for($i = 1; $i <= $numberOfPacks; $i++)
							{
								$setIds[] = $formData['pack' . $i];
							}
							break;
						case \Application\Model\Draft::MODE_CHAOS_DRAFT:
							$setIds = $formData['pack1'];
							break;
						default:
							throw new \Exception("Invalid game mode " . $mode);
								
					}
					
					switch($mode)
					{
						case \Application\Model\Draft::MODE_BOOSTER_DRAFT:
							$modeName = 'booster draft';
							break;
						case \Application\Model\Draft::MODE_SEALED_DECK:
							$modeName = 'sealed deck';
							break;
						case \Application\Model\Draft::MODE_CUBE_DRAFT:
							$modeName = 'cube draft';
							break;
						case \Application\Model\Draft::MODE_CHAOS_DRAFT:
							$modeName = 'chaos draft';
							break;
						default:
							throw new \Exception("Invalid game mode " . $mode);
					
					}
					
					$sets = array();
					$setCodes = array();					
					foreach($setIds as $setId)
					{
						$set = $setTable->getSet($setId);
						$sets[] = $set;
						$setCodes[] = $set->code;	
					}
					
					$draft = new Draft();
					$draft->name = join("/", $setCodes) . " " . $modeName . " on " . date("r").
					$draft->status = Draft::STATUS_OPEN;
					$draft->hostId = $_SESSION["user_id"];
					$draft->createdOn = date("Y-m-d H:i:s");
					$draft->pickNumber = 1;
					$draft->packNumber = 1;
					$draft->lobbyKey = md5(time() . "lobby key" . $draft->hostId);
					$draft->gameMode = $mode;
						
					$draftTable = $sm->get('Application\Model\DraftTable');
					$draftTable->saveDraft($draft);
						
					$draftSetTable = $sm->get('Application\Model\DraftSetTable');
					foreach($setIds as $index => $setId)
					{
						$draftSet = new DraftSet();
						$draftSet->draftId = $draft->draftId;
						$draftSet->setId = $setId;
						$draftSet->packNumber = $index + 1;
						$draftSetTable->saveDraftSet($draftSet);
					}
	
					$adapter->getDriver()->getConnection()->commit();
				}
				catch(Exception $e)
				{
					$adapter->getDriver()->getConnection()->rollback();
					throw $e;
				}
				
				return $this->redirect()->toRoute('member-area-with-draft-id', array('action' => 'draft-admin', 'draft_id' => $draft->draftId), array('query' => 'draft-opened'));
			}
		}
	
		$viewModel = new ViewModel();
		$viewModel->form = $form;
	
		return $viewModel;
	}
	
	public function draftAdminAction()
	{	
		$this->checkUser();
		
		$draftId = $this->getEvent()->getRouteMatch()->getParam('draft_id');
		
		$sm = $this->getServiceLocator();
		$draftTable = $sm->get('Application\Model\DraftTable');
		
		$viewModel = new ViewModel();
		$viewModel->draftOpened = isset($_GET["draft-opened"]);
		$viewModel->draft = $draftTable->getDraft($draftId);
		//$viewModel->form = $form;
	
		return $viewModel;
	}
	
	public function getDraftPlayersAction()
	{
		$this->checkUser();
		
		$draftId = $this->getEvent()->getRouteMatch()->getParam('draft_id');
		
		$sm = $this->getServiceLocator();
		$draftTable = $sm->get('Application\Model\DraftTable');
		$draftPlayerTable = $sm->get('Application\Model\DraftPlayerTable');
		
		$jsonModel = new JsonModel();
		$jsonModel->draft = $draftTable->getDraft($draftId);
		$draftPlayers = array();
		foreach($draftPlayerTable->fetchByDraft($draftId) as $row)
		{
			$draftPlayers[] = $row;
		}
		$jsonModel->draftPlayers = $draftPlayers;
		return $jsonModel;
	}
	
	public function addDraftPlayerAction()
	{
		$this->checkUser();
		
		$draftId = $this->getEvent()->getRouteMatch()->getParam('draft_id');
	
		$sm = $this->getServiceLocator();
		$draftTable = $sm->get('Application\Model\DraftTable');
		$draftPlayerTable = $sm->get('Application\Model\DraftPlayerTable');
	
		$draft = $draftTable->getDraft($draftId);
		if($draft->status != Draft::STATUS_OPEN)
		{
			throw new Exception("Invalid status");
		}
		
		$inviteKey = md5("draftplayer_" . time());
		
		$draftPlayer = new DraftPlayer();
		$draftPlayer->draftId = $draftId;
		$draftPlayer->hasJoined = 0;
		$draftPlayer->inviteKey = $inviteKey;
		$draftPlayerTable->saveDraftPlayer($draftPlayer);
		
		$jsonModel = new JsonModel();
		$jsonModel->draftPlayer = $draftPlayer;
		return $jsonModel;
	}
	
	public function startDraftAction()
	{
		$this->checkUser();
		
		try
		{
			$draftId = $this->getEvent()->getRouteMatch()->getParam('draft_id');
		
			$sm = $this->getServiceLocator();
			$adapter = $sm->get("Zend\Db\Adapter\Adapter");
			$adapter->getDriver()->getConnection()->beginTransaction();
			
			$draftTable = $sm->get('Application\Model\DraftTable');
			$draftPlayerTable = $sm->get('Application\Model\DraftPlayerTable');
			$draftSetTable = $sm->get('Application\Model\DraftSetTable');
			$cardTable = $sm->get('Application\Model\CardTable');
			$pickTable = $sm->get('Application\Model\PickTable');
			
			// Start the draft
			$draft = $draftTable->getDraft($draftId);
			if($draft->status != Draft::STATUS_OPEN)
			{
				throw new Exception("Invalid status");
			}
			
			$draft->status = $draft->gameMode == Draft::MODE_SEALED_DECK ? Draft::STATUS_FINISHED : Draft::STATUS_RUNNING;
			$draftTable->saveDraft($draft); 

			$draftPlayers = $draftPlayerTable->fetchJoinedByDraft($draftId);
			$draftPlayerArray = array();
			foreach($draftPlayers as $draftPlayer)
			{
				$draftPlayerArray[] = $draftPlayer;
			}
			$numberOfPlayers = count($draftPlayerArray);
			
			// Create packs
			if($draft->gameMode == Draft::MODE_BOOSTER_DRAFT || $draft->gameMode == Draft::MODE_SEALED_DECK)
			{
				$packGenerator = new BoosterDraftPackGenerator();
				$draftSets = $draftSetTable->fetchByDraft($draftId);
				$picks = array();
				foreach($draftSets as $setIndex => $draftSet)
				{		
					$cards = $cardTable->fetchBySet($draftSet->setId);
					$cardArray = array();
					foreach($cards as $card)
					{
						$cardArray[] = $card;
					}
					
					$packs = $packGenerator->GeneratePacks($cardArray, $numberOfPlayers);
					foreach($draftPlayerArray as $playerIndex => $player)
					{
						foreach ($packs[$playerIndex] as $card)
						{
							$pick = new Pick();
							$pick->cardId = $card->cardId;
							$pick->startingPlayerId = $player->draftPlayerId;
							$pick->currentPlayerId = $player->draftPlayerId;
							$pick->isPicked = $draft->gameMode == Draft::MODE_SEALED_DECK ? 1 : 0;
							$pick->packNumber = $setIndex + 1;
							$pick->pickNumber = null;
							$pick->zone = Pick::ZONE_MAINDECK;
							$pick->zoneColumn = 0;
							$picks[] = $pick;
						}
					}
				}
			}
			else if($draft->gameMode == Draft::MODE_CUBE_DRAFT)
			{
				$packGenerator = new CubePackGenerator();				
				$draftSet = $draftSetTable->fetchByDraft($draftId)->current();
				$cards = $cardTable->fetchBySet($draftSet->setId);
				
				$cardArray = array();				
				foreach($cards as $card)
				{
					$cardArray[] = $card;
				}
				
				$packs = $packGenerator->GeneratePacks($cardArray, $numberOfPlayers * 3);
				
				$picks = array();
				foreach($draftPlayerArray as $playerIndex => $player)
				{
					for($i = 0; $i < 3; $i++)
					{
						foreach ($packs[$playerIndex * 3 + $i] as $card)
						{
							$pick = new Pick();
							$pick->cardId = $card->cardId;
							$pick->startingPlayerId = $player->draftPlayerId;
							$pick->currentPlayerId = $player->draftPlayerId;
							$pick->isPicked = $draft->gameMode == Draft::MODE_SEALED_DECK ? 1 : 0;
							$pick->packNumber = $i + 1;
							$pick->pickNumber = null;
							$pick->zone = Pick::ZONE_MAINDECK;
							$pick->zoneColumn = 0;
							$picks[] = $pick;
						}
					}
				}
			}
			else if($draft->gameMode == Draft::MODE_CHAOS_DRAFT)
			{
				$packGenerator = new BoosterDraftPackGenerator();
				$draftSets = $draftSetTable->fetchByDraft($draftId);
				$draftSetArray = array();				
				
				$convertedDraftSets = \Application\resultSetToArray($draftSets);
				while(count($draftSetArray) < 3 * $numberOfPlayers)
				{
					foreach($convertedDraftSets as $draftSet)
					{
						$draftSetArray[] = $draftSet;		
					}
					
					if(count($draftSetArray) == 0) throw new \Exception("No sets selected for this draft");
				}
				
				shuffle($draftSetArray);
				
				$picks = array();
				foreach($draftPlayerArray as $playerIndex => $player)
				{
					for($i = 0; $i < 3; $i++)
					{

						$cards = $cardTable->fetchBySet($draftSetArray[$playerIndex * 3 + $i]->setId);
						$pack = $packGenerator->generatePacks($cards, 1)[0];
						
						foreach ($pack as $card)
						{
							$pick = new Pick();
							$pick->cardId = $card->cardId;
							$pick->startingPlayerId = $player->draftPlayerId;
							$pick->currentPlayerId = $player->draftPlayerId;
							$pick->isPicked = $draft->gameMode == Draft::MODE_SEALED_DECK ? 1 : 0;
							$pick->packNumber = $i + 1;
							$pick->pickNumber = null;
							$pick->zone = Pick::ZONE_MAINDECK;
							$pick->zoneColumn = 0;
							$picks[] = $pick;
						}
					}
				}
			}
			
			foreach($picks as $pick)
			{
				$pickTable->savePick($pick);
			}
			
			// Assign order to players
			shuffle($draftPlayerArray);
			foreach($draftPlayerArray as $playerIndex => $draftPlayer)
			{
				$draftPlayer->playerNumber = $playerIndex + 1;
				$draftPlayerTable->saveDraftPlayer($draftPlayer);
			}
			
			$adapter->getDriver()->getConnection()->commit();
			
			$jsonModel = new JsonModel();
			return $jsonModel;
			
		}
		catch(Exception $e)
		{
			$adapter->getDriver()->getConnection()->rollback();
			throw $e;
		}
	}
	
	public function retireSetAction()
	{
		$this->checkUser();
	
		if(!isset($_GET["set_id"]) || strlen($_GET["set_id"]) < 1)
		{
			throw new \Exception("Set not set");
		}
		
		$sm = $this->getServiceLocator();
		$setTable = $sm->get('Application\Model\SetTable');
	
		$set = $setTable->getSet($_GET["set_id"]);
		
		if($_SESSION["user_id"] != $set->userId)
		{
			throw new \Exception("You don't own this set.");			
		}
		
		$set->isRetired = 1;
		$setTable->saveSet($set);

		return $this->redirect()->toRoute('member-area', array(), array('query' => 'set-retired'));
	}
}
?>