<?php
  class User extends Model
  {

    /**
     * @var userdata - Responsável pelas informações do usuário.
     * @var permissions - Resopnsável pelas permissões do usuário.
     */
    private $userdata    = [];
    private $permissions = [];

    /**
     * @return null
     */
    public function __construct ()
    {
      parent::__construct();

      $this->setBaseUser();

      if (!$this->isLogged()) {
        $this->checkLoggedCookie();
      }
    }

    /**
     * @return boolean - Dependendo do estado de conexão do usuário:
     * @return true - Caso o usuário setado estiver logado.
     * @return false - Caso o usuário setado for padrão — e não estiver logado.
     */
    private function setBaseUser ()
    {
      $h = new HabboAPI();

      if (!$this->isLogged()) {
        $this->userdata = [
          'id' => 0,
          'habbo_id'      => 'no_habbo_id',
          'username'      => 'null',
          'password'      => 'null_password',
          'tag'           => 'xxx',
          'group_id'      => 0,
          'patent_id'     => 0,
          'register_date' => 0,
          'last_visit'    => 0,
          'active'        => 0,
          'banner'        => 1,
          'avatar'        => $h->getAvatar('Convidado')
        ];

        return false;
      }

      $username = $_SESSION['ccUser'];

      $query = $this->pdo->prepare('
        SELECT * FROM userlist
          WHERE
        username = :username
      ');
      $query->bindValue(':username', $username, PDO::PARAM_STR);
      $query->execute();

      if (!$query->rowCount()) {
        die('Erro global. Contate um administrador. [Informações : UserModel | Cód. 001]');
        exit;
      }

      $row = $query->fetch(PDO::FETCH_ASSOC);
      $this->userdata = $row;
      $this->userdata['avatar'] = $h->getAvatar($row['username']);

      return true;
    }

    /**
     * @return boolean - Dependendo do estado de conexão do usuário:
     * @return true - Caso o usuário estiver logado.
     * @return false - Caso o usuário não estiver logado.
     */
    public function isLogged ()
    {
      if (isset($_SESSION['ccUser']) && !empty($_SESSION['ccUser'])) {
        $query = $this->pdo->prepare('
          SELECT COUNT(*) AS c FROM userlist
            WHERE
          username = :username
        ');
        $query->bindValue(':username', $_SESSION['ccUser']);
        $query->execute();

        $row = $query->fetch();

        if (!$query->rowCount() || intval($row['c']) === 0) {
          return false;
        }

        if (intval($row['c']) > 0) {
          return true;
        }
      }

      return false;
    }

    public function getUserdata ()
    {
      return $this->userdata;
    }

    public function updateLastVisit ()
    {
      if ($_SESSION['ccUser'] !== $this->userdata['username']) {
        return false;
      }

      $query = $this->pdo->prepare('
        UPDATE userlist SET
          last_visit = :last_visit
        WHERE
          username = :username
      ');
      $query->bindValue(':last_visit', time(), PDO::PARAM_INT);
      $query->bindValue(':username', $this->userdata['username'], PDO::PARAM_STR);
      $query->execute();

      return true;
    }

    public function login ($username, $password, $remember, $token)
    {
      $password = hash('sha256', $password);

      $a = new Auth();

      $status = [
        'status'   => true,
        'response' => ''
      ];

      $query = $this->pdo->prepare('
        SELECT * FROM userlist
          WHERE
        username = :username
      ');
      $query->bindValue(':username', $username, PDO::PARAM_STR);
      $query->execute();

      $row = $query->fetch(PDO::FETCH_ASSOC);

      if (!$query->rowCount()) {
        $status['status']   = false;
        $status['response'] = 'Usuário inexistente.';

        return $status;
      }

      if ($row['username'] !== $username || $row['password'] !== $password) {
        $status['status'] = false;
        $status['response'] = 'Usuário e/ou senha incorretos.';
      } elseif (intval($row['active']) === 0) {
        $status['status'] = false;
        $status['response'] = 'Usuário inativo.';
      } elseif (intval($row['banned']) === 1) {
        $status['status'] = false;
        $status['response'] = 'Usuário <strong>banido</strong>.';
      } elseif (!$a->compareToken($token)) {
        $status['status'] = false;
        $status['response'] = 'Token inválido. <strong>Limpe o cache do navegador e tente novamente.</strong>';
      }

      if ($status['status']) {
        $_SESSION['ccUser'] = $row['username'];

        if ($remember) {
          $this->createLoginCookie($row['id'], $row['username']);
        }
      }

      return $status;
    }

    public function logout ()
    {
      unset($_SESSION['ccUser']);

      $this->deleteLoginCookie();

      session_destroy();
    }

    /**
     * Função para criar o cookie.
     * @param id - Corresponde ao ID do usuário.
     * @param username - Corresponde ao nome do usuário.
     */
    private function createLoginCookie ($id, $username)
    {
      $cookie_random_hash = hash('sha256', time() . mt_rand());

      # Inserir o token na base de dados:
      $query = $this->pdo->prepare('
        UPDATE userlist SET
          cookie_random_string = :cookie_random_string
        WHERE
          username = :username
        AND
          id = :id
      ');
      $query->bindValue(':cookie_random_string', $cookie_random_hash);
      $query->bindValue(':username', $username);
      $query->bindValue(':id', $id);
      $query->execute();

      $cookie_first_part = $id . ':' . $cookie_random_hash;
      $cookie_entry = $cookie_first_part . ':' . hash('sha256', $cookie_first_part . $id);

      setcookie('rememberme', $cookie_entry, time() + 3600 * 24 * 14, '/');
      $_COOKIE['rememberme'] = $cookie_entry;

      return true;
    }

    /**
     * @return true - Se o usuário tiver logado via cookie.
     */
    private function checkLoggedCookie ()
    {
      if (!isset($_COOKIE['rememberme']) || empty($_COOKIE['rememberme'])) {
        return false;
      }

      list ($id, $random_hash, $custom_hash) = explode(':', $_COOKIE['rememberme']);

      $cookie_first  = $id . ':' . $random_hash;
      $cookie_second = hash('sha256', $cookie_first . $id);
      $cookie_entry = $cookie_first . ':' . $cookie_second;

      if ($_COOKIE['rememberme'] !== $cookie_entry) {
        return false;
      }

      $query = $this->pdo->prepare('
        SELECT * FROM userlist
          WHERE
        id = :id
      ');
      $query->bindValue(':id', $id);
      $query->execute();

      if (!$query->rowCount()) {
        return false;
      }

      $row = $query->fetch();

      if ($row['cookie_random_string'] !== $random_hash) {
        return false;
      }

      $this->createLoginCookie($row['id'], $row['username']);

      $_SESSION['ccUser'] = $row['username'];

      return true;
    }

    /**
     * @return true - Caso o cookie for deletado.
     */
    private function deleteLoginCookie ()
    {
      if (isset($_COOKIE['rememberme'])) {
        setcookie('rememberme', null, time() - 10000, '/');
        return true;
      }

      return false;
    }
  }
?>
