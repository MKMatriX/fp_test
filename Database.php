<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $buildedQuery = $query;

        $argIndex = 0;
        $argsCount = count($args);

        $spotIndex = mb_strpos($buildedQuery, "?");
        $nextArg = $args[$argIndex];

        while($spotIndex !== false && $argIndex++ < $argsCount) {
            $beforeSpot = substr($buildedQuery, 0, $spotIndex);
            $afterSpot = substr($buildedQuery, $spotIndex + 1);

            $buildedQuery = $beforeSpot;
            if (is_string($nextArg)) {
                $buildedQuery .= "'" . $this->mysqli->real_escape_string($nextArg) . "'";
            }
            $buildedQuery .= $afterSpot;


            $spotIndex = mb_strpos($buildedQuery, "?");
            $nextArg = $args[$argIndex];
        }

        return $buildedQuery;
    }

    public function skip()
    {
        // throw new Exception();
    }
}
