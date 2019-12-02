<?php

declare(strict_types=1);

namespace App\Model;

use Nette;

final class DashboardManager
{
	use Nette\SmartObject;

	private $database;

	public function __construct(Nette\Database\Context $database)
	{
		$this->database = $database;
	}

	public function getTopTicketedProducts(int $limit): Nette\Database\ResultSet // Nette\Database\Table\Selection 
	{
		return
			$this->database->query('
				select count(ticket.id) as pocet, produkty.nazev as nazev
				from ticket, produkty
				where ticket.id_produkt = produkty.id
				group by produkty.nazev
				order by count(ticket.id) desc
				limit ?', $limit);
	}
}