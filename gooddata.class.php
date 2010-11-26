<?php

/**
 * Class simplifying use of GoodData API
 */
class GoodData {

    private static $HOST = 'secure.gooddata.com'; // configure if using a testing server
    private $headers = array('accept' => 'Accept: application/json','content-type' => 'Content-Type: application/json; charset=utf-8');
    private $authSst;
    private $authTt;

    /**
     * Constructor
     *
     * @param string $login Username to use for login
     * @param string $password Password to use for login
     * @param string $project Optional Initial project id, can be re-set later
     * @author Jakub Nesetril
     */
    public function __construct() {
        self::$HOST = 'https://secure.gooddata.com';
        $this->authSst = $sst;
    }
    
    public function login($login, $password) {
        // POST /gdc/account/login {postUserLogin: {login: '...', password: '...', remember: 0/1}}
        $data = json_encode(array('postUserLogin'=>array('login'=>$login,'password'=>$password,'remember'=>0)));
        
        // https://secure.gooddata.com/gdc/account/login
        $stream = $this->getHttpStream(self::$HOST.'/gdc/account/login', 'POST', array_values($this->headers), $data);
        $meta = stream_get_meta_data($stream);
        fclose($stream);

        // Find GDCAuthSST cookie value and save
        $this->authSst = $this->findCookie($meta['wrapper_data'], 'GDCAuthSST');
        if (is_null($this->authSst)) {
            throw new GDCException("Cookie GDCAuthSST not found (".__METHOD__.")");
        }
        
        $this->getToken();
                
        // Add both cookies to headers
        $this->headers['x-gdc-auth'] = "X-GDC-AUTH: ".$this->authSst;
        $this->headers['cookie'] = "Cookie: GDCAuthSST=".$this->authSst."; GDCAuthTT=".$this->authTt."\r\n";
    }
    
    
    /**
     * Retrieves and returns security token
     *
     * @return string
     */
    public function getToken() {
        $stream = $this->getHttpStream(self::$HOST.'/gdc/account/token', 'GET');
        $meta = stream_get_meta_data($stream);
        fclose($stream);
        // Find GDCAuthTT cookie value and save
        $token = $this->findCookie($meta['wrapper_data'], 'GDCAuthTT');
        if (is_null($token)) {
            var_dump($meta['wrapper_data']);
            throw new Exception('status=FAILED action=getToken Did not find GDCAuthTT in response cookies');
            exit;
        }
        $this->authTt = $token;
        return $token;
    }

    /**
     * Get Permissions for User in a Project
     *
     * @return String JSON-string
     * @author Jakub Nešetřil
     **/
    public function getPermissions($project, $user) {
        $url = self::$HOST.$project.'/users/'.$user.'/permissions';
        $stream = $this->getHttpStream($url,'GET');
        $response = stream_get_contents($stream);
        fclose($stream);
        return json_decode($response);
    }
    
    /**
     * Get User object from URL
     *
     * @return String JSON-string
     * @param String $url Link to the user (starting with /gdc)
     * @author Jakub Nešetřil
     **/
    public function getUser($url) {
        $url = self::$HOST.$url;
        $stream = $this->getHttpStream($url,'GET');
        $response = stream_get_contents($stream);
        fclose($stream);
        return json_decode($response);
    }
    
    /**
     * undocumented function
     *
     * @return void
     * @author Jakub Nešetřil
     **/
    public function getProjects() {
      $url = self::$HOST.'/gdc/md';
      $stream = $this->getHttpStream($url,'GET');
      $response = stream_get_contents($stream);
      fclose($stream);
      return json_decode($response);
    }

    /**
     * Get Project Information
     *
     * @return String JSON-string
     * @author Jakub Nešetřil
     **/
    public function getProject($project) {
        $url = self::$HOST.$project;
        $stream = $this->getHttpStream($url,'GET');
        $response = stream_get_contents($stream);
        fclose($stream);
        return json_decode($response);
    }

    /**
     * Utility function: Gets an HTTP stream using options passed
     *
     * @param string $api Which part of api to use (eg 'login')
     * @param string $method POST / GET
     * @param array $headers Array of headers to use, as well as standard headers
     * @param string $content Data to be included in request. Should usually be JSON-encoded
     * @return HTTP Stream context
     * @author Jakub Nesetril
     */
    private function getHttpStream($url, $method, $headers=array(), $content='') {
        global $cafile;

        // Add dynamic headers (cookie, x-gdc-auth) and combine custom headers with standard headers
        if (!array_key_exists('cookie', $headers)) {
            $headers['cookie'] = 'Cookie: GDCAuthSST='.$this->authSst.'; GDCAuthTT='.$this->authTt;
        }
        if (isset($this->authSst)) {
            $headers['x-gdc-auth'] = "X-GDC-AUTH: ".$this->authSst;
        }
        $headers = array_filter(array_merge($this->headers, $headers));

        // Set up context options including content
        $options = array(
            'ssl' => array(),
            'http' => array(
                'method' => $method,
                'header' => array_values($headers),
            )
        );
        if (!empty($content)) {
            $options['http']['content'] = $content;
        }

        // Try to set up encryption
        if (file_exists($cafile)) {
            $options['ssl']['cafile'] = $cafile;
            $options['ssl']['verify_peer'] = true;
        } else {
            echo('status=WARNING action=http Proceeding without peer cerfificate verification');
        }

        // Get context and stream, return stream
        $context = stream_context_create($options);
        $stream = fopen($url, 'r', false, $context); // Suppress warning on failure as fopen error is unusable
        if (!$stream) {
            throw new Exception('status=FAILED action=http Could not open stream to '.$url,'ERROR');
            throw new Exception('Could not open stream to '.$url);
            exit;
        }
        return $stream;
    }

    /**
     * Utility function: Retrieves specified cookie from supplied response headers
     * NB: Very basic parsing - ignores path, domain, expiry
     *
     * @return string or null if specified cookie not found
     * @author Jakub Nesetril
     */
    private function findCookie(array $headers, $name) {
        $cookie = array_shift(array_filter($headers, create_function('$v', 'return (strpos($v, "Set-Cookie: '.$name.'=") === 0);')));
        if (!$cookie) {
            return null;
        }
        $cookie = array_shift(explode('; ', str_replace('Set-Cookie: ', '', $cookie)));
        $cookie = substr($cookie, strpos($cookie, '=')+1);
        return $cookie;
    }
}

?>
