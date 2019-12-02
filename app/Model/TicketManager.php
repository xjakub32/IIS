<?php

declare(strict_types=1);

namespace App\Model;

use Nette;

final class TicketManager
{
	use Nette\SmartObject;

	private const
		TABLE_NAME = 'ticket',
		COLUMN_ID = 'id',
		COLUMN_ID_PRODUKT = 'id_produkt',
		COLUMN_ID_UZIVATEL = 'id_uzivatel',
		COLUMN_NAZEV = 'nazev',
		COLUMN_POPIS = 'popis',
		COLUMN_TIMESTAMP = 'timestamp',
		COLUMN_STAV = 'stav',
		COLUMN_ZOBRAZENI = 'pocet_zobrazeni';

	private $database;

	public function __construct(Nette\Database\Context $database)
	{
		$this->database = $database;
	}

	public function getAll(): Nette\Database\Table\Selection //Nette\Database\ResultSet
	{
		return
			/*$this->database->query('SELECT ticket.id as id, ticket.popis as popis,
				stavy_ticket.stav as stav, timestamp, id_produkt, nazev, id_uzivatel from ticket 
				join stavy_ticket on ticket.stav = stavy_ticket.id order by id DESC');*/
			$this->database->table(self::TABLE_NAME);
	}

	public function getAllByIdUser(int $id): Nette\Database\Table\Selection
	{
		return
			$this->database->table(self::TABLE_NAME)->where(self::COLUMN_ID_UZIVATEL.' ?', $id);
	}

	public function getAllByIdStav(int $id): Nette\Database\Table\Selection
	{
		return
			$this->database->table(self::TABLE_NAME)->where(self::COLUMN_STAV.' ?', $id);
	}

	public function getAllByNotIdStav(int $id): Nette\Database\Table\Selection
	{
		return
			$this->database->table(self::TABLE_NAME)->where(self::COLUMN_STAV.' != ?', $id);
	}

	public function getAllToManazer(int $id): Nette\Database\Table\Selection
	{
		return
			$this->database->table(self::TABLE_NAME);
				//->joinWhere('produkty', 'produkty.id = ticket.id_produkt');
	}

	public function addPicture($values) :void{
		$this->database->table('ticket')->where('id ?', $values->id)->update([
			'cesta' => ('' . $values->upload->name),
			]);
	}

	public function getAllUnfinished(int $id): Nette\Database\Table\Selection
	{
		return
			$this->database->table(self::TABLE_NAME)
				->where('ticket.stav != 5');
	}

	public function getById(int $id): Nette\Database\Table\Selection
	{
		return
			$this->database->table(self::TABLE_NAME)->where('id ?', $id);
	}

	public function getProduktyByName(): array
	{
		$produktyByName = [];
        foreach ($this->database->table('produkt')->select('id, nazev') as $produkt) {
            $produktyByName[$produkt->id] = $produkt->nazev;
		}
		return($produktyByName);
	}
	public function getProduktName($id_produkt)
	{
		return
			$this->database->table('produkt')->select('nazev')->where('id ?', $id_produkt);
	}

	public function create($id_produkt, $id_uzivatel, $nazev, $popis)
	{
		try{
			$this->database->table(self::TABLE_NAME)->insert([
				self::COLUMN_ID_PRODUKT => $id_produkt,
				self::COLUMN_ID_UZIVATEL => $id_uzivatel,
				self::COLUMN_NAZEV => $nazev,
				self::COLUMN_POPIS => $popis,
				self::COLUMN_STAV => 1,
			]);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new CreateException;
		}
	}

	public function delete($id)
	{
		$this->database->table(self::TABLE_NAME)->
			where(self::COLUMN_ID, $id)->
			delete();
	}

	public function getCountStav($id_stav): int
	{
		if($id_stav == NULL){
			return
				$this->database->table(self::TABLE_NAME)->count();
		} else {
		return
			$this->database->table(self::TABLE_NAME)
				->where(self::COLUMN_STAV.' = ?', $id_stav)
				->count();
		}
	}

	/*
	public function getAllStavById($id): Nette\Database\Table\Selection
	{
		//return
			//$this->database->table(self::TABLE_NAME)->select();
	}
	*/

	public function zmenitStav(Nette\Application\UI\Form $form) :void
	{
		$form = $form->getValues();
		try{
			$this->database->query('update '.self::TABLE_NAME.' set', [
				self::COLUMN_STAV => $form->stav
			], 'where id = ?', $form->id_tiket);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new CreateException;
		}
	}

	public function incStats(int $id): void
	{
		$this->database->table(self::TABLE_NAME)->where(self::COLUMN_ID.' ?', $id)
			->update(array( self::COLUMN_ZOBRAZENI.'+=' => 1 ));
	}

	public function getAllHistory(int $id): Nette\Database\Table\Selection
	{
		return
			$this->database->table('historie_stavu_ticketu')->where('ticket_id ?', $id);
	}
}

class CreateException extends \Exception
{
}