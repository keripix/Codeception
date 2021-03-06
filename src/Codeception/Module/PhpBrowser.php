<?php

namespace Codeception\Module;

use Guzzle\Http\Client;
use Codeception\Exception\TestRuntime;

/**
 * Uses [Mink](http://mink.behat.org) with [Goutte](https://github.com/fabpot/Goutte) and [Guzzle](http://guzzlephp.org/) to interact with your application over CURL.
 * Module works over CURL and requires **PHP CURL extension** to be enabled.
 *
 * Use to perform web acceptance tests with non-javascript browser.
 *
 * If test fails stores last shown page in 'output' dir.
 *
 * ## Status
 *
 * * Maintainer: **davert**
 * * Stability: **stable**
 * * Contact: davert.codecept@mailican.com
 * * relies on [Mink](http://mink.behat.org) and [Guzzle](http://guzzlephp.org/)
 *
 * *Please review the code of non-stable modules and provide patches if you have issues.*
 *
 * ## Configuration
 *
 * * url *required* - start url of your app
 * * curl - curl options
 *
 * ### Example (`acceptance.suite.yml`)
 *
 *     modules:
 *        enabled: [PhpBrowser]
 *        config:
 *           PhpBrowser:
 *              url: 'http://localhost'
 *              curl:
 *                  CURLOPT_RETURNTRANSFER: true
 *
 * ## Public Properties
 *
 * * session - contains Mink Session
 * * guzzle - contains [Guzzle](http://guzzlephp.org/) client instance: `\Guzzle\Http\Client`
 *
 * All SSL certification checks are disabled by default.
 * To configure CURL options use `curl` config parameter.
 *
 */
class PhpBrowser extends \Codeception\Util\Mink implements \Codeception\Util\FrameworkInterface {

    protected $requiredFields = array('url');
    protected $config = array('curl' => array());

    protected $curl_defaults = array(
        'CURLOPT_SSL_VERIFYPEER' => false,
        'CURLOPT_CERTINFO' => false
    );

    /**
     * @var \Guzzle\Http\Client
     */
    public $guzzle;

    public function _initialize() {
        $client = new \Behat\Mink\Driver\Goutte\Client();
        $driver = new \Behat\Mink\Driver\GoutteDriver($client);

        $curl_config = array_merge($this->curl_defaults, $this->config['curl']);
        array_walk($curl_config, function($a, &$b) { $b = "curl.$b"; });

        $client->setClient($this->guzzle = new Client('', $curl_config));
        $this->session = new \Behat\Mink\Session($driver);
        parent::_initialize();
    }

    public function submitForm($selector, $params) {
        $form = $this->session->getPage()->find('css',$selector);

        if ($form === null)
            throw new TestRuntime("Form with selector: \"$selector\" was not found on given page.");

        $fields = $this->session->getPage()->findAll('css', $selector.' input');
        $url = '';

        foreach ($fields as $field) {
            $url .= sprintf('%s=%s',$field->getAttribute('name'), $field->getAttribute('value')).'&';
        }

        $fields = $this->session->getPage()->findAll('css', $selector.' textarea');
        foreach ($fields as $field) {
            $url .= sprintf('%s=%s',$field->getAttribute('name'), $field->getValue()).'&';
        }

        $fields = $this->session->getPage()->findAll('css', $selector.' select');
        foreach ($fields as $field) {
   		    $url .= sprintf('%s=%s',$field->getAttribute('name'), $field->getValue()).'&';
   	    }

        $url .= '&'.http_build_query($params);
        parse_str($url, $params);
        $url = $form->getAttribute('action');
        $method = $form->getAttribute('method');

        $this->call($url, $method, $params);
    }

    public function sendAjaxPostRequest($uri, $params = array()) {
        $this->session->setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        $this->call($uri, 'POST', $params);
        $this->debug($this->session->getPage()->getContent());
    }

    public function sendAjaxGetRequest($uri, $params = array()) {
        $this->session->setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        $query = $params ? '?'. http_build_query($params) : '';
        $this->call($uri.$query, 'GET', $params);
        $this->debug($this->session->getPage()->getContent());
    }

    public function seePageNotFound()
    {
        $this->seeResponseCodeIs(404);
    }

    public function seeResponseCodeIs($code)
    {
        $this->assertEquals($code, $this->session->getStatusCode());
    }

    public function amHttpAuthenticated($username, $password)
    {
        $this->session->getDriver()->setBasicAuth($username, $password);
    }

    /**
     * Low-level API method.
     * If Codeception commands are not enough, use [Guzzle HTTP Client](http://guzzlephp.org/) methods directly
     *
     * Example:
     *
     * ``` php
     * <?php
     * // from the official Guzzle manual
     * $I->amGoingTo('Sign all requests with OAuth');
     * $I->executeInGuzzle(function (\Guzzle\Http\Client $client) {
     *      $client->addSubscriber(new Guzzle\Plugin\Oauth\OauthPlugin(array(
     *                  'consumer_key'    => '***',
     *                  'consumer_secret' => '***',
     *                  'token'           => '***',
     *                  'token_secret'    => '***'
     *      )));
     * });
     * ?>
     * ```
     *
     * Not recommended this command too be used on regular basis.
     * If Codeception lacks important Guzzle Client methods implement then and submit patches.
     *
     * @param callable $function
     */
    public function executeInGuzzle(\Closure $function)
    {
        return $function($this->guzzle);
    }


	protected function call($uri, $method = 'get', $params = array())
	{
        if (strpos($uri,'#')) $uri = substr($uri,0,strpos($uri,'#'));
        $browser = $this->session->getDriver()->getClient();

    	$this->debug('Request ('.$method.'): '.$uri.' '. json_encode($params));
		$browser->request($method, $uri, $params);


		$this->debug('Response code: '.$this->session->getStatusCode());
	}

	public function _failed(\Codeception\TestCase $test, $fail) {
		file_put_contents(\Codeception\Configuration::logDir().basename($test->getFileName()).'.page.fail.html', $this->session->getPage()->getContent());
	}


}
