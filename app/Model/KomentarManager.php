<?php

declare(strict_types=1);

namespace App\Model;

use Nette;

final class KomentarManager
{
    use Nette\SmartObject;

    private $database;
    
	private const
		TABLE_NAME = 'komentar',
		COLUMN_ID = 'id',
		COLUMN_ID_UZIVATEL = 'id_uzivatel',
		COLUMN_ID_TIKETU = 'id_ticket',
		COLUMN_TEXT = 'text',
		COLUMN_TIMESTAMP = 'timestamp';
    
    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }
        
    public function getAllByTicket(int $id): Nette\Database\Table\Selection
    {
        return
            $this->database->table(self::TABLE_NAME)->where(self::COLUMN_ID_TIKETU . ' ?', $id);
    }

    public function add($id_tiketu, $id_uzivatel, $text): void
    {
        try{
			$this->database->table(self::TABLE_NAME)->insert([
                self::COLUMN_ID_TIKETU => $id_tiketu,
                self::COLUMN_ID_UZIVATEL => $id_uzivatel,
                self::COLUMN_TEXT => $text,
			]);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new CreateException;
		}
    }
}