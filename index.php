<?php
/**
 * YouTube Subtitle Explorer
 * 
 * @author  Jasper Palfree <jasper@wellcaffeinated.net>
 * @copyright 2012 Jasper Palfree
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */

require_once __DIR__.'/vendor/autoload.php';

define('YTSE_ROOT', __DIR__);
define('YTSE_CONFIG_FILE', YTSE_ROOT.'/config/config.yaml');

$app = new Silex\Application();

$app->register(new Igorw\Silex\ConfigServiceProvider(YTSE_CONFIG_FILE, array(
    'ytse.root' => YTSE_ROOT,
)));

$app['ytse.root'] = YTSE_ROOT;

if (!$app['debug']){
    error_reporting(0);
}

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => YTSE_ROOT.'/logs/ytse.log',
    'monolog.level' => Monolog\Logger::WARNING,
));

// email service provider
$app->register(new Silex\Provider\SwiftmailerServiceProvider());

// doctrine for db functions
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver'   => 'pdo_sqlite',
        'path'     => $app['ytse.config']['db.path'],
    ),
));

// url service provider
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
// register the session extension
$app->register(new Silex\Provider\SessionServiceProvider(), array(
    'session.storage.options' => array(
        'secure' => true
    )
));
// register twig templating
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => array(
        YTSE_ROOT.'/user/views',
        YTSE_ROOT.'/app/views',
        YTSE_ROOT.'/app', // for user override inheritence
    ),
    'twig.options' => array(
        'cache' => YTSE_ROOT.'/cache/',
    ),
));

// auto update
$app->register(new YTSE\Util\AutoUpdateProvider());
// state manager
$app->register(new YTSE\Util\StateManagerProvider());
// email notifications
$app->register(new YTSE\Util\EmailNotificationProvider());
// register api mediator provider
$app->register(new YTSE\API\APIMediatorProvider());
// register playlist provider
$app->register(new YTSE\Playlist\YTPlaylistProvider());
// user manager
$app->register(new YTSE\Users\UserManagerProvider());
// register oauth login manager
$app->register(new YTSE\OAuth\OAuthProvider());
// caption manager
$app->register(new YTSE\Captions\CaptionManagerProvider());
// maintenance mode provider
$app->register(new YTSE\Util\MaintenanceModeProvider(), array(
    'maintenance_mode.options' => array(
        'base_dir' => YTSE_ROOT.'/config',
    ),
));

$app['refresh.data'] = $app->protect(function() use ($app) {

    $pl = $app['ytplaylist'];

    try {

        $data = $app['api']->getYTPlaylist($pl->getId());

    } catch (\Exception $e){

        $app['monolog']->addError('Failed to refresh: ' . $e->getMessage());
        return false;
    }

    if (!$data){

        return false;
    }

    $ids = array();

    foreach ($data['videos'] as &$video){

        $ids[] = $video['ytid'];
    }

    try {

        $allLangs = $app['api']->getYTLanguages($ids);
        $capData = $app['api']->getYTCaptions($ids, $app['oauth']->getValidAdminToken());

    } catch (\Exception $e){

        $app['monolog']->addError('Failed to refresh: ' . $e->getMessage());
        return false;
    }

    foreach ($data['videos'] as &$video){

        if (array_key_exists($video['ytid'], $allLangs))
            $video['languages'] = $allLangs[ $video['ytid'] ];

        if (array_key_exists($video['ytid'], $capData))
            $video['caption_links'] = $capData[ $video['ytid'] ];
    }

    try {

        $pl->setData($data);
        $pl->syncLocal();
        
    } catch (\Exception $e){
        
        $app['monolog']->addError('Failed to refresh: ' . $e->getMessage());
        return false;
    }
});

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * Error handling
 */
$app->error(function (\Exception $e, $code) use ($app) {

    $page = 'page-error-msg.twig';

    switch ($code) {
        case 404:
            $page = '404.twig';
            $message = 'The requested page could not be found.';
            break;
        case 503:
            $page = 'page-info-msg.twig';
            $message = $e->getMessage();
            break;
        case 500:
            $message = 'We are sorry, but something went terribly wrong. Try again later.';
            break;
        default:
            $message = $e->getMessage();
    }

    return $app['twig']->render($page, array(
    
        'msg' => $message,
    ));
});

/**
 * OAuth Authentication
 */

$app->mount('/', new YTSE\Routes\AuthenticationControllerProvider( $app['oauth'] ));

