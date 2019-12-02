<?php

declare(strict_types=1);

namespace App\Model;
use Nette\Application\UI\Form;

use Nette;

final class UkolStavManager
{
	use Nette\SmartObject;

	private const
		TABLE_NAME = 'stavy_ukol',
		COLUMN_ID = 'id',
		COLUMN_ID_STAV = 'stav';

	/** @var Nette\Database\Context */
	private $database;

	public function __construct(Nette\Database\Context $database)
	{
		$this->database = $database;
    }

    public function getById(int $id): Nette\Database\Table\Selection
    {
        return
            $this->database->table(self::TABLE_NAME)->where('id ?', $id);
	}

    public function getAll(): Nette\Database\Table\Selection
    {
        return
            $this->database->table(self::TABLE_NAME);
	}
}