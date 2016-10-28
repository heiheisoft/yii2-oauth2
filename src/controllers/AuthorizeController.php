<?php
/**
 * DefaultController.php
 *
 * PHP version 5.6+
 *
 * @author pgaultier
 * @copyright 2010-2016 Ibitux
 * @license http://www.ibitux.com/license license
 * @version XXX
 * @link http://www.ibitux.com
 * @package sweelix\oauth2\server\controllers
 */

namespace sweelix\oauth2\server\controllers;

use OAuth2\Request as OAuth2Request;
use OAuth2\Response as OAuth2Response;
use yii\web\Controller;
use yii\web\Response;
use Yii;

/**
 * Oauth2 main controller
 *
 * @author pgaultier
 * @copyright 2010-2016 Ibitux
 * @license http://www.ibitux.com/license license
 * @version XXX
 * @link http://www.ibitux.com
 * @package sweelix\oauth2\server\controllers
 * @since XXX
 */
class AuthorizeController extends Controller
{

    /**
     * @var string
     */
    private $userClass;

    /**
     * @return string classname for selected interface
     * @since XXX
     */
    public function getUserClass()
    {
        if ($this->userClass === null) {
            $scope = Yii::createObject('sweelix\oauth2\server\interfaces\UserModelInterface');
            $this->userClass = get_class($scope);
        }
        return $this->userClass;
    }

    /**
     * Send back an oauth token
     * @return Response
     * @since XXX
     */
    public function actionIndex()
    {
        $oauthServer = Yii::createObject('OAuth2\Server');
        $status = false;
        /* @var \Oauth2\Server $oauthServer */
        $grantType = Yii::$app->request->getQueryParam('response_type');
        switch ($grantType) {
            case 'code':
                $oauthGrantType = Yii::createObject('OAuth2\GrantType\AuthorizationCode');
                /* @var \OAuth2\GrantType\AuthorizationCode $oauthGrantType */
                $oauthServer->addGrantType($oauthGrantType);
                $oauthRequest = OAuth2Request::createFromGlobals();
                $status = $oauthServer->validateAuthorizeRequest($oauthRequest);
                break;
        }

        if ($status === true) {
            Yii::$app->session->set('oauthServer', $oauthServer);
            if (isset($oauthRequest) === true) {
                Yii::$app->session->set('oauthRequest', $oauthRequest);
            }
            $this->redirect(['authorize/login']);
        } else {
            //TODO: check if we should redirect to specific url with an error
            $this->redirect(['authorize/error']);
        }
    }

    /**
     * Display login page
     * @return Response|string
     * @since XXX
     */
    public function actionLogin()
    {
        $oauthServer = Yii::$app->session->get('oauthServer');
        /* @var \Oauth2\Server $oauthServer */
        $oauthController = $oauthServer->getAuthorizeController();
        $userForm = Yii::createObject('sweelix\oauth2\server\forms\User');
        /* @var \sweelix\oauth2\server\forms\User $userForm */
        if (Yii::$app->request->isPost === true) {
            //TODO: handle case when user decline the grants
            $userForm->load(Yii::$app->request->bodyParams);
            if ($userForm->validate() === true) {
                $userClass = $this->getUserClass();
                $realUser = $userClass::findByUsernameAndPassword($userForm->username, $userForm->password);
                /* @var \sweelix\oauth2\server\interfaces\UserModelInterface $realUser */
                if ($realUser !== null) {
                    //login successful
                    $oauthResponse = new OAuth2Response();
                    $oauthRequest = Yii::$app->session->get('oauthRequest');
                    $oauthResponse = $oauthServer->handleAuthorizeRequest($oauthRequest, $oauthResponse, true, $realUser->getId());
                    /* @var OAuth2Response $oauthResponse */
                    $error = $oauthResponse->getParameter('error');
                    Yii::$app->session->remove('oauthServer');
                    Yii::$app->session->remove('oauthRequest');
                    if ($error === null) {
                        return $this->redirect($oauthResponse->getHttpHeader('Location'));
                    } else {
                        return $this->redirect(['error', 'error' => $error]);
                    }
                } else {
                    $userForm->addError('username');
                }
            }
        }
        return $this->render('login', [
            'user' => $userForm
        ]);
    }

    /**
     * Display an error page
     * @return Response|string
     * @since XXX
     */
    public function actionError()
    {
        return $this->render('error');
    }

}