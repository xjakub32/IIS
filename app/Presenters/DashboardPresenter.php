<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model;
use Nette\Database\Connection;
use Nette\Application\UI;
use Nette\Application\UI\Form;
use Nette\Http\Request;

use Nette;

final class DashboardPresenter extends BasePresenter
{
	private $dashboardManager;
	private $userManager;
    public function __construct(
		Model\DashboardManager $dashboardManager,
		Model\UserManager $userManager
		)
    {
		$this->dashboardManager = $dashboardManager;
		$this->userManager = $userManager;
    }

    public function renderDefault(): void 
    {
		$this->presenter->privileges("Manažer" , "Nejste opravněni k této akci.", "danger");
		$this->template->topTicketedProducts = $this->dashboardManager->getTopTicketedProducts(5);
		$this->template->zamestnanci = $this->userManager->getAllEmployees();
    }
}