if ($app['state']->get('ytse_installed') !== 'yes'){
    // do installation
    $app->mount('/', new YTSE\Routes\InstallationControllerProvider());
    $app->run();
    exit;
}

function sendTokenEmailWarning(){

    global $app;

    $last = $app['state']->get('last_admin_token_warning');

    $now = new DateTime('now');

    if ($last){

        $timeout = new DateTime();
        $timeout->setTimestamp((int)$last);
        // two hour gap
        $timeout->add( new DateInterval('PT2H') );
    }

    if (!$last || $now > $timeout){

        $config = $app['ytse.config'];

        if (!isset($config['email_notify']) || empty($config['email_notify'])) return;

        $app['email_notification'](
            $config['email_notify'],
            'WARNING: Authentication failed',
            'email-notify-authentication-failed.twig'
        );

        $app['state']->set('last_admin_token_warning', $now->getTimestamp());
    }
}

/**
 * Check if the user has admin authorization (the only kind)
 */
$checkAuthorization = function(Request $req, Silex\Application $app){

    if ( !$app['oauth']->hasYoutubeAuth() ){

        $app['oauth']->doYoutubeAuth();
        $app['session']->set('login_referrer', $req->getRequestUri());

        return new \Symfony\Component\HttpFoundation\RedirectResponse(
            $app['url_generator']->generate('authenticate')
        );
    }

    // if you are not the administrator, get lost
    if ( !$app['oauth']->isAuthorized() ){
        
        return $app->abort(401, "You are not authorized. Please log out and try again.");
    }
};

/**
 * Check it the user has been authenticated with google
 */
$checkAuthentication = function(Request $req, Silex\Application $app){

    if ( !$app['oauth']->isLoggedIn() ){

        $app['session']->set('login_referrer', $req->getRequestUri());

        // redirect to login page
        return new \Symfony\Component\HttpFoundation\RedirectResponse(
            $app['url_generator']->generate('login')
        );
    }
};

/**
 * Enable app to access user's youtube data when authenticated
 */
$needYoutubeAuth = function(Request $req, Silex\Application $app){

    $app['oauth']->doYoutubeAuth();
};


/**
 * Before routing
 */
$app->before(function(Request $request) use ($app) {

    if (!$app['oauth']->isAdminTokenValid()){

        sendTokenEmailWarning();
    }

    $path = $request->getPathInfo();

    if ($app['maintenance_mode']->isEnabled() && !preg_match('/\/(admin|login|logout)/', $path) ){

        $app->abort(503, 'Site is down for maintenance. Check back soon.');
    }

    $pl = $app['ytplaylist'];

    // check to see if we need an update from remote
    if (!$pl->hasData()){ // || $request->get('refresh') === 'true'){

        // start update process
        $app['refresh.data']();
    }
    
});

/**
 * Main Site
 */
$app->get('/', function(Silex\Application $app) {

    $orderby = $app['ytplaylist.config']['orderby'];
    $direction = strtolower($app['ytplaylist.config']['direction']) === 'asc';

    return $app['twig']->render('page-video-search.twig', array(
        'playlist' => $app['ytplaylist']->getData(),
        'videos' => $app['ytplaylist']->getVideos($orderby, $direction),
    ));
})->bind('search_page');

/**
 * Language Data
 */
$app->mount('/videos', new YTSE\Routes\LanguageDataControllerProvider());

/**
 * Contributions
 */
$contrib = new YTSE\Routes\ContributionControllerProvider();
$contrib = $contrib->connect($app);
$contrib->before($checkAuthentication);
$app->mount('/contribute', $contrib);

/**
 * User Profile settings
 */
$userProfile = new YTSE\Routes\UserProfileControllerProvider();
$userProfile = $userProfile->connect($app);
$userProfile->before($checkAuthentication);
$app->mount('/profile', $userProfile);

/**
 * Administration
 */

$admin = new YTSE\Routes\AdministrationControllerProvider();
$admin = $admin->connect($app);
$admin->before($needYoutubeAuth);
$admin->before($checkAuthentication);
$admin->before($checkAuthorization);
$app->mount('/admin', $admin);

/**
 * After response is sent
 */
$app->finish(function(Request $request, Response $response) use ($app) {

    $pl = $app['ytplaylist'];

    // check to see if we need an update from remote
    if ($pl->isDirty()){

        // start update process
        $app['refresh.data']();
    }

});


/**
 * Start App
 */
$app->run();
