<?php
  class Auth extends Model
  {
    private $token = '';

    /**
     * Construtor.
     * Responsável por exigir a criação de um token caso este não exista.
     */
    public function __construct ()
    {
      if (!isset($_SESSION['token']) || empty($_SESSION['token'])) {
        $_SESSION['token'] = $this->generateToken();
      }

      $this->token = $_SESSION['token'];
    }

    /**
     * Gerador de tokens.
     *
     * @return - Um novo token.
     */
    private function generateToken ()
    {
      $token = hash('sha256', rand(0, 9999) . time() . rand(0, 9999) . hash('sha256', time() . rand(0, 9999)));

      return $token;
    }

    /**
     * Capturador do token.
     *
     * @return string - Token.
     */
    public function getToken ()
    {
      return $this->token;
    }

    /**
     * Comparador de tokens.
     * Compara o token do parâmetro pelo token original desta classe.
     *
     * @return true - Caso ambos os tokens forem iguais.
     */
    public function compareToken ($userToken)
    {
      if ($this->token === $userToken) {
        return true;
      }

      return false;
    }

    /**
     * Função para dizimar o token existente:
     */
    public function destroyToken ()
    {
      unset($_SESSION['token']);
    }
  }
?>
