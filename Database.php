<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
	private mysqli $mysqli;

	private $beforeSpot = "";
	private $afterSpot = "";
	public const MODIFIERS = ["d", "f", "a", "#"];

	public function __construct(mysqli $mysqli)
	{
		$this->mysqli = $mysqli;
	}

	public function buildQuery(string $query, array $args = []): string
	{
		// I prefer to have another var
		$buildedQuery = $query;

		// start values
		// mb it is not that good to use class vars but w/e
		$argsCount = count($args);
		$this->beforeSpot = "";
		$this->afterSpot = $buildedQuery;
		$argIndex = 0;
		$spotIndex = 0;
		$nextArg = "";

		$cycleStep = function ($argIndex) use ($args) {
			$spotIndex = mb_strpos($this->afterSpot, "?");
			$nextArg = $args[$argIndex];
			return [$spotIndex, $nextArg, $argIndex];
		};

		list($spotIndex, $nextArg) = $cycleStep($argIndex);

		// main cycle while we have places to insert && data to insert
		while ($spotIndex !== false && $argIndex++ < $argsCount) {
			$modifier = $this->explodeQuery($spotIndex);
			$this->beforeSpot .= $this->escapeArgument($nextArg, $modifier);
			list($spotIndex, $nextArg) = $cycleStep($argIndex);
		}

		// making result
		$buildedQuery = $this->beforeSpot . $this->afterSpot;

		// check if amount of "?" !== count($args), just to warn dev, mb suppress
		if ($spotIndex !== false && $argIndex != $argsCount) {
			throw new Exception("No argument to place in spot", 1);
		}
		if ($spotIndex === false && $argIndex < $argsCount) {
			throw new Exception("No spot to place an argument", 1);
		}

		return $buildedQuery;
	}

	/**
	 * @param mixed $spotIndex индекс символа ? в оставшейся части запроса
	 *
	 * делим запрос на части до места для вставки и после места для вставки
	 * т.е. "SELECT ?# FROM" -> на "SELECT " и " FROM"
	 * и возвращаем модификатор, если он есть ("#" в примере)
	 *
	 * @return $modifier string модификатор
	 */
	private function explodeQuery($spotIndex):string
	{
		$this->beforeSpot = $this->beforeSpot . mb_substr($this->afterSpot, 0, $spotIndex);
		$modifier = $this->afterSpot[$spotIndex + 1];
		$haveModifier = in_array($modifier, self::MODIFIERS, true);
		$modifier = $haveModifier ? $modifier : "";
		$this->afterSpot = mb_substr($this->afterSpot, $spotIndex + ($haveModifier? 2 : 1));

		return $modifier;
	}

	/**
	 * @param mixed $arg переменная для вставки
	 * @param string $modifier ожидаемый тип переменной, разрешенные в self::MODIFIERS
	 *
	 * Экранируем переменную в соответствии с ожидаемым типом
	 *
	 * @return $escaperArgument string экранированная переменная
	 */
	private function escapeArgument($arg, $modifier = ""):string
	{
		if ($this->proceedBlock($arg)) {
			return "";
		}

		if (is_null($arg)) {
			return "NULL";
		}

		switch ($modifier) {
			case 'a':
				return $this->escapeArgumentArray($arg);
				break;

			case '#':
				return $this->escapeColumn($arg);
				break;

			case 'd':
				return $this->escapeArgumentNumber($arg);
				break;

			case 'f':
				return $this->escapeArgumentFloat($arg);
				break;

			case '':
				return $this->escapeArgumentString($arg);
				break;
			default: // never here, but I have time
				throw new Exception("Error in modifier {$modifier} is not allowed", 1);
				break;
		}
	}

	/**
	 * @param mixed $arg параметр (на случай $this->skip)
	 *
	 * Удалит блок, т.е. "{" ... "}"
	 * с содержимым, если $arg instanceof skipDbFakeClass
	 *
	 * @return bool true если блок удален полностью и false если дальше надо разбираться с аргументом
	 */
	private function proceedBlock($arg):bool
	{
		// bad style with side effects, but this is just a test task
		if ($arg instanceof skipDbFakeClass) {
			// if we are skipping a block
			$lastOpenBracketIndex = mb_strrpos($this->beforeSpot, "{");
			$firstCloseBracketIndex = mb_strpos($this->afterSpot, "}");
			if ($lastOpenBracketIndex === false || $firstCloseBracketIndex === false) {
				throw new Exception("No matching brackets to skip", 1);
			}

			$this->beforeSpot = mb_substr($this->beforeSpot, 0, $lastOpenBracketIndex);
			$this->afterSpot = mb_substr($this->afterSpot, $firstCloseBracketIndex + 1);
			return true;
		} else {
			// in case we are in block, but there were no skip
			$lastOpenBracketIndex = mb_strrpos($this->beforeSpot, "{");
			$firstCloseBracketIndex = mb_strpos($this->afterSpot, "}");
			if ($lastOpenBracketIndex !== false && $firstCloseBracketIndex !== false) {
				$this->beforeSpot =
					mb_substr($this->beforeSpot, 0, $lastOpenBracketIndex)
					. mb_substr($this->beforeSpot, $lastOpenBracketIndex + 1);
				$this->afterSpot =
					mb_substr($this->afterSpot, 0, $firstCloseBracketIndex)
					. mb_substr($this->afterSpot, $firstCloseBracketIndex + 1);
			}
		}
		return false;
	}

	/**
	 * @param string|array $arg параметр для экранизации
	 *
	 * Экранизация с модификатором "#"
	 * examples:
	 *  name -> `name`
	 *  ["name", "last_name"] -> `name`, `last_name`
	 *  ["keys don't work" => "name", "some key" => "last_name"] -> `name`, `last_name`
	 *
	 * @return string
	 */
	private function escapeColumn($arg):string
	{
		if (is_array($arg)) {
			// recursion after refactoring, mb make it simpler?)
			return implode(
				", ",
				array_map(
					[$this, 'escapeColumn'],
					$arg
				)
			);
		}

		return "`". $this->mysqli->real_escape_string((string) $arg)."`";
	}

	/**
	 * @param array $arg параметр для экранизации
	 *
	 * Экранизация с модификатором "a"
	 * examples:
	 *  ['Jack', 'Jill'] -> 'Jack', 'Jill'
	 *  ['name' => 'Jack', 'email' => null] -> `name` = 'Jack', `email` = NULL
	 *
	 * @return string
	 */
	private function escapeArgumentArray($arg):string
	{
		if (is_array($arg)) {
			$parts = [];
			// assoc array vs just arrays
			if (array_keys($arg) === range(0, count($arg) - 1)) {
				foreach ($arg as $key => $value) {
					$parts[] = $this->escapeArgument($value);
				}
			} else {
				// if array is associative
				foreach ($arg as $key => $value) {
					$part = $this->escapeColumn($key);
					$part .= " = ";
					$part .= $this->escapeArgument($value);
					$parts[] = $part;
				}
			}
			return implode(", ", $parts);
		} else {
			throw new Exception("Error in argument '{$arg}' type, expected Array", 1);
		}
	}

	/**
	 * @param mixed $arg параметр для экранизации
	 *
	 * Экранизация с модификатором "n"
	 *
	 * @return string
	 */
	private function escapeArgumentNumber($arg):string
	{
		$message = "Error in argument '{$arg}' type, expected Int";
		if ($arg != (int) $arg) {
			throw new Exception($message, 1);
		}
		$arg = (int) $arg;
		if (!is_int($arg)) {
			throw new Exception($message, 1);
		}

		return $arg;
	}

	/**
	 * @param mixed $arg параметр для экранизации
	 *
	 * Экранизация с модификатором "f"
	 *
	 * @return string
	 */
	private function escapeArgumentFloat($arg):string
	{
		$message = "Error in argument '{$arg}' type, expected Float";
		if ($arg != (float) $arg) {
			throw new Exception($message, 1);
		}
		$arg = (float) $arg;
		if (!is_float($arg)) {
			throw new Exception($message, 1);
		}

		return $arg;
	}

	/**
	 * @param mixed $arg параметр для экранизации
	 *
	 * Экранизация без модификатора
	 *
	 * @return string
	 */
	private function escapeArgumentString($arg):string
	{
		$result = "";
		if (is_bool($arg)) {
			$result = $arg? 1 : 0;
		} elseif (is_numeric($arg)) {
			$result = (string) $arg;
		} elseif (is_string($arg)) {
			$result = "'" . $this->mysqli->real_escape_string($arg) . "'";
		} else {
			throw new Exception("Error in argument type, type: '" . gettype($arg) . "' is not supported", 1);
		}

		return $result;
	}

	public function skip()
	{
		// here were string, but that look bad on my taste
		return (new skipDbFakeClass());
	}
}

/**
 * [Description skipDbFakeClass]
 * Класс чтобы метод Database->skip() не возвращал строку
 * видимо я слишком часто общался со скалистами)
 *
 *
 * -----------------
 * понимаю что два класса в одном файле - плохо, тем более текущая spl_autoload_register
 * не найдет его если он потребуется, но это тестовое задание, а я выпендриваюсь
 * академическим стилем, вдохновленным type классами из scala
 */
class skipDbFakeClass
{
	public function __toString()
	{
		return "Database->skip()";
	}
}; // class to just use type