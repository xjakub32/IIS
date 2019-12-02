<?php

declare(strict_types=1);

namespace App\Model;
use Nette\Application\UI\Form;

use Nette;

final class UkolManager
{
	use Nette\SmartObject;

	private const
		TABLE_NAME = 'ukoly',
		COLUMN_ID = 'id',
		COLUMN_ID_UZIVATEL = 'id_uzivatel',
		COLUMN_ZADANI = 'zadani',
		COLUMN_TIMESTAMP = 'timestamp',
		COLUMN_CAS_PREDPOKLAD = 'cas_predpoklad',
		COLUMN_CAS_CELKEM = 'cas_celkem',
		COLUMN_STAV = 'stav',
		COLUMN_POPIS_RESENI = 'popis_reseni',
		COLUMN_ID_UPRAVUJICIHO = 'id_upravujiciho',
		COLUMN_TIMESTAMP_VZNIKU = 'timestamp_vzniku',
		COLUMN_ID_TIKET = 'id_tiket';

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

	public function getInfo($id): Nette\Database\Table\Selection
	{
		return
			$this->database->table(self::TABLE_NAME)->where(self::COLUMN_ID.' ?', $id);
	}

	public function getAllByTicket(int $id): Nette\Database\Table\Selection
	{
		return
			$this->database->table(self::TABLE_NAME)->where(self::COLUMN_ID_TIKET.' ?', $id);
	}
	
	public function getAllByWorker(int $id, bool $manager = FALSE): Nette\Database\Table\Selection
	{
		if($manager == TRUE)
		{
			return
				$this->database->table(self::TABLE_NAME)->where(self::COLUMN_ID_UPRAVUJICIHO.' ?', $id);
		} else {
			return
				$this->database->table(self::TABLE_NAME)->where(self::COLUMN_ID_UZIVATEL.' ?', $id);
		}
	}

	public function changeStav(int $id, int $stav): void
	{
		try{
			$this->database->table(self::TABLE_NAME)->where(self::COLUMN_ID.' ?', $id)->update(array(
				self::COLUMN_STAV => $stav,
			));
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new CreateException;
		}
	}

	public function editReseni(Nette\Application\UI\Form $form): void
	{
		$form = $form->getValues();

		$info = $this->getInfo($form->id_ukolu)->fetch();
		$novy_celkovy_cas = $info->cas_celkem + $form->cas;

		try{
			$this->database->table(self::TABLE_NAME)->where('id ?', $form->id_ukolu)
				->update( 
					array(
							self::COLUMN_POPIS_RESENI => $form->reseni,
							self::COLUMN_CAS_CELKEM => $novy_celkovy_cas
						),
				);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new CreateException;
		}
	}
	
	public function create(Nette\Application\UI\Form $form) :void
	{
		$form = $form->getValues();
		try{
			$this->database->table(self::TABLE_NAME)->insert([
				self::COLUMN_ID_UZIVATEL => $form->pracovnik,
				self::COLUMN_ID_UPRAVUJICIHO => $form->id_upravujici,
				self::COLUMN_ZADANI => $form->zadani,
				self::COLUMN_CAS_PREDPOKLAD => $form->predpoklad,
				self::COLUMN_ID_TIKET => $form->id_tiket,
			]);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new CreateException;
		}
	}
	
	public function changeState(Nette\Application\UI\Form $form) :void
	{
		$form = $form->getValues();
		try{
			$this->database->query('update '.self::TABLE_NAME.' set', [
				self::COLUMN_STAV => $form->stav,
			], 'where id = ?', $form->id_ukolu);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new CreateException;
		}

	}
}