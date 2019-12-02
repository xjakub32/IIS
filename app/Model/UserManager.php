<?php

declare(strict_types=1);

namespace App\Model;

use Nette;
use Nette\Security\Passwords;
use Nette\Utils\Strings;

final class UserManager implements Nette\Security\IAuthenticator
{
	use Nette\SmartObject;

	private const
		TABLE_NAME = 'uzivatele',
		COLUMN_ID = 'id',
		COLUMN_EMAIL = 'email',
		COLUMN_PASSWORD_HASH = 'heslo',
		COLUMN_JMENO = 'jmeno',
		COLUMN_PRIJMENI = 'prijmeni',	
		COLUMN_ROLE = 'role',
		COLUMN_TIMESTAMP = 'timestamp',
		ROLE_ADMIN = 5,
		ROLE_VEDOUCI = 4,
		ROLE_MANAGER = 3,
		ROLE_PRACOVNIK = 2,
		ROLE_ZAKAZNIK = 1,
		ROLE_ANONYM = 0;
		
	private $database;

	private $passwords;

	public function __construct(Nette\Database\Context $database, Passwords $passwords)
	{
		$this->database = $database;
		$this->passwords = $passwords;
	}

	/**
	 * Performs an authentication.
	 * @throws Nette\Security\AuthenticationException
	 */
	public function authenticate(array $credentials): Nette\Security\IIdentity
	{
		[$email, $password] = $credentials;

		$row = $this->database->table(self::TABLE_NAME)
			->where(self::COLUMN_EMAIL, $email)
			->fetch();

		$role = $this->database->table('role')
			->select('nazev')
			->where('id ?', $row['role'])
			->fetch();

		if (!$row) {
			throw new Nette\Security\AuthenticationException('Zadané uživatelske jméno není správné', self::IDENTITY_NOT_FOUND);

		} elseif (!$this->passwords->verify($password, $row[self::COLUMN_PASSWORD_HASH])) {
			throw new Nette\Security\AuthenticationException('Zadané heslo není správné', self::INVALID_CREDENTIAL);

		} elseif ($this->passwords->needsRehash($row[self::COLUMN_PASSWORD_HASH])) {
			$row->update([
				self::COLUMN_PASSWORD_HASH => $this->passwords->hash($password),
			]);
		}

		$arr = $row->toArray();
		unset($arr[self::COLUMN_PASSWORD_HASH]);
		return new Nette\Security\Identity($row[self::COLUMN_ID], $role['nazev'], $arr); //$row[self::COLUMN_ROLE]
	}

	/**
	 * Adds new user.
	 * @throws DuplicateNameException
	 */
	public function add(string $email, string $password, string $jmeno, string $prijmeni): void
	{
		Nette\Utils\Validators::assert($email, 'email');
		try {
			$this->database->table(self::TABLE_NAME)->insert([
				self::COLUMN_EMAIL => $email,
				self::COLUMN_PASSWORD_HASH => $this->passwords->hash($password),
				self::COLUMN_JMENO => Strings::firstUpper(Strings::lower($jmeno)),
				self::COLUMN_PRIJMENI => Strings::firstUpper(Strings::lower($prijmeni))
			]);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new DuplicateNameException;
		}
	}

	public function getInfo($id): Nette\Database\Table\Selection
	{
		return
			$this->database->table(self::TABLE_NAME)->select('id, email, jmeno, prijmeni,'. self::COLUMN_ROLE.', timestamp')
				->where(self::COLUMN_ID . ' ?', $id);
	}

	public function getAll(): Nette\Database\Table\Selection
	{
		return
			$this->database->table(self::TABLE_NAME)->select('id, email, jmeno, prijmeni,'. self::COLUMN_ROLE.', timestamp');
	}

	public function getAllRolesColors(): Nette\Database\Table\Selection
	{
		return
			$this->database->table('role')->select('id, nazev, barva');
	}
	
	public function getRoleInfoById(int $id): Nette\Database\Table\Selection
	{
		return
			$this->database->table('role')->select('id, nazev, barva')->where('id ?', $id);
	}

	public function getAllManagers(): Nette\Database\Table\Selection
	{
		return
			$this->database->table(self::TABLE_NAME)->select('id, email, jmeno, prijmeni, role')->where('role ?', self::ROLE_MANAGER);
	}

	public function getAllWorkers(): Nette\Database\Table\Selection
	{
		return
			$this->database->table(self::TABLE_NAME)->select('id, email, jmeno, prijmeni, role')->where('role ?', self::ROLE_PRACOVNIK);
	}

	public function getAllEmployees(): Nette\Database\Table\Selection
	{
		return
			$this->database->table(self::TABLE_NAME)->select('id, email, jmeno, prijmeni, role')->where('role != ?', self::ROLE_ZAKAZNIK)->where('role != ?', self::ROLE_ANONYM);
	}

	public function delete($id)
	{
		$this->database->table(self::TABLE_NAME)->
			where(self::COLUMN_ID, $id)->
			delete();
	}

	public function changeUserData(Nette\Application\UI\Form $form) :void
	{
		$form = $form->getValues();
		try{
			// $this->db->table('cart2product')->where($where)->update(array('quantity' => 'quantity + 1' ));
			$this->database->query('update '.self::TABLE_NAME.' set ', [
				self::COLUMN_JMENO => Strings::firstUpper(Strings::lower($form->jmeno)),
				self::COLUMN_PRIJMENI => Strings::firstUpper(Strings::lower($form->prijmeni))
			], ' where id = ?', $form->id_uzivatele);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new CreateException;
		}
	}

	public function changePrivilegesByRoleId(Nette\Application\UI\Form $form) :void
	{
		$form = $form->getValues();
		try{
			$this->database->query('update '.self::TABLE_NAME.' set ', [
				self::COLUMN_ROLE => $form->role
			], ' where id = ?', $form->id_uzivatele);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new CreateException;
		}
	}

	public function changePrivilegesByRoleName(Nette\Application\UI\Form $form) :void
	{
		$form = $form->getValues();
		try{
			$this->database->query('update '.self::TABLE_NAME.' set ', [
				self::COLUMN_ROLE => $form->role
			], ' where nazev = ?', $form->nazev_role);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new CreateException;
		}
	}
}

class DuplicateNameException extends \Exception
{
}
