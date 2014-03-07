<?php
namespace core\db;
class extended_PDO extends \PDO
{
    private $queryCount = 0;

    public function query($query)
    {
    // Increment the counter.
        ++$this->queryCount;

    // Run the query.
        return parent::query($query);
    }

    public function exec($statement) {
    // Increment the counter.
        ++$this->queryCount;

    // Execute the statement.
        return parent::exec($statement);
    }

    public function GetCount() {
        return $this->queryCount;
    }
}
?>