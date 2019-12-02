<?php

declare(strict_types=1);

namespace App\Model;

use Nette;

final class ProduktyManager
{
	use Nette\SmartObject;

	private const
		TABLE_NAME = 'produkty',
		COLUMN_ID = 'id',
		COLUMN_NAZEV = 'nazev',
		COLUMN_SPRAVUJE = 'spravuje',
		COLUMN_NADPRODUKT = 'rodic';

	private $database;

	public function __construct(Nette\Database\Context $database)
	{
		$this->database = $database;
    }
    
    public function getAll(): Nette\Database\Table\Selection
    {
        return
            $this->database->table(self::TABLE_NAME);
	}
		
	public function getProduktById($id): Nette\Database\Table\Selection
    {
        return
            $this->database->table(self::TABLE_NAME)->where(self::COLUMN_ID . ' ?', $id);
    }

	public function getProduktyByName(): array
	{
		$produktyByName = [];
        foreach ($this->database->table(self::TABLE_NAME)->select(self::COLUMN_ID .','. self::COLUMN_NAZEV) as $produkt) {
            $produktyByName[$produkt->id] = $produkt->nazev;
		}
		return($produktyByName);
    }
    
	public function getProduktName($id_produkt)
	{
		return
			$this->database->table(self::TABLE_NAME)->select(self::COLUMN_NAZEV)->where(self::COLUMN_ID.' ?', $id_produkt);
	}

	public function add(Nette\Application\UI\Form $form): void
	{
		$form = $form->getValues();
		try{
			$this->database->table(self::TABLE_NAME)->insert([
				self::COLUMN_NAZEV => $form->nazev,
				self::COLUMN_NADPRODUKT => ($form->nadprodukt == 0 ? NULL : $form->nadprodukt),
				self::COLUMN_SPRAVUJE => ($form->spravuje == 0 ? NULL : $form->spravuje),
			]);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new CreateException;
		}
	}

	public function change(Nette\Application\UI\Form $form): void
	{
		$form = $form->getValues();
		try{
			$this->database->query('update '.self::TABLE_NAME.' set', [
				self::COLUMN_NAZEV => $form->nazev,
				self::COLUMN_NADPRODUKT => ($form->nadprodukt == 0 ? NULL : $form->nadprodukt),
				self::COLUMN_SPRAVUJE => ($form->spravuje == 0 ? NULL : $form->spravuje),
			], 'where id = ?', $form->id_produkt);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new CreateException;
		}
	}

	public function assignManager(Nette\Application\UI\Form $form) :void
	{
		$form = $form->getValues();
		try{
			$this->database->query('update '.self::TABLE_NAME.' set', [
				self::COLUMN_SPRAVUJE => ($form->manager == 0 ? NULL : $form->manager),
			], 'where id = ?', $form->id_produkt);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new CreateException;
		}
	}

	public function getAllByManager(int $id):Nette\Database\Table\Selection
	{
		return
			$this->database->table(self::TABLE_NAME)->where(self::COLUMN_SPRAVUJE.' ?', $id);
	}

	public function delete($id)
	{
		$this->database->table(self::TABLE_NAME)->
			where(self::COLUMN_ID, $id)->
			delete();
	}
}