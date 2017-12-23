<?php
  class Users extends Model
  {
    public function getTotalUsers ()
    {
      $query = $this->pdo->query('SELECT COUNT(*) AS c FROM userlist');
      $query->execute();

      if (!$query->rowCount()) {
        return false;
      }

      $row = $query->fetch();

      return intval($row['c']);
    }

    public function listAll ($limit, $offset)
    {
      $query = $this->pdo->query('SELECT * FROM userlist');
      $query->execute();

      if (!$query->rowCount()) {
        return false;
      }

      $row = $query->fetchAll(PDO::FETCH_ASSOC);

      return $row;
    }
  }
?>
