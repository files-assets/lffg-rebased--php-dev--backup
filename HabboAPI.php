<?php
  class HabboAPI extends Model
  {
    /**
     * @var string - Generated token.
     */
    private $token;

    /**
     * @var string - Habbo base URL.
     */
    private $habbo_url = 'http://www.habbo.com.br';

    public function __construct ()
    {
      if (!isset($_SESSION['habbo_confirm_code']) || empty($_SESSION['habbo_confirm_code'])) {
        $_SESSION['habbo_confirm_code'] = $this->generateToken();
      }

      $this->token = $_SESSION['habbo_confirm_code'];
    }

    public function getData ($username)
    {
      $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.habbo.com.br/api/public/users?name='. $username .'&_timestamp='. time() . rand(1, 99));
        curl_setopt($ch, CURLOPT_USERAGENT, 'Site :: '. ABBR);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $data = curl_exec($ch);
        $info = curl_getinfo($ch);
      curl_close($ch);

      if ($info['http_code'] != 200) {
        return false;
      }

      if (isset($data->error)) {
        return false;
      }

      $data = json_decode($data);
      return $data;
    }

    public function getToken()
    {
      return $this->token;
    }

    public function compareToken ($userToken)
    {
      if ($this->token === $userToken) {
        return true;
      }

      return false;
    }

    private function generateToken ()
    {
      return 'RCC-Ativar-'. rand(10000, 99999);
    }

    public function destroyToken ()
    {
      unset($_SESSION['habbo_confirm_code']);
    }

    public function compareMotto($username, $userToken)
    {
      if (!$this->getData($username)) {
        return false;
      }

      if ($this->getData($username)->motto === $userToken) {
        return true;
      }

      return false;
    }

    public function getAvatar ($username)
    {
      $base  = '/habbo-imaging/avatarimage';
      $state = '&action=std&direction=2&head_direction=3&gesture=sml&size=l';

      return $this->habbo_url . $base . '?user=' . $username . $state;
    }
  }
?>
